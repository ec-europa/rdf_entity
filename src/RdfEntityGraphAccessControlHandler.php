<?php

namespace Drupal\rdf_entity;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for RDF entity graph entities.
 */
class RdfEntityGraphAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $rdf_entity_graph, $operation, AccountInterface $account) {
    switch ($operation) {
      case 'view':
        return AccessResult::allowed();

      case 'delete':
        if ($rdf_entity_graph->id() === RdfEntityGraphInterface::DEFAULT) {
          return AccessResult::forbidden()->addCacheableDependency($rdf_entity_graph);
        }
        return parent::checkAccess($rdf_entity_graph, $operation, $account)->addCacheableDependency($rdf_entity_graph);

      default:
        return parent::checkAccess($rdf_entity_graph, $operation, $account);

    }
  }

}
