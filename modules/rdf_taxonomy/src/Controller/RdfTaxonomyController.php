<?php

namespace Drupal\rdf_taxonomy\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\taxonomy\Controller\TaxonomyController;
use Drupal\taxonomy\TermInterface;
use Drupal\taxonomy\VocabularyInterface;

/**
 * Provides route responses for rdf_taxonomy.module.
 */
class RdfTaxonomyController extends TaxonomyController {

  /**
   * Returns a form to add a new term to a vocabulary.
   *
   * @param \Drupal\taxonomy\VocabularyInterface $taxonomy_vocabulary
   *   The RDF vocabulary this term will be added to.
   *
   * @return array
   *   The RDF taxonomy term add form.
   */
  public function addForm(VocabularyInterface $taxonomy_vocabulary) {
    $term = $this->entityTypeManager()->getStorage('rdf_taxonomy_term')->create(['vid' => $taxonomy_vocabulary->id()]);
    return $this->entityFormBuilder()->getForm($term);
  }

}
