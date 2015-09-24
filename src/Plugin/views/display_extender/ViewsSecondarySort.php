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
   * Stores some state booleans to be sure a certain method got called.
   *
   * @var array
   */
  public $testState;

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['secondary_sort'] = ['default' => $this->t('Empty')];

    return $options;
  }

  /**
   * Overrides Drupal\views\Plugin\views\display\DisplayPluginBase::optionsSummary().
   */
  public function optionsSummary(&$categories, &$options) {
    parent::optionsSummary($categories, $options);

    $sorts = $this->displayHandler->getOption('secondary_sort');
    $value = array();
    if ($sorts) {
      foreach ($sorts as $sort) {
        $value[] = $sort['field'];
      }
      $value = implode(', ', $value);
    }
    if (!$value) {
      $value = t('No');
    }

    $options['secondary_sort'] = array(
      'category' => 'other',
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
    switch ($form_state->get('section')) {
      case 'secondary_sort':
        $form['#title'] .= $this->t('Secondary sorts');
        $form['secondary_sort'] = array(
          '#title' => $this->t('Secondary sorts'),
        );
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

        $table = array(
          '#type' => 'table',
          '#header' => array(
            $this->t('Column'),
            $this->t('Weight'),
            $this->t('Order'),
          ),
          '#attributes' => array('id' => 'views-secondary-sort'),
        );
        foreach ($valid as $key => $label) {
          $row = array();
          $row['name'] = array('#plain_text' => $label);
          $row['weight'] = array('#plain_text' => 10);
          $row['order'] = array('#plain_text' => 'asc');
          $table[$key] = $row;
        }

        return;

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
              array('#plain_text' => $valid[$field]),
            );
            $row['weight'] = array(
              '#type' => 'weight',
              '#title' => $this->t('Weight'),
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
                'asc' => $this->t('Ascending'),
                'desc' => $this->t('Descending'),
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
  }

  /**
   * Overrides Drupal\views\Plugin\views\display\DisplayExtenderPluginBase::submitOptionsForm().
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state) {
    parent::submitOptionsForm($form, $form_state);
    switch ($form_state->get('section')) {
      case 'test_extender_test_option':
        $this->options['test_extender_test_option'] = $form_state->getValue('test_extender_test_option');
        break;
    }
  }

  /**
   * Overrides Drupal\views\Plugin\views\display\DisplayExtenderPluginBase::defaultableSections().
   */
  public function defaultableSections(&$sections, $section = NULL) {
    $sections['test_extender_test_option'] = array('test_extender_test_option');
  }

  /**
   * Overrides Drupal\views\Plugin\views\display\DisplayExtenderPluginBase::query().
   */
  public function query() {
    $this->testState['query'] = TRUE;
  }

  /**
   * Overrides Drupal\views\Plugin\views\display\DisplayExtenderPluginBase::preExecute().
   */
  public function preExecute() {
    $this->testState['preExecute'] = TRUE;
  }

}
