<?php

namespace Drupal\rdf_entity\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Annotation\ConfigEntityType;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\rdf_entity\RdfEntityGraphInterface;

/**
 * Defines the RDF entity graph config entity.
 *
 * Used to store basic information about each RDF entity graph.
 *
 * @ConfigEntityType(
 *   id = "rdf_entity_graph",
 *   label = @Translation("RDF Graph"),
 *   config_prefix = "graph",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "name",
 *     "status" = "status"
 *   },
 *   config_export = {
 *     "id",
 *     "name",
 *     "description",
 *     "entity_types",
 *   },
 * )
 */
class RdfEntityGraph extends ConfigEntityBase implements RdfEntityGraphInterface {

  /**
   * The unique ID of this RDF entity graph.
   *
   * @var string
   */
  protected $id;

  /**
   * The label of the RDF entity graph.
   *
   * @var string
   */
  protected $name;

  /**
   * The description of the RDF entity graph.
   *
   * @var string
   */
  protected $description;

  /**
   * Entity type IDs where this graph applies.
   *
   * NULL means it applies to all entity types.
   *
   * @var string[]|null
   */
  protected $entity_types = NULL;

  /**
   * {@inheritdoc}
   */
  public function setName(string $name): RdfEntityGraphInterface {
    $this->name = $name;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setDescription(string $description): RdfEntityGraphInterface {
    $this->description = $description;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return $this->description;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeIds(): ?array {
    return $this->entity_types ?: NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setEntityTypeIds(?array $entity_type_ids): RdfEntityGraphInterface {
    if (empty($entity_type_ids)) {
      $this->entity_types = NULL;
    }
    else {
      foreach ($entity_type_ids as $entity_type_id) {
        if (!$this->entityTypeManager()->getDefinition($entity_type_id, FALSE)) {
          throw new \InvalidArgumentException("Invalid entity type: '$entity_type_id'.");
        }
        $storage = $this->entityTypeManager()->getStorage($entity_type_id);
        if (!$storage instanceof RdfEntitySparqlStorage) {
          throw new \InvalidArgumentException("Entity type '$entity_type_id' not a RDF entity.");
        }
      }
      $this->entity_types = $entity_type_ids;
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function delete() {
    if ($this->id() === static::DEFAULT) {
      throw new \RuntimeException("The '" . static::DEFAULT . "' graph cannot be deleted.");
    }
    parent::delete();
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);
    if ($this->id() === static::DEFAULT) {
      if (!$this->status()) {
        throw new \RuntimeException("The '" . static::DEFAULT . "' graph cannot be disabled.");
      }
    }
  }

}
