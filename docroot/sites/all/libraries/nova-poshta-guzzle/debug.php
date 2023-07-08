<?php

require 'vendor/autoload.php';

use Drupalway\NovaPoshta\NovaPoshtaClient;

$params = [
  'defaults' => [
    'api_key' => '',
  ]
];

$api = NovaPoshtaClient::factory($params);
// right city id = 8d5a980d-391c-11dd-90d9-001a92567626
$cities = $api->getWarehouses([
  'filters' => [
    'city_id' => 'vdvdvd',
    'page' => 1
  ]]);
var_dump($cities);
