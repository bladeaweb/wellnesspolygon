<?php
/**
 * Created by PhpStorm.
 * User: vlad
 * Date: 27.04.15
 * Time: 01:20
 */

namespace Drupalway\NovaPoshta\Tests\API;

use GuzzleHttp\Message\Response;
use GuzzleHttp\Subscriber\History;
use GuzzleHttp\Subscriber\Mock;
use GuzzleHttp\Stream\Stream;
use Drupalway\NovaPoshta\NovaPoshtaClient;


class NovaPoshtaClientTest extends \PHPUnit_Framework_TestCase {
  public function providerForTestRequestBody() {
    return [
      [
        'getCities',
        [
          'filters' => [
            'search_text' => 'Киев',
            'city_id' => '8d5a980d-391c-11dd-90d9-001a92567626',
            'page' => 1,
          ]
        ],
        'POST',
        '{"methodProperties":{' .
        '"FindByString":"\u041a\u0438\u0435\u0432",' .
        '"Ref":"8d5a980d-391c-11dd-90d9-001a92567626",' .
        '"Page":"1"' .
        '},' .
        '"modelName":"Address",' .
        '"apiKey":"",' .
        '"calledMethod":"getCities"}',
      ],
      [
        'getWarehouses',
        [
          'filters' => [
            'city_id' => '8d5a980d-391c-11dd-90d9-001a92567626',
            'page' => 1,
          ]
        ],
        'POST',
        '{"methodProperties":{' .
        '"CityRef":"8d5a980d-391c-11dd-90d9-001a92567626",' .
        '"Page":"1"' .
        '},' .
        '"modelName":"Address",' .
        '"apiKey":"",' .
        '"calledMethod":"getWarehouses"}',
      ],
    ];
  }

  /**
   * @dataProvider providerForTestRequestBody
   */
  public function testRequestBody($command, $params, $expectedMethod, $expectedBody) {
    $api = NovaPoshtaClient::factory([
      'defaults' => [
        'api_key' => '',
      ]
    ]);

    $body    = Stream::factory('{"success":true,"data":[]}');
    $mock    = new Mock([new Response(200, [], $body)]);
    $history = new History();
    $emitter = $api->getHttpClient()->getEmitter();

    $emitter->attach($mock);
    $emitter->attach($history);

    $command = $api->getCommand($command, $params);
    $api->execute($command);

    $request = $history->getLastRequest();

    $this->assertEquals($expectedMethod, $request->getMethod());
    $this->assertEquals('application/json', $request->getHeader('Content-Type'));
    $this->assertEquals($expectedBody, (string) $request->getBody());
  }
}