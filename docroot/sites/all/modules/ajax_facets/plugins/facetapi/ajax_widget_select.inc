<?php

/**
 * @file
 * The facetapi_links and facetapi_checkbox_links widget plugin classes.
 */

/**
 * Widget that renders facets as a list of clickable links.
 *
 * Links make it easy for users to narrow down their search results by clicking
 * on them. The render arrays use theme_item_list() to generate the HTML markup.
 */
class FacetapiAjaxWidgetSelect extends FacetapiAjaxWidget {

  /**
   * Overrides FacetapiWidget::getDefaultSettings().
   */
  function getDefaultSettings() {
    return array(
      "show_reset_link" => 0,
    );
  }

  /**
   * Overrides FacetapiWidget::settingsForm().
   */
  public function settingsForm(&$form, &$form_state) {
    parent::settingsForm($form, $form_state);
    $form['widget']['widget_settings']['links'][$this->id]['ajax_select_default_option_label'] = array(
      '#title' => t('Default Option Label'),
      '#type' => 'textfield',
      '#default_value' => !empty($this->settings->settings['ajax_select_default_option_label']) ? $this->settings->settings['ajax_select_default_option_label'] : t('Select'),
      '#states' => array(
        'visible' => array(
          'select[name="widget"]' => array('value' => $this->id),
        ),
      ),
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
  function buildListItems($build, $level = 0) {
    $settings = $this->settings->settings;

    // Builds rows.
    $items = array(
      '#disabled_items' => array(),
    );

    // Add default item only once.
    if ($level == 0) {
      $label = $settings['ajax_select_default_option_label'];
      // Translate through i18n if it's possible.
      if (function_exists('i18n_string_translate')) {
        $label = i18n_string_translate(array('ajax_facets', 'facet_label', str_replace(':', '_', $this->key), 'label'), $label);
      }
      $items['values']['_none'] = $label;
    }

    $prefix = str_repeat('-', $level);
    $level++;

    $active_items = array();
    $have_active = FALSE;
    foreach ($build as $value => $item) {
      // Respect current selection.
      if (!empty($item['#active'])) {
        $items['active_value'] = $value;
        $have_active = TRUE;
        $active_items[] = $this->key . ':' . $item['#markup'];
      }

      $items['values'][$item['#indexed_value']] = $prefix . $item['#markup'];

      // Mark as disabled if count is 0.
      if ($item['#count'] == 0) {
        $items['#disabled_items'][$item['#indexed_value']] = TRUE;
      }

      // Show/hide counts according to the settings.
      if (!empty($this->settings->settings['display_count'])) {
        $items['values'][$item['#indexed_value']] .= ' ' . theme('facetapi_count',(array('count' => $item['#count'])));
      }

      if (!empty($item['#item_children'])) {
        $childrens = $this->buildListItems($item['#item_children'], $level);
        if (!empty($childrens['active_value'])) {
          $items['active_value'] = $childrens['active_value'];
        }
        $items['values'] = $items['values'] + $childrens['values'];
      }
    }

    $this->jsSettings['haveActiveSelection'] = $this->settings->settings['have_active_selection'] = $have_active;
    sort($active_items);
    $this->jsSettings['activeItems'] = $active_items;
    // Generate reset path on server side to make possible to use aliases.
    if ($have_active) {
      $this->jsSettings['resetPath'] = ajax_facets_facet_build_reset_path($this->facet->getFacet(), $this->facet->getAdapter());
    }
    return $items;
  }

  /**
   * Implements FacetapiWidget::execute().
   *
   * Transforms the render array into something that can be themed by
   * theme_item_list().
   *
   * @see FacetapiWidgetLinks::setThemeHooks()
   * @see FacetapiWidgetLinks::buildListItems()
   */
  public function execute() {
    $element = &$this->build[$this->facet['field alias']];
    $items = $this->buildListItems($element);

    // If name is empty - use label.
    if (empty($items['name'])) {
      $facet = $this->facet->getFacet();
      $items['name'] = $facet['label'];
    }

    $select = array(
      '#type' => 'select',
      '#title' => $this->build['#title'],
      '#options' => $items['values'],
      '#name' => rawurlencode($this->settings->facet),
      '#attributes' => array(
        'data-facet-name' => rawurlencode($this->settings->facet),
        'data-raw-facet-name' => $this->settings->facet,
      ),
      '#theme' => 'ajax_facets_select',
      '#disabled_items' => $items['#disabled_items'],
    );

    if (!empty($items['active_value'])) {
      $select['#value'] = $items['active_value'];
    }

    // We cannot use drupal_html_id to save the same id for each facet.
    $wrapper_id = $this->build['#attributes']['id'] . '-wrapper';

    $element = array(
      '#markup' => '<div id="' . $wrapper_id . '">'
        . $this->getResetLink()
        . render($select)
        . '</div>'
    );

    ajax_facets_add_ajax_js($this->facet);
  }
}
