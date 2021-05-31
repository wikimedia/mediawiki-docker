<?php
/**
 * Primary authentication provider wrapper for LdapAuthentication
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @ingroup Auth
 */

use MediaWiki\Auth\AbstractPasswordPrimaryAuthenticationProvider;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Auth\PasswordAuthenticationRequest;
use MediaWiki\Auth\PasswordDomainAuthenticationRequest;
use Wikimedia\ScopedCallback;

/**
 * Primary authentication provider wrapper for LdapAuthentication
 * @warning This is rather hacky, and probably doesn't fully support what
 *  LdapAuthenticationPlugin can do. In particular, it's probably even more
 *  likely to have bugs with multiple domains than LdapAuthenticationPlugin
 *  itself, since AuthManager may well break some of the assumptions that
 *  LdapAuthenticationPlugin makes based on the old AuthPlugin-using code, and
 *  it doesn't support any username munging (i.e.
 *  LdapAuthenticationPlugin::getCanonicalName()).
 * @todo Someday someone who knows about authenticating against LDAP should
 *  write an extension that doesn't have craziness like a global "domain"
 *  variable where we have to guess at the correct value in half the entry
 *  points.
 */
class LdapPrimaryAuthenticationProvider
	extends AbstractPasswordPrimaryAuthenticationProvider
{
	private $hasMultipleDomains;
	private $requestType;

	public function __construct() {
		parent::__construct();

		$ldap = LdapAuthenticationPlugin::getInstance();
		$this->hasMultipleDomains = count( $ldap->domainList() ) > 1;
		$this->requestType = $this->hasMultipleDomains
			? PasswordDomainAuthenticationRequest::class
			: PasswordAuthenticationRequest::class;

		// Hooks to handle updating LDAP on various core events
		\Hooks::register( 'UserSaveSettings', [ $this, 'onUserSaveSettings' ] );
		\Hooks::register( 'UserLoggedIn', [ $this, 'onUserLoggedIn' ] );
		\Hooks::register( 'LocalUserCreated', [ $this, 'onLocalUserCreated' ] );
	}

	/**
	 * Sets the "domain" to that for the specified user, if any
	 * @param LdapAuthenticationPlugin $ldap
	 * @param User $user
	 * @return ScopedCallback|null Resetter
	 */
	private function setDomainForUser( LdapAuthenticationPlugin $ldap, User $user ) {
		if ( !$this->hasMultipleDomains ) {
			// LdapAuthenticationPlugin still needs setDomain called, even if
			// getDomain is deterministic. Sigh.
			$ldap->setDomain( $ldap->getDomain() );
			return null;
		}

		$domain = LdapAuthenticationPlugin::loadDomain( $user );
		if ( $domain === null ) {
			return null;
		}

		$oldDomain = $ldap->getDomain();
		if ( $domain === $oldDomain ) {
			// No change, so no point
			return null;
		}

		$ldap->setDomain( $domain );
		return new ScopedCallback( [ $ldap, 'setDomain' ], [ $oldDomain ] );
	}

	/**
	 * Create an appropriate AuthenticationRequest
	 * @return PasswordAuthenticationRequest
	 */
	protected function makeAuthReq() {
		$ldap = LdapAuthenticationPlugin::getInstance();
		$domainList = $ldap->domainList();
		if ( count( $domainList ) > 1 ) {
			return new PasswordDomainAuthenticationRequest( $domainList );
		}

		return new PasswordAuthenticationRequest;
	}

	/**
	 * Hook function to call LdapAuthenticationPlugin::updateExternalDB()
	 * @param User $user
	 * @codeCoverageIgnore
	 */
	public function onUserSaveSettings( $user ) {
		$ldap = LdapAuthenticationPlugin::getInstance();
		$reset = $this->setDomainForUser( $ldap, $user );
		$ldap->updateExternalDB( $user );
		ScopedCallback::consume( $reset );
	}

	/**
	 * Hook function to call LdapAuthenticationPlugin::updateUser()
	 * @param User $user
	 */
	public function onUserLoggedIn( $user ) {
		$ldap = LdapAuthenticationPlugin::getInstance();
		$reset = $this->setDomainForUser( $ldap, $user );
		$ldap->updateUser( $user );
		ScopedCallback::consume( $reset );
	}

	/**
	 * Hook function to call LdapAuthenticationPlugin::initUser()
	 * @param User $user
	 * @param bool $autocreated
	 */
	public function onLocalUserCreated( $user, $autocreated ) {
		// For $autocreated, see self::autoCreatedAccount()
		if ( !$autocreated ) {
			$ldap = LdapAuthenticationPlugin::getInstance();
			$reset = $this->setDomainForUser( $ldap, $user );
			$ldap->initUser( $user, $autocreated );
			ScopedCallback::consume( $reset );
		}
	}

	public function getAuthenticationRequests( $action, array $options ) {
		switch ( $action ) {
			case AuthManager::ACTION_LOGIN:
			case AuthManager::ACTION_CREATE:
				return [ $this->makeAuthReq() ];

			case AuthManager::ACTION_CHANGE:
			case AuthManager::ACTION_REMOVE:
				// No way to know the domain here.
				$ldap = LdapAuthenticationPlugin::getInstance();
				return $ldap->allowPasswordChange() ? [ $this->makeAuthReq() ] : [];

			default:
				return [];
		}
	}

	public function beginPrimaryAuthentication( array $reqs ) {
		$req = AuthenticationRequest::getRequestByClass( $reqs, $this->requestType );
		'@phan-var PasswordAuthenticationRequest|PasswordDomainAuthenticationRequest|null $req';
		if ( !$req || $req->username === null || $req->password === null ||
			( $this->hasMultipleDomains && $req->domain === null )
		) {
			return AuthenticationResponse::newAbstain();
		}

		$username = User::getCanonicalName( $req->username, 'usable' );
		if ( $username === false ) {
			return AuthenticationResponse::newAbstain();
		}

		$ldap = LdapAuthenticationPlugin::getInstance();

		if ( $this->hasMultipleDomains ) {
			// Special:UserLogin does this. Strange.
			$domain = $req->domain;
			if ( !$ldap->validDomain( $domain ) ) {
				$domain = $ldap->getDomain();
			}
		} else {
			$domain = $ldap->getDomain();
		}
		$ldap->setDomain( $domain );

		if ( $this->testUserCanAuthenticateInternal( $ldap, User::newFromName( $username ) ) &&
			$ldap->authenticate( $username, $req->password )
		) {
			return AuthenticationResponse::newPass( $username );
		}

		$this->authoritative = $ldap->strict();

		return $this->failResponse( $req );
	}

	public function testUserCanAuthenticate( $username ) {
		$username = User::getCanonicalName( $username, 'usable' );
		if ( $username === false ) {
			return false;
		}

		$ldap = LdapAuthenticationPlugin::getInstance();
		if ( $this->hasMultipleDomains ) {
			// We have to check every domain to really determine if the user can authenticate
			$curDomain = $ldap->getDomain();
			foreach ( $ldap->domainList() as $domain ) {
				$ldap->setDomain( $domain );
				if ( $this->testUserCanAuthenticateInternal( $ldap, User::newFromName( $username ) ) ) {
					$ldap->setDomain( $curDomain );
					return true;
				}
			}
			$ldap->setDomain( $curDomain );
			return false;
		}

		// Yay, easy way out.
		$ldap->setDomain( $ldap->getDomain() );

		return $this->testUserCanAuthenticateInternal( $ldap, User::newFromName( $username ) );
	}

	/**
	 * Test if a user can authenticate against $ldap's current domain
	 * @param LdapAuthenticationPlugin $ldap
	 * @param User $user
	 * @return bool
	 */
	private function testUserCanAuthenticateInternal( LdapAuthenticationPlugin $ldap, $user ) {
		return $ldap->userExistsReal( $user->getName() );
	}

	public function providerRevokeAccessForUser( $username ) {
		$username = User::getCanonicalName( $username, 'usable' );
		if ( $username === false ) {
			return;
		}
		$user = User::newFromName( $username );
		if ( $user ) {
			// Reset the password on every domain.
			$ldap = LdapAuthenticationPlugin::getInstance();
			$curDomain = $ldap->getDomain();
			$domains = $ldap->domainList() ?: [ '(default)' ];
			$failed = [];
			foreach ( $domains as $domain ) {
				$ldap->setDomain( $domain );
				if ( $ldap->userExistsReal( $username ) &&
					!$ldap->setPassword( $user, null )
				) {
					$failed[] = $domain;
				}
			}
			$ldap->setDomain( $curDomain );
			if ( $failed ) {
				throw new \UnexpectedValueException(
					"LdapAuthenticationPlugin failed to reset password for $username in the following domains: "
						. implode( ' ', $failed )
				);
			}
		}
	}

	public function testUserExists( $username, $flags = User::READ_NORMAL ) {
		$username = User::getCanonicalName( $username, 'usable' );
		if ( $username === false ) {
			return false;
		}

		$ldap = LdapAuthenticationPlugin::getInstance();
		if ( $this->hasMultipleDomains ) {
			// We have to check every domain to really determine if the user can authenticate
			$curDomain = $ldap->getDomain();
			foreach ( $ldap->domainList() as $domain ) {
				$ldap->setDomain( $domain );
				if ( $ldap->userExistsReal( $username ) ) {
					$ldap->setDomain( $curDomain );
					return true;
				}
			}
			$ldap->setDomain( $curDomain );
			return false;
		}

		// Yay, easy way out.
		$ldap->setDomain( $ldap->getDomain() );

		return $ldap->userExistsReal( $username );
	}

	public function providerAllowsPropertyChange( $property ) {
		return true;
	}

	public function providerAllowsAuthenticationDataChange(
		AuthenticationRequest $req, $checkData = true
	) {
		'@phan-var PasswordAuthenticationRequest|PasswordDomainAuthenticationRequest|null $req';
		if ( get_class( $req ) !== $this->requestType || (
			$this->hasMultipleDomains && $req->domain === 'local' )
		) {
			return \StatusValue::newGood( 'ignored' );
		}

		$ldap = LdapAuthenticationPlugin::getInstance();

		$curDomain = $ldap->getDomain();
		if ( $checkData ) {
			$ldap->setDomain( $this->hasMultipleDomains ? $req->domain : $curDomain );
		}
		try {
			// If !$checkData the domain might be wrong. Nothing we can do about that.
			if ( !$ldap->allowPasswordChange() || !$ldap->getConf( 'WriterDN' ) ) {
				return \StatusValue::newFatal( 'authmanager-authplugin-setpass-denied' );
			}

			if ( !$checkData ) {
				return \StatusValue::newGood();
			}

			if ( $this->hasMultipleDomains ) {
				if ( $req->domain === null ) {
					return \StatusValue::newGood( 'ignored' );
				}
				if ( !$ldap->validDomain( $req->domain ) ) {
					return \StatusValue::newFatal( 'authmanager-authplugin-setpass-bad-domain' );
				}
			}

			$username = User::getCanonicalName( $req->username, 'usable' );
			if ( $username !== false ) {
				$sv = \StatusValue::newGood();
				if ( $req->password !== null ) {
					if ( $req->password !== $req->retype ) {
						$sv->fatal( 'badretype' );
					} else {
						$sv->merge( $this->checkPasswordValidity( $username, $req->password )->getStatusValue() );
					}
				}
				return $sv;
			}

			return \StatusValue::newGood( 'ignored' );
		} finally {
			$ldap->setDomain( $curDomain );
		}
	}

	public function providerChangeAuthenticationData( AuthenticationRequest $req ) {
		'@phan-var PasswordAuthenticationRequest|PasswordDomainAuthenticationRequest|null $req';
		if ( get_class( $req ) === $this->requestType ) {
			$username = $req->username !== null ? User::getCanonicalName( $req->username, 'usable' ) : false;
			if ( $username === false ) {
				return;
			}

			if ( $this->hasMultipleDomains && $req->domain === null ) {
				return;
			}

			$ldap = LdapAuthenticationPlugin::getInstance();
			$ldap->setDomain( $this->hasMultipleDomains ? $req->domain : $ldap->getDomain() );
			$user = User::newFromName( $username );
			if ( !$ldap->setPassword( $user, $req->password ) ) {
				// This is totally unfriendly and leaves other
				// AuthenticationProviders in an uncertain state, but what else
				// can we do?
				throw new \ErrorPageError(
					'authmanager-authplugin-setpass-failed-title',
					'authmanager-authplugin-setpass-failed-message'
				);
			}
		}
	}

	public function accountCreationType() {
		// No way to know the domain, just hope it works
		$ldap = LdapAuthenticationPlugin::getInstance();
		return $ldap->canCreateAccounts() ? self::TYPE_CREATE : self::TYPE_NONE;
	}

	public function testForAccountCreation( $user, $creator, array $reqs ) {
		return \StatusValue::newGood();
	}

	public function beginPrimaryAccountCreation( $user, $creator, array $reqs ) {
		if ( $this->accountCreationType() === self::TYPE_NONE ) {
			throw new \BadMethodCallException( 'Shouldn\'t call this when accountCreationType() is NONE' );
		}

		$req = AuthenticationRequest::getRequestByClass( $reqs, $this->requestType );
		'@phan-var PasswordAuthenticationRequest|PasswordDomainAuthenticationRequest|null $req';
		if ( !$req || $req->username === null || $req->password === null ||
			( $this->hasMultipleDomains && $req->domain === null )
		) {
			return AuthenticationResponse::newAbstain();
		}

		$username = User::getCanonicalName( $req->username, 'usable' );
		if ( $username === false ) {
			return AuthenticationResponse::newAbstain();
		}

		$ldap = LdapAuthenticationPlugin::getInstance();
		$ldap->setDomain( $this->hasMultipleDomains ? $req->domain : $ldap->getDomain() );
		if ( $ldap->addUser(
			$user, $req->password, $user->getEmail(), $user->getRealName()
		) ) {
			return AuthenticationResponse::newPass();
		}

		return AuthenticationResponse::newFail(
			new \Message( 'authmanager-authplugin-create-fail' )
		);
	}

	public function autoCreatedAccount( $user, $source ) {
		$ldap = LdapAuthenticationPlugin::getInstance();
		$reset = $this->setDomainForUser( $ldap, $user );
		$ldap->initUser( $user, true );
		ScopedCallback::consume( $reset );
	}
}
