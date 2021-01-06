<?php

namespace Drupal\rdf_taxonomy\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\VocabularyInterface;

/**
 * Defines the taxonomy vocabulary entity.
 *
 * @ConfigEntityType(
 *   id = "rdf_taxonomy_vocabulary",
 *   label = @Translation("RDF taxonomy vocabulary"),
 *   label_singular = @Translation("RDF vocabulary"),
 *   label_plural = @Translation("RDF vocabularies"),
 *   label_collection = @Translation("RDF Taxonomy"),
 *   handlers = {
 *     "storage" = "Drupal\taxonomy\VocabularyStorage",
 *     "list_builder" = "Drupal\taxonomy\VocabularyListBuilder",
 *     "access" = "Drupal\taxonomy\VocabularyAccessControlHandler",
 *     "form" = {
 *       "default" = "Drupal\taxonomy\VocabularyForm",
 *       "reset" = "Drupal\taxonomy\Form\VocabularyResetForm",
 *       "delete" = "Drupal\taxonomy\Form\VocabularyDeleteForm",
 *       "overview" = "Drupal\taxonomy\Form\OverviewTerms"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\taxonomy\Entity\Routing\VocabularyRouteProvider",
 *     }
 *   },
 *   admin_permission = "administer rdf taxonomy",
 *   config_prefix = "rdf_vocabulary",
 *   bundle_of = "rdf_taxonomy_term",
 *   entity_keys = {
 *     "id" = "vid",
 *     "label" = "name",
 *     "weight" = "weight"
 *   },
 *   links = {
 *     "add-form" = "/admin/structure/rdf-taxonomy/add",
 *     "delete-form" = "/admin/structure/rdf-taxonomy/manage/{rdf_taxonomy_vocabulary}/delete",
 *     "reset-form" = "/admin/structure/rdf-taxonomy/manage/{trdf_axonomy_vocabulary}/reset",
 *     "overview-form" = "/admin/structure/rdf-taxonomy/manage/{rdf_taxonomy_vocabulary}/overview",
 *     "edit-form" = "/admin/structure/rdf-taxonomy/manage/{rdf_taxonomy_vocabulary}",
 *     "collection" = "/admin/structure/rdf-taxonomy",
 *   },
 *   config_export = {
 *     "name",
 *     "vid",
 *     "description",
 *     "weight",
 *   }
 * )
 */
class RdfVocabulary extends Vocabulary {

  /**
   * {@inheritdoc}
   */
  public static function preDelete(EntityStorageInterface $storage, array $entities) {
    parent::preDelete($storage, $entities);

    // Only load terms without a parent, child terms will get deleted too.
    $term_storage = \Drupal::entityTypeManager()->getStorage('rdf_taxonomy_term');
    $terms = $term_storage->loadMultiple($storage->getToplevelTids(array_keys($entities)));
    $term_storage->delete($terms);
  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities) {
    parent::postDelete($storage, $entities);

    // Reset caches.
    $storage->resetCache(array_keys($entities));

    if (reset($entities)->isSyncing()) {
      return;
    }

    $vocabularies = [];
    foreach ($entities as $vocabulary) {
      $vocabularies[$vocabulary->id()] = $vocabulary->id();
    }
    // Load all Taxonomy module fields and delete those which use only this
    // vocabulary.
    $field_storages = \Drupal::entityTypeManager()->getStorage('field_storage_config')->loadByProperties(['module' => 'rdf_taxonomy']);
    foreach ($field_storages as $field_storage) {
      $modified_storage = FALSE;
      // Term reference fields may reference terms from more than one
      // vocabulary.
      foreach ($field_storage->getSetting('allowed_values') as $key => $allowed_value) {
        if (isset($vocabularies[$allowed_value['rdf_taxonomy_vocabulary']])) {
          $allowed_values = $field_storage->getSetting('allowed_values');
          unset($allowed_values[$key]);
          $field_storage->setSetting('allowed_values', $allowed_values);
          $modified_storage = TRUE;
        }
      }
      if ($modified_storage) {
        $allowed_values = $field_storage->getSetting('allowed_values');
        if (empty($allowed_values)) {
          $field_storage->delete();
        }
        else {
          // Update the field definition with the new allowed values.
          $field_storage->save();
        }
      }
    }
  }

}
