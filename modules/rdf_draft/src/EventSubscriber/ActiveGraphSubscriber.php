<?php

namespace Drupal\rdf_draft\EventSubscriber;

use Drupal\rdf_entity\ActiveGraphEvent;
use Drupal\rdf_entity\Event\RdfEntityEvents;
use Drupal\rdf_entity\RdfGraphHandlerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Load the draft entity on the edit form and on the draft tab.
 */
class ActiveGraphSubscriber implements EventSubscriberInterface {

  /**
   * The RDF graph handler service.
   *
   * @var \Drupal\rdf_entity\RdfGraphHandlerInterface
   */
  protected $rdfGraphHandler;

  /**
   * Constructs a new event subscriber object.
   *
   * @param \Drupal\rdf_entity\RdfGraphHandlerInterface $rdf_graph_handler
   *   The RDF graph handler service.
   */
  public function __construct(RdfGraphHandlerInterface $rdf_graph_handler) {
    $this->rdfGraphHandler = $rdf_graph_handler;
  }

  /**
   * Set the appropriate graph as an active graph for the entity.
   *
   * Currently, the following cases exist:
   *  - In the canonical view of the entity, load the entity from all graphs. If
   *  a published one exists, then it uses the default behaviour. If a published
   *  one does not exist, then returns the draft version and continues with
   *  proper access check.
   *  - In the edit view, the draft version has priority over the published. If
   *  a draft version exists, then this is the one edited. If a draft version
   *  does not exist, then the published one is cloned into the draft graph.
   *  - The delete view is the same as the canonical view. The published one has
   *  priority over the draft version.
   *  - In any other case, like a 'view draft' tab view, the corresponding graph
   *  is loaded with no fallbacks.
   *
   * @param \Drupal\rdf_entity\ActiveGraphEvent $event
   *   The event object to process.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Thrown when the access is denied and redirects to user login page.
   */
  public function graphForEntityConvert(ActiveGraphEvent $event) {
    $defaults = $event->getRouteDefaults();
    if ($defaults['_route']) {
      $entity_type_id = $event->getEntityTypeId();
      /** @var \Drupal\rdf_entity\RdfEntitySparqlStorageInterface $storage */
      $storage = \Drupal::entityTypeManager()->getStorage($entity_type_id);
      $route_parts = explode('.', $defaults['_route']);
      // On the edit form, load from draft graph if possible.
      if (array_search('edit_form', $route_parts)) {
        $entity = $storage->load($event->getEntityId(), ['draft', 'default']);
        // If the entity is empty, it means the user tried to access the edit
        // route of a non existing entity. In that case, simply return and let
        // the rdf entity try to load the entity from all graphs.
        if (empty($entity)) {
          return;
        }
        // When drafting is enabled for this entity type, try to load the draft
        // version on the edit form.
        if ($this->rdfGraphHandler->bundleHasGraph($entity_type_id, $entity->bundle(), 'draft')) {
          $event->setGraphs(['draft', 'default']);
        }
        else {
          $event->setGraphs(['default']);
        }
      }
      // Viewing the entity on a graph specific tab.
      elseif (isset($route_parts[2]) && (strpos($route_parts[2], 'rdf_draft_') === 0)) {
        // Retrieve the graph name from the route.
        $graph_id = str_replace('rdf_draft_', '', $route_parts[2]);
        $event->setGraphs([$graph_id]);
      }
      // On the canonical route, the default entity is preferred.
      elseif (isset($route_parts[2]) && $route_parts[2] === 'canonical') {
        $event->setGraphs(['default', 'draft']);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      RdfEntityEvents::GRAPH_ENTITY_CONVERT => ['graphForEntityConvert'],
    ];
  }

}
