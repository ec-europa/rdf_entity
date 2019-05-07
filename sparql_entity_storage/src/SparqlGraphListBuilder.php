<?php

namespace Drupal\sparql_entity_storage;

use Drupal\Core\Config\Entity\DraggableListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines a class to build a listing of SPARQL graph entities.
 */
class SparqlGraphListBuilder extends DraggableListBuilder {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sparql_entity_storage.graph.list';
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
  public function buildRow(EntityInterface $sparql_graph) {
    /** @var \Drupal\sparql_entity_storage\SparqlGraphInterface $sparql_graph */
    $row['label'] = $sparql_graph->label();
    $row['description'] = ['#markup' => $sparql_graph->getDescription()];

    if ($entity_types = $sparql_graph->getEntityTypeIds()) {
      $labels = implode(', ', array_intersect_key(\Drupal::service('entity_type.repository')
        ->getEntityTypeLabels(), array_flip($entity_types)));
    }
    else {
      $labels = $this->t('All SPARQL storage entity types');
    }
    $row['entity_types'] = ['#markup' => $labels];

    return $row + parent::buildRow($sparql_graph);
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
