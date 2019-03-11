<?php

namespace Drupal\rdf_entity;

use Drupal\Core\Entity\EntityTypeManagerInterface;

class LayeredGraphPriorityFilter {

  protected $entityTypeManager;

  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Created a filtered entity repository.
   *
   * The new will contain one entry for each subject. If an entity is
   * present in multiple graphs, the entity from the graph with the highest
   * priority will be selected.
   *
   * @param \Drupal\rdf_entity\RawEntityRepository $entity_repository
   * @param $graph_priorities
   * @param $entity_type_id
   *
   * @return \Drupal\rdf_entity\RawEntityRepository
   * @throws \Exception
   */
  public function filter(RawEntityRepository $entity_repository, $graph_priorities, $entity_type_id) : RawEntityRepository {
    $filtered_set = new RawEntityRepository();

    foreach ($graph_priorities as $graph_priority) {
      $uris = $this->graphUrisFromPriority($graph_priority, $entity_type_id);
      $priority_result_set = $entity_repository->newRepoFromGraphUris($uris);
      $filtered_set = $filtered_set->merge($priority_result_set);
    }
    return $filtered_set;
  }


  protected function graphUrisFromPriority(string $priority, $entity_type_id) {
    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    if (!$storage instanceof RdfEntitySparqlStorageInterface) {
      throw new \Exception('Storage must implement RDF Storage interface.');
    }
    $graph_uris = $storage->getGraphHandler()->getEntityTypeGraphUris($entity_type_id);
    return array_column($graph_uris, $priority);
  }
}