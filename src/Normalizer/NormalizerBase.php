<?php

namespace Drupal\rdf_entity\Normalizer;

use Drupal\rdf_entity\Encoder\RdfEncoder;
use Drupal\serialization\Normalizer\NormalizerBase as SerializationNormalizerBase;

/**
 * Base class for Normalizers.
 */
abstract class NormalizerBase extends SerializationNormalizerBase {

  /**
   * {@inheritdoc}
   */
  protected function checkFormat($format = NULL) {
    return !empty(RdfEncoder::getSupportedFormats()[$format]);
  }

}
