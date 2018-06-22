<?php

namespace Drupal\rdf_entity\EventSubscriber;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\rdf_entity\Event\OutboundValueEvent;
use Drupal\rdf_entity\Event\RdfEntityEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Massages outbound date/time values.
 */
class OutboundValueDateTimeSubscriber implements EventSubscriberInterface {

  use DateTimeTrait;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      RdfEntityEvents::OUTBOUND_VALUE => 'massageOutboundValue',
    ];
  }

  /**
   * Massages outbound values.
   *
   * Converts field properties with a "timestamp" data type that have been
   * mapped to date formats (xsd:date or xsd:dateTime).
   *
   * @param \Drupal\rdf_entity\Event\OutboundValueEvent $event
   *   The outbound value event.
   */
  public function massageOutboundValue(OutboundValueEvent $event) {
    $mapping_info = $event->getFieldMappingInfo();

    if ($this->isTimestampAsDateField($mapping_info)) {
      $value = DrupalDateTime::createFromTimestamp($event->getValue());
      $event->setValue($value->format($this->getDateDataTypes()[$mapping_info['format']]));
    }
  }

}
