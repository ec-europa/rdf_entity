<?php

declare(strict_types = 1);

namespace Drupal\rdf_entity;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\rdf_entity\Entity\Query\Sparql\SparqlArg;
use Drupal\rdf_entity\Entity\RdfEntityMapping;
use Drupal\rdf_entity\Event\InboundValueEvent;
use Drupal\rdf_entity\Event\OutboundValueEvent;
use Drupal\rdf_entity\Event\RdfEntityEvents;
use Drupal\rdf_entity\Exception\NonExistingFieldPropertyException;
use Drupal\rdf_entity\Exception\UnmappedFieldException;
use EasyRdf\Literal;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Contains helper methods that help with the uri mappings of Drupal elements.
 */
class RdfFieldHandler implements RdfFieldHandlerInterface {

  /**
   * A Drupal oriented property mapping array.
   *
   * A YAML representation of this array would look like:
   * @code
   * rdf_entity:
   *   bundle_key: rid
   *   bundles:
   *     catalog: http://www.w3.org/ns/dcat#Catalog
   *     other_bundle: ...
   *   fields:
   *     label:
   *       main_property: value
   *       columns:
   *         value:
   *           catalog:
   *             predicate: http://purl.org/dc/terms/title
   *             format: t_literal
   *             serialize: false
   *             data_type: string
   *           other_bundle:
   *             predicate: ...
   *             ...
   *         other_column:
   *           catalog:
   *             ...
   *     other_field: ...
   * other_entity_type:
   *   bundle_key: ...
   *   ...
   * @endcode
   *
   * @var array
   */
  protected $outboundMap;

