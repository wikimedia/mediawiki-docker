<?php
/**
 * @section LICENSE
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
 */

if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

use MediaWiki\Auth\AuthManager;
use MediaWiki\MediaWikiServices;

/**
 * Attach an existing LDAP account to the local wiki with all expected log
 * events created as well.
 *
 * Originally developed as a component of the MediaWiki OpenStackManager
 * extension.
 *
 * @copyright © 2018 Wikimedia Foundation and contributors
 */
class AttachLdapUser extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( "Attach an existing LDAP user to the local wiki" );
		$this->addOption( 'user', 'Username', true, true );
		$this->addOption( 'email', 'Email address', true, true );
		$this->addOption( 'domain', 'LDAP domain', false, true );
		$this->requireExtension( 'LDAP Authentication Plugin' );
	}

	public function execute() {
		// Setup the internal state of LdapAuthenticationPlugin as though the
		// login form was used. Ugly but this is what LdapAuthentication
		// forces us to do.
		$ldap = LdapAuthenticationPlugin::getInstance();
		$ldap->LDAPUsername = $this->getOption( 'user' );
		$ldap->email = $this->getOption( 'email' );
		$domain = $this->getOption( 'domain', $ldap->getDomain() );
		$ldap->setDomain( $domain );
		$_SESSION['wsDomain'] = $domain;

		$user = User::newFromName( $ldap->LDAPUsername, 'creatable' );
		if ( method_exists( MediaWikiServices::class, 'getAuthManager' ) ) {
			// MediaWiki 1.35+
			$authManager = MediaWikiServices::getInstance()->getAuthManager();
		} else {
			$authManager = AuthManager::singleton();
		}
		$authManager->autoCreateUser(
			$user, LdapPrimaryAuthenticationProvider::class, false );
	}
}

$maintClass = AttachLdapUser::class;
require_once RUN_MAINTENANCE_IF_MAIN;
