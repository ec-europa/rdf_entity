<?php

declare(strict_types = 1);

namespace Drupal\rdf_entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;

/**
 * Provides an interface for 'rdf_entity_mapping' entities.
 */
interface RdfEntityMappingInterface extends ConfigEntityInterface {

  /**
   * Gets the referred entity type ID.
   *
   * @return string
   */
  public function getTargetEntityTypeId(): string;

  /**
   * Gets the referred entity type.
   *
   * @return \Drupal\Core\Entity\ContentEntityTypeInterface|null
   *   The target entity type.
   */
  public function getTargetEntityType(): ?ContentEntityTypeInterface;

  /**
   * Gets the referred bundle ID.
   *
   * @return string
   */
  public function getTargetBundle(): string;

  /**
   * Sets the RDF type mapping value.
   *
   * @param string $rdf_type
   *   The RDF type mapping.
   *
   * @return $this
   */
  public function setRdfType(string $rdf_type): self;

  /**
   * Gets the RDF type mapping value.
   *
   * @return string|null
   *   The RDF type mapping.
   */
  public function getRdfType(): ?string;

  /**
   * Sets the RDF entity ID generator plugin.
   *
   * @param string $entity_id_plugin
   *   The RDF entity ID generator plugin.
   *
   * @return $this
   */
  public function setEntityIdPlugin(string $entity_id_plugin): self;

  /**
   * Gets the RDF entity ID generator plugin.
   *
   * @return string|null
   *   The RDF entity ID generator plugin.
   */
  public function getEntityIdPlugin(): ?string;

  /**
   * Adds a list of graphs.
   *
   * Graphs are added to the existing list. If a graph with the same name
   * already exists will be overridden with the new passed value.
   *
   * @param string[] $graphs
   *   Associative array keyed by graph name, having the graph URIs as values.
   *
   * @return $this
   */
  public function addGraphs(array $graphs): self;

  /**
   * Sets the list of graphs.
   *
   * Unlike ::addGraphs(), this method replaces the whole list of graphs. It's
   * mandatory to pass also the 'default'.
   *
   * @param string[] $graphs
   *   Associative array keyed by graph name, having the graph URIs as values.
   *
   * @return $this
   *
   * @throws \InvalidArgumentException
   *   If the passed list of graphs doesn't contain the 'default' graph.
   *
   * @see self::addGraphs()
   */
  public function setGraphs(array $graphs): self;

  /**
   * Gets all graphs.
   *
   * @return string[
   *   Associative array keyed by graph name, having the graph URIs as values.
   */
  public function getGraphs(): array;

  /**
   * Gets the URI value given a specific graph ID.
   *
   * @param string $graph
   *  The graph ID.
   *
   * @return string|null
   *   The graph URI or NULL if doesn't exist.
   */
  public function getGraphUri(string $graph): ?string;

  /**
   * Un-sets a list of graphs.
   *
   * @param string[] $graphs
   *   A list if graph IDs.
   *
   * @return $this
   */
  public function unsetGraphs(array $graphs): self;

  /**
   * Adds a list of base fields mappings.
   *
   * Mappings are added to the existing list. If a mapping with the same name
   * already exists will be overridden with the new passed value.
   *
   * @param array $mappings
   *   A structured associative array having the next structure:
   *   @code
   *   [
   *     'field1' => [
   *       'column1' => [
   *         'predicate' => 'http://example.com',
   *         'format' => 't_literal',
   *       ],
   *       'column2' => [
   *         'predicate' => 'http://example.com/other',
   *         'format' => 'xsd:dateTime',
   *       ],
   *     ],
   *     'field2' => [
   *       ...
   *     ],
   *   ]
   *   @endcode
   *   - The first level are field names,
   *   - The second level are field columns, such as 'value', 'target_id'.
   *   - The values are arrays with two keys: 'predicate', 'format'.
   *
   * @return $this
   */
  public function addMappings(array $mappings): self;

  /**
   * Sets the list of base fields mappings.
   *
   * Unlike ::addMappings(), this method replaces the whole list of existing
   * mappings.
   *
   * @param array $mappings
   *   A structured associative array with the same structure as the parameter
   *  from ::addMappings().
   *
   * @return $this
   *
   * @throws \InvalidArgumentException
   *   If the passed list of graphs doesn't contain the 'default' graph.
   *
   * @see self::addMappings()
   */
  public function setMappings(array $mappings): self;

  /**
   * Returns all base fields mappings.
   *
   * @return array
   */
  public function getMappings(): array;

  /**
   * Returns the mapping for a specific base field and column.
   *
   * @param string $field_name
   *   The field name.
   * @param string $column_name
   *   (optional) The column name. Defaults to 'value'.
   *
   * @return array|null
   *   Associative array with two keys: 'predicate' and 'format'.
   */
  public function getMapping(string $field_name, string $column_name = 'value'): ?array;

  /**
   * Un-sets the mappings for a given list of fields.
   *
   * @param string[] $field_names
   *   A list of field names.
   *
   * @return $this
   */
  public function unsetMappings(array $field_names): self;

  /**
   * Loads a rdf_entity_mapping entity given the entity type ID and the bundle.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $bundle
   *  The bundle.
   *
   * @return static|null
   *   The rdf_entity_mapping entity of NULL on failure.
   */
  public static function loadByName(string $entity_type_id, string $bundle): ?self;

}
