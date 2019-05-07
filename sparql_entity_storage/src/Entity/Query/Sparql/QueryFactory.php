<?php

namespace Drupal\sparql_entity_storage\Entity\Query\Sparql;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryBase;
use Drupal\Core\Entity\Query\QueryFactoryInterface;
use Drupal\sparql_entity_storage\Database\Driver\sparql\ConnectionInterface;
use Drupal\sparql_entity_storage\SparqlEntityStorageFieldHandlerInterface;
use Drupal\sparql_entity_storage\SparqlEntityStorageGraphHandlerInterface;

/**
 * Provides a factory for creating entity query objects for the null backend.
 */
class QueryFactory implements QueryFactoryInterface {

  /**
   * The connection object.
   *
   * @var \Drupal\sparql_entity_storage\Database\Driver\sparql\ConnectionInterface
   */
  protected $connection;

  /**
   * The namespace of this class, the parent class etc.
   *
   * @var array
   */
  protected $namespaces;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The SPARQL graph helper service object.
   *
   * @var \Drupal\sparql_entity_storage\SparqlEntityStorageGraphHandlerInterface
   */
  protected $graphHandler;

  /**
   * The SPARQL field mapping service.
   *
   * @var \Drupal\sparql_entity_storage\SparqlEntityStorageFieldHandlerInterface
   */
  protected $fieldHandler;

  /**
   * Constructs a QueryFactory object.
   *
   * @param \Drupal\sparql_entity_storage\Database\Driver\sparql\ConnectionInterface $connection
   *   The connection object.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\sparql_entity_storage\SparqlEntityStorageGraphHandlerInterface $sparql_graph_handler
   *   The SPARQL graph helper service.
   * @param \Drupal\sparql_entity_storage\SparqlEntityStorageFieldHandlerInterface $sparql_field_handler
   *   The SPARQL field mapping service.
   */
  public function __construct(ConnectionInterface $connection, EntityTypeManagerInterface $entity_type_manager, SparqlEntityStorageGraphHandlerInterface $sparql_graph_handler, SparqlEntityStorageFieldHandlerInterface $sparql_field_handler) {
    $this->connection = $connection;
    $this->namespaces = QueryBase::getNamespaces($this);
    $this->entityTypeManager = $entity_type_manager;
    $this->graphHandler = $sparql_graph_handler;
    $this->fieldHandler = $sparql_field_handler;
  }

  /**
   * {@inheritdoc}
   */
  public function get(EntityTypeInterface $entity_type, $conjunction) {
    $class = QueryBase::getClass($this->namespaces, 'Query');
    return new $class($entity_type, $conjunction, $this->connection, $this->namespaces, $this->entityTypeManager, $this->graphHandler, $this->fieldHandler);
  }

  /**
   * {@inheritdoc}
   */
  public function getAggregate(EntityTypeInterface $entity_type, $conjunction) {
    $class = QueryBase::getClass($this->namespaces, 'Query');
    return new $class($entity_type, $conjunction, $this->connection, $this->namespaces, $this->entityTypeManager, $this->graphHandler, $this->fieldHandler);
  }

}
