<?php

/**
 * @file
 * Base ajax_facets widget
 */

/**
 * Widget that renders facets as a list of clickable links.
 *
 * Links make it easy for users to narrow down their search results by clicking
 * on them. The render arrays use theme_item_list() to generate the HTML markup.
 */
class FacetapiAjaxWidget extends FacetapiWidgetCheckboxLinks {

  /**
   * Overrides FacetapiWidget::settingsForm().
   */
  function settingsForm(&$form, &$form_state) {
    $settings = $this->settings->settings;

    $form['widget']['widget_settings']['ajax'][$this->id]['wrapper_container'] = array(
      '#type' => 'container',
      '#states' => array(
        'visible' => array(
          'select[name="widget"]' => array('value' => $this->id),
        ),
      ),
    );

    $form['widget']['widget_settings']['ajax'][$this->id]['show_reset_link'] = array(
      '#type' => 'checkbox',
      '#title' => t('Display reset link'),
      '#default_value' => !empty($settings['show_reset_link']),
      '#description' => t('Display the link to reset facet.'),
      '#states' => array(
        'visible' => array(
          'select[name="widget"]' => array('value' => $this->id),
        ),
      ),
    );

    $form['widget']['widget_settings']['ajax'][$this->id]['reset_link_text'] = array(
      '#type' => 'textfield',
      '#title' => t('Reset link text'),
      '#default_value' => !empty($settings['reset_link_text']) ? $settings['reset_link_text'] : 'Reset filter',
      '#states' => array(
        'visible' => array(
          'select[name="widget"]' => array('value' => $this->id),
        ),
      ),
    );

    $form['widget']['widget_settings']['ajax'][$this->id]['wrapper_container']['wrapper_code'] = array(
      '#type' => 'markup',
      '#markup' => '<div class="form-item messages warning">'
        . t(
          'Please select "@empty" as "@d_text". It\'s need for avoid problem, when some facets was empty on page and cannot be loaded again after
          than values of other facets will be changed.
          ',
          array(
            '@empty' => t('Empty facet behavior'),
            '@d_text' => t('Display ajax_facets wrapper')
          )
        )
        . '<br />'
        . t('More info here !link', array('!link' => l('https://drupal.org/node/2081831', 'https://drupal.org/node/2081831')))
        . '</div>',
    );

    $form['#submit'] = empty($form['#submit']) ? array() : $form['#submit'];
    if (array_search('ajax_facets_facet_settings_form_submit', $form['#submit']) === FALSE) {
      $form['#submit'][] = 'ajax_facets_facet_settings_form_submit';
    }
  }

  /**
   * Returns uuid for the facet widget.
   * It's need to identify the facet option.
   * @param null $value
   *   Some facets have many separate input elements. For example checkboxes or links.
   *   We need $value to identify each option.
   */
  protected function getAjaxFacetsUuid($value = NULL) {
    switch ($this->id) {
      case 'facetapi_ajax_select':
      case 'facetapi_ajax_ranges':
        return str_replace('_', '-', $this->id) . '-' . str_replace(array('_', ' ', ':'), '-', $this->key);
        break;

      case 'facetapi_ajax_links':
      case 'facetapi_ajax_checkboxes':
        return str_replace('_', '-', $this->id) . '-' . str_replace(array('_', ' ', ':'), '-', $this->key . '-' . drupal_strtolower($value));
        break;
    }
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

    // Sets each item's theme hook, builds item list.
    $this->setThemeHooks($element);
    $item_list = array(
      '#theme' => 'item_list',
      '#items' => $this->buildListItems($element),
      '#attributes' => $this->build['#attributes'],
    );

    // We cannot use drupal_html_id to save the same id for each facet.
    $wrapper_id = $this->build['#attributes']['id'] . '-wrapper';

    $element = array(
      '#markup' => '<div id="' . $wrapper_id . '">'
        . $this->getResetLink()
        . render($item_list)
        . '</div>'
    );

    ajax_facets_add_ajax_js($this->facet);
  }

  /**
   * Creates reset link for ajax facets.
   */
  protected function getResetLink() {
    $settings = $this->settings->settings;
    $reset_link = '';
    if (!empty($settings['show_reset_link']) && !empty($settings['reset_link_text'])) {
      // Support for i18n.
      if (function_exists('i18n_string_translate')) {
        $text = i18n_string_translate(
          array(
            'ajax_facets',
            'reset_link_text',
            str_replace(':', '_', $this->key),
            'value'
          ),
          $settings['reset_link_text']
        );
      }
      // Pure text.
      else {
        $text = $settings['reset_link_text'];
      }
      $reset_link = '<a class="reset-link" href="#">' . $text . '</a>';
    }

    return $reset_link;
  }
}
