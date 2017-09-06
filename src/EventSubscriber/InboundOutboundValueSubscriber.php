<?php

namespace Drupal\rdf_entity\EventSubscriber;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\rdf_entity\Event\InboundValueEvent;
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
      RdfEntityEvents::INBOUND_VALUE => 'massageInboundValue',
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

  /**
   * Checks if a field is of "timestamp" data type but mapped as date xml type.
   *
   * @param array $mapping_info
   *   The field mapping info.
   *
   * @return bool
   *   True if the conditions applies, false otherwise.
   */
  protected function isTimestampAsDateField(array $mapping_info) {
    return $mapping_info['data_type'] === 'timestamp' && array_key_exists($mapping_info['format'], $this->getDateDataTypes());
  }

  /**
   * Returns the XML date data types and their format for the date() function.
   *
   * @return array
   *   The list of date data types.
   */
  protected function getDateDataTypes() {
    return [
      // \DateTime::ISO8601 is actually not compliant with ISO8601 at all.
      // @see http://php.net/manual/en/class.datetime.php#datetime.constants.iso8601
      'xsd:dateTime' => 'c',
      'xsd:date' => 'Y-m-d',
    ];
  }

}
