<?php

/**
 * @file 
 * Delta UI functionality
 *
 * ===============================
 * ====  =========  ==============
 * ====  =========  ==============
 * ====  =========  ===  =========
 * ====  ===   ===  ==    ===   ==
 * ==    ==  =  ==  ===  ===  =  =
 * =  =  ==     ==  ===  ======  =
 * =  =  ==  =====  ===  ====    =
 * =  =  ==  =  ==  ===  ===  =  =
 * ==    ===   ===  ===   ===    =
 * ===============================
 * 
 */

/**
 * Menu callback; displays the delta listing page.
 */
function delta_theme_settings_config($form, &$form_state) {
  drupal_set_title(t('Delta Theme Settings'));
  $form['delta'] = array(
    '#type' => 'fieldset',
    '#title' => t('Delta Theme API Settings & Configuration'),
    '#description' => t('In these default settings for <a href="http://himer.us/delta-module">Delta</a>, you can select which active themes will use contextual theme settings. Only enabled themes are listed here.'),
    '#collapsible' => TRUE,
    '#collapsed' => FALSE,
  );
  // get active themes for the settings form
  $filter_themes = FALSE;
  $themes = delta_get_themes_form_array($filter_themes);
  // create checkboxes to select themes that may be manipulated
  $form['delta']['delta_themes'] = array(
    '#type' => 'checkboxes',
    '#title' => t('Themes to manipulate'),
    '#options' => $themes,
    '#default_value' => variable_get('delta_themes', array()),
    '#description' => t('Here you may select the themes that should be manipulated by Delta. Only themes that are enabled will be listed. You can enable/disable themes via the <a href="'. base_path() .'admin/appearance">Theme Administration</a> page.'),
  );
  return system_settings_form($form);
}


/**
 * Menu callback;
 */
function delta_layouts_view($theme_name = FALSE) {
  drupal_set_title(t('Delta Layouts'));
  $delta_layouts_list = drupal_get_form('delta_layouts_list', $theme_name);
  return render($delta_layouts_list);
}

/**
 * Menu callback; displays the delta listing page.
 */
function delta_layouts_list($form, &$form_state) {
  $form = array();
  $form['delta'] = array(
    '#type' => 'vertical_tabs',
    '#weight' => 10,
  );
  $themes = delta_get_themes_array();
  foreach($themes AS $info) {
  	// create fieldset for each theme we have the ability to manipulate
  	$form['delta'][$info->name] = array(
	    '#type' => 'fieldset',
	    '#title' => t($info->info['name']),
	  );
	  // build the data for each theme, and it's theme settings templates
	  $form['delta'][$info->name]['content'] = delta_get_layouts($info->name);
  }
	return $form;
}

/**
 * delta_get_layouts function.
 * 
 * @access public
 * @param mixed $theme. (default: FALSE)
 * @return void
 * @todo Make the SQL SELECT statement conform to the PDO
 */
function delta_get_layouts($theme = FALSE) {
	if (isset($theme)) {
		$header = array(t('Name'), array('data' => t('Operations'), 'colspan' => '3'));
    $rows = array();
		$sql = "
	    SELECT dt.name, dt.system_name, dt.tid 
	    FROM {delta_theme_settings} dt
	    WHERE dt.theme = :theme
	    ORDER BY dt.name ASC";
	  $result = db_query($sql, array(':theme' => $theme), array('fetch' => PDO::FETCH_ASSOC));
	  foreach ($result as $item) {
      $row = array(
        'name' => '<h3>' . $item['name'] . '</h3><span class="description"><strong>' . t('Machine Name: ') . '</strong><em>' . $item['system_name'] . '</em></span>',
        'edit' => l(t('Edit'), 'admin/appearance/delta/layouts/edit/' . $item['system_name']),
        'configure' => l(t('Configure'), 'admin/appearance/delta/layouts/configure/' . $item['system_name']),
        'delete' => l(t('Delete'), 'admin/appearance/delta/layouts/delete/' . $item['system_name']),
      );
	  	$rows[] = $row;
	  }
		$build['table'] = array(
	    '#theme' => 'table',
	    '#header' => $header,
	    '#rows' => $rows,
	    '#empty' => t('No theme settings templates available. <a href="@link">Add layout</a>.', array('@link' => url('admin/appearance/delta/layouts/add'))),
	  );
		return $build;
	}
	return FALSE;
}




