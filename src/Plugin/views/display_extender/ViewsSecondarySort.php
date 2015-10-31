<?php

/**
 * @file
 * Contains \Drupal\views_secondary_sort\Plugin\views\display_extender\ViewsSecondarySort.
 */

namespace Drupal\views_secondary_sort\Plugin\views\display_extender;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\display_extender\DisplayExtenderPluginBase;

/**
 * Allows adding secondary sorts to Views tables.
 *
 * @ViewsDisplayExtender(
 *   id = "views_secondary_sort",
 *   title = @Translation("Secondary sorts for views tables")
 * )
 */
class ViewsSecondarySort extends DisplayExtenderPluginBase {
  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['secondary_sort'] = ['default' => []];

    return $options;
  }

  /**
   * Overrides Drupal\views\Plugin\views\display\DisplayPluginBase::optionsSummary().
   */
  public function optionsSummary(&$categories, &$options) {
    parent::optionsSummary($categories, $options);

    $sorts = $this->displayHandler->getOption('secondary_sort');
    $valid = $this->validColumns();
    $value = array();
    if ($sorts) {
      foreach ($sorts as $sort) {
        $field = $sort['field'];
        if (isset($valid[$field])) {
          $value[] = $valid[$field];
        }
      }
      $value = implode(', ', $value);
    }
    if (!$value) {
      $value = t('No');
    }

    $options['secondary_sort'] = array(
      'category' => 'title',
      'title' => $this->t('Secondary sort'),
      'value' => $value,
      'desc' => $this->t('Add secondary sorts to the display'),
    );
  }

  /**
   * Check if we're a table.
   */
  protected function isTable() {
    $style_plugin = $this->displayHandler->getPlugin('style');
    if (!method_exists($style_plugin, 'sanitizeColumns')) {
      return NULL;
    }
    return $style_plugin;
  }

  /**
   * Check which columns are valid.
   */
  protected function validColumns() {
    $style = $this->isTable();
    if (!$style) {
      return array();
    }

    // Get some columns, filtered by actually sortable ones.
    $groups = $style->options['columns'];
    $columns = $style->sanitizeColumns($groups);
    $columns = array_intersect_key($columns, array_flip($groups));

    $handlers = $this->displayHandler->getHandlers('field');
    $labels = $this->displayHandler->getFieldLabels();
    foreach (array_keys($columns) as $key) {
      if (!isset($handlers[$key]) || !$handlers[$key]->clickSortable()) {
        unset($columns[$key]);
      }
      $columns[$key] = $labels[$key];
    }
    return $columns;
  }

  /**
   * Overrides Drupal\views\Plugin\views\display_extender\DisplayExtenderPluginBase::buildOptionsForm().
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    if ($form_state->get('section') != 'secondary_sort') {
      return;
    }

    // Setup basic form.
    $form['#title'] .= $this->t('Secondary sorts');
    $form['secondary_sort'] = [
      '#title' => $this->t('Secondary sorts'),
    ];
    $table =& $form['secondary_sort'];

    if (!$this->isTable()) {
      $table['#markup'] = $this->t("Secondary sorts only apply to table styles.");
      return;
    }

    $valid = $this->validColumns();

    // Make sure we have sortable rows.
    if (empty($valid)) {
      $table['#markup'] = t("No sortable columns.");
      return;
    }

    // Get our options.
    $sorts = $this->displayHandler->getOption('secondary_sort');
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
        'title' => $this->t("Do not sort"),
        'message' => $this->t('No fields unsorted.'),
        'rows' => array_diff(array_keys($valid), $order),
      ),
    );

    // Build a table.
    $table['#tree'] = TRUE;
    $table['#theme'] = 'views_secondary_sort';
    $table['#regions'] = $regions;
    $table['#header']= array(
      $this->t('Column'),
      $this->t('Weight'),
      $this->t('Order')
    );

    // Add rows.
    $weight = 0;
    foreach ($regions as $region_name => $region) {
      foreach ($region['rows'] as $field) {
        $secondary_sort = ($region_name == 'sort');
        $setting = $settings[$field];

        $row = array();
        $row['#secondary_sort'] = $secondary_sort;
        $row['name_wrapper'] = array(
          '#type' => 'container',
          '#tree' => FALSE,
          '#attributes' => array(
            'class' => array('views-secondary-sort-wrapper'),
          ),
          'name' => array(
            '#plain_text' => $valid[$field],
          ),
          'sort' => array(
            '#type' => 'hidden',
            '#default_value' => (int) $secondary_sort,
            '#parents' => array('secondary_sort', $field, 'sort'),
          ),
        );
        $row['weight'] = array(
          '#type' => 'weight',
          '#delta' => 20,
          '#default_value' => $weight++,
        );
        $row['order'] = array(
          '#type' => 'select',
          '#options' => array(
            'asc' => $this->t('Ascending'),
            'desc' => $this->t('Descending'),
          ),
          '#default_value' => $setting ? $setting['order'] : 'asc',
        );
        $table[$field] = $row;
      }
    }

    $table['#attached']['library'][] = 'views_secondary_sort/views_secondary_sort';
    $table['#tabledrag'][] = array(
      'table_id' => 'views-secondary-sort',
      'action' => 'order',
      'relationship' => 'sibling',
      'group' => 'views-secondary-sort-weight',
    );
  }

  /**
   * Overrides Drupal\views\Plugin\views\display\DisplayExtenderPluginBase::submitOptionsForm().
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state) {
    if ($form_state->get('section') != 'secondary_sort') {
      return;
    }

    $values = $form_state->getValue('secondary_sort');
    $options = [];
    $weights = [];
    foreach ($values as $key => $value) {
      if (!empty($value['sort'])) {
        $weights[] = $value['weight'];
        $options[] = [
          'field' => $key,
          'order' => $value['order'],
        ];
      }
    }

    array_multisort($weights, $options);
    $this->displayHandler->setOption('secondary_sort', $options);
  }

  /**
   * Overrides Drupal\views\Plugin\views\display\DisplayExtenderPluginBase::defaultableSections().
   */
  public function defaultableSections(&$sections, $section = NULL) {
    $sections['secondary_sort'] = array('secondary_sort');
  }

  /**
   * Overrides Drupal\views\Plugin\views\display\DisplayExtenderPluginBase::query().
   */
  public function query() {
    $sorts = $this->displayHandler->getOption('secondary_sort');
    $valid = $this->validColumns();
    if (!$sorts) {
      return;
    }

    $style_plugin = $this->view->style_plugin;

    $handlers = $this->displayHandler->getHandlers('field');
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
      $handler->clickSort($sort['order']);
    }
  }

}
