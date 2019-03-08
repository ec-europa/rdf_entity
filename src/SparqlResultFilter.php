<?php

namespace Drupal\rdf_entity;

class SparqlResultFilter {

  protected $graphHandler;

  public function __construct(RdfGraphHandlerInterface $graph_handler) {
    $this->graphHandler = $graph_handler;
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
    $graph_uris = $this->graphHandler->getEntityTypeGraphUris($entity_type_id);
    return array_column($graph_uris, $prio);
  }
}