<?php

/**
 * Expose themes as context reactions.
 */
class context_reaction_delta extends context_reaction {
  /**
   * ugh
   */
  function options_form($context) {
    $values = $this->fetch_from_context($context);
    $form = array(
      '#tree' => TRUE,
      '#title' => t('Delta Override Template'),
      );
      
  		$sql = "
  	    SELECT dt.name, dt.system_name, dt.tid 
  	    FROM {delta_theme_settings} dt
  	    ORDER BY dt.name ASC";
  	  $result = db_query($sql, array(), array('fetch' => PDO::FETCH_ASSOC));
  	  $options = array();
  	  foreach ($result as $item) {
        $options[$item['system_name']] = $item['name'];
  	  }
      
      $form['delta_template'] = array(
        '#type' => 'radios',
        '#title' => t('Select Custom theme settings template.'),
        '#default_value' => isset($values['delta_template']) ? $values['delta_template'] : '',
        '#options' => $options,
      );
    return $form;
  }

  /**
   * Set 'section_title', and 'section_subtitle' if not set and merge all
   * additional classes onto the 'body_classes'.
   */
  function execute(&$vars) {
    $contexts = context_active_contexts();
    foreach ($contexts as $context) {
      if (!empty($context->reactions['delta'])) {
        // add the delta override variable to the $vars so it can be used in omega_preprocess
        $vars['delta'] = 'theme_delta_'.$context->reactions['delta']['delta_template'].'_settings';
        // allow delta to load it's own custom form data, emulating a custom theme.
        GLOBAL $delta_template;
        $delta_template = isset($vars['delta']) ? $vars['delta'] : NULL;
      }
    }    
  }
}
