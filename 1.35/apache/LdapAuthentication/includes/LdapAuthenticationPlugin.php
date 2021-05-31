<?php
/**
 * Copyright (C) 2004 Ryan Lane <https://www.mediawiki.org/wiki/User:Ryan_lane>
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
 */
use MediaWiki\MediaWikiServices;

class LdapAuthenticationPlugin {

	private static $instance = null;

	// ldap connection resource
	public $ldapconn;

	// preferences
	public $email, $lang, $realname, $nickname, $externalid;

	// username pulled from ldap
	public $LDAPUsername;

	// userdn pulled from ldap
	public $userdn;

	// groups pulled from ldap
	public $userLDAPGroups;
	public $allLDAPGroups;

	// boolean to test for failed auth
	public $authFailed;

	// boolean to test for fetched user info
	public $fetchedUserInfo;

	// the user's entry and all attributes
	public $userInfo;

	// the user we are currently bound as
	public $boundAs;

	/**
	 * Fetch the singleton instance of LdapAuthenticationPlugin
	 * @return LdapAuthenticationPlugin
	 */
	public static function getInstance() {
		global $wgAuth;

		if ( self::$instance === null ) {
			if ( $wgAuth instanceof LdapAuthenticationPlugin ) {
				self::$instance = $wgAuth;
			} else {
				self::$instance = new LdapAuthenticationPlugin;
			}
		}
		return self::$instance;
	}

	/**
	 * Wrapper for ldap_connect
	 * @param string|null $hostname
	 * @param int $port
	 * @return resource|false
	 */
	public static function ldap_connect( $hostname = null, $port = 389 ) {
		Wikimedia\suppressWarnings();
		$ret = ldap_connect( $hostname, $port );
		Wikimedia\restoreWarnings();
		return $ret;
	}

	/**
	 * Wrapper for ldap_bind
	 * @param resource $ldapconn
	 * @param string|null $dn
	 * @param string|null $password
	 * @return bool
	 */
	public static function ldap_bind( $ldapconn, $dn = null, $password = null ) {
		Wikimedia\suppressWarnings();
		$ret = ldap_bind( $ldapconn, $dn, $password );
		Wikimedia\restoreWarnings();
		return $ret;
	}

	/**
	 * Wrapper for ldap_unbind
	 * @param resource $ldapconn
	 * @return bool
	 */
	public static function ldap_unbind( $ldapconn ) {
		if ( $ldapconn ) {
			Wikimedia\suppressWarnings();
			$ret = ldap_unbind( $ldapconn );
			Wikimedia\restoreWarnings();
		} else {
			$ret = false;
		}
		return $ret;
	}

	/**
	 * Wrapper for ldap_modify
	 * @param resource $ldapconn
	 * @param string $dn
	 * @param array $entry
	 * @return bool
	 */
	public static function ldap_modify( $ldapconn, $dn, $entry ) {
		Wikimedia\suppressWarnings();
		$ret = ldap_modify( $ldapconn, $dn, $entry );
		Wikimedia\restoreWarnings();
		return $ret;
	}

	/**
	 * Wrapper for ldap_add
	 * @param resource $ldapconn
	 * @param string $dn
	 * @param array $entry
	 * @return bool
	 */
	public static function ldap_add( $ldapconn, $dn, $entry ) {
		Wikimedia\suppressWarnings();
		$ret = ldap_add( $ldapconn, $dn, $entry );
		Wikimedia\restoreWarnings();
		return $ret;
	}

	/**
	 * Wrapper for ldap_delete
	 * @param resource $ldapconn
	 * @param string $dn
	 * @return bool
	 */
	public static function ldap_delete( $ldapconn, $dn ) {
		Wikimedia\suppressWarnings();
		$ret = ldap_delete( $ldapconn, $dn );
		Wikimedia\restoreWarnings();
		return $ret;
	}

	/**
	 * Wrapper for ldap_search
	 * @param resource $ldapconn
	 * @param string $basedn
	 * @param string $filter
	 * @param array|null $attributes
	 * @param int|null $attrsonly
	 * @param int|null $sizelimit
	 * @param int|null $timelimit
	 * @param int|null $deref
	 * @return resource
	 */
	public static function ldap_search(
		$ldapconn,
		$basedn,
		$filter,
		$attributes = [],
		$attrsonly = null,
		$sizelimit = null,
		$timelimit = null,
		$deref = null
	) {
		Wikimedia\suppressWarnings();
		$ret = ldap_search(
			$ldapconn,
			$basedn,
			$filter,
			$attributes,
			$attrsonly,
			$sizelimit,
			$timelimit,
			$deref
		);
		Wikimedia\restoreWarnings();
		return $ret;
	}

	/**
	 * Wrapper for ldap_read
	 * @param resource $ldapconn
	 * @param string $basedn
	 * @param string $filter
	 * @param array|null $attributes
	 * @param int|null $attrsonly
	 * @param int|null $sizelimit
	 * @param int|null $timelimit
	 * @param int|null $deref
	 * @return resource
	 */
	public static function ldap_read(
		$ldapconn,
		$basedn,
		$filter,
		$attributes = [],
		$attrsonly = null,
		$sizelimit = null,
		$timelimit = null,
		$deref = null
	) {
		Wikimedia\suppressWarnings();
		$ret = ldap_read(
			$ldapconn,
			$basedn,
			$filter,
			$attributes,
			$attrsonly,
			$sizelimit,
			$timelimit,
			$deref
		);
		Wikimedia\restoreWarnings();
		return $ret;
	}

	/**
	 * Wrapper for ldap_list
	 * @param resource $ldapconn
	 * @param string $basedn
	 * @param string $filter
	 * @param array|null $attributes
	 * @param int|null $attrsonly
	 * @param int|null $sizelimit
	 * @param int|null $timelimit
	 * @param int|null $deref
	 * @return resource
	 */
	public static function ldap_list(
		$ldapconn,
		$basedn,
		$filter,
		$attributes = [],
		$attrsonly = null,
		$sizelimit = null,
		$timelimit = null,
		$deref = null
	) {
		Wikimedia\suppressWarnings();
		$ret = ldap_list(
			$ldapconn,
			$basedn,
			$filter,
			$attributes,
			$attrsonly,
			$sizelimit,
			$timelimit,
			$deref
		);
		Wikimedia\restoreWarnings();
		return $ret;
	}

	/**
	 * Wrapper for ldap_get_entries
	 * @param resource $ldapconn
	 * @param resource $resultid
	 * @return array
	 * @phan-return array<int|string,int|array<int|string,string|int|array<int|string,int|string>>>
	 */
	public static function ldap_get_entries( $ldapconn, $resultid ) {
		Wikimedia\suppressWarnings();
		$ret = ldap_get_entries( $ldapconn, $resultid );
		Wikimedia\restoreWarnings();
		return $ret;
	}

	/**
	 * Wrapper for ldap_count_entries
	 * @param resource $ldapconn
	 * @param resource $resultid
	 * @return int
	 */
	public static function ldap_count_entries( $ldapconn, $resultid ) {
		Wikimedia\suppressWarnings();
		$ret = ldap_count_entries( $ldapconn, $resultid );
		Wikimedia\restoreWarnings();
		return $ret;
	}

	/**
	 * Wrapper for ldap_errno
	 * @param resource $ldapconn
	 * @return int
	 */
	public static function ldap_errno( $ldapconn ) {
		Wikimedia\suppressWarnings();
		$ret = ldap_errno( $ldapconn );
		Wikimedia\restoreWarnings();
		return $ret;
	}

