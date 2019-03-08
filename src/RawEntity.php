<?php

namespace Drupal\rdf_entity;

use Drupal\Core\Language\LanguageInterface;
use EasyRdf\Literal;

/**
 * Class RawEntity
 *
 * A raw entity is the embryonic form of an entity.
 * It is not fully hydrated yet (not mapped to fields).
 */
class RawEntity implements \Iterator {

  /**
   * The subject of the entity structure.
   *
   * For revisions this will be the revision id, otherwise it is the entity id.
   * @var string
   */
  protected $subject;

  /**
   * The URI of the graph where the result was loaded from.
   * @var string
   */
  protected $graph;

  /**
   * The pseudo field structure, as returned by the storage.
   *
   * @var array
   */
  protected $objects;

  /**
   * RawEntity constructor.
   *
   * @param string $graph
   *   The URI of the graph where the entity was loaded from.
   * @param string $subject
   *   The URI of the entity subject. Can be either the id or rev id.
   */
  public function __construct($graph, $subject) {
    $this->graph = $graph;
    $this->subject = $subject;
  }

  /**
   * Gets the subject of the entity.
   *
   * @return string
   *   The subject URI.
   */
  public function getSubject() {
    return $this->subject;
  }

  /**
   * Gets the originating graph of the entity.
   * @return string
   *   The graph URI.
   */
  public function getGraphUri() {
    return $this->graph;
  }

  /**
   * Adds a predicate-object pair to the entity.
   *
   * @todo Add support for blank nodes.
   *
   * @param string $predicate
   *   The URI of the predicate.
   * @param $object
   *   A literal or resource.
   */
  public function add(string $predicate, $object) {
    $language = $this->getLanguage($object);
    $this->objects[$predicate][$language][] = (string) $object;
  }

  /**
   * Checks if an object with a given predicate exists in the set.
   *
   * @param $predicate
   *   The URI of the predicate.
   *
   * @return bool
   *   True if an object with said predicate exits in the set.
   */
  public function hasPredicate($predicate) {
    return isset($this->objects[$predicate]);
  }

  /**
   * Returns the language/object structure for given predicate.
   *
   * @param $predicate
   *   The URI of the predicate.
   *
   * @return mixed
   */
  public function getObjectDataByPredicate($predicate) {
    return $this->objects[$predicate];
  }

  /**
   * Determine the language of an object.
   *
   * @param \EasyRdf\Resource|\EasyRdf\Literal $object
   *   The object for which the language will be determined.
   *
   * @return string
   *   The language code of the object.
   */
  protected function getLanguage($object): string {
    $language = LanguageInterface::LANGCODE_DEFAULT;
    if ($object instanceof Literal) {
      $object_language = $object->getLang();
      if ($object_language) {
        $language = $object_language;
      }
    }
    return $language;
  }

  /**
   * {@inheritdoc}
   */
  public function rewind() {
    reset($this->objects);
  }

  /**
   * {@inheritdoc}
   */
  public function current() : array {
    return current($this->objects);
  }

  /**
   * {@inheritdoc}
   */
  public function key() :string {
    return key($this->objects);
  }

  /**
   * {@inheritdoc}
   */
  public function next() {
    next($this->objects);
  }

  /**
   * {@inheritdoc}
   */
  public function valid() {
    return key($this->objects) !== NULL;
  }

}
