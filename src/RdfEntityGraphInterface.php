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

}
