<?php

declare(strict_types = 1);

namespace Drupal\rdf_entity\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Annotation\ConfigEntityType;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\rdf_entity\RdfEntityGraphInterface;
use Drupal\rdf_entity\RdfEntityMappingInterface;

/**
 * Defines the RDF entity mapping config entity.
 *
 * Used to store mapping between the Drupal bundle settings, including base
 * field definitions, and the RDF backend properties.
 *
 * @ConfigEntityType(
 *   id = "rdf_entity_mapping",
 *   label = @Translation("RDF Mapping"),
 *   config_prefix = "mapping",
 *   entity_keys = {
 *     "id" = "id",
 *     "status" = "status"
 *   },
 *   config_export = {
 *     "id",
 *     "entity_type_id",
 *     "bundle",
 *     "rdf_type",
 *     "graph",
 *     "base_fields_mapping",
 *     "entity_id_plugin",
 *   },
 * )
 */
class RdfEntityMapping extends ConfigEntityBase implements RdfEntityMappingInterface {

  /**
   * The unique ID of this RDF entity mapping.
   *
   * @var string
   */
  protected $id;

  /**
   * The entity type referred by this mapping.
   *
   * @var string
   */
  protected $entity_type_id;

  /**
   * The bundle referred by this mapping.
   *
   * @var string
   */
  protected $bundle;

  /**
   * The RDF type mapping.
   *
   * @var string
   */
  protected $rdf_type;

  /**
   * The mapping of a graph definition to a graph URI.
   *
   * @var array
   */
  protected $graph = [
    RdfEntityGraphInterface::DEFAULT => NULL,
  ];

  /**
   * The base fields mapping.
   *
   * @var array
   */
  protected $base_fields_mapping;

  /**
   * The plugin that generates the entity ID.
   *
   * @var string
   */
  protected $entity_id_plugin;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $values, $entity_type) {
    if (empty($values['entity_type_id'])) {
      throw new \InvalidArgumentException('Missing required property: entity_type_id.');
    }

    // Valid entity type?
    if (!$storage = $this->entityTypeManager()->getStorage($values['entity_type_id'])) {
      throw new \InvalidArgumentException("Invalid entity type: {$values['entity_type_id']}.");
    }

    if ($storage->getEntityType()->hasKey('bundle')) {
      // If this entity type requires a bundle, the bundle should be passed.
      if (empty($values['bundle'])) {
        throw new \InvalidArgumentException('Missing required property: bundle.');
      }
    }
    else {
      // The bundle is the entiy type ID, regardless of the passed value.
      $values['bundle'] = $values['entity_type_id'];
    }

    // Only entities with RDF storage are eligible.
    if (!$storage instanceof RdfEntitySparqlStorage) {
      throw new \InvalidArgumentException("Cannot handle non-RDF entity type: {$values['entity_type_id']}.");
    }

    parent::__construct($values, $entity_type);
  }

  /**
   * {@inheritdoc}
   */
  public function id() {
    return "{$this->entity_type_id}.{$this->bundle}";
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetEntityTypeId(): string {
    return $this->entity_type_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetEntityType(): ?ContentEntityTypeInterface {
    return $this->entityTypeManager()->getDefinition($this->entity_type_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetBundle(): string {
    return $this->bundle;
  }

  /**
   * {@inheritdoc}
   */
  public function setRdfType(string $rdf_type): RdfEntityMappingInterface {
    $this->rdf_type = $rdf_type;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getRdfType(): ?string {
    return $this->rdf_type;
  }

  /**
   * {@inheritdoc}
   */
  public function setEntityIdPlugin(string $entity_id_plugin): RdfEntityMappingInterface {
    $this->entity_id_plugin = $entity_id_plugin;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityIdPlugin(): ?string {
    return $this->entity_id_plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function addGraphs(array $graphs): RdfEntityMappingInterface {
    $this->graph = $graphs + $this->graph;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setGraphs(array $graphs): RdfEntityMappingInterface {
    if (!isset($graphs[RdfEntityGraphInterface::DEFAULT])) {
      throw new \InvalidArgumentException("Passed graphs should include the '" . RdfEntityGraphInterface::DEFAULT . "' graph.");
    }
    $this->graph = $graphs;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getGraphs(): array {
    return $this->graph;
  }

  /**
   * {@inheritdoc}
   */
  public function getGraphUri(string $graph): ?string {
    return $this->graph[$graph] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function unsetGraphs(array $graphs): RdfEntityMappingInterface {
    $this->graph = array_diff_key($this->graph, array_flip($graphs));
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addMappings(array $mappings): RdfEntityMappingInterface {
    $this->base_fields_mapping = $mappings + $this->base_fields_mapping;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setMappings(array $mappings): RdfEntityMappingInterface {
    $this->base_fields_mapping = $mappings;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getMappings(): array {
    return $this->base_fields_mapping;
  }

  /**
   * {@inheritdoc}
   */
  public function getMapping(string $field_name, string $column_name = 'value'): ?array {
    return $this->base_fields_mapping[$field_name][$column_name] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function unsetMappings(array $field_names): RdfEntityMappingInterface {
    $this->base_fields_mapping = array_diff_key($this->base_fields_mapping, array_flip($field_names));
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function loadByName(string $entity_type_id, string $bundle): ?RdfEntityMappingInterface {
    return static::load("$entity_type_id.$bundle");
  }

}
