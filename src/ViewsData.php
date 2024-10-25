<?php

namespace Drupal\elasticsearch_helper_views;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\elasticsearch_helper\Elasticsearch\Index\FieldDefinition;
use Drupal\elasticsearch_helper\Elasticsearch\Index\MappingDefinition;
use Drupal\elasticsearch_helper\Plugin\ElasticsearchIndexManager;
use Drupal\elasticsearch_helper_views\Views\ViewsFieldCollection;

/**
 * Views data class.
 */
class ViewsData {

  /**
   * The Elasticsearch index manager instance.
   *
   * @var \Drupal\elasticsearch_helper\Plugin\ElasticsearchIndexManager
   */
  protected $elasticsearchIndexManager;

  /**
   * The entity type manager instance.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager instance.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The language manager instance.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The field separator.
   *
   * @var string
   */
  protected $fieldSeparator = '|';

  /**
   * The Views field collection class name.
   *
   * @var string
   */
  protected $viewsFieldCollectionClass = '\Drupal\elasticsearch_helper_views\Views\ViewsFieldCollection';

  /**
   * The Views field class name.
   *
   * @var string
   */
  protected $viewsFieldClass = '\Drupal\elasticsearch_helper_views\Views\ViewsField';

  /**
   * ElasticsearchContentIndexViewsData constructor.
   *
   * @param \Drupal\elasticsearch_helper\Plugin\ElasticsearchIndexManager $elasticsearch_index_manager
   *   The Elasticsearch index manager instance.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager instance.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager instance.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager instance.
   */
  public function __construct(ElasticsearchIndexManager $elasticsearch_index_manager, EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, LanguageManagerInterface $language_manager) {
    $this->elasticsearchIndexManager = $elasticsearch_index_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->languageManager = $language_manager;
  }

  /**
   * @return array[]
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function getViewsData() {
    $fields = [];

    foreach ($this->getViewsFieldCollection()->getFields() as $field_instance) {
      // Get the field-related Views data.
      $fields = array_merge($fields, $field_instance->getViewsData());
    }

    return ['elasticsearch_result' => $fields];
  }

  /**
   * Returns the Views field collection instance.
   *
   * @return \Drupal\elasticsearch_helper_views\Views\ViewsFieldCollection
   *   The Views field collection instance.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function getViewsFieldCollection() {
    // Create Views field collection.
    $collection = call_user_func([$this->viewsFieldCollectionClass, 'create']);

    // Loop through the Elasticsearch indices and add properties to the
    // Views field collection.
    foreach ($this->elasticsearchIndexManager->getDefinitions() as $plugin_id => $plugin_definition) {
      /** @var \Drupal\elasticsearch_helper\Plugin\ElasticsearchIndexInterface $plugin_instance */
      $plugin_instance = $this->elasticsearchIndexManager->createInstance($plugin_id);

