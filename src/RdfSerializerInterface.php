<?php

declare(strict_types = 1);

namespace Drupal\rdf_entity;

/**
 * Interface for classes that serialize RDF entities.
 */
interface RdfSerializerInterface {

  /**
   * Exports a single entity to a serialised string.
   *
   * @param \Drupal\rdf_entity\RdfInterface $entity
   *   The entity to export.
   * @param string $format
   *   The serialisation format. Defaults to turtle.
   *
   * @return string
   *   The serialised entity as a string.
   */
  public function serializeEntity(RdfInterface $entity, string $format = 'turtle'): string;

}
