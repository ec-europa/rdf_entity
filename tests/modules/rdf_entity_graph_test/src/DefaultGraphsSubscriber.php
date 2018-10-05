<?php

namespace Drupal\rdf_entity_graph_test;

use Drupal\rdf_entity\Event\DefaultGraphsEvent;
use Drupal\rdf_entity\Event\RdfEntityEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Alter the list of default graphs.
 */
class DefaultGraphsSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      RdfEntityEvents::DEFAULT_GRAPHS => 'limitGraphs',
    ];
  }

  /**
   * Reacts to default graph list building event.
   *
   * @param \Drupal\rdf_entity\Event\DefaultGraphsEvent $event
   *   The event.
   */
  public function limitGraphs(DefaultGraphsEvent $event) {
    $graphs = $event->getDefaultGraphIds();
    if (($index = array_search('non_default_graph', $graphs)) !== FALSE) {
      unset($graphs[$index]);
      $event->setDefaultGraphIds($graphs);
    }
  }

}
