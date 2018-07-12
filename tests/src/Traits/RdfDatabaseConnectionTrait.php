<?php

namespace Drupal\Tests\rdf_entity\Traits;

use Drupal\Core\Database\Database;
use EasyRdf\Http;

/**
 * Provides helpers to add a SPARQL database connection in tests.
 */
trait RdfDatabaseConnectionTrait {

  /**
   * The SPARQL database connection.
   *
   * @var \Drupal\rdf_entity\Database\Driver\sparql\Connection
   */
  protected $sparql;

  /**
   * The SPARQL database info.
   *
   * @var array
   */
  protected $sparqlConnectionInfo;

  /**
   * Checks if the triple store is an Virtuoso 6 instance.
   *
   * @throws \Exception
   *   When Virtuoso version is 6.
   */
  protected function detectVirtuoso6() {
    $client = Http::getDefaultHttpClient();
    $client->resetParameters(TRUE);
    $client->setUri("http://{$this->getSparqlConnectionInfo()['host']}:{$this->getSparqlConnectionInfo()['port']}/");
    $client->setMethod('GET');
    $response = $client->request();
    $server_header = $response->getHeader('Server');
    if (strpos($server_header, "Virtuoso/06") !== FALSE) {
      throw new \Exception('Not running on Virtuoso 6.');
    }
  }

  /**
   * Setup the db connection to the triple store.
   *
   * @throws \LogicException
   *   When SIMPLETEST_SPARQL_DB is not set.
   */
  protected function setUpSparql() {
    $this->detectVirtuoso6();
    Database::addConnectionInfo('sparql_default', 'default', $this->getSparqlConnectionInfo());
    $this->sparql = Database::getConnection('default', 'sparql_default');
  }

  /**
   * {@inheritdoc}
   */
  protected function writeSettings(array $settings) {
    // The BrowserTestBase is creating a new copy of the settings.php file to
    // the test directory so the SPARQL entry needs to be inserted into the new
    // configuration.
    $key = 'sparql_default';
    $target = 'default';

    $settings['databases'][$key][$target] = (object) [
      'value' => Database::getConnectionInfo($key)[$target],
      'required' => TRUE,
    ];

    parent::writeSettings($settings);
  }

  /**
   * Returns the SPARQL connection info.
   *
   * @return array
   *   The connection infp array.
   */
  protected function getSparqlConnectionInfo(): array {
    if (!isset($this->sparqlConnectionInfo)) {
      if (empty($db_url = getenv('SIMPLETEST_SPARQL_DB'))) {
        throw new \LogicException('No SPARQL connection was defined. Set the SIMPLETEST_SPARQL_DB environment variable.');
      }
      $this->sparqlConnectionInfo = Database::convertDbUrlToConnectionInfo($db_url, dirname(dirname(__FILE__)));
      $this->sparqlConnectionInfo['namespace'] = 'Drupal\\rdf_entity\\Database\\Driver\\sparql';
    }
    return $this->sparqlConnectionInfo;
  }

}
