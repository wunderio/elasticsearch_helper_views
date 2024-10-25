<?php

namespace Drupal\elasticsearch_helper_views\Views;

/**
 * Views data field collection class.
 *
 * This class contains a list of fields that can be exposed in Views.
 */
class ViewsFieldCollection {

  protected $fields = [];

  public static function create() {
    return new static();
  }

  public function getField($canonical_name) {
    if ($this->fieldExists($canonical_name)) {
      return $this->fields[$canonical_name];
    }

    return NULL;
  }

  /**
   * Returns all fields.
   *
   * @return \Drupal\elasticsearch_helper_views\Views\ViewsField[]
   */
  public function getFields() {
    return $this->fields;
  }

  /**
   * Check if the field exists.
   *
   * @param string $canonical_name
   *   The canonical name of the field.
   *
   * @return bool
   *   TRUE if the field exists, FALSE otherwise.
   */
  public function fieldExists($canonical_name) {
    return isset($this->fields[$canonical_name]);
  }

  /**
   * Add a field to the collection.
   *
   * @param string $canonical_name
   *   The canonical name of the field.
   * @param \Drupal\elasticsearch_helper_views\Views\ViewsField $field
   *   The field object.
   */
  public function addField($canonical_name, ViewsField $field) {
    $this->fields[$canonical_name] = $field;
  }

}
