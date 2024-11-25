<?php

namespace Drupal\elasticsearch_helper_views\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\ResultRow;

/**
 * Renders a plain value from the Elasticsearch result.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("elasticsearch_source")
 *
 * @deprecated Use the '\Drupal\elasticsearch_helper_views\Plugin\views\field\DocumentField'
 * field instead.
 */
class Source extends DocumentField {

  /**
   * The nested value separator.
   *
   * @var string
   */
  protected $nestedValueSeparator = '.';

  /**
   * The source field prefix.
   *
   * @var string
   */
  protected $prefix = '_source.';

  /**
   * {@inheritdoc}
   */
  public function defineOptions() {
    $options = parent::defineOptions();

    $options['source_field'] = ['default' => $this->definition['source_field'] ?? ''];
    unset($options['field_name']);

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    // Copy the "field_name" element to the "source_field" element.
    $form['source_field'] = $form['field_name'];

    $form['source_field']['#description'] = $this->t('Enter the key in the "_source" field. For nested fields separate the fields with a separator ("@separator"). Example: @example', [
      '@separator' => '.',
      '@example' => 'abc.xyz',
    ]);

    $form['source_field']['#default_value'] = $this->options['source_field'];
    // Remove the "field_name" element.
    unset($form['field_name']);
  }

  /**
   * {@inheritdoc}
   */
  public function adminLabel($short = FALSE) {
    $this->options['field_name'] = $this->options['source_field'];

    $label = parent::adminLabel();

    unset($this->options['field_name']);

    return $label;
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    // Get the source field.
    $source_field = $this->options['source_field'];

    // Add the prefix.
    $this->options['field_name'] = $this->prefix . $source_field;
    // Get the value.
    $result = parent::render($values);
    // Unset the "field_name" option.
    unset($this->options['field_name']);

    return $result;
  }

}
