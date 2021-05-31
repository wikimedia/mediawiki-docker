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

/**
 * LdapAuthentication plugin. LDAP Authentication and authorization integration with MediaWiki.
 *
 * @file
 * @ingroup MediaWiki
 */

/**
 * LdapAuthentication.php
 *
 * Info available at https://www.mediawiki.org/wiki/Extension:LDAP_Authentication
 * Support is available at https://www.mediawiki.org/wiki/Extension_talk:LDAP_Authentication
 */

/**
 * Sets up the auto-authentication piece of the LDAP plugin.
 *
 * This has been broken for numerous versions and will be removed when this PHP entry point is
 * removed in the near future.
 */
function AutoAuthSetup() {
	/**
	 * @todo If you want to make AutoAuthSetup() work in an AuthManager
	 *  world, what you need to do is figure out how to do it with a
	 *  SessionProvider instead of the hackiness below. You'll probably
	 *  want an ImmutableSessionProviderWithCookie subclass where
	 *  provideSessionInfo() does the first part of
	 *  LdapAutoAuthentication::Authenticate() (stop before the $localId
	 *  bit).
	 */
	throw new BadFunctionCallException( 'AutoAuthSetup() is not supported with AuthManager.' );
}

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'LdapAuthentication' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['LdapAuthentication'] = __DIR__ . '/i18n';
	/* wfWarn(
		'Deprecated PHP entry point used for LdapAuthentication extension. '.
		'Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	); */
	return;
} else {
	die( 'This version of the LdapAuthentication extension requires MediaWiki 1.29+' );
}
