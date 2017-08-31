<?php

namespace Drupal\rdf_entity\EventSubscriber;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\rdf_entity\Event\OutboundValueEvent;
use Drupal\rdf_entity\Event\RdfEntityEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Massages inbound and outbound values.
 */
class InboundOutboundValueSubscriber implements EventSubscriberInterface {

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

    $formats = [
      'xsd:dateTime' => 'c',
      'xsd:date' => 'Y-m-d',
    ];

    if ($mapping_info['data_type'] !== 'timestamp' || !array_key_exists($mapping_info['format'], $formats)) {
      return;
    }

    $value = DrupalDateTime::createFromTimestamp($event->getValue());
    $event->setValue($value->format($formats[$mapping_info['format']]));
  }

}
