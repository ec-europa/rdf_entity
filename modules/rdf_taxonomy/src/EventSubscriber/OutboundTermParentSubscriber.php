<?php

namespace Drupal\rdf_taxonomy\EventSubscriber;

use Drupal\rdf_entity\Event\OutboundValueEvent;
use Drupal\rdf_entity\Event\RdfEntityEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Massages outbound date/time values.
 */
class OutboundTermParentSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      RdfEntityEvents::OUTBOUND_VALUE => 'fixParentTermId',
    ];
  }

  /**
   * Fixes the term parent ID.
   *
   * Drupal core uses taxonomy terms with numeric IDs. If case, we convert the
   * term ID, from a numeric type to string.
   *
   * @param \Drupal\rdf_entity\Event\OutboundValueEvent $event
   *   The outbound value event.
   */
  public function fixParentTermId(OutboundValueEvent $event) {
    if ($event->getEntityTypeId() === 'taxonomy_term' && $event->getField() === 'parent') {
      $event->setValue((string) $event->getValue());
    }
  }

}
