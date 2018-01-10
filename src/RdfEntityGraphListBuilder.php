<?php

namespace Drupal\rdf_entity;

use Drupal\Core\Config\Entity\DraggableListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines a class to build a listing of RDF entity graph entities.
 */
class RdfEntityGraphListBuilder extends DraggableListBuilder {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'rdf_entity.graph.list';
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    return [
      'label' => $this->t('Name'),
      'description' => [
        'data' => $this->t('Description'),
        'class' => [RESPONSIVE_PRIORITY_MEDIUM],
      ],
      'entity_types' => $this->t('Entity types'),
    ] + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $rdf_entity_graph) {
    /** @var \Drupal\rdf_entity\RdfEntityGraphInterface $rdf_entity_graph */
    $row['label'] = $rdf_entity_graph->label();
    $row['description'] = ['#markup' => $rdf_entity_graph->getDescription()];

    if ($entity_types = $rdf_entity_graph->getEntityTypeIds()) {
      $labels = implode(', ', array_intersect_key(\Drupal::service('entity_type.repository')
        ->getEntityTypeLabels(), array_flip($entity_types)));
    }
    else {
      $labels = $this->t('All RDF entity types');
    }
    $row['entity_types'] = ['#markup' => $labels];

    return $row + parent::buildRow($rdf_entity_graph);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);

    /** @var \Drupal\Core\Access\AccessManagerInterface $access_manager */
    $access_manager = \Drupal::service('access_manager');
    foreach (['enable', 'disable'] as $operation) {
      if (isset($operations[$operation])) {
        $route_name = "entity.{$this->entityTypeId}.$operation";
        $parameters = [$this->entityTypeId => $entity->id()];
        if (!$access_manager->checkNamedRoute($route_name, $parameters)) {
          unset($operations[$operation]);
        }
      }
    }

    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $form[$this->entitiesKey]['#caption'] = $this->t('Reorder graphs to establish the graphs priority.');
    return $form;
  }

}
