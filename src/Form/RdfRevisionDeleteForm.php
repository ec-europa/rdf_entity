<?php

namespace Drupal\rdf_entity\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form for reverting a rdf revision.
 *
 * @internal
 */
class RdfRevisionDeleteForm extends ConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'rdf_revision_delete_confirm';
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
  public function getCancelUrl() {
    throw new \Exception('unimplemented.');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    throw new \Exception('unimplemented.');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $rdf_revision = NULL) {
    throw new \Exception('unimplemented.');
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    throw new \Exception('unimplemented.');
  }


}
