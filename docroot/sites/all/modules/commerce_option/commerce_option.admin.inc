<?php

/**
 * Commerce Option create/edit form
 */
function commerce_option_form($form, &$form_state, $option, $op = 'edit') {
  // Disable adding new options for now, until we figure out how to properly
  // attach it to an order.
  if ($op == 'add') {
    return array(
      'info' => array('#markup' => t('Adding an option through the UI is currently not supported.')),
    );
  }

  $form = array(
    '#parents' => array(),
    'data' => array('#tree' => TRUE),
  );

  if ($op != 'add') {
    // Create extra info that is not editable
    $line_item = commerce_line_item_load($option->line_item_id);
    $liw = entity_metadata_wrapper('commerce_line_item',$line_item);
    $product_title = $liw->commerce_product->title->value();
    $order_id = $liw->order_id->value();
    $order_id = l($order_id, 'admin/commerce/orders/' . $order_id . '/view');

    $form['info'] = array(
      '#type' => 'item',
      '#title' => t('Order info'),
      '#markup' => t('This option is attached to product %product in order !order', array('%product' => $product_title, '!order' => $order_id)),
    );
  }

  $option_sets_raw = commerce_option_set_load_multiple(FALSE);
  $option_sets = array();
  foreach ($option_sets_raw as $set) {
    $option_sets[$set->set_id] = $set->name;
  }

  $form['type'] = array(
    '#type' => 'select',
    '#title' => t('Type'),
    '#options' => $option_sets,
    '#disabled' => $op == 'add' ? FALSE : TRUE,
    '#ajax' => array(
      'callback' => 'commerce_option_get_option_set_fields',
    )
  );

  // Set the fields attached to the option bundle.
  $form['fields'] = commerce_option_build_option_set_fields($form, $form_state, $op, $option);

  $form['actions'] = array(
    '#type' => 'actions',
    'submit' => array(
      '#type' => 'submit',
      '#value' => t('Save options'),
      '#weight' => 40,
    ),
  );

  if ($op != 'add') {
    $form['actions']['delete'] = array(
      '#type' => 'submit',
      '#value' => t('Delete options'),
      '#weight' => 45,
      '#limit_validation_errors' => array(),
      '#submit' => array('commerce_option_form_submit_delete')
    );
  }

  return $form;
}

/**
 * Helper function to attach the fields defined in an option set to the option.
 */
function commerce_option_build_option_set_fields(&$form, &$form_state, $op, $option) {
  $subform = array(
    '#prefix' => '<div id="commerce-option-fields">',
    '#suffix' => '</div>',
  );

  // Get fields attached to this option.
  $fields = field_info_instances('commerce_option', $option->set_id);

  foreach ($fields as $key => $field) {
    $info = field_info_field($key);
    $langcode = field_language('commerce_option', $option, $key);

    $items = array();

    // Provide default value in case we're editing.
    if ($op != 'add' && !empty($option->{$key}[$langcode])) {
      $items = $option->{$key}[$langcode];
    }

    $subform[$key] = field_default_form('commerce_option', $option, $info, $field, $langcode, $items, $form, $form_state);
  }

  return $subform;
}

/**
 * Ajax callback that replaces the fields-part of the form with the fields
 * defined in the Commerce Option Set (type).
 */
function commerce_option_get_option_set_fields(&$form, &$form_state) {
  $type = $form_state['values']['type'];
  $option = commerce_option_new(array('set_id' => $type));

  $subform = commerce_option_build_option_set_fields($form, $form_state, 'add', $option);
  foreach (element_children($subform) as $key) {
    $subform[$key] = form_builder('commerce_option_form', $subform[$key], $form_state);
  }

  $commands[] = ajax_command_replace('#commerce-option-fields', drupal_render($subform));

  return array(
    '#type' => 'ajax',
    '#commands' => $commands,
  );
}

/**
 * Commerce Option form submit handler
 */
function commerce_option_form_submit($form, &$form_state) {
  $option = $form_state['commerce_option'];

  field_attach_submit('commerce_option', $option, $form, $form_state);

  // If we're saving a new option, add more required values.
  if ($option->is_new) {
    // @todo
    // Get product id & line_item_id. Maybe the add option should only be
    // available from an existing order with line items?
  }

  commerce_option_save($option);
}

/**
 * Commerce Option form deletion submit callback
 */
function commerce_option_form_submit_delete($form, &$form_state) {
  $form_state['redirect'] = 'admin/commerce/products/options/' . $form_state['commerce_option']->option_id . '/delete';
}

/**
 * Commerce Option form deletion confirmation.
 * Form callback for page defined in hook_menu().
 */
function commerce_option_delete_confirm($form, &$form_state, $option) {
  $form_state['commerce_option'] = $option;

  return confirm_form($form,
    t('Are you sure you want to delete option %id?', array('%id' => $option->option_id)),
    'admin/commerce/config/options',
    '<p>' . t('This action cannot be undone.') . '</p>',
    t('Delete'),
    t('Cancel'),
    'confirm'
  );
}