	/**
	 * Get configuration defined by admin, or return default value
	 *
	 * @param string $preference
	 * @param string $domain
	 * @return mixed
	 */
	public function getConf( $preference, $domain = '' ) {
		# Global preferences
		switch ( $preference ) {
		case 'DomainNames':
			global $wgLDAPDomainNames;
			return $wgLDAPDomainNames;
		case 'UseLocal':
			global $wgLDAPUseLocal;
			return $wgLDAPUseLocal;
		case 'AutoAuthUsername':
			global $wgLDAPAutoAuthUsername;
			return $wgLDAPAutoAuthUsername;
		case 'AutoAuthDomain':
			global $wgLDAPAutoAuthDomain;
			return $wgLDAPAutoAuthDomain;
		case 'LockOnBlock':
			global $wgLDAPLockOnBlock;
			return $wgLDAPLockOnBlock;
		case 'LDAPLockPasswordPolicy':
			global $wgLDAPLockPasswordPolicy;
			return $wgLDAPLockPasswordPolicy;
		}

		# Domain specific preferences
		if ( !$domain ) {
			$domain = $this->getDomain();
		}
		switch ( $preference ) {
		case 'ServerNames':
			global $wgLDAPServerNames;
			return self::setOrDefault( $wgLDAPServerNames, $domain );
		case 'EncryptionType':
			global $wgLDAPEncryptionType;
			return self::setOrDefault( $wgLDAPEncryptionType, $domain, 'tls' );
		case 'Options':
			global $wgLDAPOptions;
			return self::setOrDefault( $wgLDAPOptions, $domain, [] );
		case 'Port':
			global $wgLDAPPort;
			if ( isset( $wgLDAPPort[$domain] ) ) {
				$this->printDebug( "Using non-standard port: " . $wgLDAPPort[$domain], SENSITIVE );
				return (string)$wgLDAPPort[$domain];
			}

			if ( $this->getConf( 'EncryptionType' ) == 'ssl' ) {
				return "636";
			}

			return "389";
		case 'SearchString':
			global $wgLDAPSearchStrings;
			return self::setOrDefault( $wgLDAPSearchStrings, $domain );
		case 'ProxyAgent':
			global $wgLDAPProxyAgent;
			return self::setOrDefault( $wgLDAPProxyAgent, $domain );
		case 'ProxyAgentPassword':
			global $wgLDAPProxyAgentPassword;
			return self::setOrDefaultPrivate( $wgLDAPProxyAgentPassword, $domain );
		case 'SearchAttribute':
			global $wgLDAPSearchAttributes;
			return self::setOrDefault( $wgLDAPSearchAttributes, $domain );
		case 'BaseDN':
			global $wgLDAPBaseDNs;
			return self::setOrDefault( $wgLDAPBaseDNs, $domain );
		case 'GroupBaseDN':
			global $wgLDAPGroupBaseDNs;
			return self::setOrDefault( $wgLDAPGroupBaseDNs, $domain );
		case 'UserBaseDN':
			global $wgLDAPUserBaseDNs;
			return self::setOrDefault( $wgLDAPUserBaseDNs, $domain );
		case 'WriterDN':
			global $wgLDAPWriterDN;
			return self::setOrDefault( $wgLDAPWriterDN, $domain );
		case 'WriterPassword':
			global $wgLDAPWriterPassword;
			return self::setOrDefaultPrivate( $wgLDAPWriterPassword, $domain );
		case 'WriteLocation':
			global $wgLDAPWriteLocation;
			return self::setOrDefault( $wgLDAPWriteLocation, $domain );
		case 'AddLDAPUsers':
			global $wgLDAPAddLDAPUsers;
			return self::setOrDefault( $wgLDAPAddLDAPUsers, $domain, false );
		case 'UpdateLDAP':
			global $wgLDAPUpdateLDAP;
			return self::setOrDefault( $wgLDAPUpdateLDAP, $domain, false );
		case 'PasswordHash':
			global $wgLDAPPasswordHash;
			return self::setOrDefaultPrivate( $wgLDAPPasswordHash, $domain, 'clear' );
		case 'MailPassword':
			global $wgLDAPMailPassword;
			return self::setOrDefaultPrivate( $wgLDAPMailPassword, $domain, false );
		case 'Preferences':
			global $wgLDAPPreferences;
			return self::setOrDefault( $wgLDAPPreferences, $domain, [] );
		case 'DisableAutoCreate':
			global $wgLDAPDisableAutoCreate;
			return self::setOrDefault( $wgLDAPDisableAutoCreate, $domain, false );
		case 'GroupUseFullDN':
			global $wgLDAPGroupUseFullDN;
			return self::setOrDefault( $wgLDAPGroupUseFullDN, $domain, false );
		case 'LowerCaseUsername':
			global $wgLDAPLowerCaseUsername;
			// Default set to true for backwards compatibility with
			// versions < 2.0a
			return self::setOrDefault( $wgLDAPLowerCaseUsername, $domain, true );
		case 'GroupUseRetrievedUsername':
			global $wgLDAPGroupUseRetrievedUsername;
			return self::setOrDefault( $wgLDAPGroupUseRetrievedUsername, $domain, false );
		case 'GroupObjectclass':
			global $wgLDAPGroupObjectclass;
			return self::setOrDefault( $wgLDAPGroupObjectclass, $domain );
		case 'GroupAttribute':
			global $wgLDAPGroupAttribute;
			return self::setOrDefault( $wgLDAPGroupAttribute, $domain );
		case 'GroupNameAttribute':
			global $wgLDAPGroupNameAttribute;
			return self::setOrDefault( $wgLDAPGroupNameAttribute, $domain );
		case 'GroupsUseMemberOf':
			global $wgLDAPGroupsUseMemberOf;
			return self::setOrDefault( $wgLDAPGroupsUseMemberOf, $domain, false );
		case 'UseLDAPGroups':
			global $wgLDAPUseLDAPGroups;
			return self::setOrDefault( $wgLDAPUseLDAPGroups, $domain, false );
		case 'LocallyManagedGroups':
			global $wgLDAPLocallyManagedGroups;
			return self::setOrDefault( $wgLDAPLocallyManagedGroups, $domain, [] );
		case 'GroupsPrevail':
			global $wgLDAPGroupsPrevail;
			return self::setOrDefault( $wgLDAPGroupsPrevail, $domain, false );
		case 'RequiredGroups':
			global $wgLDAPRequiredGroups;
			return self::setOrDefault( $wgLDAPRequiredGroups, $domain, [] );
		case 'ExcludedGroups':
			global $wgLDAPExcludedGroups;
			return self::setOrDefault( $wgLDAPExcludedGroups, $domain, [] );
		case 'GroupSearchNestedGroups':
			global $wgLDAPGroupSearchNestedGroups;
			return self::setOrDefault( $wgLDAPGroupSearchNestedGroups, $domain, false );
		case 'AuthAttribute':
			global $wgLDAPAuthAttribute;
			return self::setOrDefault( $wgLDAPAuthAttribute, $domain );
		case 'ActiveDirectory':
			global $wgLDAPActiveDirectory;
			return self::setOrDefault( $wgLDAPActiveDirectory, $domain, false );
		case 'GroupSearchPosixPrimaryGroup':
			global $wgLDAPGroupSearchPosixPrimaryGroup;
			return self::setOrDefault( $wgLDAPGroupSearchPosixPrimaryGroup, $domain, false );
		}
		return '';
	}

	/**
	 * Returns the item from $array at index $key if it is set,
	 * else, it returns $default
	 *
	 * @param array $array
	 * @param string $key
	 * @param mixed $default
	 * @return mixed
	 */
	private static function setOrDefault( $array, $key, $default = '' ) {
		return $array[$key] ?? $default;
	}

	/**
	 * Returns the item from $array at index $key if it is set,
	 * else, it returns $default
	 *
	 * Use for sensitive data
	 *
	 * @param array $array
	 * @param string $key
	 * @param mixed $default
	 * @return mixed
	 */
	private static function setOrDefaultPrivate( $array, $key, $default = '' ) {
		return $array[$key] ?? $default;
	}

	/**
	 * Check whether there exists a user account with the given name.
	 * The name will be normalized to MediaWiki's requirements, so
	 * you might need to munge it (for instance, for lowercase initial
	 * letters).
	 *
	 * @param string $username
	 * @return bool
	 */
	public function userExists( $username ) {
		$this->printDebug( "Entering userExists", NONSENSITIVE );

		// If we can't add LDAP users, we don't really need to check
		// if the user exists, the authenticate method will do this for
		// us. This will decrease hits to the LDAP server.
		// We do however, need to use this if we are using auto authentication.
		if ( !$this->getConf( 'AddLDAPUsers' ) && !$this->useAutoAuth() ) {
			return true;
		}

		return $this->userExistsReal( $username );
	}

	/**
	 * Like self::userExists, but always does the check
	 * @see self::userExists()
	 * @param string $username
	 * @return bool
	 */
	public function userExistsReal( $username ) {
		$this->printDebug( "Entering userExistsReal", NONSENSITIVE );

		$ret = false;
		if ( $this->connect() ) {
			$searchstring = $this->getSearchString( $username );

			if ( $searchstring == '' ) {
				// It is possible that getSearchString will return an
				// empty string, which means "no user".
			} elseif ( $this->useAutoAuth() ) {
				// If we are using auto authentication, and we got
				// anything back, then the user exists.
				$ret = true;
			} else {
				// Search for the entry.
				$entry = self::ldap_read(
					$this->ldapconn, $searchstring, "objectclass=*"
				);
				if ( $entry &&
					self::ldap_count_entries( $this->ldapconn, $entry ) > 0
				) {
					$this->printDebug( "Found a matching user in LDAP", NONSENSITIVE );
					$ret = true;
				} else {
					$this->printDebug( "Did not find a matching user in LDAP", NONSENSITIVE );
				}
			}
			// getSearchString is going to bind, but will not unbind
			$this->unbind();
		}
		return $ret;
	}

