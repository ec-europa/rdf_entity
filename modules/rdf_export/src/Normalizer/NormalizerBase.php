<?php

namespace Drupal\rdf_export\Normalizer;

use Drupal\rdf_export\Encoder\RdfEncoder;
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