/**
 * delta_get_layout_data function.
 * 
 * @access public
 * @param mixed $layout
 * @return void
 * @todo Make the SQL SELECT statement conform to the PDO
 */
function delta_get_layout_data($layout) {
	$sql = "
      SELECT dt.name, dt.system_name, dt.tid, dt.data, dt.theme
      FROM {delta_theme_settings} dt
      WHERE dt.system_name = :layout
      LIMIT 1";
  $result = db_query($sql, array(':layout' => $layout), array('fetch' => PDO::FETCH_ASSOC));
  foreach ($result as $item) {
      $data = $item;
    }
  return $data;
}

/**
 * Form callback for creating and editing theme settings templates
 * 
 * @param $form
 * @param $form_state
 */

function delta_layout_edit($form, &$form_state, $layout = FALSE) {
  if($layout) {
  	//drupal_set_message('we are editing <strong>' . $layout . '</strong>.');
  	$data = delta_get_layout_data($layout);
  }
  $form = array();
  
  
  if(isset($form_state['build_info']['args'][0])) {
    drupal_set_title(t('Edit Delta Layout'));
    $edit = $form_state['build_info']['args'][0];
  }
  else {
    drupal_set_title(t('Add Delta Layout'));
  }
  
  $form['delta'] = array(
    '#type' => 'vertical_tabs',
    '#weight' => -100,
    '#prefix' => '',
    '#suffix' => '<div id="theme_settings_replace"></div>',
  );
  $form['delta']['settings'] = array(
    '#type' => 'fieldset',
    '#title' => t('Layout Settings'),
    '#collapsible' => TRUE,
    '#collapsed' => FALSE,
  );
  $form['delta']['settings']['tid'] = array(
    '#type' => 'value',
    '#value' => isset($data['tid']) ? $data['tid'] : NULL,
  );
  $form['delta']['settings']['title'] = array(
    '#type' => 'textfield',
    '#title' => t('Name of Layout'),
    '#required' => TRUE,
    '#size' => 32,
    '#default_value' => isset($data['name']) ? $data['name'] : NULL,
  );
  $form['delta']['settings']['name'] = array(
    '#type' => 'machine_name',
    '#title' => t('System name'),
    '#default_value' => isset($data['system_name']) ? $data['system_name'] : NULL,
    '#maxlength' => 64,
    '#description' => t('A unique name to construct the URL for the template. It must only contain lowercase letters, numbers and hyphens.'),
    '#machine_name' => array(
      'exists' => 'delta_layout_name_exists',
      'source' => array('delta', 'settings', 'title'),
      'label' => t('System Name'),
      'replace_pattern' => '[^a-z0-9-]+',
      'replace' => '-',
    ),
    '#disabled' => isset($data['tid']) ? TRUE : FALSE,
  );
  $allowed_themes = variable_get('delta_themes', array());
  $themes = delta_get_themes_form_array($allowed_themes);
  $form['delta']['settings']['theme'] = array(
	  '#type' => 'select',
	  '#title' => t('Theme'),
	  '#default_value' => isset($data['theme']) ? $data['theme'] : NULL,
	  '#options' => array('none' => 'Select Theme') + $themes,
    /*
     '#ajax' => array(
      'callback' => 'delta_load_theme_settings_callback',
      'wrapper' => 'theme_settings_replace',
      'method' => 'replace',
      'effect' => 'fade',
    ), */
    // disable changing the theme this is associated with after creation
    // you would need to create a new one to do something like that. :P
    '#disabled' => isset($data['tid']) ? TRUE : FALSE,
	);
	/*
	$form['delta']['overrides'] = array(
    '#type' => 'fieldset',
    '#title' => t('Theme Settings Overrides'),
    '#collapsible' => TRUE,
    '#collapsed' => FALSE,
  );
  if (isset($data['theme'])) {
  	$form['delta']['overrides']['custom'] = delta_load_theme_settings_callback($form, $form_state, $data['theme']);
  }
  */
  $form['actions'] = array('#type' => 'actions');
  $form['actions']['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Save'),
  );
  // Only custom theme setting templates may be deleted.
  /*
  $form['actions']['delete'] = array(
    '#type' => 'submit',
    '#value' => t('Delete'),
    //'#access' => isset($edit) ? TRUE : FALSE,
  );
  */
  //krumo($form);
  return $form;
}

