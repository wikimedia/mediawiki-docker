<?php

use MediaWiki\Session\SessionManager;

class LdapAutoAuthentication {

	/**
	 * Does the web server authentication piece of the LDAP plugin.
	 *
	 * @param User $user
	 * @param bool|null &$result
	 * @return bool
	 */
	public static function Authenticate( $user, &$result = null ) {
		$ldap = LdapAuthenticationPlugin::getInstance();

		$ldap->printDebug( "Entering AutoAuthentication.", NONSENSITIVE );

		if ( $user->isLoggedIn() ) {
			$ldap->printDebug( "User is already logged in.", NONSENSITIVE );
			return true;
		}

		$ldap->printDebug( "User isn't logged in, calling setup.", NONSENSITIVE );

		// Let regular authentication plugins configure themselves for auto
		// authentication chaining
		$ldap->autoAuthSetup();

		$autoauthname = $ldap->getConf( 'AutoAuthUsername' );
		$ldap->printDebug( "Calling authenticate with username ($autoauthname).", NONSENSITIVE );

		// The user hasn't already been authenticated, let's check them
		$authenticated = $ldap->authenticate( $autoauthname, '' );
		if ( !$authenticated ) {
			// If the user doesn't exist in LDAP, there isn't much reason to
			// go any further.
			$ldap->printDebug( "User wasn't found in LDAP, exiting.", NONSENSITIVE );
			return false;
		}

		// We need the username that MediaWiki will always use, not necessarily the one we
		// get from LDAP.
		$mungedUsername = $ldap->getCanonicalName( $autoauthname );

		$ldap->printDebug(
			"User exists in LDAP; finding the user by name ($mungedUsername) in MediaWiki.",
			NONSENSITIVE
		);
		$localId = User::idFromName( $mungedUsername );
		$ldap->printDebug( "Got id ($localId).", NONSENSITIVE );

		// Is the user already in the database?
		if ( $localId ) {
			$session = SessionManager::getGlobalSession();
			$ldap->printDebug( "User exists in local database, logging in.", NONSENSITIVE );
			$user->setID( $localId );
			$user->loadFromId();
			$user->setCookies();
			$ldap->updateUser( $user );
			$session->persist();
			$result = true;
		} else {
			$userAdded = self::attemptAddUser( $user, $mungedUsername );
			if ( !$userAdded ) {
				$result = false;
				return false;
			}
		}

		return true;
	}

	/**
	 * @param User $user
	 * @param string $mungedUsername
	 * @return bool
	 */
	public static function attemptAddUser( $user, $mungedUsername ) {
		$ldap = LdapAuthenticationPlugin::getInstance();

		if ( !$ldap->autoCreate() ) {
			$ldap->printDebug( "Cannot automatically create accounts.", NONSENSITIVE );
			return false;
		}

		$ldap->printDebug( "User does not exist in local database; creating.", NONSENSITIVE );
		// Checks passed, create the user
		$user->loadDefaults( $mungedUsername );
		$status = $user->addToDatabase();

		if ( $status !== null && !$status->isOK() ) {
			$ldap->printDebug( "Creation failed: " . $status->getWikiText(), NONSENSITIVE );
			return false;
		}
		$session = SessionManager::getGlobalSession();
		$ldap->initUser( $user, true );
		$user->setCookies();
		$session->persist();
		# Update user count
		$ssUpdate = new SiteStatsUpdate( 0, 0, 0, 0, 1 );
		$ssUpdate->doUpdate();

		return true;
	}

	/**
	 * No logout link in MW
	 * @param array &$personal_urls
	 * @param Title $title
	 * @return bool
	 */
	public static function NoLogout( &$personal_urls, $title ) {
		$ldap = LdapAuthenticationPlugin::getInstance();

		$ldap->printDebug( "Entering NoLogout.", NONSENSITIVE );
		unset( $personal_urls['logout'] );
		return true;
	}

}
