<?php

/**
 * Implements hook_views_data_alter().
 * @param $data
 */
function commerce_option_views_data_alter(&$data){

  $data['commerce_product']['commerce_option'] = array(
    'title' => t('Related option sets'),
    'relationship' => array(
      'base' => 'commerce_option',
      'base field' => 'product_id',
      'relationship field' => 'product_id',
      'handler' => 'entity_views_handler_relationship_by_bundle',
      'label' => t('Commerce option'),
      'title' => t('Commerce options referenced by this product'),
      'help' => t('Relate a product to commerce options it references.'),
    )
  );

  $data['commerce_line_item']['commerce_option'] = array(
    'title' => t('Related option sets'),
    'relationship' => array(
      'base' => 'commerce_option',
      'base field' => 'line_item_id',
      'relationship field' => 'line_item_id',
      'handler' => 'entity_views_handler_relationship_by_bundle',
      'label' => t('Commerce option'),
      'title' => t('Commerce options referenced by this line item'),
      'help' => t('Relate a line item to commerce options it references.'),
    )
  );

  $data['commerce_option']['product_id']['relationship'] = array(
    'base' => 'commerce_product',
    'base field' => 'product_id',
    'relationship field' => 'product_id',
    'handler' => 'entity_views_handler_relationship_by_bundle',
    'label' => t('Product'),
    'title' => t('Products that reference this option'),
    'help' => t('Relate an option to the products referencing it.')
  );

  $data['commerce_option']['line_item_id']['relationship'] = array(
    'base' => 'commerce_line_item',
    'base field' => 'line_item_id',
    'relationship field' => 'line_item_id',
    'handler' => 'entity_views_handler_relationship_by_bundle',
    'label' => t('Line item'),
    'title' => t('Line items that reference this option'),
    'help' => t('Relate an option to the line items referencing it.')
  );
}