	/**
	 * Connect to LDAP
	 * @param string $domain
	 * @return bool
	 */
	public function connect( $domain = '' ) {
		$this->printDebug( "Entering Connect", NONSENSITIVE );

		if ( !function_exists( 'ldap_connect' ) ) {
			$this->printDebug( "It looks like you are missing LDAP support; please ensure you " .
				"have either compiled LDAP support in, or have enabled the module. If the " .
				"authentication is working for you, the plugin isn't properly detecting the LDAP " .
				"module, and you can safely ignore this message.", NONSENSITIVE );
			return false;
		}

		// Set the server string depending on whether we use ssl or not
		$encryptionType = $this->getConf( 'EncryptionType', $domain );
		switch ( $encryptionType ) {
			case "ldapi":
				$this->printDebug( "Using ldapi", SENSITIVE );
				$serverpre = "ldapi://";
				break;
			case "ssl":
				$this->printDebug( "Using SSL", SENSITIVE );
				$serverpre = "ldaps://";
				break;
			default:
				$this->printDebug( "Using TLS or not using encryption.", SENSITIVE );
				$serverpre = "ldap://";
		}

		// Make a space separated list of server strings with the connection type
		// string added.
		$servers = "";
		$tmpservers = $this->getConf( 'ServerNames', $domain );
		$tok = strtok( $tmpservers, " " );
		while ( $tok ) {
			$servers = $servers . " " . $serverpre . $tok . ":" . $this->getConf( 'Port', $domain );
			$tok = strtok( " " );
		}
		$servers = trim( $servers );
		if ( !$servers ) {
			$this->printDebug( 'Empty server string, skipping connection', NONSENSITIVE );
			return false;
		}

		$this->printDebug( "Using servers: $servers", SENSITIVE );

		// Connect and set options
		$this->ldapconn = self::ldap_connect( $servers );
		if ( !$this->ldapconn ) {
			$this->printDebug( "PHP's LDAP connect method returned null, this likely implies a " .
				"misconfiguration of the plugin.", NONSENSITIVE );
			return false;
		}
		ldap_set_option( $this->ldapconn, LDAP_OPT_PROTOCOL_VERSION, 3 );
		ldap_set_option( $this->ldapconn, LDAP_OPT_REFERRALS, 0 );

		foreach ( $this->getConf( 'Options' )  as $key => $value ) {
			if ( !ldap_set_option( $this->ldapconn, constant( $key ), $value ) ) {
				$this->printDebug(
					"Can't set option to LDAP! Option code and value: " . $key . "=" . $value, 1
				);
			}
		}

		// TLS needs to be started after the connection resource is available
		if ( $encryptionType == "tls" ) {
			$this->printDebug( "Using TLS", SENSITIVE );
			if ( !ldap_start_tls( $this->ldapconn ) ) {
				$this->printDebug( "Failed to start TLS.", SENSITIVE );
				return false;
			}
		}
		$this->printDebug( "PHP's LDAP connect method returned true (note, this does not imply " .
			"it connected to the server).", NONSENSITIVE );

		return true;
	}

	/**
	 * Check if a username+password pair is a valid login, or if the username
	 * is allowed access to the wiki.
	 * The name will be normalized to MediaWiki's requirements, so
	 * you might need to munge it (for instance, for lowercase initial
	 * letters).
	 *
	 * @param string $username
	 * @param string $password
	 * @return bool
	 */
	public function authenticate( $username, $password = '' ) {
		$this->printDebug( "Entering authenticate for username $username", NONSENSITIVE );

		// We don't handle local authentication
		if ( 'local' == $this->getDomain() ) {
			$this->printDebug( "User is using a local domain", SENSITIVE );
			return false;
		}

		// Mediawiki munges the username before authenticate is called,
		// this can mess with authentication, group pulling/restriction,
		// preference pulling, etc. Let's allow the admin to use
		// a lowercased username if needed.
		if ( $this->getConf( 'LowerCaseUsername' ) ) {
			$username = strtolower( $username );
		}

		// If the user is using auto authentication, we need to ensure
		// that he/she isn't trying to fool us by sending a username other
		// than the one the web server got from the auto-authentication method.
		if ( $this->useAutoAuth() && $this->getConf( 'AutoAuthUsername' ) != $username ) {
			$this->printDebug( "The username provided ($username) doesn't match the username " .
				"provided by the webserver (" . $this->getConf( 'AutoAuthUsername' ) . "). " .
				"The user is probably trying to log in to the auto-authentication domain with " .
				"password authentication via the wiki. Denying access.", SENSITIVE );
			return false;
		}

		// We need to ensure that if we require a password, that it is
		// not blank. We don't allow blank passwords, so we are being
		// tricked if someone is supplying one when using password auth.
		// auto-authentication is handled by the webserver; a blank password
		// here is wanted.
		if ( '' == $password && !$this->useAutoAuth() ) {
			$this->printDebug( "User used a blank password", NONSENSITIVE );
			return false;
		}

		if ( $this->connect() ) {
			$this->userdn = $this->getSearchString( $username );

			// It is possible that getSearchString will return an
			// empty string; if this happens, the bind will ALWAYS
			// return true, and will let anyone in!
			if ( '' == $this->userdn ) {
				$this->printDebug( "User DN is blank", NONSENSITIVE );
				$this->unbind();
				$this->markAuthFailed();
				return false;
			}

			// If we are using password authentication, we need to bind as the
			// user to make sure the password is correct.
			if ( !$this->useAutoAuth() ) {
				$this->printDebug( "Binding as the user", NONSENSITIVE );
				$bind = $this->bindAs( $this->userdn, $password );
				if ( !$bind ) {
					$this->markAuthFailed();
					return false;
				}
				$result = true;
				Hooks::run( 'ChainAuth', [ $username, $password, &$result ] );
				// @phan-suppress-next-line PhanImpossibleCondition False positive
				if ( !$result ) {
					return false;
				}

				$this->printDebug( "Bound successfully", NONSENSITIVE );

				$ss = $this->getConf( 'SearchString' );
				if ( $ss ) {
					if ( strstr( $ss, "@" ) || strstr( $ss, '\\' ) ) {
						// We are most likely configured using USER-NAME@DOMAIN, or
						// DOMAIN\\USER-NAME.
						// Get the user's full DN so we can search for groups and such.
						$this->userdn = $this->getUserDN( $username );
						$this->printDebug( "Fetched UserDN: $this->userdn", NONSENSITIVE );
					} else {
						// Now that we are bound, we can pull the user's info.
						$this->getUserInfo();
					}
				}
			}

			// Ensure the user's entry has the required auth attribute
			$aa = $this->getConf( 'AuthAttribute' );
			if ( $aa ) {
				$this->printDebug( "Checking for auth attributes: $aa", NONSENSITIVE );
				$filter = "(" . $aa . ")";
				$attributes = [ "dn" ];
				$entry = self::ldap_read(
					$this->ldapconn, $this->userdn, $filter, $attributes
				);
				$info = self::ldap_get_entries( $this->ldapconn, $entry );
				if ( $info["count"] < 1 ) {
					$this->printDebug( "Failed auth attribute check", NONSENSITIVE );
					$this->unbind();
					$this->markAuthFailed();
					return false;
				}
			}

			$this->getGroups( $username );

			if ( !$this->checkGroups() ) {
				$this->unbind();
				$this->markAuthFailed();
				return false;
			}

			$this->getPreferences();
			$this->unbind();
		} else {
			$this->markAuthFailed();
			return false;
		}
		$this->printDebug( "Authentication passed", NONSENSITIVE );

		// We made it this far; the user authenticated and didn't fail any checks, so he/she gets in
		return true;
	}

	public function markAuthFailed() {
		$this->authFailed = true;
	}

	/**
	 * @return array
	 */
	public function domainList() {
		$tempDomArr = $this->getConf( 'DomainNames' );
		if ( $this->getConf( 'UseLocal' ) ) {
			$this->printDebug( "Allowing the local domain, adding it to the list.", NONSENSITIVE );
			array_push( $tempDomArr, 'local' );
		}

		if ( $this->getConf( 'AutoAuthDomain' ) ) {
			$this->printDebug(
				"Allowing auto-authentication login, removing the domain from the list.",
				NONSENSITIVE
			);
			// There is no reason for people to log in directly to the wiki if the are using an
			// auto-authentication domain. If they try to, they are probably up to something fishy.
			unset( $tempDomArr[array_search( $this->getConf( 'AutoAuthDomain' ), $tempDomArr )] );
		}

		$domains = [];
		foreach ( $tempDomArr as $tempDom ) {
			$domains["$tempDom"] = $tempDom;
		}
		return $domains;
	}

	/**
	 * Return true if the wiki should create a new local account automatically
	 * when asked to login a user who doesn't exist locally but does in the
	 * external auth database.
	 *
	 * This is just a question, and shouldn't perform any actions.
	 *
	 * @return bool
	 */
	public function autoCreate() {
		return !$this->getConf( 'DisableAutoCreate' );
	}

	/**
	 * Set the given password in LDAP.
	 * Return true if successful.
	 *
	 * @param User $user
	 * @param string|null $password
	 * @return bool
	 */
	public function setPassword( $user, $password ) {
		$this->printDebug( "Entering setPassword", NONSENSITIVE );

		if ( $this->getDomain() == 'local' ) {
			$this->printDebug( "User is using a local domain", NONSENSITIVE );

			// We don't set local passwords, but we don't want the wiki
			// to send the user a failure.
			return true;
		}
		if ( !$this->getConf( 'UpdateLDAP' ) ) {
			$this->printDebug( "Wiki is set to not allow updates", NONSENSITIVE );

			// We aren't allowing the user to change his/her own password
			return false;
		}

		$writer = $this->getConf( 'WriterDN' );
		if ( !$writer ) {
			$this->printDebug( "Wiki doesn't have wgLDAPWriterDN set", NONSENSITIVE );

			// We can't change a user's password without an account that is
			// allowed to do it.
			return false;
		}
		$pass = $this->getPasswordHash( $password );

		if ( $this->connect() ) {
			$this->userdn = $this->getSearchString( $user->getName() );
			$this->printDebug( "Binding as the writerDN", NONSENSITIVE );
			$bind = $this->bindAs( $writer, $this->getConf( 'WriterPassword' ) );
			if ( !$bind ) {
				return false;
			}
			$values = [ 'userpassword' => $pass ];

			// Blank out the password in the database. We don't want to save
			// domain credentials for security reasons.
			// This doesn't do anything. $password isn't by reference
			$password = '';

			$success = self::ldap_modify(
				$this->ldapconn, $this->userdn, $values
			);
			$this->unbind();
			if ( $success ) {
				$this->printDebug( "Successfully modified the user's password", NONSENSITIVE );
				return true;
			}
			$this->printDebug( "Failed to modify the user's password", NONSENSITIVE );
		}
		return false;
	}