/**
 * Submit callback for commerce_option_delete_confirm form().
 */
function commerce_option_delete_confirm_submit($form, &$form_state) {
  $option = $form_state['commerce_option'];

  entity_delete('commerce_option',  $option->option_id);

  drupal_set_message(t('The option with id %id has been deleted.', array('%id' => $option->option_id)));

  $form_state['redirect'] = 'admin/commerce/products/options';
}

/**
 * Commerce Option Set edit/create form.
 */
function commerce_option_set_form($form, &$form_state, $option_set, $op = 'edit') {
  if ($op == 'clone') {
    $option_set->name .= ' (cloned)';
    $option_set->set_id = '';
  }

  $form['data']['#tree'] = TRUE;

  // Human-readable name.
  $form['name'] = array(
    '#title' => t('Name'),
    '#type' => 'textfield',
    '#default_value' => isset($option_set->name) ? $option_set->name : '',
    '#description' => t('The human-readable name of this option set.'),
    '#required' => TRUE,
    '#size' => 30,
  );

  // Machine-readable name.
  $form['set_id'] = array(
    '#type' => 'machine_name',
    '#default_value' => isset($option_set->set_id) ? $option_set->set_id : '',
    '#maxlength' => 32,
    '#disabled' => !empty($option_set->set_id) ? TRUE : FALSE,
    '#machine_name' => array(
      'exists' => 'commerce_option_set_load_by_name',
      'source' => array('name'),
    ),
    '#description' => t('A unique machine-readable name for this option set. It must only contain lowercase letters, numbers, and underscores.'),
  );

  // Description.
  $form['description'] = array(
    '#type' => 'textarea',
    '#default_value' => isset($option_set->description) ? $option_set->description : '',
    '#title' => t('Description'),
    '#description' => t('This description is shown to the admin user to indicate if this option set is useful for the current purpose.'),
  );

  // Actions.
  $form['actions'] = array(
    '#type' => 'actions',
    'submit' => array(
      '#type' => 'submit',
      '#value' => t('Save option set'),
      '#weight' => 40,
    ),
  );

  if ($op != 'add') {
    $form['actions']['delete'] = array(
      '#type' => 'submit',
      '#value' => t('Delete option set'),
      '#weight' => 45,
      '#limit_validation_errors' => array(),
      '#submit' => array('commerce_option_set_form_submit_delete')
    );
  }

  return $form;
}

/**
 * Commerce Option Set form submit handler.
 */
function commerce_option_set_form_submit($form, &$form_state) {
  $option_set = entity_ui_form_submit_build_entity($form, $form_state);

  commerce_option_save($option_set);

  drupal_set_message(t('Option set saved.'));

  // Redirect based on the button clicked.
  if ($form_state['clicked_button']['#parents'][0] == 'save_continue') {
    $form_state['redirect'] = 'admin/commerce/products/option-sets/' . strtr($option_set['set_id'], '_', '-') . '/fields';
  }
  else {
    $form_state['redirect'] = 'admin/commerce/products/option-sets';
  }
}

/**
 * Commerce Option Set form delete submit handler.
 *
 * Redirect to page that we defined in hook_menu().
 */
function commerce_option_set_form_submit_delete($form, &$form_state) {
  $form_state['redirect'] = 'admin/commerce/products/option-sets/' . $form_state['commerce_option_set']->set_id . '/delete';
}

/**
 * Form callback: confirmation form for deleting a option set.
 * Called as page argument in the page we redirect to in
 * commerce_option_set_form_submit_delete().
 */
function commerce_option_set_delete_confirm($form, &$form_state, $option_set) {
  $form_state['commerce_option_set'] = $option_set;

  return confirm_form($form,
    t('Are you sure you want to delete the %name option set?', array('%name' => $option_set->name)),
    'admin/commerce/config/option-sets',
    '<p>' . t('This action cannot be undone.') . '</p>',
    t('Delete'),
    t('Cancel'),
    'confirm'
  );
}

/**
 * Submit callback for commerce_option_set_delete_form().
 *
 * @todo: Don't allow deletion of option sets that have options defined?
 */
function commerce_option_set_delete_confirm_submit($form, &$form_state) {
  $option_set = $form_state['commerce_option_set'];

  entity_delete('commerce_option_set',  $option_set->set_id);

  // Notify the field API that this bundle has been destroyed.
  field_attach_delete_bundle('commerce_option', $option_set->set_id);

  drupal_set_message(t('The option set %name has been deleted.', array('%name' => $option_set->name)));
  watchdog('commerce_option', 'Deleted option set %name.', array('%name' => $option_set->name), WATCHDOG_NOTICE);

  $form_state['redirect'] = 'admin/commerce/products/option-sets';
}
