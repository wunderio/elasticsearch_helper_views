<?php

namespace Drupal\elasticsearch_helper_views\Views;

/**
 * The Views field type class.
 */
class ViewsFieldType {

  /**
   * The data type.
   *
   * @var string
   */
  protected $dataType;

  /**
   * The field definitions.
   *
   * @var \Drupal\elasticsearch_helper\Elasticsearch\Index\FieldDefinition[]
   */
  protected $fieldDefinitions = [];

  /**
   * The class constructor.
   *
   * @param string $data_type
   *   The data type.
   */
  public function __construct($data_type) {
    $this->dataType = $data_type;
  }

  /**
   * Creates an instance of the class.
   *
   * @param string $data_type
   *   The data type.
   *
   * @return static
   *   The instance of the class.
   */
  public static function create($data_type) {
    return new static($data_type);
  }

  /**
   * Returns the data type.
   *
   * @return string
   */
  public function getDataType() {
    return $this->dataType;
  }

  /**
   * Returns TRUE if the data type is scalar.
   *
   * @return bool
   *
   * @todo Retrieve these values from the
   * \Drupal\elasticsearch_helper\Elasticsearch\DataType\DataTypeRepository
   * class.
   */
  public function isScalar() {
    $scalar_types = [
      'text',
      'keyword',
      'long',
      'integer',
      'short',
      'byte',
      'double',
      'float',
      'half_float',
      'scaled_float',
      'unsigned_long',
      'date',
      'boolean',
      'ip',
      'binary',
   ];

    return in_array($this->dataType, $scalar_types);
  }

  /**
   * Returns the field definitions.
   *
   * @return \Drupal\elasticsearch_helper\Elasticsearch\Index\FieldDefinition[]
   */
  public function getFieldDefinitions() {
    return $this->fieldDefinitions;
  }

  /**
   * Add a field definition.
   *
   * @param string $plugin_id
   *   The Elasticsearch index plugin ID.
   * @param \Drupal\elasticsearch_helper\Elasticsearch\Index\FieldDefinition $field_definition
   *   The field definition instance.
   *
   * @return $this
   */
  public function addFieldDefinition($plugin_id, $field_definition) {
    $this->fieldDefinitions[$plugin_id] = $field_definition;

    return $this;
  }

}
