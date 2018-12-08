<?php

namespace Drupal\rdf_taxonomy\Entity;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\taxonomy\Entity\Term;

/**
 * Provides an alternative entity class for 'taxonomy_term'.
 */
class RdfTerm extends Term {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $base_fields = parent::baseFieldDefinitions($entity_type);
    // Support also Drupal 8.5.x.
    if (isset($base_fields['status'])) {
      $base_fields['status']->setCustomStorage(TRUE);
    }
    return $base_fields;
  }

  /**
   * {@inheritdoc}
   */
  public function isPublished() {
    // The RDF taxonomy term doesn't support the published flag.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function setPublished($published = NULL) {
    // The RDF taxonomy term doesn't support the published flag.
    return $this;
  }

}
