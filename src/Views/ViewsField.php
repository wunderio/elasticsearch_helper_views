<?php

namespace Drupal\elasticsearch_helper_views\Views;

use Drupal\elasticsearch_helper\Elasticsearch\Index\FieldDefinition;

/**
 * Views-enabled Elasticsearch field instance class.
 *
 * Instances of this class represent fields in Elasticsearch that can be exposed
 * in Views. A field can represent a top level field or a sub-property of an
 * object.
 *
 * The canonical name of the field consists of the compound names of its parents
 * and the field name itself, joined with a dot separator. Examples: "label",
 * "user.email", "tags.label".
 *
 * An example with an index data:
 *
 * Index: articles
 * Mapping: {
 *   "label": {
 *     "type": "text"
 *   }
 * }
 *
 * Index: pages
 * Mapping: {
 *   "label": "text",
 *   "user": {
 *     "properties": {
 *       "name": {
 *         "type": "text"
 *       },
 *       "email": {
 *         "type": "keyword"
 *       }
 *     }
 *   }
 * }
 *
 * In this case, four ViewsField instances will represent the following:
 *
 * 1. The text field "label", which is shared across two indices.
 * 2. The object field "user", which is used in the "pages" index.
 * 3. The text field "user.name", which is used in the "pages" index.
 * 4. The keyword field "user.email", which is used in the "pages" index.
 */
class ViewsField {

  /**
   * The canonical field separator.
   */
  const CANONICAL_NAME_SEPARATOR = '.';

  /**
   * The canonical name of the field.
   *
   * @var string
   */
  protected $canonicalName;

  /**
   * The top-level field name.
   *
   * @var string|null
   */
  protected $name;

  /**
   * A list of top-level field names of the parent fields.
   *
   * @var array|string[]
   */
  protected $parents = [];

  /**
   * A list of types that the field represent in the indices where it's used.
   *
   * @var \Drupal\elasticsearch_helper_views\Views\ViewsFieldType[]
   */
  protected $types = [];

  /**
   * The class constructor.
   *
   * @param string $canonical_name
   *   The canonical name of the field.
   */
  public function __construct($canonical_name) {
    $this->canonicalName = $canonical_name;
    $this->name = $this->getFieldNameFromCanonicalName($canonical_name);
    $this->parents = $this->getParentsFromCanonicalName($canonical_name);
  }

  /**
   * Creates an instance of the class.
   *
   * @param $field_name
   *   The top-level field name.
   * @param array $parents
   *   The list of field parents.
   *
   * @return static
   *   The instance of the class.
   */
  public static function create($field_name, array $parents = []) {
    $canonical_name = static::createCanonicalName($field_name, $parents);
    return static::createFromCanonicalName($canonical_name);
  }

  /**
   * Creates an instance of the class from the canonical name.
   *
   * @param string $canonical_name
   *    The canonical name of the field.
   *
   * @return static
   *    The instance of the class.
   */
  public static function createFromCanonicalName($canonical_name) {
    return new static($canonical_name);
  }

  /**
   * Returns the canonical name of the field.
   *
   * @param $field_name
   *    The top-level field name.
   *
   * @param array $parents
   *    The list of field parents.
   *
   * @return string
   *   The canonical name of the field.
   */
  public static function createCanonicalName($field_name, array $field_parents) {
    $field_parents = array_merge($field_parents, [$field_name]);

    return implode(static::CANONICAL_NAME_SEPARATOR, $field_parents);
  }

  /**
   * Returns the top-level field name from the canonical name.
   *
   * @param string $canonical_name
   *    The canonical name of the field.
   *
   * @return string|null
   *   The top-level field name.
   */
  public function getFieldNameFromCanonicalName($canonical_name) {
    $parents = explode(static::CANONICAL_NAME_SEPARATOR, $canonical_name);

    return array_pop($parents);
  }

  /**
   * Returns the top-level name of the field.
   *
   * @return string|null
   *   The top-level field name.
   */
  public function getName() {
    return $this->name;
  }

  /**
   * Returns the canonical name of the field.
   *
   * @return string|null
   *   The canonical name of the field.
   */
  public function getCanonicalName() {
    return $this->canonicalName;
  }

  /**
   * Returns the label of the field.
   *
   * @return string
   *   The label of the field.
   */
  public function getLabel() {
    // Attempt to get the label from the field definition metadata.
    $types = $this->getTypes();

    if (count($types) == 1) {
      $type = reset($types);
      $field_definitions = $type->getFieldDefinitions();

      if ($field_definitions) {
        /** @var FieldDefinition $field_definition */
        $field_definition = reset($field_definitions);

        if ($label = $field_definition->getMetadata('label')) {
          return $label;
        }
      }
    }

    return ucfirst(str_replace('_', ' ', $this->getName()));
  }

