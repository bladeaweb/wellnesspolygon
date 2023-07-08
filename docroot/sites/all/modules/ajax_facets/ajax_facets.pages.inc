<?php

/**
 * @file
 * User page callbacks for the ajax_facets module.
 */

/**
 * Callback to update facets content by AJAX.
 */
function ajax_facets_refresh_facets_content() {
  if (!empty($_POST['ajax_facets'])) {
    // Dirty hack to avoid error 414 in $_GET query.
    // @see class FacetapiUrlProcessorStandard->fetchParams();
    $_GET = array_merge($_GET, $_POST['ajax_facets']);

    // Reset $_POST, because html ids can break ids of facets for update.
    unset($_POST['ajax_html_ids']);
    unset($_POST['ajax_facets']);

    $facet_query = !empty($_GET['f']) ? $_GET['f'] : '';
    $received_views = $_GET['views'];
    $processed_views = array();
    $views_settings = array(
      'views' => array(
        'ajax_path' => url('views/ajax', array('query' => array('f' => $facet_query))),
        'ajaxViews' => array(),
      ),
    );

    // Attempt to process all the received views.
    foreach ($received_views as $key => $received_view) {
      // Fix 'q' for paging.
      $view_path = isset($received_view['view_path']) ? rawurldecode($received_view['view_path']) : NULL;
      if (!empty($received_view['view_path'])) {
        $_GET['q'] = $received_view['view_path'];
      }

      // Execute view query to fill facets.
      $view = views_get_view($received_view['view_name']);
      // Check, do we need search results or not.
      if (isset($_GET['searchResultsNeeded'])) {
        if ($_GET['searchResultsNeeded'] == 'false') {
          // If we don't need search results, then we have to set limit as '0'.
          // It have to be string but not integer due the logic in
          // search_api_views.
          $view->set_items_per_page('0');
        }
        unset($_GET['searchResultsNeeded']);
      }
      // Reuse dom id.
      $view->dom_id = isset($received_view['view_dom_id']) ? preg_replace('/[^a-zA-Z0-9_-]+/', '-', $received_view['view_dom_id']) : NULL;

      $view_args = !empty($received_view['view_args']) ? explode('/', $received_view['view_args']) : array();

      // Prepare data to update certain view.
      $name = $received_view['view_name'];
      $dis_id = $received_view['view_display_id'];
      $access = FALSE;
      // Check that access to this display is restricted.
      if (!empty($view->display[$dis_id]->display_options['access'])) {
        // And that restriction is not "none".
        if ($view->display[$dis_id]->display_options['access']['type'] !== 'none') {
          // Check that we have an access to this display.
          if ($view->access($dis_id)) {
            $processed_views[$key] = [
              'view_name' => $name,
              'view_display_id' => $dis_id,
              'view_dom_id' => $view->dom_id,
              'content' => $view->preview($dis_id, $view_args),
            ];
            $access = TRUE;
          }
        }
      }
      // We are processing views on demand by name.
      // To do not let abuse this functionality.
      // We can process only that views which have access settings.
      if (!$access) {
        $commands = [
          ajax_command_alert(
            t('Search results cannot be filtered, please contact site administrator.')
          ),
        ];
        watchdog('access denied', 'Search results cannot be filtered for view - "%view_name", display - "%display_id"', [
          '%view_name' => $name,
          '%display_id' => $dis_id,
        ], WATCHDOG_WARNING);
        ajax_facets_send_commands($commands);
      }

      // @see template_preprocess_views_view();
      // Update settings when view will be reloaded. Specifically $view->dom_id.
      if ($view->use_ajax) {
        $views_settings['views']['ajaxViews']['views_dom_id:' . $view->dom_id] = array(
          'view_name' => $view->name,
          'view_display_id' => $view->current_display,
          'view_args' => check_plain(implode('/', $view->args)),
          'view_path' => $view_path,
          // Pass through URL to ensure we get e.g. language prefixes.
          'view_base_path' => $view->get_path(),
          'view_dom_id' => $view->dom_id,
          // To fit multiple views on a page, the programmer may have
          // overridden the display's pager_element.
          'pager_element' => isset($view->query->pager) ? $view->query->pager->get_pager_id() : 0,
        );
      }

      // Render exposed form separately if needed.
      // @see https://www.drupal.org/node/2034029#comment-10211699
      if (!empty($view->display[$view->current_display]->display_options['exposed_block'])) {
        $exposed_form = $view->display_handler->get_plugin('exposed_form');
        $processed_views[$key]['exposed_form'] = $exposed_form->render_exposed_form(TRUE);
      }
    }

    $searcher = 'search_api@' . $_GET['index_id'];
    $blocks = ajax_facets_process_facet_blocks($searcher);

    $result = array(
      'resetUrls' => $blocks['reset_urls'],
      'newContent' => $blocks['facet_blocks'],
      'hideBlocks' => $blocks['hide_blocks'],
      'activeItems' => $blocks['active_items'],
      'views' => $processed_views,
      'facets' => $facet_query,
      'settings' => !empty($views_settings['views']['ajaxViews']) ? $views_settings : array(),
    );

    $commands = array();
    $commands[] = array(
      'command' => 'ajax_facets_update_content',
      'data' => $result,
    );

    ajax_facets_send_commands($commands, $view);
  }
}

/**
 * Sends commands for the request.
 */
function ajax_facets_send_commands($commands, $view = NULL) {
  // Allow other modules to add, change or delete commands.
  drupal_alter('ajax_facets_data', $commands, $view);
  drupal_add_http_header('Content-Type', 'application/json; charset=utf-8');
  print ajax_render($commands);
  drupal_exit();
}