      // Add properties to the collection.
      $this->addPropertiesToCollection($collection, $plugin_id, $plugin_instance->getMappingDefinition());
    }

    return $collection;
  }

  public function addPropertiesToCollection(ViewsFieldCollection $collection, $plugin_id, FieldDefinition|MappingDefinition $field_definition, $field_parents = []) {
    foreach ($field_definition->getProperties() as $field_name => $field_definition) {
      // Get the canonical name of the field.
      $canonical_name = call_user_func([$this->viewsFieldClass, 'createCanonicalName'], $field_name, $field_parents);

      // Create a Views field instance, if one is not available in the
      // collection.
      if (!($field_instance = $collection->getField($canonical_name))) {
        $field_instance = call_user_func([$this->viewsFieldClass, 'create'], $field_name, $field_parents);
      }

      // Get the data type from the definition.
      $data_type = $field_definition->getDataType()->getType();
      // Set the data type on the Views field instance.
      $field_instance->setType($data_type, $plugin_id,  $field_definition);

      // Add the Views field to the collection.
      $collection->addField($canonical_name, $field_instance);

      // Recursively add sub-properties to the collection.
      if ($field_definition->hasProperties()) {
        $this_field_parents = array_merge($field_parents, [$field_name]);
        $this->addPropertiesToCollection($collection, $plugin_id, $field_definition, $this_field_parents);
      }

//      continue;
//      $this_field_parents = array_merge($field_parents, [$field_name]);
//      $canonical_name = implode('.', $this_field_parents);
//      $data_type = $field_definition->getDataType()->getType();
//
//      $result[$canonical_name]['name'] = $field_name;
//      $result[$canonical_name]['canonical_name'] = $canonical_name;
//      $result[$canonical_name]['parent'] = $field_parents ? implode('.', $field_parents) : NULL;
//      $result[$canonical_name]['label'] = ucfirst(str_replace('_', ' ', $field_name));
//      $result[$canonical_name]['type'][$data_type]['plugin_id'][$plugin_id]['field_definition'] = $field_definition;
//
//      if ($field_definition->hasProperties()) {
//        // $result = NestedArray::mergeDeep($result, $this->getProperties($plugin_id, $field_definition, $this_field_parents));
//        $this->addProperties($collection, $plugin_id, $field_definition, $this_field_parents)
//      }

      // @todo Add multi-fields later.
//      if ($field_definition->hasMultiFields()) {
//        foreach ($field_definition->getMultiFields() as $multi_field_name => $multi_field_definition) {
//          $this_multi_field_parents = array_merge($this_field_parents, [$multi_field_name]);
//          $full_multi_field_name = implode('.', $this_multi_field_parents);
//
//          $result[$full_multi_field_name] = [
//            'type' => [
//              $multi_field_definition->getDataType()->getType() => [
//                'index' => [
//                  $plugin_id => [
//                    'definition' => $multi_field_definition,
//                  ],
//                ],
//              ],
//            ],
//            'parent' => $this_field_parents ? implode('.', $this_field_parents) : NULL,
//          ];
//        }
//      }
    }

    // return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function _getViewsData() {
    // @todo Wrap everything in try block.
    $data = [];

    // Track field appearance in various indices.
    $field_instances = [];
    // Track index names.
    $index_names_all = [];

    foreach ($this->elasticsearchIndexManager->getDefinitions() as $plugin_id => $plugin_definition) {
      $entity_type = $plugin_definition['entityType'];
      $bundle = $plugin_definition['bundle'] ?? NULL;

      // Get entity keys.
      $entity_keys = $this->entityTypeManager->getDefinition($entity_type)->getKeys();

      // Get field definitions.
      $field_definitions = $this->entityFieldManager->getFieldDefinitions($entity_type, $bundle);

      /** @var \Drupal\elasticsearch_helper\Plugin\ElasticsearchIndexInterface $plugin_instance */
      $plugin_instance = $this->elasticsearchIndexManager->createInstance($plugin_id);

      if (method_exists($plugin_instance, 'getIndexNames')) {
        $index_instance_index_names = array_values($plugin_instance->getIndexNames());
      }
      else {
        $index_instance_index_names = $plugin_instance->getExistingIndices();
      }

      foreach ($plugin_instance->getMappingDefinition()->getProperties() as $field_name => $property) {
        $field_label = ucfirst(str_replace('_', ' ', $field_name));
        $field_label = t('@label', ['@label' => $field_label]);

        /** @var \Drupal\elasticsearch_helper\Elasticsearch\Index\FieldDefinition[] $property_collection */
        // Some fields contain a single property while others might be
        // objects that contain multiple properties.
        // Do not add primary property if it has inner properties.
        $property_collection = $property->hasProperties() ? $property->getProperties() : [NULL => $property];

        foreach ($property_collection as $property_name => $property_item) {
          // Field names are dependent on their depth.
          $views_field_name_parts = [$field_name];
          $property_label_hint_parts = [];

          // Get views options.
          $views_options = [
            'handlers' => [
              'field' => [
                'id' => 'elasticsearch_source',
              ],
            ],
          ];

          // Property names exist only for sub-properties of object type.
          if ($property_name) {
            $views_field_name_parts[] = $property_name;

            // Add sub-property to the label.
            $t_args = ['@property_name' => sprintf('%s:%s', $field_name, $property_name)] + $field_label->getArguments();
            $field_label = t('@label (@property_name)', $t_args);
          }

          // Prepare views field name.
          $views_field_name = implode($this->fieldSeparator, $views_field_name_parts);

          // Field instances are tracked by field name and Elasticsearch
          // data type.
          // There may be entity types with identical field names, but with
          // different field types (e.g., Comment as entity reference on
          // Node entity type and Comment as string on Taxonomy term entity
          // type. Search across multiple indices would not be possible if
          // different typed fields are combined into the same Views field
          // definition.
          $data_type = $property_item->getDataType()->getType();

          // There may be multiple labels for the same field name and type
          // across indices.
          $field_instances[$views_field_name][$data_type]['label'][] = $field_label;
          // $field_instances[$views_field_name][$data_type]['views_options'] = $views_options;
          // Record field usage across index names.
          foreach ($index_instance_index_names as $index_name) {
            $field_instances[$views_field_name][$data_type]['index_name'][] = $index_name;
          }

          // Add property fields to views data (if available).
          foreach ($property_item->getMultiFields() as $property_field_name => $property_field_property) {
            $views_field_name_parts[] = $property_field_name;
            $property_label_hint_parts[] = $property_field_name;

            $field_label = t('@label (@property_name)', ['@property_name' => implode(':', $property_label_hint_parts)] + $field_label->getArguments());

            // Prepare views field name.
            $views_field_name = implode($this->fieldSeparator, $views_field_name_parts);
            // Get multi-field data type.
            $data_type = $property_item->getDataType()->getType();

            $field_instances[$views_field_name][$data_type]['label'][] = $field_label;

            // Record field usage across index names.
            foreach ($index_instance_index_names as $index_name) {
              $field_instances[$views_field_name][$data_type]['index_name'][] = $index_name;
            }

            // Mark this field as being multi-field.
            $field_instances[$views_field_name][$data_type]['multi_field'][] = TRUE;
            // $field_instances[$views_field_name]['views_options'] = $views_options;
          }
        }
      }

      $index_names_all = array_merge($index_names_all, $index_instance_index_names);
    }

    // Loop over prepared field instance array.
    foreach ($field_instances as $field_name => $field_instance) {
      foreach ($field_instance as $data_type => $field_definition) {
        $field_name_parts = explode($this->fieldSeparator, $field_name);
        $field_label = implode(', ', array_unique($field_definition['label']));

        $field = [];

        // Multi-fields are only useful in filter contexts (only filtering).
        if (empty($field_definition['multi_field'])) {
          $field = [
            'title' => $field_label,
            // @todo Change the field plugin to type-specific.
            'id' => 'elasticsearch_source',
            // Inner field names in Elasticsearch are referenced with dots.
            'source_field' => implode('.', $field_name_parts),
          ];
        }

        $data['elasticsearch_result'][implode('_', $field_name_parts)] = [
          'title' => $field_label,
          'field' => $field,
          'help' => t('Appears in: <small><code>@indices</code></small>.', [
            '@indices' => implode(', ', $field_definition['index_name']),
          ]),
          'real field' => implode('.', $field_name_parts),
        ];
      }
    }

    return $data;
  }

}
