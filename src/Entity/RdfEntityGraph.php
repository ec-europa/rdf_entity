<?php

namespace Drupal\rdf_entity\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Annotation\ConfigEntityType;

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
 *   },
 * )
 */
class RdfEntityGraph extends ConfigEntityBase {

  /**
   * Default graph.
   *
   * @var string
   */
  const DEFAULT = 'default';

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

}
