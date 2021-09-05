<?php

namespace Drupal\elasticsearch_helper_views\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\ConfigurableActionBase;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Elasticsearch\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Delete Elasticsearch document.
 *
 * @Action(
 *   id = "elasticsearch_document_delete",
 *   label = @Translation("Delete"),
 *   type = "elasticsearch_helper"
 * )
 */
class Delete extends ConfigurableActionBase implements ContainerFactoryPluginInterface {

  use DependencySerializationTrait;

  /**
   * @var \Elasticsearch\Client
   */
  protected $client;

  /**
   * Delete constructor.
   *
   * @param array $configuration
   * @param $plugin_id
   * @param $plugin_definition
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Client $client) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->client = $client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('elasticsearch_helper.elasticsearch_client')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function access($id, AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = AccessResult::allowed();

    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute() {
  }

  /**
   * Executes the action on multiple Elasticsearch documents.
   *
   * @param array $documents
   *
   * @throws \Exception
   */
  public function executeMultiple(array $documents) {
    // @todo Use bulk requests for documents in the same index.
    foreach ($documents as $document) {
      if (isset($document['index'], $document['type'], $document['id'])) {
        $this->client->delete($document);
      }
    }

    // Wait a a couple of seconds to give Elasticsearch a change to digest the
    // delete operation.
    // @todo Change to a better solution.
    sleep(2);
  }

}
