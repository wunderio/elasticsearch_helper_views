<?php

namespace Drupal\elasticsearch_helper_views\Plugin\views\field;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Renders a plain value from the Elasticsearch result.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("elasticsearch_source")
 */
class Source extends FieldPluginBase {

  /**
   * The nested value separator.
   *
   * @var string
   */
  protected $nestedValueSeparator = '.';

  /**
   * {@inheritdoc}
   */
  public function defineOptions() {
    $options = parent::defineOptions();

    $options['source_field'] = ['default' => $this->definition['source_field'] ?? ''];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $t_args_description = ['@separator' => $this->nestedValueSeparator, '@example' => implode($this->nestedValueSeparator, ['abc', 'xyz'])];
    $form['source_field'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Source field'),
      '#description' => $this->t('Enter the key in the "_source" field. For nested fields separate the fields with a separator ("@separator"). Example: @example', $t_args_description),
      '#required' => TRUE,
      '#default_value' => $this->options['source_field']
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function adminLabel($short = FALSE) {
    $label = parent::adminLabel();

    if ($this->options['source_field'] != '') {
      return $label . ' (' . $this->options['source_field'] . ')';
    }

    return $label;
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $row) {
    $source_field = $this->options['source_field'];

    if (isset($row->_source) && is_array($row->_source)) {
      return $this->getNestedValue($source_field, $row->_source);
    }

    return '';
  }

  /**
   * Returns the value from the nested array.
   *
   * @param $key
   * @param array $data
   * @param $default
   *
   * @return mixed|null
   */
  protected function getNestedValue($key, array $data = [], $default = '') {
    $parents = explode($this->nestedValueSeparator, $key);

    $flattened_data = $this->simplifyData($data);
    $result = NestedArray::getValue($flattened_data, $parents, $key_exists);

    if (is_array($result)) {
      $result = implode(', ', $result);
    }

    return $result != '' ? $result : $default;
  }

  /**
   * Simplifies the data array.
   *
   * Each field in Elasticsearch document can have one of more values, including
   * nested or object fields. To simplify the data extraction, this method
   * groups all field values, including multiple values, into a single value
   * array.
   *
   * Example:
   *
   * Field definition:
   *
   * 'family_field' => FieldDefinition::create('object')
   *   ->addProperty('first_name', FieldDefinition::create('text'))
   *   ->addProperty('last_name', FieldDefinition::create('text'))
   *   ->addProperty('family', FieldDefinition::create('object')
   *     ->addProperty('family_name', FieldDefinition::create('text'))
   * );
   *
   * Data:
   *
   * $data = [
   *   'family_field' => [
   *     [
   *       'first_name' => 'John',
   *       'last_name' => 'Doe',
   *       'family' => [
   *         [
   *           'family_name' => ['Doe', 'Jones'],
   *         ],
   *         [
   *           'family_name' => ['Smith', 'Robertson'],
   *         ]
   *       ],
   *     ],
   *     [
   *       'first_name' => ['James', 'Jacob'],
   *       'last_name' => ['Bradly'],
   *       'family' => [
   *         'family_name' => 'Dove',
   *       ],
   *     ],
   *   ],
   * ];
   *
   * Result:
   *
   * $result = [
   *   'family_field' => [
   *     'first_name' => ['John', 'James', 'Jacob'],
   *     'last_name' => ['Doe', 'Bradly'],
   *     'family' => [
   *       'family_name' => ['Doe', 'Jones', 'Smith', 'Robertson', 'Dove'],
   *     ],
   *   ],
   * ];
   *
   *
   * @param array $data
   * @param array $parent
   * @param array $result
   *
   * @return array
   */
  protected function simplifyData(array $data, array $parent = [], array $result = []) {
    foreach ($data as $key => $value) {
      // Do not include the numeric keys in parents array.
      if (is_numeric($key)) {
        $new_parents = $parent;
      }
      else {
        $new_parents = array_merge($parent, [$key]);
      }

      if (is_array($value)) {
        $result = array_merge($result, $this->simplifyData($value, $new_parents, $result));
      }
      else {
        // Get existing value.
        $existing_value = &NestedArray::getValue($result, $new_parents, $key_exists);
        // Set the value into the resulting array.
        $existing_value[] = $value;
        NestedArray::setValue($result, $new_parents, $existing_value);
      }
    }

    return $result;
  }

}