	/**
	 * Update user information in LDAP
	 * Return true if successful.
	 *
	 * @param User $user
	 * @return bool
	 */
	public function updateExternalDB( $user ) {
		$this->printDebug( "Entering updateExternalDB", NONSENSITIVE );
		if ( !$this->getConf( 'UpdateLDAP' ) || $this->getDomain() == 'local' ) {
			$this->printDebug(
				"Either the user is using a local domain, or the wiki isn't allowing updates",
				NONSENSITIVE
			);
			// We don't handle local preferences, but we don't want the
			// wiki to return an error.
			return true;
		}

		$writer = $this->getConf( 'WriterDN' );
		if ( !$writer ) {
			$this->printDebug( "The wiki doesn't have wgLDAPWriterDN set", NONSENSITIVE );
			// We can't modify LDAP preferences if we don't have a user
			// capable of editing LDAP attributes.
			return false;
		}

		$this->email = $user->getEmail();
		$this->realname = $user->getRealName();
		$this->nickname = $user->getOption( 'nickname' );
		$this->lang = $user->getOption( 'language' );
		if ( $this->connect() ) {
			$this->userdn = $this->getSearchString( $user->getName() );
			$this->printDebug( "Binding as the writerDN", NONSENSITIVE );
			$bind = $this->bindAs( $writer, $this->getConf( 'WriterPassword' ) );
			if ( !$bind ) {
				return false;
			}

			$values = [];
			$prefs = $this->getConf( 'Preferences' );
			foreach ( array_keys( $prefs ) as $key ) {
				$attr = strtolower( $prefs[$key] );
				switch ( $key ) {
					case "email":
						if ( is_string( $this->email ) ) {
							$values[$attr] = $this->email;
						}
						break;
					case "nickname":
						if ( is_string( $this->nickname ) ) {
							$values[$attr] = $this->nickname;
						}
						break;
					case "realname":
						if ( is_string( $this->realname ) ) {
							$values[$attr] = $this->realname;
						}
						break;
					case "language":
						if ( is_string( $this->lang ) ) {
							$values[$attr] = $this->lang;
						}
						break;
				}
			}

			if ( count( $values ) &&
				self::ldap_modify( $this->ldapconn, $this->userdn, $values )
			) {
				// We changed the user, we need to invalidate the memcache key
				$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
				$key = $cache->makeKey( 'ldapauthentication-userinfo', $this->userdn );
				$cache->delete( $key );

				$this->printDebug( "Successfully modified the user's attributes", NONSENSITIVE );
				$this->unbind();
				return true;
			}
			$this->printDebug( "Failed to modify the user's attributes", NONSENSITIVE );
			$this->unbind();
		}
		return false;
	}

	/**
	 * Can the wiki create accounts in LDAP?
	 * Return true if yes.
	 *
	 * @return bool
	 */
	public function canCreateAccounts() {
		return $this->getConf( 'AddLDAPUsers' );
	}

	/**
	 * Can the wiki change passwords in LDAP, or can the user
	 * change passwords locally?
	 * Return true if yes.
	 *
	 * @return bool
	 */
	public function allowPasswordChange() {
		$this->printDebug( "Entering allowPasswordChange", NONSENSITIVE );

		// Local domains need to be able to change passwords
		return ( $this->getConf( 'UseLocal' ) && 'local' == $this->getDomain() )
			|| $this->getConf( 'UpdateLDAP' )
			|| $this->getConf( 'MailPassword' );
	}

	/**
	 * Disallow MediaWiki from setting local passwords in the database,
	 * unless UseLocal is true. Warning: if you set $wgLDAPUseLocal,
	 * it will cause MediaWiki to leak LDAP passwords into the local database.
	 * @return bool
	 */
	public function allowSetLocalPassword() {
		return $this->getConf( 'UseLocal' );
	}

	/**
	 * Add a user to LDAP.
	 * Return true if successful.
	 *
	 * @param User $user
	 * @param string $password
	 * @param string $email
	 * @param string $realname
	 * @return bool
	 */
	public function addUser( $user, $password, $email = '', $realname = '' ) {
		$this->printDebug( "Entering addUser", NONSENSITIVE );

		if ( !$this->getConf( 'AddLDAPUsers' ) || 'local' == $this->getDomain() ) {
			$this->printDebug( "Either the user is using a local domain, or the wiki isn't " .
				"allowing users to be added to LDAP", NONSENSITIVE );

			// Tell the wiki not to return an error.
			return true;
		}
		if ( $this->getConf( 'RequiredGroups' ) ) {
			$this->printDebug( "The wiki is requiring users to be in specific groups, and cannot " .
				"add users as this would be a security hole.", NONSENSITIVE );
			// It is possible that later we can add users into
			// groups, but since we don't support it, we don't want
			// to open holes!
			return false;
		}

		$writer = $this->getConf( 'WriterDN' );
		if ( !$writer ) {
			$this->printDebug( "The wiki doesn't have wgLDAPWriterDN set", NONSENSITIVE );

			// We can't add users without an LDAP account capable of doing so.
			return false;
		}

		$this->email = $user->getEmail();
		$this->realname = $user->getRealName();
		$username = $user->getName();
		if ( $this->getConf( 'LowerCaseUsername' ) ) {
			$username = strtolower( $username );
		}
		$pass = $this->getPasswordHash( $password );
		if ( $this->connect() ) {
			$writeloc = $this->getConf( 'WriteLocation' );
			$this->userdn = $this->getSearchString( $username );
			if ( '' == $this->userdn ) {
				$this->printDebug(
					"userdn is blank, attempting to use wgLDAPWriteLocation", NONSENSITIVE
				);
				if ( $writeloc ) {
					$this->printDebug( "wgLDAPWriteLocation is set, using that", NONSENSITIVE );
					$this->userdn = $this->getConf( 'SearchAttribute' ) . "=" .
						$username . "," . $writeloc;
				} else {
					$this->printDebug( "wgLDAPWriteLocation is not set, failing", NONSENSITIVE );
					// getSearchString will bind, but will not unbind
					$this->unbind();
					return false;
				}
			}

			$this->printDebug( "Binding as the writerDN", NONSENSITIVE );
			$bind = $this->bindAs( $writer, $this->getConf( 'WriterPassword' ) );
			if ( !$bind ) {
				$this->printDebug( "Failed to bind as the writerDN; add failed", NONSENSITIVE );
				return false;
			}

			// Set up LDAP objectclasses and attributes
			// TODO: make objectclasses and attributes configurable
			$values = [ 'uid' => $username ];
			// sn is required for objectclass inetorgperson
			$values["sn"] = $username;
			$prefs = $this->getConf( 'Preferences' );
			foreach ( array_keys( $prefs ) as $key ) {
				$attr = strtolower( $prefs[$key] );
				switch ( $key ) {
					case "email":
						if ( is_string( $this->email ) ) {
							$values[$attr] = $this->email;
						}
						break;
					case "nickname":
						if ( is_string( $this->nickname ) ) {
							$values[$attr] = $this->nickname;
						}
						break;
					case "realname":
						if ( is_string( $this->realname ) ) {
							$values[$attr] = $this->realname;
						}
						break;
					case "language":
						if ( is_string( $this->lang ) ) {
							$values[$attr] = $this->lang;
						}
						break;
				}
			}
			if ( !array_key_exists( "cn", $values ) ) {
				$values["cn"] = $username;
			}
			$values["userpassword"] = $pass;
			$values["objectclass"] = [ "inetorgperson" ];

			$result = true;
			# Let other extensions modify the user object before creation
			Hooks::run( 'LDAPSetCreationValues',
				[ $this, $username, &$values, $writeloc, &$this->userdn, &$result ] );
			// @phan-suppress-next-line PhanImpossibleCondition False positive
			if ( !$result ) {
				$this->printDebug(
					"Failed to add user because LDAPSetCreationValues returned false", NONSENSITIVE
				);
				$this->unbind();
				return false;
			}

			$this->printDebug( "Adding user", NONSENSITIVE );
			if ( self::ldap_add( $this->ldapconn, $this->userdn, $values ) ) {
				$this->printDebug( "Successfully added user", NONSENSITIVE );
				$this->unbind();
				return true;
			}
			$errno = self::ldap_errno( $this->ldapconn );
			# Constraint violation, let's allow other plugins a chance to retry
			if ( $errno === 19 ) {
				$result = false;
				Hooks::run( 'LDAPRetrySetCreationValues',
					[ $this, $username, &$values, $writeloc, &$this->userdn, &$result ] );
				// @phan-suppress-next-line PhanImpossibleCondition False positive
				if ( $result &&
					self::ldap_add( $this->ldapconn, $this->userdn, $values )
				) {
					$this->printDebug( "Successfully added user", NONSENSITIVE );
					$this->unbind();
					return true;
				}
			}
			$this->printDebug( "Failed to add user", NONSENSITIVE );
			$this->unbind();
		}
		return false;
	}

