<?php

/**
 * @file
 * Provide views data for Elasticsearch indices.
 */

use Drupal\Component\Utility\NestedArray;

/**
 * Implements hook_views_data().
 *
 * Use hook_views_data_alter() hook to alter or add fields or filters to
 * "elasticsearch_result" data type.
 *
 * @see hook_views_data_alter()
 */
function elasticsearch_helper_views_views_data() {
  $data = [];

  $data['elasticsearch_result']['table']['group'] = t('Elasticsearch result');

  $data['elasticsearch_result']['table']['base'] = [
    'field' => 'id',
    'title' => t('Elasticsearch result'),
    'help' => t('Elasticsearch result'),
    'query_id' => 'elasticsearch_query',
  ];

  $data['elasticsearch_result']['entity_relationship'] = [
    'relationship' => [
      'id' => 'entity_relationship',
      'title' => t('Entity relationship'),
      'base' => 'elasticsearch_result',
    ],
  ];

  $data['elasticsearch_result']['rendered_entity'] = [
    'field' => [
      'title' => t('Rendered entity'),
      'help' => t('Renders an entity in a view mode.'),
      'id' => 'elasticsearch_rendered_entity',
    ],
  ];

  $data['elasticsearch_result']['source'] = [
    'title' => t('Source field'),
    'help' => t('Returns a value from the "_source" field.'),
    'field' => [
      'id' => 'elasticsearch_source',
    ],
  ];

  $data['elasticsearch_result']['document_field'] = [
    'title' => t('Document field'),
    'help' => t('Returns a value from the Elasticsearch document field.'),
    'field' => [
      'id' => 'elasticsearch_document_field',
    ],
  ];

  $data['elasticsearch_result']['bulk_form'] = [
    'field' => [
      'title' => t('Bulk form'),
      'help' => t('Provides bulk form to perform operation on Elasticsearch documents.'),
      'id' => 'elasticsearch_bulk_form',
    ],
  ];

  return $data;
}
