<?php

namespace Drupal\rdf_entity;

use Drupal\Core\Entity\EntityTypeManagerInterface;

class SparqlResultFilter {

  protected $entityTypeManager;

  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  public function filter(RawEntityRepository $entity_repo, $graph_priorities, $entity_type_id) : RawEntityRepository {
    $filtered_set = new RawEntityRepository();

    foreach ($graph_priorities as $graph_priority) {
      $uris = $this->graphUriFromPrio($graph_priority, $entity_type_id);
      $priority_resultset = $entity_repo->newRepoFromGraphUris($uris);
      $filtered_set = $filtered_set->merge($priority_resultset);
    }
    return $filtered_set;
  }

  protected function graphUriFromPrio(string $prio, $entity_type_id) {
    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    if (!$storage instanceof RdfEntitySparqlStorageInterface) {
      throw new \Exception('Storage must implement RDF Storage interface.');
    }
    $graph_uris = $storage->getGraphHandler()->getEntityTypeGraphUris($entity_type_id);
    return array_column($graph_uris, $prio);
  }
}