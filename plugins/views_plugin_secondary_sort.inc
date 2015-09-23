<?php

/**
 * @file
 * Contains views_plugin_secondary_sort.
 */

/**
 * Add secondary sort orders to views.
 */
// @codingStandardsIgnoreLine views use different standards
class views_plugin_secondary_sort extends views_plugin_display_extender {

  /**
   * Initialize this display extender.
   */
  // @codingStandardsIgnoreLine views use different standards
  function init(&$view, &$display) {
    parent::init($view, $display);

    // Can't add JS in AJAX callback, must do it here.
    // High weight, so we get attached AFTER tabledrag.
    drupal_add_js(drupal_get_path('module', 'views_secondary_sort')
      . '/views_secondary_sort.js',
      array('weight' => 100));
  }

  /**
   * Set default options for this plugin.
   */
  // @codingStandardsIgnoreLine views use different standards
  function options_definition_alter(&$options) {
    $options['secondary_sort'] = array('default' => array());
  }

  /**
   * Provide a form to edit options for this plugin.
   */
  // @codingStandardsIgnoreLine views use different standards
  function options_form(&$form, &$form_state) {
    // Check if it's our form.
    if ($form_state['section'] != 'secondary_sort') {
      return;
    }

    // Setup basic form.
    $form['#title'] .= t('Secondary sorts');
    $form['secondary_sort'] = array(
      '#title' => t('Secondary sorts'),
    );
    $table =& $form['secondary_sort'];

    // Make sure we're a table.
    if (!$this->is_table()) {
      $table['#markup'] = t("Secondary sorts only apply to table styles.");
      return;
    }

    $valid = $this->valid_columns();

    // Make sure we have sortable rows.
    if (empty($valid)) {
      $table['#markup'] = t("No sortable columns.");
      return;
    }

    // Get our options.
    $sorts = $this->display->get_option('secondary_sort');
    $order = array();
    $settings = array();
    foreach ($sorts as $sort) {
      if (isset($valid[$sort['field']])) {
        $order[] = $sort['field'];
        $settings[$sort['field']] = $sort;
      }
    }

    // Figure out what is in which region.
    $regions = array(
      'sort' => array(
        'message' => t('No secondary sort fields selected.'),
        'rows' => $order,
      ),
      'no_sort' => array(
        'title' => t("Do not sort"),
        'message' => t('No fields unsorted.'),
        'rows' => array_diff(array_keys($valid), $order),
      ),
    );

    // Build a table.
    $table['#tree'] = TRUE;
    $table['#theme'] = 'views_secondary_sort';
    $table['#regions'] = $regions;

    // Add rows.
    $weight = 0;
    foreach ($regions as $region_name => $region) {
      foreach ($region['rows'] as $field) {
        $secondary_sort = ($region_name == 'sort');
        $setting = $settings[$field];

        $row = array();
        $row['#secondary_sort'] = $secondary_sort;
        $row['name'] = array(
          array('#markup' => check_plain($valid[$field])),
        );
        $row['weight'] = array(
          '#type' => 'weight',
          '#title' => 'Weight',
          '#title_display' => 'invisible',
          '#delta' => 20,
          '#default_value' => $weight++,
        );
        $row['sort'] = array(
          '#type' => 'hidden',
          '#default_value' => $secondary_sort,
        );
        $row['order'] = array(
          '#type' => 'select',
          '#options' => array(
            'asc' => t('Ascending'),
            'desc' => t('Descending'),
          ),
          '#default_value' => $setting ? $setting['order'] : 'asc',
        );
        $table[$field] = $row;
      }
    }

    $form['#attached']['css'][] = drupal_get_path('module', 'views_secondary_sort')
      . '/views_secondary_sort.css';
    $form['#attached']['drupal_add_tabledrag'][] = array(
      'views-secondary-sort',
      'order',
      'sibling',
      'views-secondary-sort-weight',
    );
  }

  /**
   * Handle form submission.
   */
  // @codingStandardsIgnoreLine views use different standards
  function options_submit(&$form, &$form_state) {
    if ($form_state['section'] != 'secondary_sort') {
      return;
    }

    $values = $form_state['values']['secondary_sort'];
    if (!$values) {
      return;
    }

    $sorts = array();
    foreach ($values as $field => $value) {
      if (isset($value['sort']) && $value['sort']) {
        $sorts[$value['weight']] = array(
          'field' => $field,
          'order' => $value['order'],
        );
      }
    }
    ksort($sorts, SORT_NUMERIC);
    $sorts = array_values($sorts);
    $this->display->set_option('secondary_sort', $sorts);
  }

  /**
   * Check if we're a table.
   */
  // @codingStandardsIgnoreLine views use different standards
  function is_table() {
    $style_plugin = $this->display->get_plugin('style');
    if (!method_exists($style_plugin, 'sanitize_columns')) {
      return NULL;
    }
    return $style_plugin;
  }

  /**
   * Check which columns are valid.
   */
  // @codingStandardsIgnoreLine views use different standards
  function valid_columns() {
    $style = $this->is_table();
    if (!$style) {
      return array();
    }

    // Get some columns, filtered by actually sortable ones.
    $groups = $style->options['columns'];
    $columns = $style->sanitize_columns($groups);
    $columns = array_intersect_key($columns, array_flip($groups));

    $handlers = $this->display->get_handlers('field');
    $labels = $this->display->get_field_labels();
    foreach (array_keys($columns) as $key) {
      if (!isset($handlers[$key]) || !$handlers[$key]->click_sortable()) {
        unset($columns[$key]);
      }
      $columns[$key] = $labels[$key];
    }
    return $columns;
  }

  /**
   * Add extra click sorts.
   */
  // @codingStandardsIgnoreLine views use different standards
  function query() {
    $sorts = $this->display->get_option('secondary_sort');
    $valid = $this->valid_columns();
    if (!$sorts) {
      return;
    }

    $style_plugin = $this->view->style_plugin;

    $handlers = $this->display->get_handlers('field');
    foreach ($sorts as $sort) {
      if (!isset($sort['field'])) {
        continue;
      }

      $field = $sort['field'];
      if (!isset($valid[$field])) {
        continue;
      }

      // Don't re-sort if the style plugin has it.
      if (isset($style_plugin->active) && $style_plugin->active == $field) {
        continue;
      }

      if (!isset($handlers[$field])) {
        continue;
      }
      $handler = $handlers[$field];
      $handler->click_sort($sort['order']);
    }
  }

  /**
   * Provide the default summary for options in the views UI.
   */
  // @codingStandardsIgnoreLine views use different standards
  function options_summary(&$categories, &$options) {
    $sorts = $this->display->get_option('secondary_sort');
    $valid = $this->valid_columns();

    $value = array();
    if ($sorts) {
      foreach ($sorts as $sort) {
        if (isset($valid[$sort['field']])) {
          $value[] = $valid[$sort['field']];
        }
      }
      $value = implode(', ', $value);
    }
    if (!$value) {
      $value = t('No');
    }

    $options['secondary_sort'] = array(
      'category' => 'other',
      'title' => t('Secondary sort'),
      'value' => $value,
      'desc' => t('Add secondary sorts to the display'),
    );
  }

}
