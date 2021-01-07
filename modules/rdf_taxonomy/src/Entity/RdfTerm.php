<?php

namespace Drupal\rdf_taxonomy\Entity;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\taxonomy\Entity\Term;

/**
 * Defines the taxonomy term entity.
 *
 * @ContentEntityType(
 *   id = "rdf_taxonomy_term",
 *   label = @Translation("RDF Taxonomy term"),
 *   label_collection = @Translation("RDF Taxonomy terms"),
 *   label_singular = @Translation("RDF taxonomy term"),
 *   label_plural = @Translation("RDF taxonomy terms"),
 *   bundle_label = @Translation("Vocabulary"),
 *   handlers = {
 *     "storage" = "Drupal\rdf_taxonomy\TermRdfStorage",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\rdf_taxonomy\RdfTaxonomyTermListBuilder",
 *     "access" = "Drupal\taxonomy\TermAccessControlHandler",
 *     "form" = {
 *       "default" = "Drupal\taxonomy\TermForm",
 *       "delete" = "Drupal\taxonomy\Form\TermDeleteForm"
 *     },
 *     "translation" = "Drupal\taxonomy\TermTranslationHandler"
 *   },
 *   uri_callback = "taxonomy_term_uri",
 *   translatable = TRUE,
 *   entity_keys = {
 *     "id" = "tid",
 *     "bundle" = "vid",
 *     "label" = "name",
 *     "langcode" = "langcode",
 *     "uuid" = "uuid",
 *     "published" = "status",
 *   },
 *   bundle_entity_type = "rdf_taxonomy_vocabulary",
 *   field_ui_base_route = "entity.taxonomy_vocabulary.overview_form",
 *   common_reference_target = TRUE,
 *   links = {
 *     "canonical" = "/rdf-taxonomy/term/{rdf_taxonomy_term}",
 *     "delete-form" = "/rdf-taxonomy/term/{rdf_taxonomy_term}/delete",
 *     "edit-form" = "/rdf-taxonomy/term/{rdf_taxonomy_term}/edit",
 *     "create" = "/rdf-taxonomy/term",
 *   },
 *   permission_granularity = "bundle",
 *   constraints = {
 *     "TaxonomyHierarchy" = {}
 *   }
 * )
 */
class RdfTerm extends Term {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $base_fields = parent::baseFieldDefinitions($entity_type);

    /** @var \Drupal\Core\Field\BaseFieldDefinition $original_tid */
    $original_tid = $base_fields['tid'];

    // Change the tid type to string (RDF uri).
    $fields['tid'] = BaseFieldDefinition::create('string')
      ->setName('tid')
      ->setTargetEntityTypeId('rdf_taxonomy_term')
      ->setTargetBundle(NULL)
      ->setLabel($original_tid->getLabel())
      ->setDescription($original_tid->getDescription())
      ->setProvider('rdf_taxonomy')
      ->setReadOnly($original_tid->isReadOnly());

    $base_fields['status']->setCustomStorage(TRUE);

    $base_fields['graph'] = BaseFieldDefinition::create('entity_reference')
      ->setName('graph')
      ->setLabel(t('The graph where the entity is stored.'))
      ->setTargetEntityTypeId('rdf_taxonomy_term')
      ->setTargetBundle(NULL)
      ->setCustomStorage(TRUE)
      ->setSetting('target_type', 'rdf_entity_graph');

    $base_fields['vid'] = BaseFieldDefinition::create('entity_reference')
      ->setName('vid')
      ->setLabel(t('RDF vocabulary'))
      ->setDescription(t('The vocabulary to which the term is assigned.'))
      ->setTargetEntityTypeId('rdf_taxonomy_term')
      ->setTargetBundle(NULL)
      ->setCustomStorage(TRUE)
      ->setSetting('target_type', 'rdf_taxonomy_vocabulary');

    return $base_fields;
  }

  /**
   * {@inheritdoc}
   */
  public function isPublished() {
    // The RDF taxonomy term doesn't support the published flag.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function setPublished($published = NULL) {
    // The RDF taxonomy term doesn't support the published flag.
    return $this;
  }

}