  /**
   * Returns a list of field parents.
   *
   * @return string[]
   *   A list of field parents.
   */
  public function getParents() {
    return $this->parents;
  }

  /**
   * Returns a list of field parents from the canonical name.
   *
   * @param string $canonical_name
   *    The canonical name of the field.
   *
   * @return string[]
   *   A list of field parents.
   */
  public function getParentsFromCanonicalName($canonical_name) {
    $parents = explode(static::CANONICAL_NAME_SEPARATOR, $canonical_name);

    // Remove the field name from the canonical field name.
    array_pop($parents);

    return $parents;
  }

  /**
   * Returns a list of field type instances.
   *
   * @return \Drupal\elasticsearch_helper_views\Views\ViewsFieldType[]
   *   A list of field type instances.
   */
  public function getTypes() {
    return $this->types;
  }

  /**
   * Returns an instance of the field type.
   *
   * @param $data_type
   *   The data type of the field.
   *
   * @return \Drupal\elasticsearch_helper_views\Views\ViewsFieldType|null
   *   An instance of the field type.
   */
  public function getType($data_type) {
    if ($this->typeExists($data_type)) {
      return $this->types[$data_type];
    }

    return NULL;
  }

  /**
   * Returns TRUE if the field type instance exists.
   *
   * @param $data_type
   *   The data type of the field.
   *
   * @return bool
   *   A bool representing the existence of the field type instance.
   */
  public function typeExists($data_type) {
    return isset($this->types[$data_type]);
  }

  /**
   * Stores the field type instance.
   *
   * @param string $data_type
   *   The data type of the field.
   * @param string $plugin_id
   *   The Elasticsearch index plugin ID.
   * @param \Drupal\elasticsearch_helper\Elasticsearch\Index\FieldDefinition $field_definition
   *   The field definition instance.
   *
   * @return void
   */
  public function setType($data_type, $plugin_id, FieldDefinition $field_definition) {
    // Create the type instance if it doesn't exist.
    if (!($type = $this->getType($data_type))) {
      $type = ViewsFieldType::create($data_type);
    }

    $type->addFieldDefinition($plugin_id, $field_definition);
    $this->types[$data_type] = $type;
  }

  /**
   * Returns a list of types and their scalarity values.
   *
   * @return bool[]
   *   A list of scalarity values.
   */
  public function getTypeScalarity() {
    return array_map(function (ViewsFieldType $type) {
      return $type->isScalar();
    }, $this->getTypes());
  }

  /**
   * Returns the list of the index plugin IDs.
   *
   * @param string|null $data_type
   *   The data type of the field.
   *
   * @return string[]
   *  A list of index plugin IDs.
   */
  public function getIndexPluginIds($data_type = NULL) {
    if ($data_type) {
      return array_keys($this->getType($data_type)->getFieldDefinitions());
    }
    else {
      $result = [];

      foreach ($this->getTypes() as $type) {
        $plugin_ids = array_keys($type->getFieldDefinitions());
        $result = array_merge($result, $plugin_ids);
      }

      return array_unique($result);
    }
  }

  /**
   * Returns the views data structure of the field.
   *
   * @return array
   *   The views data structure of the field.
   */
  public function getViewsData() {
    $data = [];

    // Get a list of types that are scalar.
    $scalar_types = array_filter($this->getTypeScalarity());

    // Do not provide the Views data if there are no scalar types available.
    if (empty($scalar_types)) {
       return [];
    }

    // Get the field's canonical name.
    $canonical_name = $this->getCanonicalName();
    // Get the label.
    $label = $this->getLabel();

    // Replace the dots with underscores as Views data field names cannot
    // contain dots.
    $machine_name = str_replace('.', '__', $canonical_name);

    // Get the data types.
    $data_types = array_map(function (ViewsFieldType $type) {
      return $type->getDataType();
    }, $this->getTypes());

    $data[$machine_name] = [
      'field' => [
        'id' => 'elasticsearch_source',
        'source_field' => $canonical_name,
        'title' => sprintf('%s (%s)', $label, $canonical_name),
        'title short' => $label,
        'help' => t('Appears in the following index plugins: <small><code>@indices</code></small>.<br />Type: <small>@types.</small>', [
          '@types' => implode(', ', $data_types),
          '@indices' => implode(', ', $this->getIndexPluginIds()),
        ]),
      ],
    ];

    return $data;
  }

}
