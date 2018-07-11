<?php

namespace Drupal\rdf_entity\Entity\Query\Sparql;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryBase;
use Drupal\Core\Entity\Query\QueryFactoryInterface;
use Drupal\rdf_entity\Database\Driver\sparql\ConnectionInterface;
use Drupal\rdf_entity\RdfFieldHandlerInterface;
use Drupal\rdf_entity\RdfGraphHandlerInterface;

/**
 * Provides a factory for creating entity query objects for the null backend.
 */
class QueryFactory implements QueryFactoryInterface {

  /**
   * The connection object.
   *
   * @var \Drupal\rdf_entity\Database\Driver\sparql\ConnectionInterface
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
   * The rdf graph helper service object.
   *
   * @var \Drupal\rdf_entity\RdfGraphHandlerInterface
   */
  protected $graphHandler;

  /**
   * The rdf mapping helper service object.
   *
   * @var \Drupal\rdf_entity\RdfFieldHandlerInterface
   */
  protected $fieldHandler;

  /**
   * Constructs a QueryFactory object.
   *
   * @param \Drupal\rdf_entity\Database\Driver\sparql\ConnectionInterface $connection
   *   The connection object.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\rdf_entity\RdfGraphHandlerInterface $rdf_graph_handler
   *   The rdf graph helper service.
   * @param \Drupal\rdf_entity\RdfFieldHandlerInterface $rdf_field_handler
   *   The rdf mapping helper service.
   */
  public function __construct(ConnectionInterface $connection, EntityTypeManagerInterface $entity_type_manager, RdfGraphHandlerInterface $rdf_graph_handler, RdfFieldHandlerInterface $rdf_field_handler) {
    $this->connection = $connection;
    $this->namespaces = QueryBase::getNamespaces($this);
    $this->entityTypeManager = $entity_type_manager;
    $this->graphHandler = $rdf_graph_handler;
    $this->fieldHandler = $rdf_field_handler;
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