	/**
	 * Set the domain this plugin is supposed to use when authenticating.
	 *
	 * @param string $domain
	 */
	public function setDomain( $domain ) {
		$this->printDebug( "Setting domain as: $domain", NONSENSITIVE );
		$_SESSION['wsDomain'] = $domain;
	}

	/**
	 * Get the user's domain
	 *
	 * @return string
	 */
	public function getDomain() {
		global $wgUser;

		$this->printDebug( "Entering getDomain", NONSENSITIVE );

		# If there's only a single domain set, there's no reason
		# to bother with sessions, tokens, etc.. This works around
		# a number of bugs caused by supporting multiple domains.
		# The bugs will still exist when using multiple domains,
		# though.
		$domainNames = $this->getConf( 'DomainNames' );
		if ( ( count( $domainNames ) === 1 ) && !$this->getConf( 'UseLocal' ) ) {
			return $domainNames[0];
		}
		# First check if we already have a valid domain set
		if ( isset( $_SESSION['wsDomain'] ) && $_SESSION['wsDomain'] != 'invaliddomain' ) {
			$this->printDebug( "Pulling domain from session.", NONSENSITIVE );
			return $_SESSION['wsDomain'];
		}
		# If the session domain isn't set, the user may have been logged
		# in with a token, check the user options.
		# If $wgUser isn't defined yet, it might be due to an LDAPAutoAuthDomain config.
		if ( isset( $wgUser ) && $wgUser->isLoggedIn() && $wgUser->getToken( false ) ) {
			$this->printDebug( "Pulling domain from user options.", NONSENSITIVE );
			$domain = self::loadDomain( $wgUser );
			if ( $domain ) {
				return $domain;
			}
		}
		# The user must be using an invalid domain
		$this->printDebug( "No domain found, returning invaliddomain", NONSENSITIVE );
		return 'invaliddomain';
	}

	/**
	 * Check to see if the specific domain is a valid domain.
	 * Return true if the domain is valid.
	 *
	 * @param string $domain
	 * @return bool
	 */
	public function validDomain( $domain ) {
		$this->printDebug( "Entering validDomain", NONSENSITIVE );
		if ( in_array( $domain, $this->getConf( 'DomainNames' ) ) ||
			( $this->getConf( 'UseLocal' ) && 'local' == $domain )
		) {
			$this->printDebug( "User is using a valid domain ($domain).", NONSENSITIVE );
			return true;
		}
		$this->printDebug( "User is not using a valid domain ($domain).", NONSENSITIVE );
		return false;
	}

	/**
	 * When a user logs in, update user with information from LDAP.
	 *
	 * @param User &$user
	 * TODO: fix the setExternalID stuff
	 */
	public function updateUser( &$user ) {
		$this->printDebug( "Entering updateUser", NONSENSITIVE );
		if ( $this->authFailed ) {
			$this->printDebug( "User didn't successfully authenticate, exiting.", NONSENSITIVE );
			return;
		}

		if ( $this->getConf( 'Preferences' ) ) {
			$this->printDebug( "Setting user preferences.", NONSENSITIVE );
			if ( is_string( $this->lang ) ) {
				$this->printDebug( "Setting language.", NONSENSITIVE );
				$user->setOption( 'language', $this->lang );
			}
			if ( is_string( $this->nickname ) ) {
				$this->printDebug( "Setting nickname.", NONSENSITIVE );
				$user->setOption( 'nickname', $this->nickname );
			}
			if ( is_string( $this->realname ) ) {
				$this->printDebug( "Setting realname.", NONSENSITIVE );
				$user->setRealName( $this->realname );
			}
			if ( is_string( $this->email ) ) {
				$this->printDebug( "Setting email.", NONSENSITIVE );
				$user->setEmail( $this->email );
				$user->confirmEmail();
			}
		}

		if ( $this->getConf( 'UseLDAPGroups' ) ) {
			$this->printDebug( "Setting user groups.", NONSENSITIVE );
			$this->setGroups( $user );
		}

		# We must set a user option if we want token based logins to work
		if ( $user->getToken( false ) ) {
			$this->printDebug( "User has a token, setting domain in user options.", NONSENSITIVE );
			self::saveDomain( $user, $_SESSION['wsDomain'] );
		}

		# Let other extensions update the user
		Hooks::run( 'LDAPUpdateUser', [ &$user ] );

		$this->printDebug( "Saving user settings.", NONSENSITIVE );
		$user->saveSettings();
	}

	/**
	 * When creating a user account, initialize user with information from LDAP.
	 * TODO: fix setExternalID stuff
	 *
	 * @param User &$user
	 * @param bool $autocreate
	 */
	public function initUser( &$user, $autocreate = false ) {
		$this->printDebug( "Entering initUser", NONSENSITIVE );

		if ( $this->authFailed ) {
			$this->printDebug( "User didn't successfully authenticate, exiting.", NONSENSITIVE );
			return;
		}
		if ( 'local' == $this->getDomain() ) {
			$this->printDebug( "User is using a local domain", NONSENSITIVE );
			return;
		}

		if ( $autocreate && !$this->userExists( $user->mName ) ) {
			// Generate a random password for the account under the assumption
			// that either the caller will be setting a password immediately
			// after using User::changeAuthenticationData or that other
			// password recovery means will be used if the account is meant
			// for interactive use.
			$pwreq = MediaWiki\Auth\TemporaryPasswordAuthenticationRequest::newRandom();
			$this->addUser( $user, $pwreq->password );
		}

		// The update user function does everything else we need done.
		$this->updateUser( $user );

		// updateUser() won't necessarily save the user's settings
		$user->saveSettings();
	}

	/**
	 * Return true to prevent logins that don't authenticate here from being
	 * checked against the local database's password fields.
	 *
	 * This is just a question, and shouldn't perform any actions.
	 *
	 * @return bool
	 */
	public function strict() {
		$this->printDebug( "Entering strict.", NONSENSITIVE );

		if ( $this->getConf( 'UseLocal' ) || $this->getConf( 'MailPassword' ) ) {
			$this->printDebug( "Returning false in strict().", NONSENSITIVE );
			return false;
		}
		$this->printDebug( "Returning true in strict().", NONSENSITIVE );
		return true;
	}

	/**
	 * Munge the username based on a scheme (lowercase, by default), by search attribute
	 * otherwise.
	 *
	 * @param string $username
	 * @return string
	 */
	public function getCanonicalName( $username ) {
		$this->printDebug( "Entering getCanonicalName", NONSENSITIVE );
		if ( User::isIP( $username ) ) {
			$this->printDebug( "Username is an IP, not munging.", NONSENSITIVE );
			return $username;
		}
		$canonicalname = $username;
		if ( $username != '' ) {
			$this->printDebug( "Username is: $username", NONSENSITIVE );
			if ( $this->getConf( 'LowerCaseUsername' ) ) {
				$canonicalname = ucfirst( strtolower( $canonicalname ) );
			} else {
				# Fetch username, so that we can possibly use it.
				$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
				$userInfo = $cache->getWithSetCallback(
					$cache->makeKey( 'ldapauthentication-canonicalname', $username ),
					$cache::TTL_DAY,
					function () use ( $username ) {
						$canonicalname = $username;

						if ( $this->validDomain( $this->getDomain() ) && $this->connect() ) {
							// Try to pull the username from LDAP. In the case of straight binds,
							// try to fetch the username by search before bind.
							$this->userdn = $this->getUserDN( $username, true );
							$hookSetUsername = $this->LDAPUsername;
							Hooks::run( 'SetUsernameAttributeFromLDAP',
								[ &$hookSetUsername, $this->userInfo ] );
							if ( is_string( $hookSetUsername ) ) {
								$this->printDebug(
									"Username munged by hook: $hookSetUsername", NONSENSITIVE
								);
								$this->LDAPUsername = $hookSetUsername;
							} else {
								$this->printDebug(
									"Fetched username is not a string (check your hook code...). " .
									"This message can be safely ignored if you do not have the " .
									"SetUsernameAttributeFromLDAP hook defined.", NONSENSITIVE
								);
							}
						}

						// We want to use the username returned by LDAP if it exists
						if ( $this->LDAPUsername != '' ) {
							$canonicalname = ucfirst( $this->LDAPUsername );
							$this->printDebug( "Using LDAPUsername: $canonicalname", NONSENSITIVE );
						}

						return [ 'username' => $username, 'canonicalname' => $canonicalname ];
					}
				);

				$canonicalname = $userInfo['canonicalname'];
			}
		}
		$this->printDebug( "Munged username: $canonicalname", NONSENSITIVE );
		return $canonicalname;
	}

	/**
	 * Configures the authentication plugin for use with auto-authentication
	 * plugins.
	 */
	public function autoAuthSetup() {
		$this->setDomain( $this->getConf( 'AutoAuthDomain' ) );
	}

	/**
	 * Gets the searchstring for a user based upon settings for the domain.
	 * Returns a full DN for a user.
	 *
	 * @param string $username
	 * @return string
	 */
	private function getSearchString( $username ) {
		$this->printDebug( "Entering getSearchString", NONSENSITIVE );
		$ss = $this->getConf( 'SearchString' );
		if ( $ss ) {
			// This is a straight bind
			$this->printDebug( "Doing a straight bind", NONSENSITIVE );
			$userdn = str_replace( "USER-NAME", $username, $ss );
		} else {
			$userdn = $this->getUserDN( $username, true );
		}
		$this->printDebug( "userdn is: $userdn", SENSITIVE );
		return $userdn;
	}

