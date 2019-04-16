<?php

declare(strict_types = 1);

namespace Drupal\rdf_entity\Entity\Query\Sparql;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryBase;
use Drupal\Core\Entity\Query\Sql\ConditionAggregate;
use Drupal\rdf_entity\Database\Driver\sparql\ConnectionInterface;
use Drupal\rdf_entity\RdfEntitySparqlStorageInterface;
use Drupal\rdf_entity\RdfFieldHandlerInterface;
use Drupal\rdf_entity\RdfGraphHandlerInterface;

/**
 * The base entity query class for RDF entities.
 */
class Query extends QueryBase implements SparqlQueryInterface {

  /**
   * The connection object.
   *
   * @var \Drupal\rdf_entity\Database\Driver\sparql\ConnectionInterface
   */
  protected $connection;

  /**
   * The string query.
   *
   * @var string
   */
  public $query = '';

  /**
   * Indicates if preExecute() has already been called.
   *
   * @var bool
   */
  protected $prepared = FALSE;

  /**
   * The graph IDs from where the query is going load entities from.
   *
   * If the value is NULL, the query will load entities from all graphs.
   *
   * @var string[]|null
   */
  protected $graphIds;

  /**
   * An array that is meant to hold the results.
   *
   * @var array
   */
  protected $results = NULL;

  /**
   * The SPARQL entity storage.
   *
   * @var \Drupal\rdf_entity\RdfEntitySparqlStorageInterface
   */
  protected $entityStorage;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The rdf graph handler service object.
   *
   * @var \Drupal\rdf_entity\RdfGraphHandlerInterface
   */
  protected $graphHandler;

  /**
   * The rdf mapping handler service object.
   *
   * @var \Drupal\rdf_entity\RdfFieldHandlerInterface
   */
  protected $fieldHandler;

