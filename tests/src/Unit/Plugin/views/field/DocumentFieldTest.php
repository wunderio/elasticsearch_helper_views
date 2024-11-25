<?php

namespace Drupal\Tests\elasticsearch_helper_views\Unit\Plugin\views\field;

use Drupal\elasticsearch_helper_views\Plugin\views\field\DocumentField;
use Drupal\Tests\UnitTestCase;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;

/**
 * @coversDefaultClass \Drupal\elasticsearch_helper_views\Plugin\views\field\DocumentField
 *
 * @group elasticsearch_helper_views
 */
class DocumentFieldTest extends UnitTestCase {

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
   * @dataProvider documentDataProvider
   */
  public function testRender($data, $source_field, $expected) {
    $field = new DocumentField([], '', ['field_name' => $source_field]);
    $view = $this->createMock(ViewExecutable::class);
    $display = $this->createMock(DisplayPluginBase::class);
    $field->init($view, $display);

    $row = new ResultRow($data);
    $this->assertEquals($field->render($row), $expected);
  }

  /**
   * The document value provider method.
   *
   * @return array[]
   */
  public function documentDataProvider() {
    $elasticsearch_document = $this->getElasticsearchDocument();

    return [
      [
        // The document data.
        $elasticsearch_document,
        // The source field.
        '_index',
        // The expected data.
        'person',
      ],
      [
        // The document data.
        $elasticsearch_document,
        // The source field.
        '_id',
        // The expected data.
        '7',
      ],
      [
        // The document data.
        [
          '_source' => [
            'first_name' => 'John',
          ],
        ],
        // The document field.
        '_source.first_name',
        // The expected data.
        'John',
      ],
      [
        // The document data.
        [
          '_source' => [
            'tags' => ['Foo', 'Bar'],
          ],
        ],
        // The document field.
        '_source.tags',
        // The expected data.
        'Foo, Bar',
      ],
      [
        // The document data.
        $elasticsearch_document,
        // The document field.
        '_source.person.first_name',
        // Expected data.
        'John, James, Jacob',
      ],
      [
        // The document data.
        $elasticsearch_document,
        // The document field.
        '_source.person.work.company',
        // The expected data.
        'One Inc., Two Inc., One Inc., Three Inc., Four Inc.',
      ],
    ];
  }

}
