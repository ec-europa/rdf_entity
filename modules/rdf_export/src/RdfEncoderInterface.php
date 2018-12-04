<?php

declare(strict_types = 1);

namespace Drupal\rdf_export;

use Symfony\Component\Serializer\Encoder\EncoderInterface;

/**
 * Provides an interface to RDF encoders.
 */
interface RdfEncoderInterface extends EncoderInterface {

  /**
   * Builds a list of supported formats.
   *
   * @return \EasyRdf\Serialiser[]
   *   List of supported formats.
   */
  public static function getSupportedFormats(): array;

}
