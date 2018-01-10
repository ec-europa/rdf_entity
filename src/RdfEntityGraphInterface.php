<?php

declare(strict_types = 1);

namespace Drupal\rdf_entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface for 'rdf_entity_graph' entities.
 */
interface RdfEntityGraphInterface extends ConfigEntityInterface {

  /**
   * Default graph.
   *
   * @var string
   */
  const DEFAULT = 'default';

  /**
   * Set the graph name.
   *
   * @param string $name
   *   The graph name.
   *
   * @return $this
   */
  public function setName(string $name): self;

  /**
   * Set the graph description.
   *
   * @param string $description
   *   The graph description.
   *
   * @return $this
   */
  public function setDescription(string $description): self;

  /**
   * Gets the graph description.
   *
   * @return string
   */
  public function getDescription(): string;

  /**
   * Gets the entity types supporting this graph.
   *
   * @return string[]|null
   *   A list of entity type IDs or NULL if this graph is available to all
   *   entity types.
   */
  public function getEntityTypeIds(): ?array;

  /**
   * Sets the entity type IDs to whom this graph is made available.
   *
   * @param string[]|null $entity_type_ids
   *   A list of entity type IDs or NULL to expose this graph to all types.
   *
   * @return $this
   *
   * @throws \InvalidArgumentException
   *   If there are non-eligible entity types in the list. Eligible entity type
   *   IDs are those each fulfilling all the following conditions:
   *   - An entity type exists for that ID,
   *   - The entity type is a content entity type,
   *   - The entity type storage is an instance of RdfEntitySparqlStorage.
   *
   * @see \Drupal\rdf_entity\Entity\RdfEntitySparqlStorage
   */
  public function setEntityTypeIds(?array $entity_type_ids): self;

}
