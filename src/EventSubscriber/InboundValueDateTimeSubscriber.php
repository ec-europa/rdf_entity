<?php

namespace Drupal\rdf_entity\EventSubscriber;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\rdf_entity\Event\InboundValueEvent;
use Drupal\rdf_entity\Event\RdfEntityEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Massages inbound date/time values.
 */
class InboundValueDateTimeSubscriber implements EventSubscriberInterface {

  use DateTimeTrait;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      RdfEntityEvents::INBOUND_VALUE => 'massageInboundValue',
    ];
  }

  /**
   * Massages inbound values.
   *
   * Converts field properties with a "timestamp" data type that have been
   * mapped to date formats (xsd:date or xsd:dateTime).
   *
   * @param \Drupal\rdf_entity\Event\InboundValueEvent $event
   *   The inbound value event.
   */
  public function massageInboundValue(InboundValueEvent $event) {
    $mapping_info = $event->getFieldMappingInfo();

    if ($this->isTimestampAsDateField($mapping_info)) {
      // We cannot use DrupalDateTime::createFromFormat() as it relies on
      // \DateTime::createFromFormat(), which has issues with ISO8601 dates.
      // Instantiating a new object works instead.
      // @see https://bugs.php.net/bug.php?id=51950
      $value = new DrupalDateTime($event->getValue());
      $event->setValue($value->getTimestamp());
    }
  }

}
