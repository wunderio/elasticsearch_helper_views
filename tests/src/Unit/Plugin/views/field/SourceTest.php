<?php

namespace Drupal\Tests\elasticsearch_helper_views\Unit\Plugin\views\field;

use Drupal\elasticsearch_helper_views\Plugin\views\field\Source;
use Drupal\Tests\UnitTestCase;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;

/**
 * @coversDefaultClass \Drupal\elasticsearch_helper_views\Plugin\views\field\Source
 *
 * @group elasticsearch_helper_views
 */
class SourceTest extends UnitTestCase {

  use DocumentFieldTestTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->assertTrue(TRUE);
  }

  /**
   * Tests the render method when getEntity returns NULL.
   *
   * @covers ::render
   * @dataProvider documentSourceProvider
   */
  public function testRender($data, $source_field, $expected) {
    $field = new Source([], '', ['source_field' => $source_field]);
    $view = $this->createMock(ViewExecutable::class);
    $display = $this->createMock(DisplayPluginBase::class);
    $field->init($view, $display);

    $row = new ResultRow($data);
    $this->assertEquals($field->render($row), $expected);
  }

  /**
   * The document _source" value provider method.
   *
   * @return array[]
   */
  public function documentSourceProvider() {
    $elasticsearch_document = $this->getElasticsearchDocument();

    return [
      [
        // The source data.
        [
          '_source' => [
            'first_name' => 'John',
          ],
        ],
        // The source field.
        'first_name',
        // The expected data.
        'John',
      ],
      [
        // The source data.
        [
          '_source' => [
            'tags' => ['Foo', 'Bar'],
          ],
        ],
        // The source field.
        'tags',
        // The expected data.
        'Foo, Bar',
      ],
      [
        // The source data.
        $elasticsearch_document,
        // The source field.
        'person.first_name',
        // Expected data.
        'John, James, Jacob',
      ],
      [
        // The source data.
        $elasticsearch_document,
        // The source field.
        'person.work.company',
        // The expected data.
        'One Inc., Two Inc., One Inc., Three Inc., Four Inc.',
      ],
    ];
  }

}
