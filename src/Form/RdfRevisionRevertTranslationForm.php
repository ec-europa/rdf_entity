<?php

namespace Drupal\rdf_entity\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form for reverting a rdf revision for a single translation.
 *
 * @internal
 */
class RdfRevisionRevertTranslationForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'rdf_revision_revert_translation_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    throw new \Exception('unimplemented.');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    throw new \Exception('unimplemented.');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $rdf_revision = NULL, $langcode = NULL) {
    throw new \Exception('unimplemented.');
  }

}
