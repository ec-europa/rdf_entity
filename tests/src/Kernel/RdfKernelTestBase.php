<?php

namespace Drupal\Tests\rdf_entity\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\Tests\rdf_entity\Traits\RdfDatabaseConnectionTrait;

/**
 * A base class for the rdf tests.
 *
 * Mainly, assures the connection to the triple store database.
 *
 * IMPORTANT! You should not use real RDF entity bundles for testing because the
 * test is using the same backend storage as the main site and you can end up
 * with changes to the main site content. Create your own RDF entity bundles for
 * testing purposes, like the one provided in the rdf_entity_test.module. That
 * module uses a dedicated testing graphs, (http://example.com/dummy/published
 * and http://example.com/dummy/draft). This base class enables, at startup, the
 * rdf_entity_test.module and takes care of deleting testing data. For other
 * custom testing data that you are adding for testing, you should take care of
 * cleaning it after the test. You can extend the tearDown() method for this
 * purpose.
 */
abstract class RdfKernelTestBase extends EntityKernelTestBase {

  use RdfDatabaseConnectionTrait;

  /**
   * Modules to enable for this test.
   *
   * @var string[]
   */
  public static $modules = [
    'comment',
    'datetime',
    'rdf_entity_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->setUpSparql();

    $this->installModule('rdf_entity');
    $this->installModule('rdf_draft');
    $this->installConfig(['rdf_entity', 'rdf_draft']);
    $this->installEntitySchema('rdf_entity');
    $this->installEntitySchema('user');
    $this->installConfig(['rdf_entity_test']);
  }

  /**
   * {@inheritdoc}
   */
  public function tearDown() {
    // Delete all data produced by testing module.
    foreach (['dummy', 'with_owner', 'multifield'] as $bundle) {
      foreach (['published', 'draft'] as $graph) {
        $query = <<<EndOfQuery
DELETE {
  GRAPH <http://example.com/$bundle/$graph> {
    ?entity ?field ?value
  }
}
WHERE {
  GRAPH <http://example.com/$bundle/$graph> {
    ?entity ?field ?value
  }
}
EndOfQuery;
        $this->sparql->query($query);
      }
    }

    parent::tearDown();
  }

}
