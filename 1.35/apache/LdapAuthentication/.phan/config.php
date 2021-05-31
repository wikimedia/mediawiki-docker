<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

$cfg['file_list'][] = 'includes/LdapAuthenticationPlugin.php';
$cfg['file_list'][] = 'includes/LdapAutoAuthentication.php';
$cfg['file_list'][] = 'includes/LdapPrimaryAuthenticationProvider.php';

return $cfg;
