<?php

namespace Drupal\Tests\rdf_draft\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\rdf_entity\Entity\RdfEntityGraph;
use Drupal\rdf_entity\Entity\RdfEntityMapping;
use Drupal\Tests\rdf_entity\Traits\RdfDatabaseConnectionTrait;

/**
 * Tests RDF entity graphs.
 *
 * @group rdf_entity
 */
class RdfDraftGraphTest extends KernelTestBase {

  use RdfDatabaseConnectionTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'rdf_draft',
    'rdf_draft_test',
    'rdf_entity',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->setUpSparql();
    $this->installConfig(['rdf_entity', 'rdf_draft', 'rdf_draft_test']);
  }

  /**
   * Tests graphs.
   */
  public function test() {
    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $manager */
    $manager = $this->container->get('entity_type.manager');
    /** @var \Drupal\rdf_entity\Entity\RdfEntitySparqlStorage $storage */
    $storage = $manager->getStorage('rdf_entity');

    /** @var \Drupal\rdf_entity\RdfInterface $apple */
    $apple = $storage->create([
      'rid' => 'fruit',
      'label' => 'Draft of Apple',
      'graph' => 'draft',
    ]);
    $apple->save();

    $id = $apple->id();

    // Check that, by default, only the draft exists.
    $apple = $storage->load($id);
    $this->assertEquals('draft', $apple->graph->value);
    $this->assertFalse($storage->hasGraph($apple, 'default'));

    // Check cascading over the graph candidate list.
    $apple = $storage->load($id, ['default', 'draft']);
    $this->assertEquals('draft', $apple->graph->value);

    // Add the 'default' graph.
    $apple
      ->set('graph', 'default')
      ->setName('Apple')
      ->save();

    // Check that, by default, the 'default' graph is loaded.
    $apple = $storage->load($id);
    $this->assertEquals('default', $apple->graph->value);
    $this->assertTrue($storage->hasGraph($apple, 'default'));
    $this->assertTrue($storage->hasGraph($apple, 'draft'));

    // Create a new graph and add it to the mapping.
    RdfEntityGraph::create([
      'id' => 'arbitrary',
      'label' => 'Some graph',
    ])->save();
    RdfEntityMapping::loadByName('rdf_entity', 'fruit')
      ->addGraphs(['arbitrary' => 'http://example.com/fruit/graph/arbitrary'])
      ->save();

    $apple
      ->set('graph', 'arbitrary')
      ->setName('Apple in arbitrary graph')
      ->save();

    $apple = $storage->load($id, ['arbitrary']);
    $this->assertEquals('arbitrary', $apple->graph->value);
    $this->assertEquals('Apple in arbitrary graph', $apple->label());

    // Try to request the entity from a non-existing graph.
    $this->setExpectedException(\InvalidArgumentException::class, "Graph 'invalid graph' doesn't exist for entity type 'rdf_entity'.");
    $apple = $storage->load($id, ['invalid graph', 'default', 'draft']);

    // Delete the draft version.
    $storage->deleteFromGraph([$apple], 'draft');
    $this->assertNull($storage->load($id, ['draft']));
    $this->assertNotNull($storage->load($id, ['default']));
    $this->assertNotNull($storage->load($id, ['arbitrary']));

    // Delete the entity.
    $apple->delete();
    // All versions are gone.
    $this->assertNull($storage->load($id, ['default']));
    $this->assertNull($storage->load($id, ['arbitrary']));
  }

}
