<?php

/**
 * @file
 * The facetapi ajax links widget.
 */

/**
 * Widget that renders facets as a list of clickable links.
 *
 * Links make it easy for users to narrow down their search results by clicking
 * on them. The render arrays use theme_item_list() to generate the HTML markup.
 */
class FacetapiAjaxWidgetLinks extends FacetapiAjaxWidget {

  /**
   * Overrides FacetapiWidget::getDefaultSettings().
   */
  function getDefaultSettings() {
    return array(
      "show_reset_link" => 0,
    );
  }

  /**
   * Transforms the render array for use with theme_item_list().
   *
   * The recursion allows this function to act on the various levels of a
   * hierarchical data set.
   *
   * @param array $build
   *   The items in the facet's render array being transformed.
   *
   * @return array
   *   The "items" parameter for theme_item_list().
   */
  function buildListItems($build) {

    $settings = $this->settings->settings;

    // Initializes links attributes, adds rel="nofollow" if configured.
    $attributes = ($settings['nofollow']) ? array('rel' => 'nofollow') : array();
    $attributes += array('class' => $this->getItemClasses());

    // Builds rows.
    $items = array();
    foreach ($build as $value => $item) {
      $row = array('class' => array());

      // Initializes variables passed to theme hook.
      $variables = array(
        'text' => $item['#markup'],
        'path' => $item['#path'],
        'count' => !empty($settings['display_count']) ? $item['#count'] : NULL,
        'options' => array(
          'attributes' => $attributes,
          'html' => $item['#html'],
          'query' => $item['#query'],
        ),
      );

      // Adds the facetapi-zero-results class to items that have no results.
      if (!$item['#count']) {
        $variables['options']['attributes']['class'][] = 'facetapi-zero-results';
      }

      // If the item has no children, it is a leaf.
      if (empty($item['#item_children'])) {
        $row['class'][] = 'leaf';
      }
      else {
        // If the item is active or the "show_expanded" setting is selected,
        // show this item as expanded so we see its children.
        if (!empty($item['#active']) || !empty($settings['show_expanded'])) {
          $row['class'][] = 'expanded';
          $row['children'] = $this->buildListItems($item['#item_children']);
        }
        else {
          $row['class'][] = 'collapsed';
        }
      }

      // Gets theme hook, adds last minute classes.
      $class = !empty($item['#active']) ? 'facetapi-active' : 'facetapi-inactive';
      $variables['options']['attributes']['class'][] = $class;

      // Set options for ajax query.
      $variables['options']['attributes']['data-facet-value'] = $item['#indexed_value'];
      $variables['options']['attributes']['data-facet-name'] = rawurlencode($this->settings->facet);
      $variables['options']['attributes']['data-raw-facet-name'] = $this->settings->facet;

      // Themes the link, adds row to items.
      $row['data'] = theme($item['#theme'], $variables);
      $items[] = $row;
    }

    $facet_settings = $this->facet->getSettings();
    $this->jsSettings['limit_active_items'] = $facet_settings->settings['limit_active_items'];

    return $items;
  }

  /**
   * Recursive function that sets each item's theme hook.
   *
   * The indivual items will be rendered by different theme hooks depending on
   * whether or not they are active.
   *
   * @param array &$build
   *   A render array containing the facet items.
   *
   * @return FacetapiWidget
   *   An instance of this class.
   *
   * @see theme_facetapi_link_active()
   * @see theme_facetapi_link_inactive()
   */
  protected function setThemeHooks(array &$build) {
    foreach ($build as $value => &$item) {
      $item['#theme'] = !empty($item['#active']) ? 'facetapi_link_active' : 'facetapi_link_inactive';
      if (!empty($item['#item_children'])) {
        $this->setThemeHooks($item['#item_children']);
      }
    }
    return $this;
  }

  /**
   * Overrides FacetapiWidgetLinks::getItemClasses().
   *
   * Sets the base class for checkbox facet items.
   */
  public function getItemClasses() {
    return array();
  }
}
