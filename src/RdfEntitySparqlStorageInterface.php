<?php

declare(strict_types = 1);

namespace Drupal\rdf_entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides an interface for RDF entity SPARQL storage.
 */
interface RdfEntitySparqlStorageInterface extends ContentEntityStorageInterface {

  /**
   * Gets the defined graph types for this entity type.
   *
   * This is here for convenience.
   *
   * @return array
   *   A structured array of graph definitions containing a title and a
   *   description. The array keys are the machine names of the graphs.
   *
   * @see \Drupal\rdf_entity\RdfGraphHandler::getGraphDefinitions
   */
  public function getGraphDefinitions(): array;

  /**
   * Returns the graph handler object.
   *
   * @return \Drupal\rdf_entity\RdfGraphHandlerInterface
   *   The graph handler service.
   */
  public function getGraphHandler(): RdfGraphHandlerInterface;

  /**
   * Gets the predicate used to determine the bundle.
   *
   * @return string[]
   *   A list of bundle predicates.
   */
  public function getBundlePredicates(): array;

  /**
   * Checks if a specific entity ID already exists in the backend.
   *
   * @param string $id
   *   The ID to be checked.
   * @param string $graph
   *   (optional) The bundle resource uri. If passed, the id will be checked
   *   only against this graph.
   *
   * @return bool
   *   TRUE if this entity ID already exists, FALSE otherwise.
   *
   * @throws \Drupal\rdf_entity\Exception\SparqlQueryException
   *   If the SPARQL query fails.
   * @throws \Exception
   *   The query fails with no reason.
   */
  public function idExists(string $id, string $graph = NULL): bool;

  /**
   * Checks if a RDF entity has a specific graph.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity object.
   * @param string $graph_id
   *   The graph to be checked ('draft', etc).
   *
   * @return bool
   *   TRUE if this entity has the specified graph.
   *
   * @throws \Exception
   *   When the graph cannot be determined.
   */
  public function hasGraph(EntityInterface $entity, string $graph_id): bool;

  /**
   * Deletes a the version of the entity stored in a given graph.
   *
   * @param string $entity_id
   *   The ID of the entity to be deleted.
   * @param string $graph_id
   *   The ID of the graph from where to delete the entity.
   */
  public function deleteFromGraph(string $entity_id, string $graph_id): void;

  /**
   * Loads one entity.
   *
   * The storage will attempt to load the entity, with $id, graph having the ID
   * equals to the first item from $graph_ids array. If is not found, will try
   * with the next and so on. If the entity is not found in any graph, this will
   * return NULL.
   *
   * @param string $id
   *   The ID of the entity to load.
   * @param string[]|null $graph_ids
   *   An ordered list of candidate graph IDs.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   An entity object. NULL if no matching entity is found.
   */
  public function load($id, array $graph_ids = NULL): ?ContentEntityInterface;

  /**
   * Loads one or more entities.
   *
   * @param string[]|null $ids
   *   An array of entity IDs, or NULL to load all entities.
   * @param string[]|null $graph_ids
   *   An ordered list of candidate graph IDs.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   An array of entity objects indexed by their IDs. Returns an empty array
   *   if no matching entities are found.
   */
  public function loadMultiple(array $ids = NULL, array $graph_ids = NULL): array;

  /**
   * Loads an unchanged entity from the database.
   *
   * @param mixed $id
   *   The ID of the entity to load.
   * @param string[]|null $graph_ids
   *   An ordered list of candidate graph IDs.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The unchanged entity, or NULL if the entity cannot be loaded.
   */
  public function loadUnchanged($id, array $graph_ids = NULL): ?ContentEntityInterface;

  /**
   * Load entities by their property values.
   *
   * @param array $values
   *   An associative array where the keys are the property names and the
   *   values are the values those properties must have.
   * @param string[]|null $graph_ids
   *   An ordered list of candidate graph IDs.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   An array of entity objects indexed by their ids.
   */
  public function loadByProperties(array $values = [], array $graph_ids = NULL): array;

}
