<?php

namespace Drupal\rdf_taxonomy\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityConstraintViolationListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\taxonomy\TermForm;
use Drupal\taxonomy\VocabularyInterface;

/**
 * Form controller for the rdf term entity edit forms.
 */
class RdfTermForm extends TermForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $term = $this->entity;
    $vocab_storage = $this->entityTypeManager->getStorage('rdf_taxonomy_vocabulary');
    /** @var \Drupal\taxonomy\TermStorageInterface $taxonomy_storage */
    $taxonomy_storage = $this->entityTypeManager->getStorage('rdf_taxonomy_term');
    $vocabulary = $vocab_storage->load($term->bundle());

    $parent = array_keys($taxonomy_storage->loadParents($term->id()));
    $form_state->set(['taxonomy', 'parent'], $parent);
    $form_state->set(['taxonomy', 'rdf_taxonomy_vocabulary'], $vocabulary);

    $form['relations'] = [
      '#type' => 'details',
      '#title' => $this->t('Relations'),
      '#open' => $taxonomy_storage->getVocabularyHierarchyType($vocabulary->id()) == VocabularyInterface::HIERARCHY_MULTIPLE,
      '#weight' => 10,
    ];

    // \Drupal\taxonomy\TermStorageInterface::loadTree() and
    // \Drupal\taxonomy\TermStorageInterface::loadParents() may contain large
    // numbers of items so we check for taxonomy.settings:override_selector
    // before loading the full vocabulary. Contrib modules can then intercept
    // before hook_form_alter to provide scalable alternatives.
    if (!$this->config('taxonomy.settings')->get('override_selector')) {
      $exclude = [];
      if (!$term->isNew()) {
        $parent = array_keys($taxonomy_storage->loadParents($term->id()));
        $children = $taxonomy_storage->loadTree($vocabulary->id(), $term->id());

        // A term can't be the child of itself, nor of its children.
        foreach ($children as $child) {
          $exclude[] = $child->tid;
        }
        $exclude[] = $term->id();
      }

      $tree = $taxonomy_storage->loadTree($vocabulary->id());
      $options = ['<' . $this->t('root') . '>'];
      if (empty($parent)) {
        $parent = [0];
      }

      foreach ($tree as $item) {
        if (!in_array($item->tid, $exclude)) {
          $options[$item->tid] = str_repeat('-', $item->depth) . $item->name;
        }
      }

      $form['relations']['parent'] = [
        '#type' => 'select',
        '#title' => $this->t('Parent terms'),
        '#options' => $options,
        '#default_value' => $parent,
        '#multiple' => TRUE,
      ];
    }

    $form['relations']['weight'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Weight'),
      '#size' => 6,
      '#default_value' => $term->getWeight(),
      '#description' => $this->t('Terms are displayed in ascending order by weight.'),
      '#required' => TRUE,
    ];

    $form['vid'] = [
      '#type' => 'value',
      '#value' => $vocabulary->id(),
    ];

    $form['tid'] = [
      '#type' => 'value',
      '#value' => $term->id(),
    ];

    return parent::form($form, $form_state);
  }

}
