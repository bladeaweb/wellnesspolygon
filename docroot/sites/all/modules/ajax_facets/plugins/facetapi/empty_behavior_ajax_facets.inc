<?php

/**
 * @file
 * The empty_text empty behavior class.
 */

/**
 * Empty behavior plugin that displays markup, usually just some text.
 *
 * This plugin allows administrators to display markup filtered through text
 * formats as the contents of a facet when it has no items.
 */
class FacetapiEmptyBehaviorAjaxFacets extends FacetapiEmptyBehavior {

  /**
   * A boolean flagging whether the input format is set, FALSE means it is
   * being pulled from FacetapiEmptyBehavior::getDefaultSettings().
   *
   * @var bool
   */
  protected $formatSet = FALSE;

  /**
   * Overrides FacetapiEmptyBehavior::__construct().
   *
   * Checks if a format was selected, calls parent's constructor.
   */
  public function __construct(stdClass $settings) {
    if (isset($settings->settings['empty_text']['format'])) {
      $this->formatSet = TRUE;
    }
    parent::__construct($settings);
  }

  /**
   * Implements FacetapiEmptyBehavior::execute().
   */
  public function execute() {
    $class1 = str_replace('facetapi_', '', $this->settings['widget']);
    $class1 = drupal_html_class("facet-wrapper-$class1");

    // This class should match facet widget build "id" attribute.
    // Explode configName to searcher, realm name and facet name,
    // and create the class the same way as "id" for widget.
    // @see FacetapiWidget::init()
    $facet_widget_build = explode(':', $this->configName, 3);
    // We shouldn't use drupal_html_id because id shouldn't be changed.
    $id = drupal_html_class("facetapi-facet-$facet_widget_build[0]-{$facet_widget_build[1]}-{$facet_widget_build[2]}-wrapper");
    $class2 = drupal_html_class("facetapi-facet-$facet_widget_build[0]-{$facet_widget_build[1]}-{$facet_widget_build[2]}");
    $wrapper = '<div class="ajax-facets-empty-behavior ' . $class1 . ' ' . $class2 . '" id="' . $id . '"></div>';
    return array('#markup' => $wrapper);
  }
}
