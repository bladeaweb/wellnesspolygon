<?php

/**
 * @file
 * Drupal settings.
 *
 * Create settings.local.php file to include local settings overrides.
 */

// Environment constants.
define('ENVIRONMENT_LOCAL', 'local');
define('ENVIRONMENT_PROD', 'prod');
$conf['environment'] = ENVIRONMENT_LOCAL;

// Session variables.
ini_set('session.gc_probability', 1);
ini_set('session.gc_divisor', 100);
ini_set('session.gc_maxlifetime', 200000);
ini_set('session.cookie_lifetime', 2000000);

$update_free_access = FALSE;

$drupal_hash_salt = 'O6rPZysUeDkDs0HY4EJVct2wEGr9nmGjtXazP66u_VM';

$conf['404_fast_paths_exclude'] = '/\/(?:styles)|(?:system\/files)\//';
$conf['404_fast_paths'] = '/\.(?:txt|png|gif|jpe?g|css|js|ico|swf|flv|cgi|bat|pl|dll|exe|asp)$/i';
$conf['404_fast_html'] = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML+RDFa 1.0//EN" "http://www.w3.org/MarkUp/DTD/xhtml-rdfa-1.dtd"><html xmlns="http://www.w3.org/1999/xhtml"><head><title>404 Not Found</title></head><body><h1>Not Found</h1><p>The requested URL "@path" was not found on this server.</p></body></html>';

$databases = [
  'default' =>
    [
      'default' =>
        [
          'database' => getenv('MYSQL_DATABASE'),
          'username' => getenv('drupal7'),
          'password' => getenv('MYSQL_PASSWORD'),
          'host' => 'mysql',
          'port' => '',
          'driver' => 'mysql',
          'prefix' => '',
        ],
    ],
];

// Include generated beetbox settings file.
if (file_exists(DRUPAL_ROOT . '/' . conf_path() . '/settings.beetbox.php')) {
  include DRUPAL_ROOT . '/' . conf_path() . '/settings.beetbox.php';
}

// Load local development override configuration, if available.
//
// Use settings.local.php to override variables on secondary (staging,
// development, etc) installations of this site. Typically used to disable
// caching, JavaScript/CSS compression, re-routing of outgoing emails, and
// other things that should not happen on development and testing sites.
//
// Keep this code block at the end of this file to take full effect.
if (file_exists(DRUPAL_ROOT . '/' . conf_path() . '/settings.local.php')) {
  include DRUPAL_ROOT . '/' . conf_path() . '/settings.local.php';
}

// Lando settings.
if (getenv('LANDO_INFO') && file_exists(DRUPAL_ROOT . '/' . conf_path() . '/settings.lando.php')) {
  include DRUPAL_ROOT . '/' . conf_path() . '/settings.lando.php';
}
