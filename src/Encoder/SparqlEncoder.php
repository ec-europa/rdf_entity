<?php

declare(strict_types = 1);

namespace Drupal\rdf_entity\Encoder;

use Drupal\rdf_entity\SparqlEncoderInterface;
use EasyRdf\Format;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;

/**
 * Adds RDF encoder support for the Serialization API.
 */
class SparqlEncoder implements SparqlEncoderInterface {

  /**
   * Memory cache for supported formats.
   *
   * @var \EasyRdf\Serialiser[]
   */
  protected static $supportedFormats;

  /**
   * {@inheritdoc}
   */
  public function supportsEncoding($format): bool {
    return !empty(static::getSupportedFormats()[$format]);
  }

  /**
   * {@inheritdoc}
   */
  public function encode($data, $format, array $context = []): string {
    if (!isset($data['_sparql_entity'])) {
      throw new UnexpectedValueException("Data to be encoded is missing the '_sparql_entity' key.");
    }
    return $data['_sparql_entity'];
  }

  /**
   * {@inheritdoc}
   */
  public static function getSupportedFormats(): array {
    if (!isset(static::$supportedFormats)) {
      $container_registered_formats = \Drupal::getContainer()->getParameter('sparql_entity.encoders');
      $rdf_serializers = Format::getFormats();
      static::$supportedFormats = array_intersect_key($rdf_serializers, $container_registered_formats);
    }
    return static::$supportedFormats;
  }

}