	/**
	 * Gets the DN of a user based upon settings for the domain.
	 * This function will set $this->LDAPUsername
	 *
	 * @param string $username
	 * @param bool $bind
	 * @param string $searchattr
	 * @return string
	 */
	public function getUserDN( $username, $bind = false, $searchattr = '' ) {
		$this->printDebug( "Entering getUserDN", NONSENSITIVE );
		if ( $bind ) {
			// This is a proxy bind, or an anonymous bind with a search
			$proxyagent = $this->getConf( 'ProxyAgent' );
			if ( $proxyagent ) {
				// This is a proxy bind
				$this->printDebug( "Doing a proxy bind", NONSENSITIVE );
				$bind = $this->bindAs( $proxyagent, $this->getConf( 'ProxyAgentPassword' ) );
			} else {
				// This is an anonymous bind
				$this->printDebug( "Doing an anonymous bind", NONSENSITIVE );
				$bind = $this->bindAs();
			}
			if ( !$bind ) {
				$this->printDebug( "Failed to bind", NONSENSITIVE );
				$this->fetchedUserInfo = false;
				$this->userInfo = null;
				return '';
			}
		}

		if ( !$searchattr ) {
			$searchattr = $this->getConf( 'SearchAttribute' );
		}
		// we need to do a subbase search for the entry
		$filter = "(" . $searchattr . "=" . $this->getLdapEscapedString( $username ) . ")";
		$this->printDebug( "Created a regular filter: $filter", SENSITIVE );

		// We explicitly put memberof here because it's an operational attribute in some servers.
		$attributes = [ "*", "memberof" ];
		$base = $this->getBaseDN( USERDN );
		$this->printDebug( "Using base: $base", SENSITIVE );
		$entry = self::ldap_search(
			$this->ldapconn, $base, $filter, $attributes
		);
		if ( self::ldap_count_entries( $this->ldapconn, $entry ) == 0 ) {
			$this->printDebug( "Couldn't find an entry", NONSENSITIVE );
			$this->fetchedUserInfo = false;
			$this->userInfo = null;
			return '';
		}
		$this->userInfo = self::ldap_get_entries( $this->ldapconn, $entry );
		$this->fetchedUserInfo = true;
		if ( isset( $this->userInfo[0][$searchattr] ) ) {
			$username = $this->userInfo[0][$searchattr][0];
			$this->printDebug(
				"Setting the LDAPUsername based on fetched wgLDAPSearchAttributes: $username",
				NONSENSITIVE
			);
			$this->LDAPUsername = $username;
		}
		return $this->userInfo[0]["dn"];
	}

	/**
	 * Load the current user's entry
	 *
	 * @return bool
	 */
	public function getUserInfo() {
		// Don't fetch the same data more than once
		if ( $this->fetchedUserInfo ) {
			return true;
		}
		$userInfo = $this->getUserInfoStateless( $this->userdn );
		if ( $userInfo === null ) {
			$this->fetchedUserInfo = false;
		} else {
			$this->fetchedUserInfo = true;
			$this->userInfo = $userInfo;
		}
		return $this->fetchedUserInfo;
	}

	/**
	 * @param string $userdn
	 * @return array|null
	 * @phan-return ?array<int|string,int|array<int|string,string|int|array<int|string,int|string>>>
	 */
	public function getUserInfoStateless( $userdn ) {
		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();

		return $cache->getWithSetCallback(
			$cache->makeKey( 'ldapauthentication-userinfo', $userdn ),
			$cache::TTL_DAY,
			function ( $oldValue, &$ttl ) use ( $userdn, $cache ) {
				$entry = self::ldap_read(
					$this->ldapconn,
					$userdn,
					"objectclass=*",
					[ '*', 'memberof' ]
				);
				$userInfo = self::ldap_get_entries( $this->ldapconn, $entry );
				if ( $userInfo["count"] < 1 ) {
					$ttl = $cache::TTL_UNCACHEABLE;

					return null;
				}

				return $userInfo;
			}
		);
	}

	/**
	 * Retrieve user preferences from LDAP
	 */
	private function getPreferences() {
		$this->printDebug( "Entering getPreferences", NONSENSITIVE );

		// Retrieve preferences
		$prefs = $this->getConf( 'Preferences' );
		if ( !$prefs ) {
			return;
		}
		if ( !$this->getUserInfo() ) {
			$this->printDebug(
				"Failed to get preferences, the user's entry wasn't found.", NONSENSITIVE
			);
			return;
		}
		$this->printDebug( "Retrieving preferences", NONSENSITIVE );
		foreach ( array_keys( $prefs ) as $key ) {
			$attr = strtolower( $prefs[$key] );
			if ( !isset( $this->userInfo[0][$attr] ) ) {
				continue;
			}
			$value = $this->userInfo[0][$attr][0];
			switch ( $key ) {
				case "email":
					$this->email = $value;
					$this->printDebug(
						"Retrieved email ($this->email) using attribute ($prefs[$key])",
						NONSENSITIVE
					);
					break;
				case "language":
					$this->lang = $value;
					$this->printDebug(
						"Retrieved language ($this->lang) using attribute ($prefs[$key])",
						NONSENSITIVE
					);
					break;
				case "nickname":
					$this->nickname = $value;
					$this->printDebug(
						"Retrieved nickname ($this->nickname) using attribute ($prefs[$key])",
						NONSENSITIVE
					);
					break;
				case "realname":
					$this->realname = $value;
					$this->printDebug(
						"Retrieved realname ($this->realname) using attribute ($prefs[$key])",
						NONSENSITIVE
					);
					break;
			}
		}
	}

	/**
	 * Checks to see whether a user is in a required group.
	 *
	 * @return bool
	 */
	private function checkGroups() {
		$this->printDebug( "Entering checkGroups", NONSENSITIVE );

		$excgroups = $this->getConf( 'ExcludedGroups' );
		if ( $excgroups ) {
			$this->printDebug( "Checking for excluded group membership", NONSENSITIVE );

			$excgroups = array_map( 'strtolower', $excgroups );

			$this->printDebug( "Excluded groups:", NONSENSITIVE, $excgroups );

			foreach ( $this->userLDAPGroups["dn"] as $group ) {
				$this->printDebug( "Checking against: $group", NONSENSITIVE );
				if ( in_array( $group, $excgroups ) ) {
					$this->printDebug( "Found user in an excluded group.", NONSENSITIVE );
					return false;
				}
			}
		}

		$reqgroups = $this->getConf( 'RequiredGroups' );
		if ( $reqgroups ) {
			$this->printDebug( "Checking for (new style) group membership", NONSENSITIVE );

			$reqgroups = array_map( 'strtolower', $reqgroups );

			$this->printDebug( "Required groups:", NONSENSITIVE, $reqgroups );

			foreach ( $this->userLDAPGroups["dn"] as $group ) {
				$this->printDebug( "Checking against: $group", NONSENSITIVE );
				if ( in_array( $group, $reqgroups ) ) {
					$this->printDebug( "Found user in a group.", NONSENSITIVE );
					return true;
				}
			}

			$this->printDebug( "Couldn't find the user in any groups.", NONSENSITIVE );
			return false;
		}

		// Ensure we return true if we aren't checking groups.
		return true;
	}

