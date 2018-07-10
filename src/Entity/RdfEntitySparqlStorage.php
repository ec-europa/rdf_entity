<?php

declare(strict_types = 1);

namespace Drupal\rdf_entity\Entity;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityStorageBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\rdf_entity\Database\Driver\sparql\Connection;
use Drupal\rdf_entity\Entity\Query\Sparql\SparqlArg;
use Drupal\rdf_entity\Exception\DuplicatedIdException;
use Drupal\rdf_entity\RdfEntityIdPluginManager;
use Drupal\rdf_entity\RdfEntitySparqlStorageInterface;
use Drupal\rdf_entity\RdfFieldHandlerInterface;
use Drupal\rdf_entity\RdfGraphHandlerInterface;
use EasyRdf\Graph;
use EasyRdf\Literal;
use EasyRdf\Sparql\Result;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a entity storage backend that uses a Sparql endpoint.
 */
class RdfEntitySparqlStorage extends ContentEntityStorageBase implements RdfEntitySparqlStorageInterface {

  /**
   * Sparql database connection.
   *
   * @var \Drupal\rdf_entity\Database\Driver\sparql\Connection
   */
  protected $sparql;

  /**
   * Language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The default bundle predicate.
   *
   * @var string[]
   */
  protected $bundlePredicate = ['http://www.w3.org/1999/02/22-rdf-syntax-ns#type'];

  /**
   * The rdf graph helper service object.
   *
   * @var \Drupal\rdf_entity\RdfGraphHandlerInterface
   */
  protected $graphHandler;

  /**
   * The rdf mapping helper service object.
   *
   * @var \Drupal\rdf_entity\RdfFieldHandlerInterface
   */
  protected $fieldHandler;

  /**
   * The RDF entity ID generator plugin manager.
   *
   * @var \Drupal\rdf_entity\RdfEntityIdPluginManager
   */
  protected $entityIdPluginManager;

