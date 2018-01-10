<?php

declare(strict_types = 1);

namespace Drupal\rdf_entity\Plugin\pathauto\AliasType;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\pathauto\Plugin\pathauto\AliasType\EntityAliasTypeBase;
use Drupal\rdf_entity\Entity\Query\Sparql\Query;
use Drupal\rdf_entity\UriEncoder;

/**
 * A pathauto alias type plugin for RDF entities.
 *
 * @AliasType(
 *   id = "rdf_entity",
 *   label = @Translation("Rdf entity"),
 *   types = {"rdf_entity"},
 *   provider = "rdf_entity",
 *   context = {
 *     "rdf_entity" = @ContextDefinition("entity:rdf_entity")
 *   }
 * )
 */
class RdfEntityAliasType extends EntityAliasTypeBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  protected function getEntityTypeId() {
    return 'rdf_entity';
  }

  /**
   * {@inheritdoc}
   */
  public function getSourcePrefix() {
    return '/rdf_entity/';
  }

  /**
   * {@inheritdoc}
   */
  public function batchUpdate($action, &$context) {
    if (!isset($context['sandbox']['count'])) {
      $context['sandbox']['count'] = 0;
    }

    $query = $this->getRdfEntityQuery();
    $query->addTag('rdf_entity_pathauto_bulk_update');

    switch ($action) {
      case 'create':
        // Process RDF entities that are not in the list of URL aliases.
        $aliased_rdf_entity_ids = $this->getAliasedEntityIds($context['sandbox']);
        if (!empty($aliased_rdf_entity_ids)) {
          $query->condition('id', $aliased_rdf_entity_ids, 'NOT IN');
        }
        break;

      case 'update':
        // Process RDF entities that are in the list of URL aliases.
        $aliased_rdf_entity_ids = $this->getAliasedEntityIds($context['sandbox']);
        if (!empty($aliased_rdf_entity_ids)) {
          $query->condition('id', $aliased_rdf_entity_ids, 'IN');
        }
        break;

      case 'all':
        // Nothing to filter. We want all entities.
        break;

      default:
        // Unknown action. Abort!
        return;
    }

    // Keep track of the total amount of items to process.
    if (!isset($context['sandbox']['total'])) {
      $count_query = clone $query;
      $context['sandbox']['total'] = $count_query->count()->execute();

      // If there are no entities to update, then stop immediately.
      if (!$context['sandbox']['total']) {
        $context['finished'] = 1;
        return;
      }
    }

    $query->range($context['sandbox']['count'], 25);
    $ids = $query->execute();

    $updates = $this->bulkUpdate($ids);
    $context['sandbox']['count'] += count($ids);
    $context['results']['updates'] += $updates;
    $context['message'] = $this->t('Updated alias for Rdf entity @id.', ['@id' => end($ids)]);

    if ($context['sandbox']['count'] != $context['sandbox']['total']) {
      $context['finished'] = $context['sandbox']['count'] / $context['sandbox']['total'];
    }
  }

  /**
   * Returns the full list of RDF entity IDs.
   *
   * These are persisted in the batch operation sandbox.
   *
   * @param array $sandbox
   *   The batch operation sandbox.
   *
   * @return array
   *   An array of RDF entity IDs.
   */
  protected function getRdfEntityIds(array &$sandbox) : array {
    if (empty($sandbox['rdf_entity_ids'])) {
      $sandbox['rdf_entity_ids'] = $this->getRdfEntityQuery()->execute();
    }

    return $sandbox['rdf_entity_ids'];
  }

  /**
   * Returns a Query object for RDF entities.
   *
   * @return \Drupal\rdf_entity\Entity\Query\Sparql\Query
   *   The entity query.
   */
  protected function getRdfEntityQuery() : Query {
    /** @var \Drupal\rdf_entity\Entity\RdfEntitySparqlStorage $storage */
    $storage = $this->entityTypeManager->getStorage('rdf_entity');
    /** @var \Drupal\rdf_entity\Entity\Query\Sparql\Query $query */
    $query = $storage->getQuery();
    $query->setGraphType($storage->getGraphHandler()->getEntityTypeGraphIds('rdf_entity'));

    return $query;
  }

  /**
   * Returns the url alias source paths that correspond with RDF entities.
   *
   * This data will be persisted in the batch operation sandbox.
   *
   * @param array $sandbox
   *   The batch operation sandbox.
   *
   * @return array
   *   An associative array of source paths, keyed by url alias ID.
   */
  protected function getSourcePaths(array &$sandbox) : array {
    if (!isset($sandbox['source_paths'])) {
      $query = $this->database->select('url_alias', 'ua');
      $query->fields('ua', ['pid', 'source']);
      $query->condition('source', '/rdf_entity/%', 'LIKE');
      $query->orderBy('ua.pid');
      $source_paths = $query->execute()->fetchAllKeyed();

      // Filter out any source paths that point to subpaths of RDF entities.
      $sandbox['source_paths'] = preg_grep('|^/rdf_entity/.+/.+$|', $source_paths, PREG_GREP_INVERT);
    }

    return $sandbox['source_paths'];
  }

  /**
   * Converts the url alias source paths to RDF entity IDs.
   *
   * This will strip off the leading '/rdf_entity/' component, and decode the
   * ID.
   *
   * @param array $source_paths
   *   The source paths.
   *
   * @return array
   *   The converted RDF entity IDs.
   */
  protected function convertPathsToEntityIds(array $source_paths) : array {
    return array_map(function ($source_path) {
      // Strip off the leading '/rdf_entity/' from the path and decode it.
      return UriEncoder::decodeUrl(substr($source_path, 12));
    }, $source_paths);
  }

  /**
   * Returns a list of RDF entity IDs that have a URL alias.
   *
   * This result will be persisted in the batch operation sandbox.
   *
   * @param array $sandbox
   *   The batch operation sandbox.
   *
   * @return array
   *   An array of RDF entity IDs.
   */
  protected function getAliasedEntityIds(array &$sandbox) : array {
    // Get a list of all source paths that start with '/rdf_entity/' from the
    // URL alias table.
    $source_paths = $this->getSourcePaths($sandbox);

    // Convert the source paths to entity IDs.
    return $this->convertPathsToEntityIds($source_paths);
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeId() {
    // Pretend we're an alias type provided by the entity alias type deriver.
    // For the moment the support for tokens and bundles in patterns is
    // hardcoded to only work with derived alias types.
    // @see \Drupal\pathauto\Plugin\Deriver\EntityAliasTypeDeriver
    return $this->getEntityTypeId();
  }

}