  /**
   * Constructs a query object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param string $conjunction
   *   - AND: all of the conditions on the query need to match.
   *   - OR: at least one of the conditions on the query need to match.
   * @param \Drupal\rdf_entity\Database\Driver\sparql\ConnectionInterface $connection
   *   The database connection to run the query against.
   * @param array $namespaces
   *   List of potential namespaces of the classes belonging to this query.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service object.
   * @param \Drupal\rdf_entity\RdfGraphHandlerInterface $rdf_graph_handler
   *   The rdf graph handler service.
   * @param \Drupal\rdf_entity\RdfFieldHandlerInterface $rdf_field_handler
   *   The rdf mapping handler service.
   */
  public function __construct(EntityTypeInterface $entity_type, $conjunction, ConnectionInterface $connection, array $namespaces, EntityTypeManagerInterface $entity_type_manager, RdfGraphHandlerInterface $rdf_graph_handler, RdfFieldHandlerInterface $rdf_field_handler) {
    // Assign the handlers before calling the parent so that they can be passed
    // to the condition class properly.
    $this->graphHandler = $rdf_graph_handler;
    $this->fieldHandler = $rdf_field_handler;
    $this->entityTypeManager = $entity_type_manager;
    $this->connection = $connection;
    parent::__construct($entity_type, $conjunction, $namespaces);

    // Set a unique tag for the rdf_entity queries.
    $this->addTag('rdf_entity');
    $this->addMetaData('entity_type', $this->entityType);
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityType(): EntityTypeInterface {
    return $this->entityType;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityStorage(): RdfEntitySparqlStorageInterface {
    if (!isset($this->entityStorage)) {
      $this->entityStorage = $this->entityTypeManager->getStorage($this->getEntityTypeId());
    }
    return $this->entityStorage;
  }

  /**
   * {@inheritdoc}
   */
  public function count($field = TRUE) {
    $this->count = $field;
    return $this;
  }

  /**
   * Implements \Drupal\Core\Entity\Query\QueryInterface::execute().
   */
  public function execute() {
    return $this
      ->prepare()
      ->compile()
      ->addSort()
      ->addPager()
      ->run()
      ->result();
  }

  /**
   * {@inheritdoc}
   */
  public function graphs(array $graph_ids = NULL): SparqlQueryInterface {
    $this->graphIds = $graph_ids;
    return $this;
  }

  /**
   * Initialize the query.
   *
   * @return $this
   */
  protected function prepare() {
    // Running as count query?
    if ($this->count) {
      if (is_string($this->count)) {
        $this->query = 'SELECT count(' . $this->count . ') AS ?count ';
      }
      else {
        $this->query = 'SELECT count(?entity) AS ?count ';
      }
    }
    else {
      $this->query = 'SELECT DISTINCT(?entity) ';
    }
    $this->query .= "\n";

    if (!$this->graphIds) {
      // Allow all default graphs for this entity type.
      $this->graphIds = $this->graphHandler->getEntityTypeDefaultGraphIds($this->getEntityTypeId());
    }
    $graph_uris = $this->graphHandler->getEntityTypeGraphUrisFlatList($this->getEntityTypeId(), $this->graphIds);
    foreach ($graph_uris as $graph_uri) {
      $this->query .= "FROM <$graph_uri>\n";
    }

    return $this;
  }

  /**
   * Add the registered conditions to the WHERE clause.
   *
   * @return $this
   */
  protected function compile() {
    // Modules may alter all queries or only those having a particular tag.
    if (isset($this->alterTags)) {
      // Remap the entity reference default tag to the rdf_entity reference
      // because the first one requires that the query is an instance of the
      // SelectInterface.
      // @todo: Maybe overwrite the default selection class?
      if (isset($this->alterTags['entity_reference'])) {
        $this->alterTags['rdf_entity_reference'] = $this->alterTags['entity_reference'];
        unset($this->alterTags['entity_reference']);
      }
      $hooks = ['query'];
      foreach ($this->alterTags as $tag => $value) {
        $hooks[] = 'query_' . $tag;
      }
      \Drupal::moduleHandler()->alter($hooks, $this);
    }

    $this->condition->compile($this);
    $this->query .= "WHERE {\n" . $this->condition->toString() . "\n}";
    return $this;
  }

  /**
   * Adds the sort to the build query.
   *
   * @return \Drupal\rdf_entity\Entity\Query\Sparql\Query
   *   Returns the called object.
   */
  protected function addSort() {
    if ($this->count) {
      $this->sort = [];
    }
    // Simple sorting. For the POC, only uri's and bundles are supported.
    // @todo Implement sorting on bundle fields?
    if ($this->sort) {
      // @todo Support multiple sort conditions.
      $sort = array_pop($this->sort);
      // @todo Can we use the field mapper here as well?
      // Consider looping over the sort criteria in both the compile step and
      // here: We can add ?entity <pred> ?sort_1 in the condition, and
      // ORDER BY ASC ?sort_1 here (I think).
      switch ($sort['field']) {
        case 'id':
          $this->query .= 'ORDER BY ' . $sort['direction'] . ' (?entity)';
          break;

        case 'rid':
          $this->query .= 'ORDER BY ' . $sort['direction'] . ' (?bundle)';
          break;
      }
    }
    return $this;
  }

  /**
   * Add pager to query.
   */
  protected function addPager() {
    $this->initializePager();
    if (!$this->count && $this->range) {
      $this->query .= 'LIMIT ' . $this->range['length'] . "\n";
      $this->query .= 'OFFSET ' . $this->range['start'] . "\n";
    }
    return $this;
  }

  /**
   * Commit the query to the backend.
   */
  protected function run() {
    /** @var \EasyRdf_Http_Response $results */
    $this->results = $this->connection->query($this->query);
    return $this;
  }

  /**
   * Do the actual query building.
   */
  protected function result() {
    // Count query.
    if ($this->count) {
      foreach ($this->results as $result) {
        return (string) $result->count;
      }
    }
    $uris = [];

    // SELECT query.
    foreach ($this->results as $result) {
      // If the query does not return any results, EasyRdf_Sparql_Result still
      // contains an empty result object. If this is the case, skip it.
      if (!empty((array) $result)) {
        $uri = (string) $result->entity;
        $uris[$uri] = $uri;
      }
    }
    return $uris;
  }

  /**
   * Returns the array of conditions.
   *
   * @return array
   *   The array of conditions.
   */
  public function &conditions() {
    return $this->condition->conditions();
  }

  /**
   * {@inheritdoc}
   */
  public function existsAggregate($field, $function, $langcode = NULL) {
    return $this->conditionAggregate->exists($field, $function, $langcode);
  }

  /**
   * {@inheritdoc}
   */
  public function notExistsAggregate($field, $function, $langcode = NULL) {
    return $this->conditionAggregate->notExists($field, $function, $langcode);
  }

  /**
   * {@inheritdoc}
   */
  public function conditionAggregateGroupFactory($conjunction = 'AND') {
    return new ConditionAggregate($conjunction, $this);
  }

  /**
   * {@inheritdoc}
   */
  protected function conditionGroupFactory($conjunction = 'AND') {
    $class = static::getClass($this->namespaces, 'SparqlCondition');
    return new $class($conjunction, $this, $this->namespaces, $this->graphHandler, $this->fieldHandler);
  }

  /**
   * Return the query string for debugging help.
   *
   * @return string
   *   Query.
   */
  public function __toString() {
    return $this->query;
  }

}
