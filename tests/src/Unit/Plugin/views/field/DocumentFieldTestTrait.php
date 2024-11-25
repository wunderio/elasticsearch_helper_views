<?php

namespace Drupal\Tests\elasticsearch_helper_views\Unit\Plugin\views\field;

/**
 * Provides a trait for document field tests.
 */
trait DocumentFieldTestTrait {

  /**
   * The document provider method.
   *
   * @return array[]
   */
  public function getElasticsearchDocument() {
    return [
      '_id' => '7',
      '_index' => 'person',
      '_source' => [
        'person' => [
          [
            'first_name' => 'John',
            'last_name' => 'Smith',
            'work' => [
              [
                'company' => [
                  0 => 'One Inc.',
                  1 => 'Two Inc.',
                ],
              ],
              [
                'company' => [
                  0 => 'One Inc.',
                  1 => 'Three Inc.',
                ],
              ],
            ],
          ],
          [
            'first_name' => ['James', 'Jacob'],
            'last_name' => ['Bradly'],
            'work' => [
              'company' => 'Four Inc.',
            ],
          ],
        ],
      ],
    ];
  }

}