  /**
   * A SPARQL oriented property mapping array.
   *
   * A YAML representation of this array would look like:
   * @code
   * rdf_entity:
   *   bundle_key: rid
   *   bundles:
   *     http://www.w3.org/ns/dcat#Catalog:
   *       - catalog
   *       - collection
   *     http://example.com:
   *       - other_bundle
   *   fields:
   *     http://purl.org/dc/terms/title:
   *       catalog:
   *         field_name: label
   *         column: value
   *         serialize: false
   *         type: string
   *         data_type: string
   *       other_field:
   *         field_name: ...
   *         ...
   *     http://example.com/field_mapping:
   *       ....
   * other_entity_type:
   *   bundle_key: ...
   *   ...
   * @endcode
   *
   * @var array
   */
  protected $inboundMap;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The event dispatcher service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Constructs a QueryFactory object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, EventDispatcherInterface $event_dispatcher) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * Prepares the property mappings for the given entity type ID.
   *
   * This is the central point where the field maps SPARQL-to-Drupal (inbound)
   * and Drupal-to-SPARQL (outbound) are build. The parsed results are
   * statically cached.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @throws \Exception
   *   Thrown when a bundle does not have the mapped bundle.
   */
  protected function buildEntityTypeProperties($entity_type_id) {
    if (empty($this->outboundMap[$entity_type_id]) && empty($this->inboundMap[$entity_type_id])) {
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);

      // @todo Support entity types without bundles or without bundle config
      // entities, in #18.
      // @see https://github.com/ec-europa/rdf_entity/issues/18
      $bundle_type = $entity_type->getBundleEntityType();

      $bundle_storage = $this->entityTypeManager->getStorage($bundle_type);
      $this->outboundMap[$entity_type_id] = $this->inboundMap[$entity_type_id] = [];
      $this->outboundMap[$entity_type_id]['bundle_key'] = $this->inboundMap[$entity_type_id]['bundle_key'] = $bundle_storage->getEntityType()->getKey('id');
      $bundle_entities = $this->entityTypeManager->getStorage($bundle_type)->loadMultiple();

      foreach ($bundle_entities as $bundle_id => $bundle_entity) {
        $field_definitions = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle_entity->id());
        $mapping = RdfEntityMapping::loadByName($entity_type_id, $bundle_entity->id());
        if (!$bundle_mapping = $mapping->getRdfType()) {
          throw new \Exception("The {$bundle_entity->label()} rdf entity does not have an rdf_type set.");
        }
        $this->outboundMap[$entity_type_id]['bundles'][$bundle_id] = $bundle_mapping;
        // More than one Drupal bundle can share the same mapped URI.
        $this->inboundMap[$entity_type_id]['bundles'][$bundle_mapping][] = $bundle_id;
        $base_fields_mapping = $mapping->getMappings();
        foreach ($field_definitions as $field_name => $field_definition) {
          $field_storage_definition = $field_definition->getFieldStorageDefinition();

          // @todo Unify field mappings in #16.
          // @see https://github.com/ec-europa/rdf_entity/issues/16
          if ($field_storage_definition instanceof BaseFieldDefinition) {
            $field_mapping = $base_fields_mapping[$field_name] ?? NULL;
          }
          else {
            $field_mapping = $field_storage_definition->getThirdPartySetting('rdf_entity', 'mapping');
          }

          // This field is not mapped.
          if (!$field_mapping) {
            continue;
          }

          $this->outboundMap[$entity_type_id]['fields'][$field_name]['main_property'] = $field_storage_definition->getMainPropertyName();;
          foreach ($field_mapping as $column_name => $column_mapping) {
            if (empty($column_mapping['predicate'])) {
              continue;
            }

            // Handle the serialized values.
            $serialize = FALSE;
            $field_storage_schema = $field_storage_definition->getSchema()['columns'];
            // Inflate value back into a normal item.
            if (!empty($field_storage_schema[$column_name]['serialize'])) {
              $serialize = TRUE;
            }

            // Retrieve the property definition primitive data type.
            $property_definition = $field_storage_definition->getPropertyDefinition($column_name);
            if (empty($property_definition)) {
              throw new NonExistingFieldPropertyException("Field '$field_name' of type '{$field_storage_definition->getType()}'' has no property '$column_name'.");
            }
            $data_type = $property_definition->getDataType();

            $this->outboundMap[$entity_type_id]['fields'][$field_name]['columns'][$column_name][$bundle_id] = [
              'predicate' => $column_mapping['predicate'],
              'format' => $column_mapping['format'],
              'serialize' => $serialize,
              'data_type' => $data_type,
            ];

            $this->inboundMap[$entity_type_id]['fields'][$column_mapping['predicate']][$bundle_id] = [
              'field_name' => $field_name,
              'column' => $column_name,
              'serialize' => $serialize,
              'type' => $field_storage_definition->getType(),
              'data_type' => $data_type,
            ];
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getInboundMap(string $entity_type_id): array {
    if (!isset($this->inboundMap[$entity_type_id])) {
      $this->buildEntityTypeProperties($entity_type_id);
    }
    return $this->inboundMap[$entity_type_id];
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldPredicates(string $entity_type_id, string $field_name, ?string $column_name = NULL, ?string $bundle = NULL): array {
    $drupal_to_sparql = $this->getOutboundMap($entity_type_id);
    if (!isset($drupal_to_sparql['fields'][$field_name])) {
      throw new UnmappedFieldException("You are requesting the mapping for a non mapped field: $field_name.");
    }
    $field_mapping = $drupal_to_sparql['fields'][$field_name];
    $column_name = $column_name ?: $field_mapping['main_property'];

    $bundles = $bundle ? [$bundle] : array_keys($drupal_to_sparql['bundles']);
    $return = [];
    foreach ($bundles as $bundle) {
      if (isset($field_mapping['columns'][$column_name][$bundle]['predicate'])) {
        $return[$bundle] = $field_mapping['columns'][$column_name][$bundle]['predicate'];
      }
    }
    return array_filter($return);
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldFormat(string $entity_type_id, string $field_name, ?string $column_name = NULL, ?string $bundle = NULL): array {
    $drupal_to_sparql = $this->getOutboundMap($entity_type_id);
    if (!isset($drupal_to_sparql['fields'][$field_name])) {
      throw new \Exception("You are requesting the mapping for a non mapped field: $field_name.");
    }
    $field_mapping = $drupal_to_sparql['fields'][$field_name];
    $column_name = $column_name ?: $field_mapping['main_property'];

    if (!empty($bundle)) {
      return [$field_mapping['columns'][$column_name][$bundle]['format']];
    }

    return array_values(array_column($field_mapping['columns'][$column_name], 'format'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldMainProperty(string $entity_type_id, string $field_name): string {
    $outbound_data = $this->getOutboundMap($entity_type_id);
    return $outbound_data['fields'][$field_name]['main_property'];
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyListToArray(string $entity_type_id): array {
    $inbound_map = $this->getInboundMap($entity_type_id);
    return array_unique(array_keys($inbound_map['fields']));
  }

  /**
   * {@inheritdoc}
   */
  public function hasFieldPredicate(string $entity_type_id, string $bundle, string $field_name, string $column_name): bool {
    $drupal_to_sparql = $this->getOutboundMap($entity_type_id);
    return isset($drupal_to_sparql['fields'][$field_name]['columns'][$column_name][$bundle]);
  }

  /**
   * {@inheritdoc}
   */
  public function bundlesToUris(string $entity_type_id, array $bundles, bool $to_resource_uris = FALSE): array {
    if (SparqlArg::isValidResources($bundles)) {
      return $bundles;
    }

    foreach ($bundles as $index => $bundle) {
      $value = $this->getOutboundBundleValue($entity_type_id, $bundle);
      if (empty($value)) {
        throw new \Exception("The $bundle bundle does not have a mapping.");
      }
      $bundles[$index] = $to_resource_uris ? SparqlArg::uri($value) : $value;
    }

    return $bundles;
  }

  /**
   * {@inheritdoc}
   */
  public function getOutboundValue(string $entity_type_id, string $field_name, $value, ?string $langcode = NULL, ?string $column_name = NULL, ?string $bundle = NULL) {
    $outbound_map = $this->getOutboundMap($entity_type_id);
    $format = $this->getFieldFormat($entity_type_id, $field_name, $column_name, $bundle);
    $format = reset($format);

    $field_mapping_info = $this->getFieldInfoFromOutboundMap($entity_type_id, $field_name, $column_name, $bundle);
    $field_mapping_info = reset($field_mapping_info);

    $event = new OutboundValueEvent($entity_type_id, $field_name, $value, $field_mapping_info, $langcode, $column_name, $bundle);
    $this->eventDispatcher->dispatch(RdfEntityEvents::OUTBOUND_VALUE, $event);
    $value = $event->getValue();

    $serialize = $this->isFieldSerializable($entity_type_id, $field_name, $column_name);
    if ($serialize) {
      $value = serialize($value);
    }

    if ($field_name == $outbound_map['bundle_key']) {
      $value = $this->getOutboundBundleValue($entity_type_id, $value);
    }

    switch ($format) {
      case static::RESOURCE:
        return [
          'type' => substr($value, 0, 2) == '_:' ? 'bnode' : 'uri',
          'value' => $value,
        ];

      case static::NON_TYPE:
        return new Literal($value);

      case static::TRANSLATABLE_LITERAL:
        return Literal::create($value, $langcode);

      default:
        return Literal::create($value, NULL, $format);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getInboundBundleValue(string $entity_type_id, string $bundle_uri): array {
    $inbound_map = $this->getInboundMap($entity_type_id);
    if (empty($inbound_map['bundles'][$bundle_uri])) {
      throw new \Exception("A bundle mapped to <$bundle_uri> was not found.");
    }

    return $inbound_map['bundles'][$bundle_uri];
  }

  /**
   * {@inheritdoc}
   */
  public function getInboundValue(string $entity_type_id, string $field_name, $value, ?string $langcode = NULL, ?string $column_name = NULL, ?string $bundle = NULL) {
    // The outbound map contains the same information as the inbound map: the
    // only difference is how the data is structured. It's safe to retrieve the
    // field information from the outbound map.
    // @see self::buildEntityTypeProperties()
    $field_mapping_info = $this->getFieldInfoFromOutboundMap($entity_type_id, $field_name, $column_name, $bundle);
    $field_mapping_info = reset($field_mapping_info);

    $event = new InboundValueEvent($entity_type_id, $field_name, $value, $field_mapping_info, $langcode, $column_name, $bundle);
    $this->eventDispatcher->dispatch(RdfEntityEvents::INBOUND_VALUE, $event);
    $value = $event->getValue();

    if ($this->isFieldSerializable($entity_type_id, $field_name, $column_name)) {
      $value = unserialize($value);
    }

    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSupportedDataTypes(): array {
    return [
      static::RESOURCE => t('Resource'),
      static::TRANSLATABLE_LITERAL => t('Translatable literal'),
      static::NON_TYPE => t('String (No type)'),
      'xsd:string' => t('Literal'),
      'xsd:boolean' => t('Boolean'),
      'xsd:date' => t('Date'),
      'xsd:dateTime' => t('Datetime'),
      'xsd:decimal' => t('Decimal'),
      'xsd:integer' => t('Integer'),
      'xsd:anyURI' => t('URI (xsd:anyURI)'),
    ];
  }

  /**
   * Returns the Drupal-to-SPARQL mapping array.
   *
   * @param string $entity_type_id
   *   The entity type id.
   *
   * @return array
   *   The drupal-to-sparql array.
   */
  protected function getOutboundMap(string $entity_type_id): array {
    if (!isset($this->outboundMap[$entity_type_id])) {
      $this->buildEntityTypeProperties($entity_type_id);
    }
    return $this->outboundMap[$entity_type_id];
  }

  /**
   * Returns whether the field is serializable.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $field_name
   *   The field name.
   * @param string|null $column_name
   *   (optional) The column name. If omitted, the main property will be used.
   *
   * @return bool
   *   Whether the field is serializable.
   *
   * @throws \Exception
   *   Thrown when a non existing field is requested.
   */
  protected function isFieldSerializable(string $entity_type_id, string $field_name, ?string $column_name = NULL): bool {
    $drupal_to_sparql = $this->getOutboundMap($entity_type_id);
    if (!isset($drupal_to_sparql['fields'][$field_name])) {
      throw new \Exception("You are requesting the mapping for a non mapped field: $field_name.");
    }
    $field_mapping = $drupal_to_sparql['fields'][$field_name];
    $column_name = $column_name ?: $field_mapping['main_property'];

    $serialize_array = array_column($field_mapping['columns'][$column_name], 'serialize');
    if (empty($serialize_array)) {
      return FALSE;
    }

    $serialize = reset($serialize_array);
    return $serialize;
  }

  /**
   * Returns the outbound bundle mapping.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $bundle
   *   The bundle ID.
   *
   * @return string
   *   The bundle mapping.
   *
   * @throws \Exception
   *    Thrown when the bundle is not found.
   */
  protected function getOutboundBundleValue(string $entity_type_id, string $bundle): string {
    $outbound_map = $this->getOutboundMap($entity_type_id);
    if (empty($outbound_map['bundles'][$bundle])) {
      throw new \Exception("The $bundle bundle does not have a mapped id.");
    }

    return $outbound_map['bundles'][$bundle];
  }

  /**
   * Retrieves information about the mapping of a certain field.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $field_name
   *   The field name.
   * @param string|null $column_name
   *   (optional) The column name. If omitted, the field main property is used.
   * @param string|null $bundle
   *   (optional) If passed, filter the final array by bundle.
   *
   * @return array
   *   An associative array with the information about the field mappings.
   *   When no bundle is specified, an array of arrays is returned, where the
   *   first level keys are all the bundles with that field.
   *
   * @throws \Exception
   *   Thrown when the field is not found.
   */
  protected function getFieldInfoFromOutboundMap(string $entity_type_id, string $field_name, ?string $column_name = NULL, ?string $bundle = NULL): array {
    $mapping = $this->getOutboundMap($entity_type_id);

    if (!isset($mapping['fields'][$field_name])) {
      throw new \Exception("You are requesting the mapping info for a non mapped field: $field_name.");
    }

    $field_mapping = $mapping['fields'][$field_name];
    $column_name = $column_name ?: $field_mapping['main_property'];

    if (!empty($bundle)) {
      return [$field_mapping['columns'][$column_name][$bundle]];
    }

    return array_values($field_mapping['columns'][$column_name]);
  }

}
