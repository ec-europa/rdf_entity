<?php

declare(strict_types = 1);

namespace Drupal\Tests\rdf_entity\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\rdf_entity\Entity\RdfEntityGraph;
use Drupal\rdf_entity\Entity\RdfEntityMapping;
use Drupal\Tests\rdf_entity\Traits\RdfDatabaseConnectionTrait;

/**
 * Tests RDF entity graphs.
 *
 * @group rdf_entity
 */
class RdfEntityGraphTest extends KernelTestBase {

  use RdfDatabaseConnectionTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'rdf_entity',
    'rdf_entity_graph_test',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->setUpSparql();
    $this->installConfig(['rdf_entity', 'rdf_entity_graph_test']);
  }

  /**
   * Tests graphs.
   */
  public function test() {
    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $manager */
    $manager = $this->container->get('entity_type.manager');
    /** @var \Drupal\rdf_entity\Entity\RdfEntitySparqlStorage $storage */
    $storage = $manager->getStorage('rdf_entity');

    // Create a 2nd graph.
    $this->createGraph('foo', 10);

    /** @var \Drupal\rdf_entity\RdfInterface $apple */
    $apple = $storage->create([
      'rid' => 'fruit',
      'label' => 'Apple in foo graph',
      'graph' => 'foo',
    ]);
    $apple->save();

    $id = $apple->id();

    // Check that, by default, the entity exists only in the foo graph.
    $apple = $storage->load($id);
    $this->assertEquals('foo', $apple->graph->target_id);
    $this->assertFalse($storage->hasGraph($apple, 'default'));

    // Check cascading over the graph candidate list.
    $apple = $storage->load($id, ['default', 'foo']);
    $this->assertEquals('foo', $apple->graph->target_id);

    // Set the 'default' graph.
    $apple
      ->set('graph', 'default')
      ->setName('Apple')
      ->save();

    // Check that, by default, the 'default' graph is loaded.
    $apple = $storage->load($id);
    $this->assertEquals('default', $apple->graph->target_id);
    $this->assertTrue($storage->hasGraph($apple, 'default'));
    $this->assertTrue($storage->hasGraph($apple, 'foo'));

    // Create a new 'arbitrary' graph and add it to the mapping.
    $this->createGraph('arbitrary');

    $apple
      ->set('graph', 'arbitrary')
      ->setName('Apple in arbitrary graph')
      ->save();

    $apple = $storage->load($id, ['arbitrary']);
    $this->assertEquals('arbitrary', $apple->graph->target_id);
    $this->assertEquals('Apple in arbitrary graph', $apple->label());

    // Delete the foo version.
    $storage->deleteFromGraph([$apple], 'foo');
    $this->assertNull($storage->load($id, ['foo']));
    $this->assertNotNull($storage->load($id, ['default']));
    $this->assertNotNull($storage->load($id, ['arbitrary']));

    // Create a graph that is excluded from the default graphs list.
    // @see \Drupal\rdf_entity_graph_test\DefaultGraphsSubscriber
    $this->createGraph('non_default_graph');

    $apple
      ->set('graph', 'non_default_graph')
      ->setName('Apple in non_default_graph graph')
      ->save();

    // Delete the entity from 'default' and 'arbitrary'.
    $storage->deleteFromGraph([$apple], 'default');
    $storage->deleteFromGraph([$apple], 'arbitrary');

    // Check that the entity is loaded from an explicitly passed graph even it's
    // a non-default graph.
    $this->assertNotNull($storage->load($id, ['non_default_graph']));

    // Even the entity exists in 'non_default_graph' is not returned because
    // this graph is not a default graph.
    $this->assertNull($storage->load($id));

    // Delete the entity.
    $apple->delete();
    // All versions are gone.
    $this->assertNull($storage->load($id, ['default']));
    $this->assertNull($storage->load($id, ['foo']));
    $this->assertNull($storage->load($id, ['arbitrary']));
    $this->assertNull($storage->load($id, ['non_default_graph']));

    // Try to request the entity from a non-existing graph.
    $this->setExpectedException(\InvalidArgumentException::class, "Graph 'invalid graph' doesn't exist for entity type 'rdf_entity'.");
    $storage->load($id, ['invalid graph', 'default', 'foo']);
  }

  /**
   * Creates a new graph entity and adds it to the 'fruit' mapping.
   *
   * @param string $id
   *   The graph ID.
   * @param int $weight
   *   (optional) The graph weight. Defaults to 0.
   */
  protected function createGraph(string $id, int $weight = 0): void {
    RdfEntityGraph::create(['id' => $id, 'label' => ucwords($id)])
      ->setWeight($weight)
      ->save();
    RdfEntityMapping::loadByName('rdf_entity', 'fruit')
      ->addGraphs([$id => "http://example.com/fruit/graph/$id"])
      ->save();
  }

}
