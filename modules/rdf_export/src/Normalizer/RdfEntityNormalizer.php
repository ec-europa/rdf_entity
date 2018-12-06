<?php

namespace Drupal\rdf_export\Normalizer;

use Drupal\rdf_entity\RdfInterface;
use Drupal\rdf_export\RdfSerializer;
use Drupal\serialization\Normalizer\FieldableEntityNormalizerTrait;

/**
 * Converts the Drupal entity object structure to a HAL array structure.
 */
class RdfEntityNormalizer extends NormalizerBase {

  use FieldableEntityNormalizerTrait;

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = RdfInterface::class;

  /**
   * RdfEntityNormalizer constructor.
   *
   * @param \Drupal\rdf_export\RdfSerializer $rdf_serializer
   *   RDF Serializer service.
   */
  public function __construct(RdfSerializer $rdf_serializer) {
    $this->rdfSerializer = $rdf_serializer;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($entity, $format = NULL, array $context = []) {
    return ['_rdf_entity' => $this->rdfSerializer->serializeEntity($entity, $format)];
  }

}
