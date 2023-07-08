<?php

/**
 * @file
 * Contains NovaPoshtaClient.
 */

namespace Drupalway\NovaPoshta;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Command\Guzzle\GuzzleClient;
use GuzzleHttp\Command\Guzzle\Description;
use Drupalway\NovaPoshta\Subscriber\CommandWrapper;
use Drupalway\NovaPoshta\Subscriber\ResponseWrapper;
use Drupalway\NovaPoshta\Subscriber\ErrorHandlerListener;

/**
 * NovaPoshtaClient object for executing commands against the API.
 *
 * @method array getCities(array $arguments)
 * @method array getWarehouses(array $arguments)
 *
 * @package Drupalway\NovaPoshta
 */
class NovaPoshtaClient extends GuzzleClient {

  /**
   * Gets a new NovaPoshtaClient.
   *
   * @param array $config
   *   GuzzleHttp\Command\Guzzle\GuzzleClient $config options.
   * @param array $http_config
   *   GuzzleHttp\Client $config options.
   *
   * @return NovaPoshtaClient
   *   An wrapped GuzzleClient.
   */
  public static function factory($config = [], $http_config = []) {
    $description_path = __DIR__ . '/Resources/novaposhta-json-v2.php';

    $description = new Description(include $description_path);

    $defaults = [];

    $config = array_merge($defaults, $config);

    $http_client = new HttpClient($http_config);

    $client = new self($http_client, $description, $config);

    $emitter = $client->getEmitter();

    $emitter->attach(new ResponseWrapper());
    $emitter->attach(new ErrorHandlerListener($description));
    $emitter->attach(new CommandWrapper());

    return $client;
  }

}
