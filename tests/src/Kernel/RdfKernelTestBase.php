<?php

namespace Drupal\Tests\rdf_entity\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\Tests\sparql_entity_storage\Traits\SparqlConnectionTrait;

/**
 * A base class for the RDF tests.
 *
 * Sets up the SPARQL database connection.
 */
abstract class RdfKernelTestBase extends EntityKernelTestBase {

  use SparqlConnectionTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'datetime',
    'rdf_draft',
    'rdf_entity',
    'rdf_entity_test',
    'link',
    'sparql_entity_storage',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->setUpSparql();
    $this->installConfig([
      'rdf_entity',
      'rdf_draft',
      'rdf_entity_test',
      'sparql_entity_storage',
    ]);
    $this->installEntitySchema('rdf_entity');
  }

  /**
   * {@inheritdoc}
   */
  public function tearDown() {
    // Delete all data produced by testing module.
    foreach (['dummy', 'with_owner'] as $bundle) {
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
