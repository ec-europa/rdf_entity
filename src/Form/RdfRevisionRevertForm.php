<?php

namespace Drupal\rdf_entity\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\rdf_entity\RdfInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for reverting a rdf entity revision.
 *
 * @internal
 */
class RdfRevisionRevertForm extends ConfirmFormBase {

  /**
   * The node revision.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $revision;

  /**
   * The node storage.
   *
   * @var \Drupal\node\NodeStorageInterface
   */
  protected $sparqlStorage;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs a new NodeRevisionRevertForm.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $node_storage
   *   The node storage.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(EntityStorageInterface $sparql_storage, DateFormatterInterface $date_formatter, TimeInterface $time) {
    $this->sparqlStorage = $sparql_storage;
    $this->dateFormatter = $date_formatter;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager')->getStorage('rdf_entity'),
      $container->get('date.formatter'),
      $container->get('datetime.time')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'rdf_entity_revision_revert_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {

    $date_formatted = $this->dateFormatter->format($this->getRevisionTime());
    return t('Are you sure you want to revert to the revision from %revision-date?', ['%revision-date' => $date_formatted]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.rdf_entity.version_history', ['rdf_entity' => $this->revision->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return t('Revert');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $rdf_revision = NULL) {
    $this->revision = $rdf_revision;
    $form = parent::buildForm($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // The revision timestamp will be updated when the revision is saved. Keep
    // the original one for the confirmation message.
    $original_revision_timestamp = $this->getRevisionTime();

    $this->revision = $this->prepareRevertedRevision($this->revision, $form_state);
    $this->revision->revision_log = t('Copy of the revision from %date.', ['%date' => $this->dateFormatter->format($original_revision_timestamp)]);
    $this->revision->setRevisionUserId($this->currentUser()->id());
    $this->revision->setRevisionCreationTime($this->time->getRequestTime());
    $this->revision->setChangedTime($this->time->getRequestTime());
    $this->revision->save();

    $this->logger('content')->notice('@type: reverted %title revision %revision.', ['@type' => $this->revision->bundle(), '%title' => $this->revision->label(), '%revision' => $this->revision->getRevisionId()]);
    $this->messenger()
      ->addStatus($this->t('@type %title has been reverted to the revision from %revision-date.', [
        '@type' => $this->revision->bundle(),
        '%title' => $this->revision->label(),
        '%revision-date' => $this->dateFormatter->format($original_revision_timestamp),
      ]));
    $form_state->setRedirect(
      'entity.rdf_entity.version_history',
      ['rdf_entity' => $this->revision->id()]
    );
  }

  protected function getRevisionTime() {
    $date = $this->revision->getRevisionCreationTime();
    if (!$date) {
      $date = $this->revision->getCreatedTime();
    }
    return $date;
  }

  /**
   * Prepares a revision to be reverted.
   *
   * @param \Drupal\node\NodeInterface $revision
   *   The revision to be reverted.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return \Drupal\node\NodeInterface
   *   The prepared revision ready to be stored.
   */
  protected function prepareRevertedRevision(RdfInterface $revision, FormStateInterface $form_state) {
    $revision->setNewRevision();
    $revision->isDefaultRevision(TRUE);

    return $revision;
  }

}
