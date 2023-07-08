<?php

/**
 * @file
 * Description of the Nova Poshta API.
 *
 * @see GuzzleHttp\Command\Guzzle\Description
 */

return [
  'description' => 'Nova Poshta API',
  'baseUrl' => 'https://api.novaposhta.ua/v2.0/json/',
  'operations' => [
    'AbstractOperation' => [
      'parameters' => [
        'api_key'  => [
          'location' => 'json',
          'required' => TRUE,
          'sentAs' => 'apiKey'
        ],
      ]
    ],
    'AddressOperation' => [
      'extends' => 'AbstractOperation',
      'parameters' => [
        'modelName' => [
          'type'     => 'string',
          'location' => 'json',
          'static'   => TRUE,
          'default'  => 'Address'
        ]
      ]
    ],
    'getCities' => [
      'extends' => 'AddressOperation',
      'httpMethod' => 'POST',
      'parameters' => [
        'filters' => [
          'location' => 'json',
          'type'     => 'object',
          'sentAs' => 'methodProperties',
          'properties' => [
            'search_text' => [
              'type'   => 'string',
              'sentAs' => 'FindByString'
            ],
            'city_id' => [
              'type'   => 'string',
              'sentAs' => 'Ref'
            ],
            'page' => [
              'type'   => 'string',
              'sentAs' => 'Page'
            ]
          ]
        ],
      ],
      'responseModel' => 'CityListResponse',
      'summary' => 'Returns list of cities there are warehouses of the Nova Poshta.'
    ],
    'getWarehouses' => [
      'extends' => 'AddressOperation',
      'httpMethod' => 'POST',
      'parameters' => [
        'filters' => [
          'location' => 'json',
          'type'     => 'object',
          'sentAs' => 'methodProperties',
          'properties' => [
            'city_id' => [
              'type'     => 'string',
              'required' => TRUE,
              'sentAs'   => 'CityRef'
            ],
            'page' => [
              'description' => 'Response will return up to 500 items per request',
              'type'   => 'string',
              'sentAs' => 'Page',
            ]
          ]
        ],
      ],
      'responseModel' => 'WarehousesListResponse',
      'summary' => 'Returns list of warehouses of the Nova Poshta.'
    ]
  ],
  'models' => [
    'AbstractResponse' => [
      'type' => 'object',
      'properties' => [
        'success' => [
          'type'  => 'boolean',
          'location' => 'json'
        ],
        'errors' => [
          'type' => ['string', 'array'],
          'location' => 'json'
        ],
        'warnings' => [
          'type' => 'array',
          'location' => 'json'
        ],
        'info' => [
          'type' => 'array',
          'location' => 'json'
        ]
      ]
    ],
    'CityListResponse' => [
      'type' => 'object',
      'extends' => 'AbstractResponse',
      'properties'  => [
        'data' => [
          'type' => 'array',
          'location' => 'json',
          'items' => [
            'type' => 'object',
            'properties' => [
              'description' => [
                'type' => 'string',
                'sentAs' => 'Description'
              ],
              'description_ru' => [
                'type' => 'string',
                'sentAs' => 'DescriptionRu'
              ],
              'city_id' => [
                'type' => 'string',
                'sentAs' => 'Ref'
              ],
              'area_id' => [
                'type' => 'string',
                'sentAs' => 'Area'
              ]
            ]
          ]
        ]
      ]
    ],
    'WarehousesListResponse' => [
      'type' => 'object',
      'extends' => 'AbstractResponse',
      'properties'  => [
        'data' => [
          'type' => 'array',
          'location' => 'json',
          'items' => [
            'type' => 'object',
            'properties' => [
              'description' => [
                'type' => 'string',
                'sentAs' => 'Description'
              ],
              'description_ru' => [
                'type' => 'string',
                'sentAs' => 'DescriptionRu'
              ],
              'warehouse_id' => [
                'type' => 'string',
                'sentAs' => 'Ref'
              ],
              'city_id' => [
                'type' => 'string',
                'sentAs' => 'CityRef'
              ],
              'department_id' => [
                'type' => 'numeric',
                'sentAs' => 'Number'
              ],
              'lon' => [
                'type' => 'string',
                'sentAs' => 'Longitude'
              ],
              'lat' => [
                'type' => 'string',
                'sentAs' => 'Latitude'
              ],
              'department_type_id' => [
                'type' => 'string',
                'sentAs' => 'TypeOfWarehouse'
              ]
            ]
          ]
        ]
      ]
    ]
  ]

];
