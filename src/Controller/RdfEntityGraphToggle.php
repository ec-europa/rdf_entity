<?php

declare(strict_types = 1);

namespace Drupal\rdf_entity\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\rdf_entity\RdfEntityGraphInterface;

/**
 * Toggles an RDF entity graph to enabled or disabled.
 */
class RdfEntityGraphToggle extends ControllerBase {

  /**
   * Checks if the current user is able to toggle the RDF entity graph status.
   *
   * @param \Drupal\rdf_entity\RdfEntityGraphInterface $rdf_entity_graph
   *   The RDF graph entity.
   * @param string $toggle_operation
   *   The operation: 'enable', 'disable'.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result object.
   */
  public function access(RdfEntityGraphInterface $rdf_entity_graph, string $toggle_operation): AccessResultInterface {
    $forbidden =
      // The operation is 'enable' and the entity is already enabled.
      ($toggle_operation === 'enable' && $rdf_entity_graph->status()) ||
      // The operation is 'disable' and the entity is already disabled.
      ($toggle_operation === 'disable' && !$rdf_entity_graph->status()) ||
      // This is the 'default' RDF entity graph.
      ($rdf_entity_graph->id() === RdfEntityGraphInterface::DEFAULT);

    return $forbidden ? AccessResult::forbidden() : AccessResult::allowed();
  }

  /**
   * Toggles the RDF entity graph status.
   *
   * @param \Drupal\rdf_entity\RdfEntityGraphInterface $rdf_entity_graph
   *   The RDF graph entity.
   * @param string $toggle_operation
   *   The operation: 'enable', 'disable'.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   In case of failures on entity save.
   */
  public function toggle(RdfEntityGraphInterface $rdf_entity_graph, string $toggle_operation) {
    $arguments = [
      '%name' => $rdf_entity_graph->label(),
      '%id' => $rdf_entity_graph->id(),
    ];

    if ($toggle_operation === 'enable') {
      $rdf_entity_graph->enable()->save();
      $message = $this->t("The %name (%id) graph has been enabled.", $arguments);
    }
    else {
      $rdf_entity_graph->disable()->save();
      $message = $this->t("The %name (%id) graph has been disabled.", $arguments);
    }
    drupal_set_message($message);

    return $this->redirect('entity.rdf_entity_graph.collection');
  }

}
