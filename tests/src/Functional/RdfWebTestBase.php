<?php

namespace Drupal\Tests\rdf_entity\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\rdf_entity\Traits\RdfDatabaseConnectionTrait;

/**
 * Base setup for a browser tests using RDF module.
 */
abstract class RdfWebTestBase extends BrowserTestBase {

  use RdfDatabaseConnectionTrait;

  /**
   * An array of graphs to clear after the test.
   *
   * @var array
   */
  protected $usedGraphs = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    $this->setUpSparql();
    parent::setUp();
  }

  /**
   * {@inheritdoc}
   */
  public function tearDown() {
    // Delete all data produced by testing module.
    foreach ($this->usedGraphs as $graph) {
      $query = <<<EndOfQuery
DELETE {
  GRAPH <$graph> {
    ?entity ?field ?value
  }
}
WHERE {
  GRAPH <$graph> {
    ?entity ?field ?value
  }
}
EndOfQuery;
      $this->sparql->query($query);
    }

    parent::tearDown();
  }

}
