<?php

declare(strict_types = 1);

namespace Drupal\rdf_entity\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
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
 *     "status" = "status",
 *     "weight" = "weight",
 *   },
 *   config_export = {
 *     "id",
 *     "weight",
 *     "name",
 *     "description",
 *     "entity_types",
 *   },
 *   handlers = {
 *     "access" = "Drupal\rdf_entity\RdfEntityGraphAccessControlHandler",
 *     "form" = {
 *       "add" = "Drupal\rdf_entity\Form\RdfEntityGraphForm",
 *       "edit" = "Drupal\rdf_entity\Form\RdfEntityGraphForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     },
 *     "list_builder" = "Drupal\rdf_entity\RdfEntityGraphListBuilder",
 *   },
 *   links = {
 *     "edit-form" = "/admin/config/rdf_entity/graph/manage/{rdf_entity_graph}",
 *     "delete-form" = "/admin/config/rdf_entity/graph/manage/{rdf_entity_graph}/delete",
 *     "collection" = "/admin/config/rdf_entity/graph",
 *     "enable" = "/admin/config/rdf_entity/graph/manage/{rdf_entity_graph}/enable",
 *     "disable" = "/admin/config/rdf_entity/graph/manage/{rdf_entity_graph}/disable",
 *   },
 *   admin_permission = "administer rdf entity",
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
   * The weight value is used to define the order in the list of graphs.
   *
   * @var int
   */
  protected $weight = 0;

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
  public function setWeight(int $weight): RdfEntityGraphInterface {
    $this->weight = $weight;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight(): int {
    return $this->weight;
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
  public function getDescription(): ?string {
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
    if ($this->entity_types === []) {
      // Normalize 'entity_types' empty array to NULL.
      $this->entity_types = NULL;
    }

    if ($this->id() === static::DEFAULT) {
      if (!$this->status()) {
        throw new \RuntimeException("The '" . static::DEFAULT . "' graph cannot be disabled.");
      }
      if ($this->getEntityTypeIds()) {
        throw new \RuntimeException("The '" . static::DEFAULT . "' graph cannot be limited to certain entity types.");
      }
    }
    parent::preSave($storage);
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);
    // Wipe out the static cache of the RDF entity graph handler.
    \Drupal::service('sparql.graph_handler')->clearCache();
  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities) {
    parent::postDelete($storage, $entities);
    \Drupal::service('sparql.graph_handler')->clearCache();
  }

}
