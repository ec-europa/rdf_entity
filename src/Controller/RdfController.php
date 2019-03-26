<?php

namespace Drupal\rdf_entity\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\Controller\EntityViewController;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Drupal\rdf_entity\RdfEntitySparqlStorageInterface;
use Drupal\rdf_entity\RdfEntityTypeInterface;
use Drupal\rdf_entity\RdfInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides route responses for rdf_entity.module.
 */
class RdfController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs a RdfController object.
   *
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   */
  public function __construct(DateFormatterInterface $date_formatter, RendererInterface $renderer) {
    $this->dateFormatter = $date_formatter;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('date.formatter'),
      $container->get('renderer')
    );
  }

  /**
   * Route title callback.
   *
   * @param \Drupal\rdf_entity\RdfEntityTypeInterface $rdf_type
   *   The rdf type.
   *
   * @return array
   *   The rdf type label as a render array.
   */
  public function rdfTypeTitle(RdfEntityTypeInterface $rdf_type) {
    return [
      '#markup' => $rdf_type->label(),
      '#allowed_tags' => Xss::getHtmlTagList(),
    ];
  }

  /**
   * Route title callback.
   *
   * @param \Drupal\rdf_entity\RdfInterface $rdf_entity
   *   The rdf entity.
   *
   * @return array
   *   The rdf entity label as a render array.
   */
  public function rdfTitle(RdfInterface $rdf_entity) {
    return [
      '#markup' => $rdf_entity->getName(),
      '#allowed_tags' => Xss::getHtmlTagList(),
    ];
  }

  /**
   * Provides the RDF submission form.
   *
   * @param \Drupal\rdf_entity\RdfEntityTypeInterface $rdf_type
   *   The RDF bundle entity for the RDF entity.
   *
   * @return array
   *   A RDF submission form.
   */
  public function add(RdfEntityTypeInterface $rdf_type) {
    $rdf_entity = $this->entityTypeManager()
      ->getStorage('rdf_entity')
      ->create([
        'rid' => $rdf_type->id(),
      ]);

    $form = $this->entityFormBuilder()->getForm($rdf_entity, 'add');

    return $form;
  }

  /**
   * Displays add content links for available rdf types.
   *
   * Redirects to rdf_entity/add/[type] if only one rdf type is available.
   *
   * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
   *   A render array for a list of the rdf bundles that can be added; however,
   *   if there is only one rdf type defined for the site, the function
   *   will return a RedirectResponse to the rdf add page for that one rdf
   *   type.
   */
  public function addPage() {
    $build = [
      '#theme' => 'rdf_add_list',
      '#cache' => [
        'tags' => $this->entityTypeManager()
          ->getDefinition('rdf_type')
          ->getListCacheTags(),
      ],
    ];

    $content = [];

    // Only use RDF types the user has access to.
    foreach ($this->entityTypeManager()->getStorage('rdf_type')->loadMultiple() as $type) {
      $access = $this->entityTypeManager()
        ->getAccessControlHandler('rdf_entity')
        ->createAccess($type->id(), NULL, [], TRUE);
      if ($access->isAllowed()) {
        $content[$type->id()] = $type;
      }
    }
    // Bypass the rdf_entity/add listing if only one content type is available.
    if (count($content) == 1) {
      $type = array_shift($content);
      return $this->redirect('rdf_entity.rdf_add', ['rdf_type' => $type->id()]);
    }

    $build['#content'] = $content;

    return $build;
  }

  /**
   * Generates an overview table of older revisions of a entity.
   *
   * @param \Drupal\rdf_entity\RdfInterface $rdf_entity
   *   A rdf object.
   *
   * @return array
   *   An array as expected by \Drupal\Core\Render\RendererInterface::render().
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function revisionOverview(RdfInterface $rdf_entity) {
    $account = $this->currentUser();
    $langcode = $rdf_entity->language()->getId();
    $langname = $rdf_entity->language()->getName();
    $languages = $rdf_entity->getTranslationLanguages();
    $has_translations = (count($languages) > 1);
    $rdf_storage = $this->entityManager()->getStorage('rdf_entity');
    $type = $rdf_entity->getType();

    $build['#title'] = $has_translations ? $this->t('@langname revisions for %title', ['@langname' => $langname, '%title' => $rdf_entity->label()]) : $this->t('Revisions for %title', ['%title' => $rdf_entity->label()]);
    $header = [$this->t('Revision'), $this->t('Operations')];

    $revert_permission = (($account->hasPermission("revert $type revisions") || $account->hasPermission('revert all revisions') || $account->hasPermission('administer nodes')) && $rdf_entity->access('update'));
    $delete_permission = (($account->hasPermission("delete $type revisions") || $account->hasPermission('delete all revisions') || $account->hasPermission('administer nodes')) && $rdf_entity->access('delete'));

    $rows = [];
    $default_revision = $rdf_entity->getRevisionId();
    $current_revision_displayed = FALSE;

    $revisions = $this->getRevisionIds($rdf_entity, $rdf_storage);

    foreach ($revisions as $vid) {
      /** @var \Drupal\rdf_entity\RdfInterface $revision */
      $revision = $rdf_storage->loadRevision($vid);
      // Only show revisions that are affected by the language that is being
      // displayed.
      // @todo && $revision->getTranslation($langcode)->isRevisionTranslationAffected()
      if ($revision->hasTranslation($langcode) ) {
        $username = [
          '#theme' => 'username',
          '#account' => $revision->getRevisionUser(),
        ];

        // Use revision link to link to revisions that are not active.
        $date = $this->dateFormatter->format($revision->getChangedTime(), 'short');

        // We treat also the latest translation-affecting revision as current
        // revision, if it was the default revision, as its values for the
        // current language will be the same of the current default revision in
        // this case.
        $is_current_revision = $vid == $default_revision || (!$current_revision_displayed && $revision->wasDefaultRevision());
        if (!$is_current_revision) {
          $link = $this->l($date, new Url('entity.rdf_entity.revision', ['rdf_entity' => $rdf_entity->id(), 'rdf_revision' => $vid]));
        }
        else {
          $link = $rdf_entity->link($date);
          $current_revision_displayed = TRUE;
        }

        $row = [];
        $column = [
          'data' => [
            '#type' => 'inline_template',
            '#template' => '{% trans %}{{ date }} by {{ username }}{% endtrans %}{% if message %}<p class="revision-log">{{ message }}</p>{% endif %}',
            '#context' => [
              'date' => $link,
              'username' => $this->renderer->renderPlain($username),
              'message' => ['#markup' => $revision->revision_log->value, '#allowed_tags' => Xss::getHtmlTagList()],
            ],
          ],
        ];
        // @todo Simplify once https://www.drupal.org/node/2334319 lands.
        $this->renderer->addCacheableDependency($column['data'], $username);
        $row[] = $column;

        if ($is_current_revision) {
          $row[] = [
            'data' => [
              '#prefix' => '<em>',
              '#markup' => $this->t('Current revision'),
              '#suffix' => '</em>',
            ],
          ];

          $rows[] = [
            'data' => $row,
            'class' => ['revision-current'],
          ];
        }
        else {
          $links = [];
          if ($revert_permission) {
            $links['revert'] = [
              'title' => $vid < $rdf_entity->getRevisionId() ? $this->t('Revert') : $this->t('Set as current revision'),
              'url' => $has_translations ?
                Url::fromRoute('rdf_entity.revision_revert_translation_confirm', ['rdf_rntity' => $rdf_entity->id(), 'rdf_revision' => $vid, 'langcode' => $langcode]) :
                Url::fromRoute('rdf_entity.revision_revert_confirm', ['rdf_entity' => $rdf_entity->id(), 'rdf_revision' => $vid]),
            ];
          }

          if ($delete_permission) {
            $links['delete'] = [
              'title' => $this->t('Delete'),
              'url' => Url::fromRoute('rdf_entity.revision_delete_confirm', ['rdf_entity' => $rdf_entity->id(), 'rdf_revision' => $vid]),
            ];
          }

          $row[] = [
            'data' => [
              '#type' => 'operations',
              '#links' => $links,
            ],
          ];

          $rows[] = $row;
        }
      }
    }

    $build['node_revisions_table'] = [
      '#theme' => 'table',
      '#rows' => $rows,
      '#header' => $header,
      '#attached' => [
        'library' => ['node/drupal.node.admin'],
      ],
      '#attributes' => ['class' => 'node-revision-table'],
    ];

    $build['pager'] = ['#type' => 'pager'];

    // Bypass cache for development
    $build['#cache']['max-age'] = 0;

    return $build;
  }

  /**
   * Gets a list of rdf revision IDs for a specific entity.
   *
   * @param \Drupal\rdf_entity\RdfInterface $rdf_entity
   *   The rdf entity.
   * @param \Drupal\rdf_entity\RdfEntitySparqlStorageInterface $rdf_storage
   *   The rdf storage handler.
   *
   * @return int[]
   *   Node revision IDs (in descending order).
   */
  protected function getRevisionIds(RdfInterface $rdf_entity, RdfEntitySparqlStorageInterface $rdf_storage) {
    $query = $rdf_storage->getQuery()
      ->allRevisions()
      //->condition($rdf_entity->getEntityType()->getKey('bundle'), 'event')
      ->condition($rdf_entity->getEntityType()->getKey('id'), $rdf_entity->id())
      ->sort($rdf_entity->getEntityType()->getKey('revision'), 'DESC')
      ->pager(50);
    $result= $query->execute();
    return array_keys($result);
  }


  /**
   * Displays a rdf revision.
   *
   * @param \Drupal\rdf_entity\RdfInterface $rdf_revision
   *   The rdf revision.
   *
   * @return array
   *   An array suitable for \Drupal\Core\Render\RendererInterface::render().
   */
  public function revisionShow(RdfInterface $rdf_revision) {
    $rdf_entity = $this->entityManager()->getTranslationFromContext($rdf_revision);
    $view_controller = new EntityViewController($this->entityManager, $this->renderer);
    $page = $view_controller->view($rdf_entity);
    unset($page['#cache']);
    return $page;
  }

}