	/**
	 * Function to get the user's groups.
	 * @param string $username
	 */
	protected function getGroups( $username ) {
		$this->printDebug( "Entering getGroups", NONSENSITIVE );

		// Ensure userLDAPGroups is set, no matter what
		$this->userLDAPGroups = [ "dn" => [], "short" => [] ];

		// Find groups
		if ( $this->getConf( 'RequiredGroups' ) || $this->getConf( 'UseLDAPGroups' ) ) {
			$this->printDebug( "Retrieving LDAP group membership", NONSENSITIVE );

			// Let's figure out what we should be searching for
			if ( $this->getConf( 'GroupUseFullDN' ) ) {
				$usertopass = $this->userdn;
			} else {
				if ( $this->getConf( 'GroupUseRetrievedUsername' ) && $this->LDAPUsername != '' ) {
					$usertopass = $this->LDAPUsername;
				} else {
					$usertopass = $username;
				}
			}

			if ( $this->getConf( 'GroupsUseMemberOf' ) ) {
				$this->printDebug( "Using memberOf", NONSENSITIVE );
				if ( !$this->getUserInfo() ) {
					$this->printDebug( "Couldn't get the user's entry.", NONSENSITIVE );
				} elseif ( isset( $this->userInfo[0]["memberof"] ) ) {
					# The first entry is always a count
					$memberOfMembers = $this->userInfo[0]["memberof"];
					array_shift( $memberOfMembers );
					$groups = [ "dn" => [], "short" => [] ];

					foreach ( $memberOfMembers as $mem ) {
						array_push( $groups["dn"], strtolower( $mem ) );

						// Get short name of group
						$memAttrs = explode( ',', strtolower( $mem ) );
						if ( isset( $memAttrs[0] ) ) {
							$memAttrs = explode( '=', $memAttrs[0] );
							if ( isset( $memAttrs[0] ) ) {
								array_push( $groups["short"], strtolower( $memAttrs[1] ) );
							}
						}
					}
					$this->printDebug( "Got the following groups:", SENSITIVE, $groups["dn"] );

					$this->userLDAPGroups = $groups;
				} else {
					$this->printDebug( "memberOf attribute isn't set", NONSENSITIVE );
				}
			} else {
				$this->printDebug( "Searching for the groups", NONSENSITIVE );
				$this->userLDAPGroups = $this->searchGroups( $usertopass );
				if ( $this->getConf( 'GroupSearchNestedGroups' ) ) {
					$this->userLDAPGroups = $this->searchNestedGroups( $this->userLDAPGroups );
					$this->printDebug(
						"Got the following nested groups:", SENSITIVE, $this->userLDAPGroups["dn"]
					);
				}
			}

			if ( $this->getConf( 'GroupSearchPosixPrimaryGroup' ) ) {
				if ( !$this->getUserInfo() ) {
					$this->printDebug( "Couldn't get the user's entry.", NONSENSITIVE );
				} elseif ( isset( $this->userInfo[0]["gidnumber"] ) ) {
					$base = $this->getBaseDN( GROUPDN );
					$objectclass = $this->getConf( 'GroupObjectclass' );
					$filter = "(&(objectClass={$objectclass})" .
						"(gidNumber={$this->userInfo[0]['gidnumber'][0]}))";
					$info = self::ldap_search( $this->ldapconn, $base, $filter );
					$entries = self::ldap_get_entries( $this->ldapconn, $info );
					if ( empty( $entries[0] ) ) {
						$this->printDebug( "Couldn't get the user's primary group.", NONSENSITIVE );
					} else {
						$primary_group_dn = strtolower( $entries[0]["dn"] );
						$this->printDebug( "Got the user's primary group:" . $primary_group_dn, SENSITIVE );
						$this->userLDAPGroups["dn"][] = $primary_group_dn;
						$nameattribute = strtolower( $this->getConf( 'GroupNameAttribute' ) );
						$this->userLDAPGroups["short"][] = $entries[0][$nameattribute][0];
					}
				}
			}

			// Only find all groups if the user has any groups; otherwise, we are
			// just wasting a search.
			if ( $this->getConf( 'GroupsPrevail' ) && count( $this->userLDAPGroups ) != 0 ) {
				$this->allLDAPGroups = $this->searchGroups( '*' );
			}
		}
	}

	/**
	 * Function to return an array of nested groups when given a group or list of groups.
	 * $searchedgroups is used for tail recursion and shouldn't be provided
	 * when called externally.
	 *
	 * @param array $groups
	 * @param array $searchedgroups
	 * @return array
	 */
	private function searchNestedGroups( $groups, $searchedgroups = [ "dn" => [], "short" => [] ] ) {
		$this->printDebug( "Entering searchNestedGroups", NONSENSITIVE );

		// base case, no more groups left to check
		if ( count( $groups["dn"] ) == 0 ) {
			$this->printDebug( "No more groups to search.", NONSENSITIVE );
			return $searchedgroups;
		}

		$this->printDebug( "Searching groups:", SENSITIVE, $groups["dn"] );
		$groupstosearch = [ "short" => [], "dn" => [] ];
		foreach ( $groups["dn"] as $group ) {
			$returnedgroups = $this->searchGroups( $group );
			$this->printDebug(
				"Group $group is in the following groups:", SENSITIVE, $returnedgroups["dn"]
			);
			foreach ( $returnedgroups["dn"] as $searchme ) {
				if ( in_array( $searchme, $searchedgroups["dn"] ) ) {
					// We already searched this, move on
					continue;
				}

				// We'll need to search this group's members now
				$this->printDebug( "Adding $searchme to the list of groups (1)", SENSITIVE );
				$groupstosearch["dn"][] = $searchme;
			}
			foreach ( $returnedgroups["short"] as $searchme ) {
				if ( in_array( $searchme, $searchedgroups["short"] ) ) {
					// We already searched this, move on
					continue;
				}

				$this->printDebug( "Adding $searchme to the list of groups (2)", SENSITIVE );
				// We'll need to search this group's members now
				$groupstosearch["short"][] = $searchme;
			}
		}
		$searchedgroups = array_merge_recursive( $groups, $searchedgroups );

		return $this->searchNestedGroups( $groupstosearch, $searchedgroups );
	}

	/**
	 * Search groups for the supplied DN
	 *
	 * @param string $dn
	 * @return array
	 */
	private function searchGroups( $dn ) {
		$this->printDebug( "Entering searchGroups", NONSENSITIVE );

		$base = $this->getBaseDN( GROUPDN );
		$objectclass = $this->getConf( 'GroupObjectclass' );
		$attribute = $this->getConf( 'GroupAttribute' );
		$nameattribute = $this->getConf( 'GroupNameAttribute' );

		// We actually want to search for * not \2a, ensure we don't escape *
		$value = $dn;
		if ( $value != "*" ) {
			$value = $this->getLdapEscapedString( $value );
		}

		$proxyagent = $this->getConf( 'ProxyAgent' );
		if ( $proxyagent ) {
			// We'll try to bind as the proxyagent as the proxyagent should normally have more
			// rights than the user. If the proxyagent fails to bind, we will still be able
			// to search as the normal user (which is why we don't return on fail).
			$this->printDebug( "Binding as the proxyagent", NONSENSITIVE );
			$this->bindAs( $proxyagent, $this->getConf( 'ProxyAgentPassword' ) );
		}

		$groups = [ "short" => [], "dn" => [] ];

		// AD does not include the primary group in the list of groups, we have to find it ourselves
		if ( $dn != "*" && $this->getConf( 'ActiveDirectory' ) ) {
			$PGfilter = "(&(distinguishedName=$value)(objectclass=user))";
			$this->printDebug( "User Filter: $PGfilter", SENSITIVE );
			$PGinfo = self::ldap_search( $this->ldapconn, $base, $PGfilter );
			$PGentries = self::ldap_get_entries( $this->ldapconn, $PGinfo );
			if ( !empty( $PGentries[0] ) ) {
				$Usid = $PGentries[0]['objectsid'][0];
				$PGrid = $PGentries[0]['primarygroupid'][0];
				$PGsid = bin2hex( $Usid );
				$PGSID = [];
				for ( $i = 0; $i < 56; $i += 2 ) {
					$PGSID[] = substr( $PGsid, $i, 2 );
				}
				$dPGrid = dechex( $PGrid );
				$dPGrid = str_pad( $dPGrid, 8, '0', STR_PAD_LEFT );
				$PGRID = [];
				for ( $i = 0; $i < 8; $i += 2 ) {
					array_push( $PGRID, substr( $dPGrid, $i, 2 ) );
				}
				for ( $i = 24; $i < 28; $i++ ) {
					$PGSID[$i] = array_pop( $PGRID );
				}
				$PGsid_string = '';
				foreach ( $PGSID as $PGsid_bit ) {
					$PGsid_string .= "\\" . $PGsid_bit;
				}
				$PGfilter = "(&(objectSid=$PGsid_string)(objectclass=$objectclass))";
				$this->printDebug( "Primary Group Filter: $PGfilter", SENSITIVE );
				$info = self::ldap_search( $this->ldapconn, $base, $PGfilter );
				$PGentries = self::ldap_get_entries( $this->ldapconn, $info );
				array_shift( $PGentries );
				$dnMember = strtolower( $PGentries[0]['dn'] );
				$groups["dn"][] = $dnMember;
				// Get short name of group
				$memAttrs = explode( ',', strtolower( $dnMember ) );
				if ( isset( $memAttrs[0] ) ) {
					$memAttrs = explode( '=', $memAttrs[0] );
					if ( isset( $memAttrs[1] ) ) {
						$groups["short"][] = strtolower( $memAttrs[1] );
					}
				}

			}
		}

		$filter = "(&($attribute=$value)(objectclass=$objectclass))";
		$this->printDebug( "Search string: $filter", SENSITIVE );
		$info = self::ldap_search( $this->ldapconn, $base, $filter );
		if ( !$info ) {
			$this->printDebug( "No entries returned from search.", SENSITIVE );
			// Return an array so that other functions
			// don't error out.
			return [ "short" => [], "dn" => [] ];
		}

		$entries = self::ldap_get_entries( $this->ldapconn, $info );
		if ( $entries ) {
			// We need to shift because the first entry will be a count
			array_shift( $entries );
			// Let's get a list of both full dn groups and shortname groups
			foreach ( $entries as $entry ) {
				$shortMember = strtolower( $entry[$nameattribute][0] );
				$dnMember = strtolower( $entry['dn'] );
				$groups["short"][] = $shortMember;
				$groups["dn"][] = $dnMember;
			}
		}

		$this->printDebug( "Returned groups:", SENSITIVE, $groups["dn"] );
		return $groups;
	}

	/**
	 * Returns true if this group is in the list of the currently authenticated
	 * user's groups, else false.
	 *
	 * @param string $group
	 * @return bool
	 */
	private function hasLDAPGroup( $group ) {
		$this->printDebug( "Entering hasLDAPGroup", NONSENSITIVE );
		return in_array( strtolower( $group ), $this->userLDAPGroups["short"] );
	}

