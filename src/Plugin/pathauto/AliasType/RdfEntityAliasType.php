<?php

declare(strict_types = 1);

namespace Drupal\rdf_entity\Plugin\pathauto\AliasType;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\pathauto\Plugin\pathauto\AliasType\EntityAliasTypeBase;
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

    switch ($action) {
      case 'create':
        // Get a list of all source paths that start with '/rdf_entity/'.
        $source_paths = $this->getSourcePaths($context['sandbox']);
        // Convert the source paths to entity IDs.
        $aliased_rdf_entity_ids = $this->convertPathsToEntityIds($source_paths);
        // Get a list of 25 RDF entity IDS that are not in the list of URL
        // aliases.
        $query = $this->getRdfEntityQuery();
        if (!empty($aliased_rdf_entity_ids)) {
          $query->condition('id', $aliased_rdf_entity_ids, 'NOT IN');
        }
        break;
      case 'update':
        // Get a list of 25 RDF entity IDs from the `url_alias` table with a
        // `source` field that starts with `rdf_entity/`.
        $query = $this->database->select('url_alias', 'ua');
        break;
      case 'all':
        $rdf_entity_ids = $this->getRdfEntityIds($context['sandbox']);
        $query = $this->database->select('url_alias', 'ua');
        // Nothing to do. We want all paths.
        break;
      default:
        // Unknown action. Abort!
        return;
    }

    $query->addTag('rdf_entity_pathauto_bulk_update');

    // Get the total amount of items to process.
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
    // $ids = $query->execute()->fetchCol();
    $ids = $query->execute();

    $updates = $this->bulkUpdate($ids);
    $context['sandbox']['count'] += count($ids);
    // $context['sandbox']['current'] = max($ids);
    $context['results']['updates'] += $updates;
    $context['message'] = $this->t('Updated alias for Rdf entity @id.', array('@id' => end($ids)));

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
  protected function getRdfEntityIds(array $sandbox) : array {
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
  protected function getRdfEntityQuery() {
    /** @var \Drupal\rdf_entity\Entity\RdfEntitySparqlStorage $storage */
    $storage = $this->entityTypeManager->getStorage('rdf_entity');
    /** @var \Drupal\rdf_entity\Entity\Query\Sparql\Query $query */
    $query = $storage->getQuery();
    // @todo Is it OK to only return the published entities? Unpublished
    //   entities don't need to get URL aliased, but what happens if an
    //   unpublished entity gets published? Does it get an alias at that point?
    $query->setGraphType($storage->getGraphHandler()->getEntityTypeEnabledGraphs());

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
  protected function getSourcePaths(array $sandbox) : array {
    if (empty($sandbox['source_paths'])) {
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
