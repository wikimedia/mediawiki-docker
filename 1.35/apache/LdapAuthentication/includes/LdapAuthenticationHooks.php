<?php
/**
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

use MediaWiki\Block\DatabaseBlock;

class LdapAuthenticationHooks {

	/**
	 * Get an LdapAuthenticationPlugin instance that is connected to the LDAP
	 * directory and bound as the wgLDAPWriterDN user.
	 *
	 * @return LdapAuthenticationPlugin|bool LDAP connection or false if initialization fails
	 */
	private static function getLDAP() {
		$ldap = LdapAuthenticationPlugin::getInstance();
		if ( $ldap->ldapconn === null ) {
			if ( !$ldap->connect() ) {
				$ldap->printDebug( 'Failed to connect to LDAP directory', NONSENSITIVE );
				return false;
			}
		}
		$writer = $ldap->getConf( 'WriterDN' );
		if ( !$writer ) {
			$ldap->printDebug( "Wiki doesn't have wgLDAPWriterDN set", NONSENSITIVE );
			return false;

		}
		$bind = $ldap->bindAs( $writer, $ldap->getConf( 'WriterPassword' ) );
		if ( !$bind ) {
			$ldap->printDebug( 'Failed to bind to directory as wgLDAPWriterDN', NONSENSITIVE );
			return false;
		}
		return $ldap;
	}

	/**
	 * Lock/unlock an LDAP account via a 'pwdAccountLockedTime' attribute.
	 * Optionally set a password policy. This should help cover some cases
	 * pwdAccountLockedTime doesn't cover well like out-of-band (e.g. by an
	 * admin) password resets.
	 *
	 * @param User $user User to lock/unlock
	 * @param bool $lock True to lock, False to unlock
	 * @return null|bool|string status of operation, suitable for use as a Hook
	 *   handler response
	 */
	private static function setLdapLockStatus( User $user, $lock ) {
		$ldap = static::getLDAP();
		if ( !$ldap ) {
			return 'Failed to initialize LDAP connection';
		}

		$ppolicy = $ldap->getConf( 'LDAPLockPasswordPolicy' );

		$actionStr = $lock ? 'lock' : 'unlock';
		// * '000001010000Z' means that the account has been locked
		// permanently, and that only a password administrator can unlock the
		// account.
		// * If a password policy has been configured, apply that as well
		// * empty array means delete the attribute
		$lockData = [];
		$lockData['pwdAccountLockedTime'] = $lock ? '000001010000Z' : [];
		if ( $ppolicy ) {
			$lockData['pwdPolicySubentry'] = $lock ? $ppolicy : [];
		}

		$userDN = $ldap->getUserDN( $user->getName() );
		if ( !$userDN ) {
			return "Failed to lookup DN for user {$user->getName()}";
		}
		$ldap->printDebug(
			"Attempting to {$actionStr} {$userDN}", NONSENSITIVE );
		$success = LdapAuthenticationPlugin::ldap_modify(
			$ldap->ldapconn,
			$userDN,
			$lockData );
		if ( !$success ) {
			$msg = "Failed to {$actionStr} LDAP account {$userDN}";
			$errno = LdapAuthenticationPlugin::ldap_errno( $ldap->ldapconn );
			$ldap->printDebug( $msg . ": LDAP errno {$errno}", NONSENSITIVE );
			return $msg;
		}
	}

	/**
	 * Inspect new blocks and lock the backing LDAP account when an indefinite
	 * block is made against a specific user. Alternately, unlock the account
	 * if a new block is placed replacing a prior indefinite block.
	 *
	 * @param DatabaseBlock $block The block object that was saved
	 * @param User $user The user who performed the unblock
	 * @param DatabaseBlock|null $prior Previous block that was replaced
	 * @return null|bool|string Hook status
	 */
	public static function onBlockIpComplete( DatabaseBlock $block, User $user, $prior ) {
		global $wgLDAPLockOnBlock;
		if ( $wgLDAPLockOnBlock ) {
			if ( $block->getType() === DatabaseBlock::TYPE_USER
				&& $block->getExpiry() === 'infinity'
				&& $block->isSitewide()
			) {
				return static::setLdapLockStatus( $block->getTarget(), true );
			} elseif ( $prior ) {
				// New block replaced a prior block. Process the prior block
				// as though it was explicitly removed.
				return static::onUnblockUserComplete( $prior, $user );
			}
		}
	}

	/**
	 * Inspect removed blocks and unlock the backing LDAP account when an
	 * indefinite block is lifted against a specific user.
	 *
	 * @param DatabaseBlock $block the block object that was saved
	 * @param User $user The user who performed the unblock
	 * @return null|bool|string Hook status
	 */
	public static function onUnblockUserComplete( DatabaseBlock $block, User $user ) {
		global $wgLDAPLockOnBlock;
		if ( $wgLDAPLockOnBlock
			&& $block->getType() === DatabaseBlock::TYPE_USER
			&& $block->getExpiry() === 'infinity'
			&& $block->isSitewide()
		) {
			return static::setLdapLockStatus( $block->getTarget(), false );
		}
	}

	public static function onRegistration() {
		global $wgPasswordResetRoutes;
		$wgPasswordResetRoutes['domain'] = true;

		// constants for search base
		define( "GROUPDN", 0 );
		define( "USERDN", 1 );
		define( "DEFAULTDN", 2 );

		// constants for error reporting
		define( "NONSENSITIVE", 1 );
		define( "SENSITIVE", 2 );
		define( "HIGHLYSENSITIVE", 3 );
	}

	/**
	 * @param DatabaseUpdater $updater
	 * @return bool
	 */
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
		$base = dirname( __DIR__ );
		switch ( $updater->getDB()->getType() ) {
			case 'mysql':
			case 'sqlite':
				$updater->addExtensionTable(
					'ldap_domains',
					"$base/schema/ldap-mysql.sql"
				);
			break;

			case 'postgres':
				$updater->addExtensionTable(
					'ldap_domains',
					"$base/schema/ldap-postgres.sql"
				);
				break;
		}
		return true;
	}

}
