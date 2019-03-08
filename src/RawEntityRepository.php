<?php

namespace Drupal\rdf_entity;

use EasyRdf\Sparql\Result;

/**
 * Class RawEntityRepository
 *
 * Manages access to a list of raw entities.
 */
class RawEntityRepository implements \Iterator {

  /**
   * The referenced raw entities, keyed by subject.
   *
   * @var array
   */
  protected $repoBySubject;

  /**
   * The referenced raw entities, in a flat list for iterating.
   *
   * @var array
   */
  protected $repoFlat;

  /**
   * The position of the iterator.
   *
   * @var int
   */
  protected $position;

  /**
   * Populates the repo with raw entities from SPARQL result.
   *
   * @param \EasyRdf\Sparql\Result $results
   *   The result of the SPARQL query.
   */
  public function createFromResult(Result $results) {
    foreach ($results as $result) {
      $graph = (string) $result->graph;
      $subject = (string) $result->entity_subject;
      $predicate = (string) $result->predicate;
      $object = $result->field_value;

      $this->addResult($graph, $subject, $predicate, $object);
    }
  }

  /**
   * Adds a value to the repo. Creates new EntityValue object if needed.
   *
   * @param string $graph
   *   The graph URI.
   * @param string $subject
   *   The subject URI.
   * @param string $predicate
   *   The predicate URI.
   * @param $object
   *   The object.
   */
  protected function addResult(string $graph, string $subject, string $predicate, $object) {
    $entity_values = $this->loadCreateRawEntity($graph, $subject);
    $entity_values->add($predicate, $object);
  }

  /**
   * Loads a raw entity from the repo if it exists, or creates one if not.
   *
   * @param $graph
   *   The graph URI.
   * @param $subject
   *   The subject URI.
   *
   * @return \Drupal\rdf_entity\RawEntity
   */
  protected function loadCreateRawEntity($graph, $subject) : RawEntity {
    if (isset($this->repoBySubject[$subject][$graph])) {
      return $this->repoBySubject[$subject][$graph];
    }
    return $this->createRawEntity($graph, $subject);
  }

  /**
   * Create a new raw entity, and add it to the repo.
   *
   * @param $graph
   *   The graph URI.
   * @param $subject
   *   The subject URI.
   *
   * @return \Drupal\rdf_entity\RawEntity
   *   The entity values object.
   */
  protected function createRawEntity($graph, $subject) : RawEntity {
    $sparql_result = new RawEntity($graph, $subject);

    $this->trackEntity($sparql_result);
    return $sparql_result;
  }

  /**
   * Associates a raw entity with the repo.
   *
   * @param \Drupal\rdf_entity\RawEntity $raw_entity
   *   The raw entity to keep track of.
   */
  public function trackEntity(RawEntity $raw_entity) {
    // Add the same object the lookup tables.
    $this->repoBySubject[$raw_entity->getSubject()][$raw_entity->getGraphUri()] = $raw_entity;
    $this->repoFlat[] = $raw_entity;
  }

  /**
   * Create new immutable entity repo, filtered by graph.
   *
   * @param $uris The graph uri to filter on.
   *
   * @return \Drupal\rdf_entity\RawEntityRepository
   */
  public function newRepoFromGraphUris($uris) {
    $filtered_entity_repo = new RawEntityRepository();
    foreach ($this as $raw_entity) {
      if (in_array($raw_entity->getGraphUri(), $uris)) {
        $filtered_entity_repo->trackEntity($raw_entity);
      }
    }
    return $filtered_entity_repo;
  }

  /**
   * Merges two entity repos into a new one.
   *
   * Only results with a subject that is not present in the set get merged in.
   *
   * @param \Drupal\rdf_entity\RawEntityRepository $repo_to_merge
   *
   * @return \Drupal\rdf_entity\RawEntityRepository
   */
  public function merge(RawEntityRepository $repo_to_merge) {
    $merged_repo = clone $this;
    foreach ($repo_to_merge as $entity_to_merge) {
      if (!$merged_repo->hasSubject($entity_to_merge->getSubject())) {
        $merged_repo->trackEntity($entity_to_merge);
      }
    }
    return $merged_repo;
  }

  /**
   * A raw entity with the given subject is present in the repo.
   *
   * @param $subject
   *   The URI of the subject.
   *
   * @return bool
   *   True if the raw entity was found.
   */
  public function hasSubject($subject) {
    return isset($this->repoBySubject[$subject]);
  }

  /**
   * {@inheritdoc}
   */
  public function rewind() {
    $this->position = 0;
  }

  /**
   * {@inheritdoc}
   */
  public function current() : RawEntity{
    return $this->repoFlat[$this->position];
  }

  /**
   * {@inheritdoc}
   */
  public function key() :string {
    return $this->position;
  }

  /**
   * {@inheritdoc}
   */
  public function next() {
    ++$this->position;
  }

  /**
   * {@inheritdoc}
   */
  public function valid() {
    return isset($this->repoFlat[$this->position]);
  }

}