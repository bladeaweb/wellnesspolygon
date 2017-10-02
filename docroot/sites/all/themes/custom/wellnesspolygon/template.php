<?php

/**
 * @file
 * The primary PHP file for this theme.
 */

/**
 * Implements hook_preprocess_block().
 */
function wellnesspolygon_preprocess_block(&$variables) {
  // Add unique class to each blocks.
  $variables['classes_array'][] = $variables['block_html_id'];
}