/**
 * delta_template_configure function.
 * 
 * @access public
 * @param mixed $form
 * @param mixed &$form_state
 * @param mixed $layout. (default: FALSE)
 * @return void
 */
function delta_layout_configure($form, &$form_state, $layout = FALSE) {
  if($layout) {
  	$data = delta_get_layout_data($layout);
  	drupal_set_title(t('Editing '. $data['name']));
  }
  if (isset($data['theme'])) {

  	$form = delta_load_theme_settings_callback($data['theme'], $layout);
    
    $form['#submit'] = array('delta_ui_layout_configure_submit');
    $form['#validate'] = array('delta_ui_layout_configure_validate');
    
    $form['actions'] = array('#type' => 'actions');
	  $form['actions']['submit'] = array(
	    '#type' => 'submit',
	    '#value' => t('Save Layout'),
	  );
    return $form;
  }
  return 'Error Loading Theme Settings form...';
}


/**
 * delta_template_configure function.
 * 
 * @access public
 * @param mixed $form
 * @param mixed &$form_state
 * @param mixed $layout. (default: FALSE)
 * @return void
 */
function delta_layout_delete($form, &$form_state, $layout = FALSE) {
	if($layout) {
  	$data = delta_get_layout_data($layout);
  	drupal_set_title(t('Deleting '. $data['name']));
  }
  if (isset($data['theme'])) {

  	$form = array();
    	$form['tid'] = array(
      '#type' => 'value',
      '#value' => isset($data['tid']) ? $data['tid'] : NULL,
    );
    $form['template_delete'] = array(
      '#markup' => '<p><strong>WARNING:</strong> Deleting this theme settings layout will immediately make it unavailable.</p>',
    );
    $form['#submit'] = array('delta_ui_layout_delete_submit');
    
    $form['actions'] = array('#type' => 'actions');
	  $form['actions']['submit'] = array(
	    '#type' => 'submit',
	    '#value' => t('Delete Layout'),
	  );
    return $form;
  }
  return 'Error Loading Theme Settings form...';
}


