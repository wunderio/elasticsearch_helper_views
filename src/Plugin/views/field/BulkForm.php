<?php

namespace Drupal\elasticsearch_helper_views\Plugin\views\field;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Routing\RedirectDestinationTrait;
use Drupal\Core\Url;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\Plugin\views\field\UncacheableFieldHandlerTrait;
use Drupal\views\Plugin\views\style\Table;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Defines a actions-based bulk operation form element.
 *
 * @ViewsField("elasticsearch_bulk_form")
 */
class BulkForm extends FieldPluginBase {

  // This trait is crucial for bulk form to work. It doesn't
  // sanitize the output from self::getValue() and allows views
  // substitution to replace the comment with actual input field.
  use UncacheableFieldHandlerTrait;
  use RedirectDestinationTrait;
  use MessengerTrait;

  /**
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $actionStorage;

  /**
   * An array of actions that can be executed.
   *
   * @var \Drupal\system\Entity\Action[]
   */
  protected $actions = [];

  /**
   * BulkForm constructor.
   *
   * @param array $configuration
   * @param $plugin_id
   * @param $plugin_definition
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->actionStorage = $entity_type_manager->getStorage('action');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);

    $this->actions = array_filter($this->actionStorage->loadMultiple(), function ($action) {
      /** @var \Drupal\system\Entity\Action $action */
      return $action->getPluginDefinition()['type'] == 'elasticsearch_helper';
    });
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['action_title'] = ['default' => $this->t('Action')];
    $options['include_exclude'] = [
      'default' => 'exclude',
    ];
    $options['selected_actions'] = [
      'default' => [],
    ];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    $form['action_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Action title'),
      '#default_value' => $this->options['action_title'],
      '#description' => $this->t('The title shown above the actions dropdown.'),
    ];

    $form['include_exclude'] = [
      '#type' => 'radios',
      '#title' => $this->t('Available actions'),
      '#options' => [
        'exclude' => $this->t('All actions, except selected'),
        'include' => $this->t('Only selected actions'),
      ],
      '#default_value' => $this->options['include_exclude'],
    ];
    $form['selected_actions'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Selected actions'),
      '#options' => $this->getBulkOptions(FALSE),
      '#default_value' => $this->options['selected_actions'],
    ];

    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateOptionsForm(&$form, FormStateInterface $form_state) {
    parent::validateOptionsForm($form, $form_state);

    $selected_actions = $form_state->getValue(['options', 'selected_actions']);
    $form_state->setValue(['options', 'selected_actions'], array_values(array_filter($selected_actions)));
  }

  /**
   * {@inheritdoc}
   */
  public function preRender(&$values) {
    parent::preRender($values);

    // If the view is using a table style, provide a placeholder for a
    // "select all" checkbox.
    if (!empty($this->view->style_plugin) && $this->view->style_plugin instanceof Table) {
      // Add the tableselect css classes.
      $this->options['element_label_class'] .= 'select-all';
      // Hide the actual label of the field on the table header.
      $this->options['label'] = '';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getValue(ResultRow $row, $field = NULL) {
    return '<!--form-item-' . $this->options['id'] . '--' . $row->index . '-->';
  }

  /**
   * Form constructor for the bulk form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function viewsForm(&$form, FormStateInterface $form_state) {
    // Get current step.
    $step = $form_state->get('step');

    // Handle the initial form view.
    if ($step == 'views_form_views_form') {
      // Make sure we do not accidentally cache this form.
      // @todo Evaluate this again in https://www.drupal.org/node/2503009.
      $form['#cache']['max-age'] = 0;

      // Add the tableselect javascript.
      $form['#attached']['library'][] = 'core/drupal.tableselect';
      $form['#attached']['library'][] = 'elasticsearch_helper_views/admin-views';

      // Only add the bulk form options and buttons if there are results.
      if (!empty($this->view->result)) {
        // Render checkboxes for all rows.
        $form[$this->options['id']]['#tree'] = TRUE;

        foreach ($this->view->result as $row_index => $row) {
          $id_key = $this->getIdKeyFromRow($row);

          $form[$this->options['id']][$row_index] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Update this item'),
            '#title_display' => 'invisible',
            '#default_value' => !empty($form_state->getValue($this->options['id'])[$row_index]) ? 1 : NULL,
            '#return_value' => $this->calculateBulkFormKey($id_key),
          ];
        }

        // Replace the form submit button label.
        $form['actions']['submit']['#value'] = $this->t('Apply to selected items');

        // Ensure a consistent container for filters/operations in the view header.
        $form['header'] = [
          '#type' => 'container',
          '#weight' => -100,
        ];

        // Build the bulk operations action widget for the header.
        // Allow themes to apply .container-inline on this separate container.
        $form['header'][$this->options['id']] = [
          '#type' => 'container',
        ];
        $form['header'][$this->options['id']]['action'] = [
          '#type' => 'select',
          '#title' => $this->options['action_title'],
          '#options' => $this->getBulkOptions(),
        ];

        // Duplicate the form actions into the action container in the header.
        $form['header'][$this->options['id']]['actions'] = $form['actions'];

        // Select all element container.
        $form['select_all'] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => [
              'select-all-markup',
            ],
          ],
        ];

        // Indicates if select all should capture results across all pages.
        $form['select_all_pages'] = [
          '#type' => 'hidden',
          // #default_value can be changed in the browser while #value cannot.
          '#default_value' => 0,
          '#attributes' => [
            'class' => [
              'select-all-pages-flag',
            ],
          ],
        ];

        // Get count.
        $count_all_pages = $this->view->total_rows;
        $count_this_page = count($this->view->result);

        $form['select_all']['select_all_pages_button'] = [
          '#type' => 'button',
          '#value' => t('Select all @row_count in this view', ['@row_count' => $count_all_pages]),
          '#prefix' => '<span class="select-all-pages-button">' . t('Selected <strong>@count rows</strong> on this page', ['@count' => $count_this_page]),
          '#suffix' => '</span>',
        ];
        $form['select_all']['select_this_page_button'] = [
          '#type' => 'button',
          '#value' => t('Select only @row_count on this page', ['@row_count' => $count_this_page]),
          '#prefix' => '<span class="select-this-page-button">' . t('Selected <strong>@count rows</strong> in this view', ['@count' => $count_all_pages]),
          '#suffix' => '</span>',
        ];
      }

      // Remove the default actions build array.
      unset($form['actions']);
    }
    // Display action plugin configuration form.
    elseif ($step == 'action_plugin_configuration_form') {
      // Check if all view results are selected.
      $select_all = $form_state->getValue('select_all_pages');

      // Reset the form.
      $form = [
        '#tree' => TRUE,
      ];

      // Store the selected values.
      foreach (['action', 'select_all_pages', $this->options['id']] as $key) {
        $form[$key] = [
          '#type' => 'value',
          '#value' => $form_state->getValue($key),
        ];
      }

      // Get selected values.
      if ($selected = array_filter($form_state->getValue($this->options['id'], []))) {
        // Get total selected items count.
        $count = $select_all ? $this->view->total_rows : count($selected);
        // Get a chunk of selected items to list.
        $selected_list = array_splice($selected, 0, $this->view->query->getLimit());
        // Get selected list count.
        $selected_list_count = count($selected_list);

        // Get listing items.
        $items = array_map(function ($id) {
          return implode(':', $id);
        }, $this->getIdsFromBulkFormKeys($selected_list));

        if ($count > $selected_list_count) {
          $items[] = $this->t('and other @count items.', [
            '@count' => $count - $selected_list_count,
          ]);
        }

        $form['message'][] = [
          '#markup' => $this->formatPlural(
            $count,
            'You are about to apply an action on @count item:',
            'You are about to apply an action on @count items:', [
              '@count' => $count,
            ]
          ),
        ];

        $form['message'][] = [
          '#theme' => 'item_list',
          '#items' => $items,
        ];
      }

      // Get action plugin.
      $action = $this->actions[$form_state->getValue('action')];
      /** @var \Drupal\Core\Action\ConfigurableActionBase $action_plugin */
      $action_plugin = $action->getPlugin();

      // Set action plugin configuration form.
      $form['action_plugin_configuration'] = $action_plugin->buildConfigurationForm($form, $form_state);

      // Set actions.
      $form['actions'] = [
        '#type' => 'actions',
      ];

      $form['actions'] += $this->getActions($form_state);
    }
  }

  /**
   * Returns a list of actions.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array
   */
  protected function getActions(FormStateInterface $form_state) {
    // Get current step.
    $step = $form_state->get('step');

    $actions['execute'] = [
      '#type' => 'submit',
      '#value' => $this->t('Execute'),
      '#button_type' => 'primary',
      '#access' => $step == 'action_plugin_configuration_form',
      '#weight' => -50,
    ];

    $actions['back'] = [
      '#type' => 'link',
      '#title' => t('Back'),
      '#attributes' => ['class' => ['button']],
      '#url' => Url::fromUserInput(\Drupal::request()->getRequestUri()),
      '#access' => $step == 'action_plugin_configuration_form',
    ];

    return $actions;
  }

  /**
   * Submit handler for the bulk form.
   *
   * @param $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function viewsFormValidate(&$form, FormStateInterface $form_state) {
    // Get action entity.
    $action = $this->actions[$form_state->getValue('action')];
    // Get action plugin.
    $action_plugin = $action->getPlugin();
    // Check if action plugin is configurable.
    $configurable = $action->isConfigurable();

    // Validate input if action plugin is configurable.
    if ($configurable && $form_state->get('step') == 'action_plugin_configuration_form') {
      // Get action plugin configuration.
      if ($subform = &NestedArray::getValue($form, ['action_plugin_configuration'])) {
        // Submit the plugin configuration.
        $subform_state = SubformState::createForSubform($subform, $form, $form_state);
        /** @var \Drupal\Core\Action\ConfigurableActionBase $action_plugin */
        $action_plugin->validateConfigurationForm($subform, $subform_state);
      }
    }
  }

  /**
   * Submit handler for the bulk form.
   *
   * @param $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function viewsFormSubmit(&$form, FormStateInterface $form_state) {
    // Get action entity.
    $action = $this->actions[$form_state->getValue('action')];
    // Get action plugin.
    $action_plugin = $action->getPlugin();
    // Check if action plugin is configurable.
    $configurable = $action->isConfigurable();
    // Check if all view results are selected.
    $select_all = $form_state->getValue('select_all_pages');

    // Move to configuration step if action plugin is configurable.
    if ($configurable && $form_state->get('step') == 'views_form_views_form') {
      $form_state->set('step', 'action_plugin_configuration_form');
      $form_state->setRebuild();
    }
    // Execute the action.
    else {
      if ($configurable) {
        // Get action plugin configuration.
        if ($subform = &NestedArray::getValue($form, ['action_plugin_configuration'])) {
          // Submit the plugin configuration.
          $subform_state = SubformState::createForSubform($subform, $form, $form_state);
          /** @var \Drupal\Core\Action\ConfigurableActionBase $action_plugin */
          $action_plugin->submitConfigurationForm($subform, $subform_state);
        }

        // Set table name in action plugin configuration.
        $configuration = $action_plugin->getConfiguration();
        $configuration['table_name'] = $this->view->storage->get('base_table');
        $configuration['view'] = $this->view;
        $action_plugin->setConfiguration($configuration);
      }

      // Get selected checkboxes from values.
      $selected = array_filter($form_state->getValue($this->options['id']));

      // Collect row IDs.
      $row_ids = [];

      if ($select_all) {
        $view_clone = clone $this->view;
        $view_clone->build_info['query']['size'] = 10000;
        $view_clone->build_info['query']['from'] = 0;
        $view_clone->getQuery()->execute($view_clone);

        foreach ($view_clone->result as $row_index => $row) {
          $id = $this->getIdKeyFromRow($row);
          $bulk_form_key = $this->calculateBulkFormKey($id);
          $row_ids[$bulk_form_key] = $id;
        }
      }
      else {
        $row_ids = $this->getIdsFromBulkFormKeys($selected);
      }

      // Assume that action result is void.
      $action_result = NULL;
      // Indicates if status message needs to be displayed.
      $display_status = FALSE;

      try {
        // Execute the action.
        $action_result = $action->execute($row_ids);
        $display_status = TRUE;
      }
      catch (\Exception $e) {
        $this->messenger()->addError($e->getMessage());
      }

      // Get action definition.
      $action_definition = $action->getPluginDefinition();

      // Set response if action plugin returns it.
      if ($action_result instanceof Response) {
        $form_state->setResponse($action_result);
      }
      elseif (!empty($action_definition['confirm_form_route_name'])) {
        $options = [
          'query' => $this->getDestinationArray(),
        ];

        $form_state->setRedirect($action_definition['confirm_form_route_name'], [], $options);
      }
      else {
        // Check if status message needs to be displayed.
        if ($display_status) {
          // Don't display the message unless there are some elements affected and
          // there is no confirmation form.
          $count = count($row_ids);

          if ($count) {
            $this->messenger()->addStatus($this->formatPlural($count, 'Action %action was applied to @count item.', 'Action %action was applied to @count items.', [
              '%action' => $action->label(),
            ]));
          }
        }
      }
    }
  }

  /**
   * Returns the available operations for this form.
   *
   * @param bool $filtered
   *   (optional) Whether to filter actions to selected actions.
   * @return array
   *   An associative array of operations, suitable for a select element.
   */
  protected function getBulkOptions($filtered = TRUE) {
    $options = [];
    // Filter the action list.
    foreach ($this->actions as $id => $action) {
      if ($filtered) {
        $in_selected = in_array($id, $this->options['selected_actions']);

        // If the field is configured to include only the selected actions,
        // skip actions that were not selected.
        if (($this->options['include_exclude'] == 'include') && !$in_selected) {
          continue;
        }
        // Otherwise, if the field is configured to exclude the selected
        // actions, skip actions that were selected.
        elseif (($this->options['include_exclude'] == 'exclude') && $in_selected) {
          continue;
        }
      }

      $options[$id] = $action->label();
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
  }

  /**
   * Returns row IDs from bulk form keys.
   *
   * @param array $selected
   *
   * @return array
   */
  protected function getIdsFromBulkFormKeys(array $selected) {
    $results = [];

    // Loop over selected checkboxes.
    foreach ($selected as $bulk_form_key) {
      $id = $this->loadIdFromBulkFormKey($bulk_form_key);

      $results[$bulk_form_key] = $id;
    }

    return $results;
  }

  /**
   * Returns ID key from result row.
   *
   * @param \Drupal\views\ResultRow $row
   *
   * @return array
   */
  protected function getIdKeyFromRow(ResultRow $row) {
    return [
      'index' => $row->_index,
      'id' => $row->_id,
    ];
  }

  /**
   * Calculates a bulk form key.
   *
   * This generates a key that is used as the checkbox return value when
   * submitting a bulk form.
   *
   * @param array $id
   *
   * @return string
   *
   * @see self::loadIdFromBulkFormKey()
   */
  protected function calculateBulkFormKey(array $id) {
    // An entity ID could be an arbitrary string (although they are typically
    // numeric). JSON then Base64 encoding ensures the bulk_form_key is
    // safe to use in HTML, and that the key parts can be retrieved.
    $key = json_encode($id);

    return base64_encode($key);
  }

  /**
   * Loads a entry ID based on a bulk form key.
   *
   * @param $bulk_form_key
   *
   * @return array|null
   */
  protected function loadIdFromBulkFormKey($bulk_form_key) {
    $id = base64_decode($bulk_form_key);
    $id = (array) json_decode($id);

    return $id ?: NULL;
  }

}
