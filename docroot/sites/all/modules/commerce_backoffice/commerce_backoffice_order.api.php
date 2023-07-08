<?php

/**
 * @file
 * Documents hooks invoked by the Commerce Backoffice Order module.
 */

/**
 * Alter the order's megarow operations links.
 *
 * @param $links
 *   A list of links to provide within the dropbutton, suitable for use
 *   in via Drupal's theme('links').
 * @param $context
 *   An associative array containing the following key-value pairs:
 *   - view: The view object.
 *   - order_id: The order's ID.
 */
function hook_commerce_backoffice_order_operations_links_alter(&$links, $context) {
  if (isset($links['commerce-order-history'])) {
    $links['commerce-order-history']['title'] = t('View history');
  }
}
