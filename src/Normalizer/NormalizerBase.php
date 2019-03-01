<?php

declare(strict_types = 1);

namespace Drupal\rdf_entity\Normalizer;

use Drupal\rdf_entity\Encoder\SparqlEncoder;
use Drupal\serialization\Normalizer\NormalizerBase as SerializationNormalizerBase;

/**
 * Base class for Normalizers.
 */
abstract class NormalizerBase extends SerializationNormalizerBase {

  /**
   * {@inheritdoc}
   */
  protected function checkFormat($format = NULL): bool {
    return !empty(SparqlEncoder::getSupportedFormats()[$format]);
  }

}
