<?php

declare(strict_types = 1);

namespace Drupal\rdf_entity\Entity;

use Drupal\rdf_entity\RdfEntityMappingInterface;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Annotation\ConfigEntityType;

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
    'default' => NULL,
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
  public static function loadByName(string $entity_type_id, string $bundle): ?RdfEntityMappingInterface {
    return static::load("$entity_type_id.$bundle");
  }

}
