# Nova Poshta API
[![Build Status](https://api.shippable.com/projects/554552d4edd7f2c052ddf3d3/badge?branchName=2.x)](https://app.shippable.com/projects/554552d4edd7f2c052ddf3d3/builds/latest)

A PHP implementation of the Nova Poshta API via Guzzle.

This project currently implements the Cities & Warehouses List.

## Usage

You will need an API Key to run the examples.
You can get them from Nova Poshta user cabinet https://my.novaposhta.ua/settings/index#apikeys. Note: you should be registered to do so.

```php
use Drupalway\NovaPoshta\NovaPoshtaClient;

$api = NovaPoshtaClient::factory([
  'defaults' => [
    'api_key' => YOUR_API_KEY,
  ]
]);
```
### Get cities list (partial search by title)
```php
use Drupalway\NovaPoshta\NovaPoshtaClient;

$api = NovaPoshtaClient::factory([
  'defaults' => [
    'api_key' => YOUR_API_KEY,
  ]
]);

$cities = $api->getCities();

$cities = $api->getCities([
  'filters' => [
    'search_text' => 'Ки',
  ]]);
```

## Requirements

 * PHP 5.4
 * php5-curl (suggested, unless you want to use a custom adapter)

## Credits

[Guzzle](http://guzzlephp.org) does most of the heavy lifting. This project is really just an elaborate Guzzle Services config.

## License

MIT