  /**
   * Initialize the storage backend.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type this storage is about.
   * @param \Drupal\rdf_entity\Database\Driver\sparql\Connection $sparql
   *   The connection object.
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\rdf_entity\RdfGraphHandlerInterface $rdf_graph_handler
   *   The rdf graph helper service.
   * @param \Drupal\rdf_entity\RdfFieldHandlerInterface $rdf_field_handler
   *   The rdf mapping helper service.
   * @param \Drupal\rdf_entity\RdfEntityIdPluginManager $entity_id_plugin_manager
   *   The RDF entity ID generator plugin manager.
   */
  public function __construct(EntityTypeInterface $entity_type, Connection $sparql, EntityManagerInterface $entity_manager, EntityTypeManagerInterface $entity_type_manager, CacheBackendInterface $cache, LanguageManagerInterface $language_manager, ModuleHandlerInterface $module_handler, RdfGraphHandlerInterface $rdf_graph_handler, RdfFieldHandlerInterface $rdf_field_handler, RdfEntityIdPluginManager $entity_id_plugin_manager) {
    parent::__construct($entity_type, $entity_manager, $cache);
    $this->sparql = $sparql;
    $this->languageManager = $language_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler = $module_handler;
    $this->graphHandler = $rdf_graph_handler;
    $this->fieldHandler = $rdf_field_handler;
    $this->entityIdPluginManager = $entity_id_plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('sparql_endpoint'),
      $container->get('entity.manager'),
      $container->get('entity_type.manager'),
      $container->get('cache.entity'),
      $container->get('language_manager'),
      $container->get('module_handler'),
      $container->get('sparql.graph_handler'),
      $container->get('sparql.field_handler'),
      $container->get('plugin.manager.rdf_entity.id')
    );
  }

  /**
   * Builds a new graph (list of triples).
   *
   * @param string $graph_uri
   *   The URI of the graph.
   *
   * @return \EasyRdf\Graph
   *   The EasyRdf graph object.
   */
  protected static function getGraph($graph_uri) {
    $graph = new Graph($graph_uri);
    return $graph;
  }

  /**
   * {@inheritdoc}
   */
  public function getBundlePredicates(): array {
    return $this->bundlePredicate;
  }

  /**
   * {@inheritdoc}
   */
  public function getGraphHandler(): RdfGraphHandlerInterface {
    return $this->graphHandler;
  }

  /**
   * {@inheritdoc}
   */
  public function getGraphDefinitions(): array {
    return $this->getGraphHandler()->getGraphDefinitions($this->entityTypeId);
  }

  /**
   * {@inheritdoc}
   */
  protected function doLoadMultiple(array $ids = NULL, array $graph_ids = []) {
    // Attempt to load entities from the persistent cache. This will remove IDs
    // that were loaded from $ids.
    $entities_from_cache = $this->getFromPersistentCache($ids, $graph_ids);
    // Load any remaining entities from the database.
    $entities_from_storage = $this->getFromStorage($ids, $graph_ids);

    return $entities_from_cache + $entities_from_storage;
  }

  /**
   * Gets entities from the storage.
   *
   * @param array|null $ids
   *   If not empty, return entities that match these IDs. Return all entities
   *   when NULL.
   * @param array $graph_ids
   *   A list of graph IDs.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface[]
   *   Array of entities from the storage.
   *
   * @throws \Drupal\rdf_entity\Exception\SparqlQueryException
   *   If the SPARQL query fails.
   * @throws \Exception
   *   The query fails with no specific reason.
   */
  protected function getFromStorage(array $ids = NULL, array $graph_ids = []): array {
    if (empty($ids)) {
      return [];
    }
    $remaining_ids = $ids;
    $entities = [];
    while (count($remaining_ids)) {
      $operation_ids = array_slice($remaining_ids, 0, 50, TRUE);
      foreach ($operation_ids as $k => $v) {
        unset($remaining_ids[$k]);
      }
      $entities_values = $this->loadFromStorage($operation_ids, $graph_ids);
      if ($entities_values) {
        foreach ($entities_values as $id => $entity_values) {
          $bundle = $this->bundleKey ? $entity_values[$this->bundleKey][LanguageInterface::LANGCODE_DEFAULT] : FALSE;
          $langcode_key = $this->getEntityType()->getKey('langcode');
          $translations = [];
          if (!empty($entities_values[$id][$langcode_key])) {
            foreach ($entities_values[$id][$langcode_key] as $langcode => $data) {
              if (!empty(reset($data)['value'])) {
                $translations[] = reset($data)['value'];
              }
            }
          }
          $entity = new $this->entityClass($entity_values, $this->entityTypeId, $bundle, $translations);
          $this->trackOriginalGraph($entity);
          $entities[$id] = $entity;
        }
        $this->invokeStorageLoadHook($entities);
        $this->setPersistentCache($entities);
      }
    }
    return $entities;
  }

  /**
   * Retrieves the entity data from the SPARQL endpoint.
   *
   * @param string[] $ids
   *   A list of entity IDs.
   * @param string[]|null $graph_ids
   *   An ordered list of candidate graph IDs.
   *
   * @return array|null
   *   The entity values indexed by the field mapping ID or NULL in there are no
   *   results.
   *
   * @throws \Drupal\rdf_entity\Exception\SparqlQueryException
   *   If the SPARQL query fails.
   * @throws \Exception
   *   The query fails with no specific reason.
   */
  protected function loadFromStorage(array $ids, array $graph_ids): ?array {
    if (empty($ids)) {
      return [];
    }

    // @todo: We should filter per entity per graph and not load the whole
    // database only to filter later on.
    // @see https://github.com/ec-europa/rdf_entity/issues/19
    $ids_string = SparqlArg::serializeUris($ids, ' ');
    $graphs = $this->getGraphHandler()->getEntityTypeGraphUrisFlatList($this->getEntityTypeId());
    $named_graph = '';
    foreach ($graphs as $graph) {
      $named_graph .= 'FROM NAMED ' . SparqlArg::uri($graph) . "\n";
    }

    // @todo Get rid of the language filter. It's here because of eurovoc:
    // \Drupal\taxonomy\Form\OverviewTerms::buildForm loads full entities
    // of the whole tree: 7000+ terms in 24 languages is just too much.
    // @see https://github.com/ec-europa/rdf_entity/issues/19
    $query = <<<QUERY
SELECT ?graph ?entity_id ?predicate ?field_value
$named_graph
WHERE{
  GRAPH ?graph {
    ?entity_id ?predicate ?field_value .
    VALUES ?entity_id { $ids_string } .
  }
}
QUERY;

    $entity_values = $this->sparql->query($query);
    return $this->processGraphResults($entity_values, $graph_ids);
  }

  /**
   * Processes results from the load query and returns a list of values.
   *
   * When an entity is loaded, the values might derive from multiple graph. This
   * function will process the results and attempt to load a published version
   * of the entity. If there is no published version available, then it will
   * fallback to the rest of the graphs.
   *
   * If the graph parameter can be used to restrict the available graphs to load
   * from.
   *
   * @param \EasyRdf\Sparql\Result|\EasyRdf\Graph $results
   *   A set of query results indexed per graph and entity id.
   * @param string[] $graph_ids
   *   Graph IDs.
   *
   * @return array|null
   *   The entity values indexed by the field mapping ID or NULL in there are no
   *   results.
   *
   * @throws \Exception
   *    Thrown when the entity graph is empty.
   *
   * @see https://github.com/ec-europa/rdf_entity/issues/19
   *
   * @todo Reduce the cyclomatic complexity of this function in #19.
   */
  protected function processGraphResults($results, array $graph_ids): ?array {
    $values_per_entity = $this->deserializeGraphResults($results);
    if (empty($values_per_entity)) {
      return NULL;
    }

    $default_language = $this->languageManager->getDefaultLanguage()->getId();
    $inbound_map = $this->fieldHandler->getInboundMap($this->entityTypeId);
    $return = [];
    foreach ($values_per_entity as $entity_id => $values_per_graph) {
      $graph_uris = $this->getGraphHandler()->getEntityTypeGraphUris($this->getEntityTypeId());
      foreach ($graph_ids as $priority_graph_id) {
        foreach ($values_per_graph as $graph_uri => $entity_values) {
          // If the entity has been processed or the backend didn't returned
          // anything for this graph, jump to the next graph retrieved from the
          // SPARQL backend.
          if (isset($return[$entity_id]) || array_search($graph_uri, array_column($graph_uris, $priority_graph_id)) === FALSE) {
            continue;
          }

          $bundle = $this->getActiveBundle($entity_values);
          if (!$bundle) {
            continue;
          }

          // Check if the graph checked is in the request graphs. If there are
          // multiple graphs set, probably the default is requested with the
          // rest as fallback or it is a neutral call. If the default is
          // requested, it is going to be first in line so in any case, use the
          // first one.
          if (!$graph_id = $this->getGraphHandler()->getBundleGraphId($this->getEntityTypeId(), $bundle, $graph_uri)) {
            continue;
          }

          // Map bundle and entity id.
          $return[$entity_id][$this->bundleKey][LanguageInterface::LANGCODE_DEFAULT] = $bundle;
          $return[$entity_id][$this->idKey][LanguageInterface::LANGCODE_DEFAULT] = $entity_id;
          $return[$entity_id]['graph'][LanguageInterface::LANGCODE_DEFAULT] = $graph_id;

          $rdf_type = NULL;
          foreach ($entity_values as $predicate => $field) {
            $field_name = isset($inbound_map['fields'][$predicate][$bundle]['field_name']) ? $inbound_map['fields'][$predicate][$bundle]['field_name'] : NULL;
            if (empty($field_name)) {
              continue;
            }

            $column = $inbound_map['fields'][$predicate][$bundle]['column'];
            foreach ($field as $lang => $items) {
              $langcode_key = ($lang === $default_language) ? LanguageInterface::LANGCODE_DEFAULT : $lang;
              foreach ($items as $item) {
                $item = $this->fieldHandler->getInboundValue($this->getEntityTypeId(), $field_name, $item, $langcode_key, $column, $bundle);

                if (!isset($return[$entity_id][$field_name][$langcode_key]) || !is_string($return[$entity_id][$field_name][$langcode_key])) {
                  $return[$entity_id][$field_name][$langcode_key][][$column] = $item;
                }
              }
              if (is_array($return[$entity_id][$field_name][$langcode_key])) {
                $this->applyFieldDefaults($inbound_map['fields'][$predicate][$bundle]['type'], $return[$entity_id][$field_name][$langcode_key]);
              }
            }
          }
        }
      }
    }
    return $return;
  }

  /**
   * Deserializes a list of graph results to an array.
   *
   * The results array is an array of loaded entity values from different
   * graphs.
   * @code
   *    $results = [
   *      'http://entity_id.uri' => [
   *        'http://field.mapping.uri' => [
   *          'x-default' => [
   *            0 => 'actual value'
   *          ]
   *        ]
   *      ];
   * @code
   *
   * @param \EasyRdf\Sparql\Result|\EasyRdf\Result $results
   *   A set of query results indexed per graph and entity id.
   *
   * @return array
   *   The entity values indexed by the field mapping id.
   */
  protected function deserializeGraphResults(Result $results): array {
    $values_per_entity = [];
    foreach ($results as $result) {
      $entity_id = (string) $result->entity_id;
      $entity_graphs[$entity_id] = (string) $result->graph;

      $lang = LanguageInterface::LANGCODE_DEFAULT;
      if ($result->field_value instanceof Literal) {
        $lang_temp = $result->field_value->getLang();
        if ($lang_temp) {
          $lang = $lang_temp;
        }
      }
      $values_per_entity[$entity_id][(string) $result->graph][(string) $result->predicate][$lang][] = (string) $result->field_value;
    }

    return $values_per_entity;
  }

  /**
   * Derives the bundle from the rdf:type.
   *
   * @param array $entity_values
   *   Entity in a raw formatted array.
   *
   * @return string
   *   The bundle ID string.
   *
   * @throws \Exception
   *    Thrown when the bundle is not found.
   */
  protected function getActiveBundle(array $entity_values): ?string {
    $bundle_predicates = $this->bundlePredicate;
    $bundles = [];
    foreach ($bundle_predicates as $bundle_predicate) {
      if (isset($entity_values[$bundle_predicate])) {
        $bundle_data = $entity_values[$bundle_predicate];
        $bundles += $this->fieldHandler->getInboundBundleValue($this->entityTypeId, $bundle_data[LanguageInterface::LANGCODE_DEFAULT][0]);
      }
    }
    if (empty($bundles)) {
      return NULL;
    }

    // Since it is possible to map more than one bundles to the same uri, allow
    // modules to handle this.
    $this->moduleHandler->alter('rdf_load_bundle', $entity_values, $bundles);
    if (count($bundles) > 1) {
      throw new \Exception('More than one bundles are defined for this uri.');
    }
    return reset($bundles);
  }

  /**
   * {@inheritdoc}
   */
  public function load($id, array $graph_ids = NULL): ?ContentEntityInterface {
    $entities = $this->loadMultiple([$id], $graph_ids);
    return array_shift($entities);
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultiple(array $ids = NULL, array $graph_ids = NULL): array {
    $this->checkGraphs($graph_ids);

    // We copy this part from parent::loadMultiple(), otherwise we cannot pass
    // the $graph_ids to self::getFromStaticCache() and self::doLoadMultiple().
    // START parent::loadMultiple() fork.
    $entities = [];
    $passed_ids = !empty($ids) ? array_flip($ids) : FALSE;
    if ($this->entityType->isStaticallyCacheable() && $ids) {
      $entities += $this->getFromStaticCache($ids, $graph_ids);
      if ($passed_ids) {
        $ids = array_keys(array_diff_key($passed_ids, $entities));
      }
    }
    if ($ids === NULL || $ids) {
      $queried_entities = $this->doLoadMultiple($ids, $graph_ids);
    }
    if (!empty($queried_entities)) {
      $this->postLoad($queried_entities);
      $entities += $queried_entities;
    }
    if ($this->entityType->isStaticallyCacheable()) {
      if (!empty($queried_entities)) {
        $this->setStaticCache($queried_entities);
      }
    }
    if ($passed_ids) {
      $passed_ids = array_intersect_key($passed_ids, $entities);
      foreach ($entities as $entity) {
        $passed_ids[$entity->id()] = $entity;
      }
      $entities = $passed_ids;
    }
    // END parent::loadMultiple() fork.
    if (empty($entities)) {
      return [];
    }
    $uuid_key = $this->entityType->getKey('uuid');
    array_walk($entities, function (ContentEntityInterface $rdf_entity) use ($uuid_key) {
      // The ID of 'rdf_entity' is universally unique because it's a URI. As
      // the backend schema has no UUID, ID is reused as UUID.
      $rdf_entity->set($uuid_key, $rdf_entity->id());
    });

    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  protected function doPreSave(EntityInterface $entity) {
    // The code bellow is forked from EntityStorageBase::doPreSave() and
    // ContentEntityStorageBase::doPreSave(). We are not using the original
    // methods in order to be able to pass an additional list of graphs
    // parameter to ::loadUnchanged() method.
    // START forking from ContentEntityStorageBase::doPreSave().
    /** @var \Drupal\Core\Entity\ContentEntityBase $entity */
    $entity->updateOriginalValues();
    if ($entity->getEntityType()->isRevisionable() && !$entity->isNew() && empty($entity->getLoadedRevisionId())) {
      $entity->updateLoadedRevisionId();
    }

    // START forking from EntityStorageBase::doPreSave().
    $id = $entity->id();
    if ($entity->getOriginalId() !== NULL) {
      $id = $entity->getOriginalId();
    }
    $id_exists = $this->has($id, $entity);
    if ($id_exists && $entity->isNew()) {
      throw new EntityStorageException("'{$this->entityTypeId}' entity with ID '$id' already exists.");
    }
    if ($id_exists && !isset($entity->original)) {
      // In the case when the entity graph has been changed before saving, we
      // need the original graph, so that we load the original/unchanged entity
      // from the backend. This property was set in during entity load, in
      // rdf_entity_entity_storage_load(). We can rely on this property also
      // when the entity us saved via UI, as this value persists in entity over
      // an entity form submit, because the entity is stored in the form state.
      // @see rdf_entity_entity_storage_load()
      $entity->original = $this->loadUnchanged($id, [$entity->rdfEntityOriginalGraph]);
    }
    $entity->preSave($this);
    $this->invokeHook('presave', $entity);
    // END forking from EntityStorageBase::doPreSave().
    if (!$entity->isNew()) {
      if (empty($entity->original) || $entity->id() != $entity->original->id()) {
        throw new EntityStorageException("Update existing '{$this->entityTypeId}' entity while changing the ID is not supported.");
      }
      if (!$entity->isNewRevision() && $entity->getRevisionId() != $entity->getLoadedRevisionId()) {
        throw new EntityStorageException("Update existing '{$this->entityTypeId}' entity revision while changing the revision ID is not supported.");
      }
    }
    // END forking from ContentEntityStorageBase::doPreSave().
    // Finally reset the entity original graph property so that that its updated
    // value is available for the rest of this request.
    $this->trackOriginalGraph($entity);

    return $id;
  }

  /**
   * {@inheritdoc}
   */
  public function loadUnchanged($id, array $graph_ids = NULL): ?ContentEntityInterface {
    $this->checkGraphs($graph_ids);

    // START: Code forked from parent::loadUnchanged() and adapted to accept
    // graph andidates.
    $ids = [$id];
    parent::resetCache($ids);

    // START: Code adapted from EntityStorageBase::resetCache().
    // This part is replacing the ContentEntityStorageBase::resetCache() line.
    if ($this->entityType->isStaticallyCacheable()) {
      foreach ($graph_ids as $graph_id) {
        unset($this->entities[$id][$graph_id]);
      }
    }
    // END: Code adapted from EntityStorageBase::resetCache().
    $entities = $this->getFromPersistentCache($ids, $graph_ids);
    if (!$entities) {
      $entities[$id] = $this->load($id, $graph_ids);
    }
    else {
      $this->postLoad($entities);
      if ($this->entityType->isStaticallyCacheable()) {
        $this->setStaticCache($entities);
      }
    }

    return $entities[$id];
    // END: Code forked from parent::loadUnchanged().
  }

  /**
   * {@inheritdoc}
   */
  public function loadRevision($revision_id) {
    list($entity_id, $graph) = explode('||', $revision_id);

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteRevision($revision_id) {
  }

  /**
   * {@inheritdoc}
   */
  public function deleteFromGraph(array $entities, string $graph_id): void {
    if (!empty($entities)) {
      $ids = array_map(function (ContentEntityInterface $entity): string {
        return $entity->id();
      }, $entities);
      // Make sure that passed entities are keyed by entity ID and are loaded
      // only from the requested graph.
      $entities = $this->loadMultiple($ids, [$graph_id]);
      $this->doDelete($entities);
      $this->resetCache(array_keys($entities));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function hasGraph(EntityInterface $entity, string $graph_id): bool {
    $graph_uri = $this->getGraphHandler()->getBundleGraphUri($entity->getEntityTypeId(), $entity->bundle(), $graph_id);
    return $this->idExists($entity->id(), $graph_uri);
  }

  /**
   * {@inheritdoc}
   */
  public function loadByProperties(array $values = [], array $graph_ids = NULL): array {
    $this->checkGraphs($graph_ids);

    // If UUID is queried, just swap it with the ID. They are the same but UUID
    // is not stored, while on ID we can rely.
    $uuid_key = $this->entityType->getKey('uuid');
    if (isset($values[$uuid_key]) && !isset($values['id'])) {
      $values[$this->entityType->getKey('id')] = $values[$uuid_key];
      unset($values[$uuid_key]);
    }

    /** @var \Drupal\rdf_entity\Entity\Query\Sparql\SparqlQueryInterface $query */
    $query = $this->getQuery()
      ->graphs($graph_ids)
      ->accessCheck(FALSE);
    $this->buildPropertyQuery($query, $values);
    $result = $query->execute();

    return $result ? $this->loadMultiple($result, $graph_ids) : [];
  }

  /**
   * {@inheritdoc}
   */
  public function delete(array $entities) {
    if (!$entities) {
      // If no entities were passed, do nothing.
      return;
    }

    // Ensure that the entities are keyed by ID.
    $keyed_entities = [];
    foreach ($entities as $entity) {
      $keyed_entities[$entity->id()] = $entity;
    }

    // Allow code to run before deleting.
    $entity_class = $this->entityClass;
    $entity_class::preDelete($this, $keyed_entities);
    foreach ($keyed_entities as $entity) {
      $this->invokeHook('predelete', $entity);
    }
    $entities_by_graph = [];
    /** @var \Drupal\Core\Entity\EntityInterface $keyed_entity */
    foreach ($keyed_entities as $keyed_entity) {
      // Determine all possible graphs for the entity.
      $graphs = $this->getGraphHandler()->getEntityTypeGraphUris($this->getEntityTypeId());
      foreach ($graphs[$keyed_entity->bundle()] as $graph_name => $graph_uri) {
        $entities_by_graph[$graph_uri][$keyed_entity->id()] = $keyed_entity;
      }
    }
    /** @var string $id */
    foreach ($entities_by_graph as $graph => $entities_to_delete) {
      $this->doDeleteFromGraph($entities_to_delete, $graph);
    }
    $this->resetCache(array_keys($keyed_entities));

    // Allow code to run after deleting.
    $entity_class::postDelete($this, $keyed_entities);
    foreach ($keyed_entities as $entity) {
      $this->invokeHook('delete', $entity);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function doDelete($entities) {
    $entities_by_graph = [];
    /** @var string $id */
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    foreach ($entities as $id => $entity) {
      $graph_uri = $this->getGraphHandler()->getBundleGraphUri($entity->getEntityTypeId(), $entity->bundle(), $entity->graph->target_id);
      $entities_by_graph[$graph_uri][$id] = $entity;
    }
    foreach ($entities_by_graph as $graph_uri => $entities_to_delete) {
      $this->doDeleteFromGraph($entities, $graph_uri);
    }
  }

  /**
   * Constructs and execute the delete query.
   *
   * @param array $entities
   *   An array of entity objects to delete.
   * @param string $graph_uri
   *   The graph URI to delete from.
   *
   * @throws \Drupal\rdf_entity\Exception\SparqlQueryException
   *   If the SPARQL query fails.
   * @throws \Exception
   *   The query fails with no specific reason.
   */
  protected function doDeleteFromGraph(array $entities, string $graph_uri): void {
    $entity_list = SparqlArg::serializeUris(array_keys($entities));

    $query = <<<QUERY
DELETE FROM <$graph_uri>
{
  ?entity ?field ?value
}
WHERE
{
  ?entity ?field ?value
  FILTER(
    ?entity IN ($entity_list)
  )
}
QUERY;
    $this->sparql->query($query);
  }

  /**
   * {@inheritdoc}
   */
  protected function getQueryServiceName() {
    return 'entity.query.sparql';
  }

  /**
   * {@inheritdoc}
   */
  protected function doLoadRevisionFieldItems($revision_id) {
  }

  /**
   * {@inheritdoc}
   */
  protected function doSaveFieldItems(ContentEntityInterface $entity, array $names = []) {
  }

  /**
   * {@inheritdoc}
   */
  protected function doDeleteFieldItems($entities) {
  }

  /**
   * {@inheritdoc}
   */
  protected function doDeleteRevisionFieldItems(ContentEntityInterface $revision) {
  }

  /**
   * {@inheritdoc}
   */
  protected function readFieldItemsToPurge(FieldDefinitionInterface $field_definition, $batch_size) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected function purgeFieldItems(ContentEntityInterface $entity, FieldDefinitionInterface $field_definition) {
  }

  /**
   * {@inheritdoc}
   */
  protected function doSave($id, EntityInterface $entity) {
    $bundle = $entity->bundle();
    // Generate an ID before saving, if none is available. If the ID generation
    // occurs earlier in the process (like on EntityInterface::create()), the
    // entity might be considered not new by modules that don't strictly use the
    // EntityInterface::isNew() method.
    if (empty($id)) {
      $id = $this->entityIdPluginManager->getPlugin($entity)->generate();
      $entity->{$this->idKey} = $id;
    }
    elseif ($entity->isNew() && $this->idExists($id)) {
      throw new DuplicatedIdException("Attempting to create a new entity with the ID '$id' already taken.");
    }

    // If the graph is not specified, fallback to the default one for the entity
    // type.
    if ($entity->get('graph')->isEmpty()) {
      $entity->set('graph', $this->getGraphHandler()->getDefaultGraphId($this->getEntityTypeId()));
    }

    $graph_id = $entity->get('graph')->target_id;
    $graph_uri = $this->getGraphHandler()->getBundleGraphUri($entity->getEntityTypeId(), $entity->bundle(), $graph_id);
    $graph = self::getGraph($graph_uri);
    $lang_array = $this->toLangArray($entity);
    foreach ($lang_array as $field_name => $langcode_data) {
      foreach ($langcode_data as $langcode => $field_item) {
        foreach ($field_item as $delta => $column_data) {
          foreach ($column_data as $column => $value) {
            // Filter out empty values or non mapped fields. The id is also
            // excluded as it is not mapped.
            if ($value === NULL || $value === '' || !$this->fieldHandler->hasFieldPredicate($this->getEntityTypeId(), $bundle, $field_name, $column)) {
              continue;
            }
            $predicate = $this->fieldHandler->getFieldPredicates($this->getEntityTypeId(), $field_name, $column, $bundle);
            $predicate = reset($predicate);
            $value = $this->fieldHandler->getOutboundValue($this->getEntityTypeId(), $field_name, $value, $langcode, $column, $bundle);
            $graph->add((string) $id, $predicate, $value);
          }
        }
      }
    }

    // Give implementations a chance to alter the graph right before is saved.
    $this->alterGraph($graph, $entity);

    if (!$entity->isNew()) {
      $this->deleteBeforeInsert($id, $graph_uri);
    }
    try {
      $this->insert($graph, $graph_uri);
      return $entity->isNew() ? SAVED_NEW : SAVED_UPDATED;
    }
    catch (\Exception $e) {
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function doPostSave(EntityInterface $entity, $update) {
    parent::doPostSave($entity, $update);

    // After saving, this is now the "original entity", but subsequent saves
    // must be able to reference the original graph.
    // @see \Drupal\Core\Entity\EntityStorageBase::doPostSave()
    $this->trackOriginalGraph($entity);
  }

  /**
   * In this method the latest values have to be applied to the entity.
   *
   * The end array should have an index with the x-default language which should
   * be the default language to save and one index for each other translation.
   *
   * Since the user can be presented with non translatable fields in the
   * translation form, the process has to give priority to the values of the
   * current language over the default language.
   *
   * So, the process is:
   * - If the current language is the default one, add all fields to the
   *   x-default index.
   * - If the current language is not the default language, then the default
   * - language will only provide the translatable fields as default and the
   *   non-translatable will be filled by the current language.
   * - All the other languages, will only provide the translatable fields.
   *
   * Only t_literal fields should be translatable.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to convert to an array of values.
   *
   * @return array
   *   The array of values including the translations.
   */
  protected function toLangArray(ContentEntityInterface $entity): array {
    $values = [];
    $languages = array_keys(array_filter($entity->getTranslationLanguages(), function (LanguageInterface $language) {
      return !$language->isLocked();
    }));
    $translatable_fields = array_keys($entity->getTranslatableFields());
    $fields = array_keys($entity->getFields());
    $non_translatable_fields = array_diff($fields, $translatable_fields);

    $current_langcode = $entity->language()->getId();
    if ($entity->isDefaultTranslation()) {
      foreach ($entity->getFields(FALSE) as $name => $field_item_list) {
        if (!$field_item_list->isEmpty()) {
          $values[$name][$current_langcode] = $field_item_list->getValue();
        }
      }
      $processed = [$entity->language()->getId()];
    }
    else {
      // Fill in the translatable fields of the default language and then all
      // the fields from the current language.
      $default_translation = $entity->getUntranslated();
      $default_langcode = $default_translation->language()->getId();
      foreach ($translatable_fields as $name) {
        $values[$name][$default_langcode] = $default_translation->get($name)->getValue();
      }
      // For the current language, add the translatable fields as a translation
      // and the non translatable fields as default.
      foreach ($non_translatable_fields as $name) {
        $values[$name][$default_langcode] = $entity->get($name)->getValue();
      }
      // The current language is not included in the translations if it is a
      // new translation and is outdated if it is not a new translation.
      // Thus, the handling occurs here, instead of the generic handling below.
      foreach ($translatable_fields as $name) {
        $values[$name][$current_langcode] = $entity->get($name)->getValue();
      }

      $processed = [$current_langcode, $default_langcode];
    }

    // For the rest of the languages not computed above, simply add the
    // the translatable fields. This will prevent data loss from the database.
    foreach (array_diff($languages, $processed) as $langcode) {
      if (!$entity->hasTranslation($langcode)) {
        continue;
      }
      $translation = $entity->getTranslation($langcode);
      foreach ($translatable_fields as $name) {
        $item_list = $translation->get($name);
        if (!$item_list->isEmpty()) {
          $values[$name][$langcode] = $item_list->getValue();
        }
      }
    }
    return $values;
  }

  /**
   * Resolves the language based on entity and current site language.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $field_name
   *   The field name for which to resolve the language.
   * @param string $langcode
   *   A default langcode or the fields detected langcode.
   *
   * @return string|null
   *   A language code or NULL, if the field has no language.
   *
   * @throws \Exception
   *   Thrown when a non existing field is requested.
   */
  protected function resolveFieldLangcode($entity_type_id, $field_name, $langcode = NULL): ?string {
    $format = $this->fieldHandler->getFieldFormat($entity_type_id, $field_name);
    $non_languages = [
      LanguageInterface::LANGCODE_NOT_SPECIFIED,
      LanguageInterface::LANGCODE_DEFAULT,
      LanguageInterface::LANGCODE_NOT_APPLICABLE,
      LanguageInterface::LANGCODE_SITE_DEFAULT,
      LanguageInterface::LANGCODE_SYSTEM,
    ];

    if ($format == RdfFieldHandlerInterface::TRANSLATABLE_LITERAL && !empty($langcode) && !in_array($langcode, $non_languages)) {
      return $langcode;
    }

    $langcode = $this->languageManager->getCurrentLanguage()->getId();
    if (in_array($langcode, $non_languages)) {
      return NULL;
    }
    return $langcode;
  }

  /**
   * Alters the graph before saving the entity.
   *
   * Implementations are able to change, delete or add items to the graph before
   * this is saved to SPARQL backend.
   *
   * @param \EasyRdf\Graph $graph
   *   The graph to be altered.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being saved.
   */
  protected function alterGraph(Graph &$graph, EntityInterface $entity): void {}

  /**
   * Insert a graph of triples.
   *
   * @param \EasyRdf\Graph $graph
   *   The graph to insert.
   * @param string $graph_uri
   *   Graph to save to.
   *
   * @return \EasyRdf\Sparql\Result
   *   Response.
   *
   * @throws \Drupal\rdf_entity\Exception\SparqlQueryException
   *   If the SPARQL query fails.
   * @throws \Exception
   *   The query fails with no specific reason.
   */
  protected function insert(Graph $graph, string $graph_uri): Result {
    $graph_uri = SparqlArg::uri($graph_uri);
    $query = "INSERT DATA INTO $graph_uri {\n";
    $query .= $graph->serialise('ntriples') . "\n";
    $query .= '}';
    return $this->sparql->update($query);
  }

  /**
   * {@inheritdoc}
   */
  protected function has($id, EntityInterface $entity) {
    return !$entity->isNew();
  }

  /**
   * {@inheritdoc}
   */
  public function countFieldData($storage_definition, $as_bool = FALSE) {
    return $as_bool ? FALSE : 0;
  }

  /**
   * {@inheritdoc}
   */
  public function hasData() {
    return FALSE;
  }

  /**
   * Allow overrides for some field types.
   *
   * @param string $type
   *   The field type.
   * @param array $values
   *   The field values.
   *
   * @todo: To be removed when columns will be supported. No need to manually
   * set this.
   */
  protected function applyFieldDefaults($type, array &$values): void {
    if (empty($values)) {
      return;
    }
    foreach ($values as &$value) {
      // Textfield: provide default filter when filter not mapped.
      switch ($type) {
        case 'text_long':
          if (!isset($value['format'])) {
            $value['format'] = 'full_html';
          }
          break;

        // Strip timezone part in dates.
        // @todo Move in InboundOutboundValueSubscriber::massageInboundValue()
        case 'datetime':
          $time_stamp = (int) $value['value'];
          // $time_stamp = strtotime($value['value']);.
          $date = date('o-m-d', $time_stamp) . "T" . date('H:i:s', $time_stamp);
          $value['value'] = $date;
          break;
      }
    }
    $this->moduleHandler->alter('rdf_apply_default_fields', $type, $values);
  }

  /**
   * {@inheritdoc}
   */
  protected function getFromStaticCache(array $ids, array $graph_ids = []) {
    $entities = [];
    foreach ($ids as $id) {
      // If there are more than one graphs in the request, return only the first
      // one, if exists. If the first candidate doesn't exist in the static
      // cache, we don't pickup the following because the first might be
      // available later in the persistent cache or in the storage.
      if (isset($this->entities[$id][$graph_ids[0]])) {
        if (!isset($entities[$id])) {
          $entities[$id] = $this->entities[$id][$graph_ids[0]];
        }
      }
    }
    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  protected function setStaticCache(array $entities) {
    if ($this->entityType->isStaticallyCacheable()) {
      foreach ($entities as $id => $entity) {
        $this->entities[$id][$entity->graph->target_id] = $entity;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getFromPersistentCache(array &$ids = NULL, array $graph_ids = []) {
    if (!$this->entityType->isPersistentlyCacheable() || empty($ids)) {
      return [];
    }
    $entities = [];
    // Build the list of cache entries to retrieve.
    $cid_map = [];
    foreach ($ids as $id) {
      $graph_id = reset($graph_ids);
      $cid_map[$id] = "{$this->buildCacheId($id)}:{$graph_id}";
    }
    $cids = array_values($cid_map);
    if ($cache = $this->cacheBackend->getMultiple($cids)) {
      // Get the entities that were found in the cache.
      foreach ($ids as $index => $id) {
        $cid = $cid_map[$id];
        if (isset($cache[$cid]) && !isset($entities[$id])) {
          $entities[$id] = $cache[$cid]->data;
          unset($ids[$index]);
        }
      }
    }
    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  protected function setPersistentCache($entities) {
    if (!$this->entityType->isPersistentlyCacheable()) {
      return;
    }

    $cache_tags = [
      $this->entityTypeId . '_values',
      'entity_field_info',
    ];
    foreach ($entities as $id => $entity) {
      $cid = "{$this->buildCacheId($id)}:{$entity->graph->target_id}";
      $this->cacheBackend->set($cid, $entity, CacheBackendInterface::CACHE_PERMANENT, $cache_tags);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function resetCache(array $ids = NULL, array $graph_ids = NULL): void {
    if ($graph_ids && !$ids) {
      throw new \InvalidArgumentException('Passing a value in $graphs_ids works only when used with non-null $ids.');
    }

    $this->checkGraphs($graph_ids);

    if ($ids) {
      $cids = [];
      foreach ($ids as $id) {
        foreach ($graph_ids as $graph) {
          unset($this->entities[$id][$graph]);
          $cids[] = "{$this->buildCacheId($id)}:{$graph}";
        }
      }
      if ($this->entityType->isPersistentlyCacheable()) {
        $this->cacheBackend->deleteMultiple($cids);
      }
    }
    else {
      $this->entities = [];
      if ($this->entityType->isPersistentlyCacheable()) {
        Cache::invalidateTags([$this->entityTypeId . '_values']);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function buildCacheId($id) {
    return "values:{$this->entityTypeId}:$id";
  }

  /**
   * Delete an entity before it gets saved.
   *
   * The difference between deleteBeforeInsert and delete method is the
   * properties_list variable. Filtering the fields to be deleted using this
   * variable, ensures that additional data that might be imported through an
   * external repository are not lost during an entity update.
   *
   * @param string $id
   *   The entity uri.
   * @param string $graph_uri
   *   The graph uri.
   *
   * @throws \Drupal\rdf_entity\Exception\SparqlQueryException
   *   If the SPARQL query fails.
   * @throws \Exception
   *   The query fails with no specific reason.
   */
  protected function deleteBeforeInsert(string $id, string $graph_uri): void {
    $property_list = $this->fieldHandler->getPropertyListToArray($this->getEntityTypeId());
    $serialized = SparqlArg::serializeUris($property_list);
    $id = SparqlArg::uri($id);
    $graph_uri = SparqlArg::uri($graph_uri);
    $query = <<<QUERY
DELETE {
  GRAPH $graph_uri {
    $id ?field ?value
  }
}
WHERE {
  GRAPH $graph_uri {
    $id ?field ?value .
    FILTER (?field IN ($serialized))
  }
}
QUERY;
    $this->sparql->query($query);
  }

  /**
   * {@inheritdoc}
   */
  public function idExists(string $id, string $graph = NULL): bool {
    $id = SparqlArg::uri($id);
    $predicates = SparqlArg::serializeUris($this->bundlePredicate, ' ');
    if ($graph) {
      $graph = SparqlArg::uri($graph);
      $query = "ASK WHERE { GRAPH $graph { $id ?type ?o . VALUES ?type { $predicates } } }";
    }
    else {
      $query = "ASK { $id ?type ?value . VALUES ?type { $predicates } }";
    }

    return $this->sparql->query($query)->isTrue();
  }

  /**
   * Validates a list of graphs and provide defaults.
   *
   * @param string[]|null $graph_ids
   *   An ordered list of candidate graph IDs.
   *
   * @throws \InvalidArgumentException
   *   If at least one of passed graphs doesn't exist for this entity type.
   */
  protected function checkGraphs(array &$graph_ids = NULL): void {
    $entity_type_graph_ids = $this->getGraphHandler()->getEntityTypeGraphIds($this->getEntityTypeId());

    if (!$graph_ids) {
      // No passed graph means "all graphs for this entity type".
      $graph_ids = $entity_type_graph_ids;
      return;
    }

    // Validate each passed graph.
    array_walk($graph_ids, function (string $graph_id) use ($entity_type_graph_ids): void {
      if (!in_array($graph_id, $entity_type_graph_ids)) {
        throw new \InvalidArgumentException("Graph '$graph_id' doesn't exist for entity type '{$this->getEntityTypeId()}'.");
      }
    });
  }

  /**
   * Keep track of the originating graph of an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity object.
   */
  protected function trackOriginalGraph(EntityInterface $entity): void {
    // Store the graph ID of the loaded entity to be, eventually, used when this
    // entity gets saved. During the saving process, this value is passed to
    // RdfEntitySparqlStorage::loadUnchanged() to correctly determine the
    // original entity graph. This value persists in entity over an entity form
    // submit, as the entity is stored in the form state, so that the entity
    // save can rely on it.
    // @see \Drupal\rdf_entity\Entity\RdfEntitySparqlStorage::doPreSave()
    // @see \Drupal\Core\Entity\EntityForm
    $entity->rdfEntityOriginalGraph = $entity->get('graph')->target_id;
  }

}
