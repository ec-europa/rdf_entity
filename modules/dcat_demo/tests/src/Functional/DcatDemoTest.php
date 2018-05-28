<?php

namespace Drupal\Tests\dcat_demo\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\rdf_entity\Traits\RdfDatabaseConnectionTrait;

/**
 * Makes a demo as functional test.
 */
class DcatDemoTest extends BrowserTestBase {

  use RdfDatabaseConnectionTrait {
    getSparqlConnectionInfo as getSparqlConnectionInfoTrait;
  }

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'dcat_demo',
  ];

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
  public function test() {
    $query = <<<Query
SELECT DISTINCT ?Concept
WHERE {
  [] a ?Concept
}
LIMIT 100
Query;
    $response = $this->sparql->query($query);
    $this->assertNotEmpty($response);
  }

  /**
   * {@inheritdoc}
   */
  protected function getSparqlConnectionInfo(): array {
    if (!isset($this->sparqlConnectionInfo)) {
      $this->getSparqlConnectionInfoTrait();
      $this->sparqlConnectionInfo['host'] = 'data.europa.eu';
      $this->sparqlConnectionInfo['port'] = 80;
      $this->sparqlConnectionInfo['database'] = 'euodp/sparqlep';
    }
    return $this->sparqlConnectionInfo;
  }

}