function delta_load_theme_settings_callback($theme, $layout) {
	if ($theme != 'none') {
	  $key = $theme;
	  $var = 'theme_delta_' . $layout . '_settings';
	  $themes = system_rebuild_theme_data();
    $features = $themes[$key]->info['features'];
	  $form = array();
	  $form_state['build_info']['args'][0] = $key;
    // Call engine-specific settings.
    $function = $themes[$key]->prefix . '_engine_settings';
    if (function_exists($function)) {
      $form['engine_specific'] = array(
        '#type' => 'fieldset',
        '#title' => t('Theme-engine-specific settings'),
        '#description' => t('These settings only exist for the themes based on the %engine theme engine.', array('%engine' => $themes[$key]->prefix)),
      );
      $function($form, $form_state);
    }
    // create the variable we will store the data in.
    $form['var'] = $form['delta_template'] = array('#type' => 'hidden', '#value' => $var);
    // Create a list which includes the current theme and all its base themes.
    if (isset($themes[$key]->base_themes)) {
      $theme_keys = array_keys($themes[$key]->base_themes);
      $theme_keys[] = $key;
    }
    else {
      $theme_keys = array($key);
    }

    // Save the name of the current theme (if any), so that we can temporarily
    // override the current theme and allow theme_get_setting() to work
    // without having to pass the theme name to it.
    $default_theme = !empty($GLOBALS['theme_key']) ? $GLOBALS['theme_key'] : NULL;
    $GLOBALS['theme_key'] = $key;

    // Process the theme and all its base themes.
    foreach ($theme_keys as $theme) {
      // Include the theme-settings.php file.
      $filename = DRUPAL_ROOT . '/' . str_replace("/$theme.info", '', $themes[$theme]->filename) . '/theme-settings.php';
      if (file_exists($filename)) {
        require_once $filename;
      }

      // Call theme-specific settings.
      $function = $theme . '_form_system_theme_settings_alter';
      if (function_exists($function)) {
        $function($form, $form_state, $theme);
      }
    }

    // Restore the original current theme.
    if (isset($default_theme)) {
      $GLOBALS['theme_key'] = $default_theme;
    }
    else {
      unset($GLOBALS['theme_key']);
    }	
	  //krumo($form);
	  //$variable = variable_get('theme_delta_omega_starterkit_omega-starterkit-home-layout_settings');
	  //krumo($variable);
    
		//module_load_include('inc', 'system', 'system.admin');
		//$form = drupal_get_form('system_theme_settings', $theme);
	  return $form;
	}
  else {
  	return t('<div id="theme_settings_replace"><p>Unable to load theme settings form group... WTF...</p></div>');
  }
}


/**
 * Validation handler for delta_template_edit()
 * 
 * This validation handler checks to ensure a theme has been selected.
 * 
 * @param $form
 * @param $form_state
 */
function delta_layout_edit_validate($form, &$form_state) {
	$values = $form_state['values'];
	//dsm($values);
	if ($values['theme'] == 'none') {
		form_set_error('theme', t('You must select a theme in order to create a theme settings template. The settings stored are based on the default theme settings of the selected theme.'));
	}
}

/**
 * Submit handler for delta_template_edit()
 * 
 * This submit handler will insert or update the database with the appropriate
 * settings provided by the form submission.
 * 
 * @param $form
 * @param $form_state
 */
function delta_layout_edit_submit($form, &$form_state) {
  $values = $form_state['values'];
  $item = array(
    'tid' => isset($values['tid']) ? $values['tid'] : FALSE,
    'name' => check_plain($values['title']),
    'system_name' => check_plain($values['name']),
    'theme' => check_plain($values['theme']),
    'data' => array(),
  );
  // save record
  if ($item['tid']) {
  	drupal_write_record('delta_theme_settings', $item, 'tid');
  }
  else {
    drupal_write_record('delta_theme_settings', $item);	
  }
  
  drupal_set_message('The custom theme settings layout <strong>' . $item['name'] . '</strong> has been saved for <em>'. $item['theme'] .'</em>...');
  drupal_set_message('You may now configure the <strong>' . $item['name'] . '</strong> theme settings layout.');
  drupal_goto('admin/appearance/delta/layouts/configure/'. $item['system_name']);
}

/**
 * Helper function for machine readable names in {delta_theme_settings}
 * @param $t
 */
function delta_layout_name_exists($t) {
  $exists = db_query_range('SELECT 1 FROM {delta_theme_settings} WHERE system_name = :name', 0, 1, array(':name' => $t))->fetchField();
  return $exists;
}

/**
 * Menu callback; displays the delta listing page.
 */
function delta_overrides_list($form, &$form_state) {
  drupal_set_title(t('Delta Overrides'));
  //krumo($form_state);
  $theme = isset($form_state['build_info']['args'][0]) ? $form_state['build_info']['args'][0] : FALSE;
  $themes = list_themes();
  //krumo($themes);
  if($theme) {
    drupal_set_title(t('Delta Overrides for ' . $themes[$theme]->info['name']));
  }
  
  $form['actions'] = array('#type' => 'actions');
  $form['actions']['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Save'),
  );
  return $form;
}