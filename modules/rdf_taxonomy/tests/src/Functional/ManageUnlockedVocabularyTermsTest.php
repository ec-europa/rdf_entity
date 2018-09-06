<?php

namespace Drupal\Tests\rdf_taxonomy\Functional;

use Drupal\rdf_entity\Entity\RdfEntityMapping;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\rdf_entity\Traits\RdfDatabaseConnectionTrait;

/**
 * Tests adding, editing and deleting terms in an unlocked vocabulary.
 *
 * @group rdf_taxonomy
 */
class ManageUnlockedVocabularyTermsTest extends BrowserTestBase {

  use RdfDatabaseConnectionTrait;

  /**
   * The testing term.
   *
   * @var \Drupal\taxonomy\TermInterface
   */
  protected $term;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'rdf_draft',
    'rdf_taxonomy',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    $this->setUpSparql();
    parent::setUp();

    // Create an unlocked vocabulary and its mapping.
    Vocabulary::create([
      'vid' => 'unlocked_vocab',
      'name' => $this->randomString(),
    ])->setThirdPartySetting('rdf_taxonomy', 'locked', FALSE)
      ->save();
    RdfEntityMapping::create([
      'entity_type_id' => 'taxonomy_term',
      'bundle' => 'unlocked_vocab',
    ])->setRdfType('http://example.com/unlocked-vocab')
      ->setGraphs(['default' => 'http://example.com/graph/unlocked-vocab'])
      ->setEntityIdPlugin('default')
      ->setMappings([
        'vid' => [
          'target_id' => [
            'predicate' => 'http://www.w3.org/2004/02/skos/core#inScheme',
            'format' => 'resource',
          ],
        ],
        'name' => [
          'value' => [
            'predicate' => 'http://www.w3.org/2004/02/skos/core#prefLabel',
            'format' => 't_literal',
          ],
        ],
        'parent' => [
          'target_id' => [
            'predicate' => 'http://www.w3.org/2004/02/skos/core#broaderTransitive',
            'format' => 'resource',
          ],
        ],
        'description' => [
          'value' => [
            'predicate' => 'http://www.w3.org/2004/02/skos/core#definition',
            'format' => 't_literal',
          ],
        ],
      ])->save();

    $this->drupalLogin($this->drupalCreateUser([
      'create terms in unlocked_vocab',
      'edit terms in unlocked_vocab',
      'delete terms in unlocked_vocab',
      'access taxonomy overview',
    ]));
  }

  /**
   * Tests adding, editing and deleting terms from an unlocked vocabulary.
   */
  public function test() {
    // Tests creation of a new term via UI.
    $edit = [
      'name[0][value]' => 'Top Level Term',
      'description[0][value]' => $this->randomString(),
    ];
    $this->drupalPostForm('admin/structure/taxonomy/manage/unlocked_vocab/add', $edit, 'Save');
    $this->assertSession()->pageTextContains('Created new term Top Level Term.');

    /** @var \Drupal\taxonomy\TermStorageInterface $storage */
    $storage = $this->container->get('entity_type.manager')->getStorage('taxonomy_term');
    $terms = $storage->loadByProperties([
      'vid' => 'unlocked_vocab',
      'name' => 'Top Level Term',
    ]);
    $this->term = reset($terms);

    // Test term view.
    $this->drupalGet($this->term->toUrl());
    $this->assertSession()->statusCodeEquals(200);

    // Tests term editing.
    $edit = [
      'name[0][value]' => 'Changed Term',
    ];
    $this->drupalPostForm($this->term->toUrl('edit-form'), $edit, 'Save');
    $this->assertSession()->pageTextContains('Updated term Changed Term.');

    // Tests term deletion.
    $this->getSession()->getPage()->clickLink('Delete');
    $this->assertSession()->pageTextContains('Are you sure you want to delete the taxonomy term Changed Term?');
    $this->getSession()->getPage()->pressButton('Delete');
    $this->assertSession()->pageTextContains('Deleted term Changed Term.');
  }

  /**
   * {@inheritdoc}
   */
  public function tearDown() {
    $this->term->delete();
    parent::tearDown();
  }

}