	/**
	 * Returns true if an LDAP group with this name exists, else false.
	 *
	 * @param string $group
	 * @return bool
	 */
	private function isLDAPGroup( $group ) {
		$this->printDebug( "Entering isLDAPGroup", NONSENSITIVE );
		return in_array( strtolower( $group ), $this->allLDAPGroups["short"] );
	}

	/**
	 * Helper function for updateUser() and initUser(). Adds users into MediaWiki security groups
	 * based upon groups retrieved from LDAP.
	 *
	 * @param User &$user
	 */
	private function setGroups( &$user ) {
		global $wgGroupPermissions;

		// TODO: this is *really* ugly code. clean it up!
		$this->printDebug( "Entering setGroups.", NONSENSITIVE );

		# Add ldap groups as local groups
		if ( $this->getConf( 'GroupsPrevail' ) ) {
			$this->printDebug(
				"Adding all groups to wgGroupPermissions: ", SENSITIVE, $this->allLDAPGroups
			);

			foreach ( $this->allLDAPGroups["short"] as $ldapgroup ) {
				if ( !array_key_exists( $ldapgroup, $wgGroupPermissions ) ) {
					$wgGroupPermissions[$ldapgroup] = [];
				}
			}
		}

		# add groups permissions
		$localAvailGrps = $user->getAllGroups();
		$localUserGrps = $user->getEffectiveGroups();
		$defaultLocallyManagedGrps = [ 'bot', 'sysop', 'bureaucrat' ];
		$locallyManagedGrps = $this->getConf( 'LocallyManagedGroups' );
		if ( $locallyManagedGrps ) {
			$locallyManagedGrps = array_unique(
				array_merge( $defaultLocallyManagedGrps, $locallyManagedGrps ) );
			$this->printDebug( "Locally managed groups: ", SENSITIVE, $locallyManagedGrps );
		} else {
			$locallyManagedGrps = $defaultLocallyManagedGrps;
			$this->printDebug(
				"Locally managed groups is unset, using defaults: ", SENSITIVE, $locallyManagedGrps
			);
		}

		$this->printDebug( "Available groups are: ", NONSENSITIVE, $localAvailGrps );
		$this->printDebug( "Effective groups are: ", NONSENSITIVE, $localUserGrps );
		# note: $localUserGrps does not need to be updated with $cGroup added,
		#       as $localAvailGrps contains $cGroup only once.
		foreach ( $localAvailGrps as $cGroup ) {
			# did we once add the user to the group?
			if ( in_array( $cGroup, $localUserGrps ) ) {
				$this->printDebug(
					"Checking to see if we need to remove user from: $cGroup", NONSENSITIVE
				);
				if ( !$this->hasLDAPGroup( $cGroup ) &&
					!in_array( $cGroup, $locallyManagedGrps )
				) {
					$this->printDebug( "Removing user from: $cGroup", NONSENSITIVE );
					# the ldap group overrides the local group
					# so as the user is currently not a member of the ldap group,
					# he shall be removed from the local group
					$user->removeGroup( $cGroup );
				}
			} else {
				# no, but maybe the user has recently been added to the ldap group?
				$this->printDebug( "Checking to see if user is in: $cGroup", NONSENSITIVE );
				if ( $this->hasLDAPGroup( $cGroup ) ) {
					$this->printDebug( "Adding user to: $cGroup", NONSENSITIVE );
					$user->addGroup( $cGroup );
				}
			}
		}
	}

	/**
	 * Returns a password that is created via the configured hash settings.
	 *
	 * @param string $password
	 * @return string
	 */
	private function getPasswordHash( $password ) {
		$this->printDebug( "Entering getPasswordHash", NONSENSITIVE );

		// Set the password hashing based upon admin preference
		switch ( $this->getConf( 'PasswordHash' ) ) {
			case 'crypt':
				$pass = '{CRYPT}' . crypt( $password );
				break;
			case 'clear':
				$pass = $password;
				break;
			default:
				$pwd_sha = base64_encode( pack( 'H*', sha1( $password ) ) );
				$pass = "{SHA}" . $pwd_sha;
				break;
		}

		return $pass;
	}

	/**
	 * Prints debugging information. $debugText is what you want to print, $debugVal
	 * is the level at which you want to print the information.
	 *
	 * @param string $debugText
	 * @param int $debugVal One of NONSENSITIVE, SENSITIVE or HIGHLYSENSITIVE
	 * @param array|null $debugArr
	 */
	public function printDebug( $debugText, $debugVal, $debugArr = null ) {
		if ( !function_exists( 'wfDebugLog' ) ) {
			return;
		}

		global $wgLDAPDebug;

		if ( $wgLDAPDebug >= $debugVal ) {
			if ( isset( $debugArr ) ) {
				$debugText = $debugText . " " . implode( "::", $debugArr );
			}
			/* Update second parameter when bumping versions */
			wfDebugLog( 'ldap', '2.2.0' . ' ' . $debugText, false );
		}
	}

	/**
	 * Binds as $userdn with $password. This can be called with only the ldap
	 * connection resource for an anonymous bind.
	 *
	 * @param string|null $userdn
	 * @param string|null $password
	 * @return bool
	 */
	public function bindAs( $userdn = null, $password = null ) {
		// Let's see if the user can authenticate.
		if ( $userdn == null || $password == null ) {
			$bind = self::ldap_bind( $this->ldapconn );
		} else {
			$bind = self::ldap_bind( $this->ldapconn, $userdn, $password );
		}
		if ( !$bind ) {
			$this->printDebug( "Failed to bind as $userdn", NONSENSITIVE );
			return false;
		}
		$this->boundAs = $userdn;
		return true;
	}

	/**
	 * Unbind and destroy the current LDAP connection.
	 * @return void
	 */
	public function unbind() {
		self::ldap_unbind( $this->ldapconn );
		// ldap_unbind marks the connection resource as unusable, so discard
		// it so that we know to recreate it when needed in the future.
		$this->ldapconn = null;
		$this->boundAs = null;
	}

	/**
	 * Returns true if auto-authentication is allowed, and the user is
	 * authenticating using the auto-authentication domain.
	 *
	 * @return bool
	 */
	private function useAutoAuth() {
		return $this->getDomain() == $this->getConf( 'AutoAuthDomain' );
	}

	/**
	 * Returns a string which has the chars *, (, ), \ & NUL escaped to LDAP compliant
	 * syntax as per RFC 2254
	 * Thanks and credit to Iain Colledge for the research and function.
	 *
	 * @param string $string
	 * @return string
	 */
	public function getLdapEscapedString( $string ) {
		// Make the string LDAP compliant by escaping *, (, ) , \ & NUL
		return str_replace(
			[ "\\", "(", ")", "*", "\x00" ],
			[ "\\5c", "\\28", "\\29", "\\2a", "\\00" ],
			$string
			);
	}

	/**
	 * Returns a basedn by the type of entry we are searching for.
	 *
	 * @param int $type
	 * @return string
	 */
	public function getBaseDN( $type ) {
		$this->printDebug( "Entering getBaseDN", NONSENSITIVE );

		$ret = '';
		switch ( $type ) {
			case USERDN:
				$ret = $this->getConf( 'UserBaseDN' );
				break;
			case GROUPDN:
				$ret = $this->getConf( 'GroupBaseDN' );
				break;
			case DEFAULTDN:
				$ret = $this->getConf( 'BaseDN' );
				if ( $ret ) {
					return $ret;
				}

				$this->printDebug( "basedn is not set.", NONSENSITIVE );
				return '';
		}

		if ( $ret == '' ) {
			$this->printDebug(
				"basedn is not set for this type of entry, trying to get the default basedn.",
				NONSENSITIVE
			);
			// We will never reach here if $type is self::DEFAULTDN, so to avoid code
			// code duplication, we'll get the default by re-calling the function.
			return $this->getBaseDN( DEFAULTDN );
		}

		$this->printDebug( "basedn is $ret", NONSENSITIVE );
		return $ret;
	}

	/**
	 * @param User $user
	 * @return string|null
	 */
	public static function loadDomain( $user ) {
		$user_id = $user->getId();
		if ( $user_id != 0 ) {
			$dbr = wfGetDB( DB_REPLICA );
			$row = $dbr->selectRow(
				'ldap_domains',
				[ 'domain' ],
				[ 'user_id' => $user_id ],
				__METHOD__ );

			if ( $row ) {
				return $row->domain;
			}
		}

		return null;
	}

	/**
	 * @param User $user
	 * @param string $domain
	 * @return bool
	 */
	public static function saveDomain( $user, $domain ) {
		$user_id = $user->getId();
		if ( $user_id != 0 ) {
			$dbw = wfGetDB( DB_MASTER );
			$olddomain = self::loadDomain( $user );
			if ( $olddomain ) {
				// Check we really need to update domain.
				// Otherwise we can receive an error when logging in with
				// $wgReadOnly.
				if ( $olddomain != $domain ) {
					return $dbw->update(
						'ldap_domains',
						[ 'domain' => $domain ],
						[ 'user_id' => $user_id ],
						__METHOD__
					);
				}
			} else {
				return $dbw->insert(
					'ldap_domains',
					[
						'domain' => $domain,
						'user_id' => $user_id
					],
					__METHOD__
				);
			}
		}
		return false;
	}

}
