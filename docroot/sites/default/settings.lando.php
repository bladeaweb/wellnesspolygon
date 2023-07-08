<?php

/**
 * @file
 * Generated Docksal settings.
 *
 * Do not modify this file if you need to override default settings.
 */

// Lando DB settings.
$info = json_decode(getenv('LANDO_INFO'), TRUE);

$databases = [
  'default' =>
    [
      'default' =>
        [
          'database' => $info['database']['creds']['database'],
          'username' => $info['database']['creds']['user'],
          'password' => $info['database']['creds']['password'],
          'host' => $info['database']['internal_connection']['host'],
          'port' => $info['database']['internal_connection']['port'],
          'driver' => 'mysql',
          'prefix' => '',
        ],
    ],
];
