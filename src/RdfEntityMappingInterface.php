<?php

declare(strict_types = 1);

namespace Drupal\rdf_entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface for 'rdf_entity_mapping' entities.
 */
interface RdfEntityMappingInterface extends ConfigEntityInterface {

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
