<?php

declare(strict_types = 1);

namespace Drupal\rdf_entity\Normalizer;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\rdf_entity\SparqlSerializer;
use Drupal\sparql_entity_storage\SparqlEntityStorageInterface;

/**
 * Converts the Drupal entity object structure to a HAL array structure.
 */
class SparqlEntityNormalizer extends NormalizerBase {

  /**
   * The serializer service.
   *
   * @var \Drupal\rdf_entity\SparqlSerializerInterface
   */
  protected $sparqlSerializer;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * RdfEntityNormalizer constructor.
   *
   * @param \Drupal\rdf_entity\SparqlSerializer $rdf_serializer
   *   RDF Serializer service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(SparqlSerializer $rdf_serializer, EntityTypeManagerInterface $entity_type_manager) {
    $this->sparqlSerializer = $rdf_serializer;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsNormalization($data, $format = NULL): bool {
    // Not an object or the format is not supported return now.
    if (!is_object($data) || !$this->checkFormat($format)) {
      return FALSE;
    }

    if ($data instanceof ContentEntityInterface) {
      $storage = $this->entityTypeManager->getStorage($data->getEntityTypeId());
      return $storage instanceof SparqlEntityStorageInterface;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($entity, $format = NULL, array $context = []): array {
    $format = $format ?: 'turtle';
    return ['_sparql_entity' => $this->sparqlSerializer->serializeEntity($entity, $format)];
  }

}
