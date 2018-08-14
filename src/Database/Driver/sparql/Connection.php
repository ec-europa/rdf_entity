<?php

declare(strict_types = 1);

namespace Drupal\rdf_entity\Database\Driver\sparql;

use Drupal\Core\Database\Log;
use Drupal\rdf_entity\Exception\SparqlQueryException;
use EasyRdf\Http\Exception as EasyRdfException;
use EasyRdf\Sparql\Client;
use EasyRdf\Sparql\Result;

/**
 * @addtogroup database
 * @{
 */

/**
 * SPARQL connection service.
 */
class Connection implements ConnectionInterface {

  /**
   * The client instance object that performs requests to the SPARQL endpoint.
   *
   * @var \EasyRdf\Sparql\Client
   */
  protected $easyRdfClient;

  /**
   * The connection information for this connection object.
   *
   * @var array
   */
  protected $connectionOptions;

  /**
   * The static cache of a DB statement stub object.
   *
   * @var \Drupal\Core\Database\StatementInterface
   */
  protected $statementStub;

  /**
   * The database target this connection is for.
   *
   * We need this information for later auditing and logging.
   *
   * @var string|null
   */
  protected $target = NULL;

  /**
   * The key representing this connection.
   *
   * The key is a unique string which identifies a database connection. A
   * connection can be a single server or a cluster of primary and replicas
   * (use target to pick between primary and replica).
   *
   * @var string|null
   */
  protected $key = NULL;

  /**
   * The current database logging object for this connection.
   *
   * @var \Drupal\Core\Database\Log|null
   */
  protected $logger = NULL;

  /**
   * Constructs a new connection instance.
   *
   * @param \EasyRdf\Sparql\Client $easy_rdf_client
   *   Object of \EasyRdf\Sparql\Client which is a database connection.
   * @param array $connection_options
   *   An associative array of connection options. See the "Database settings"
   *   section from 'sites/default/settings.php' a for a detailed description of
   *   the structure of this array.
   */
  public function __construct(Client $easy_rdf_client, array $connection_options) {
    $this->easyRdfClient = $easy_rdf_client;
    $this->connectionOptions = $connection_options;
  }

  /**
   * {@inheritdoc}
   */
  public function query(string $query, array $args = [], array $options = []): Result {
    // @todo Remove this in #55.
    // @see https://github.com/ec-europa/rdf_entity/issues/55
    if ($args) {
      throw new \InvalidArgumentException('Replacement arguments are not yet supported.');
    }

    if ($this->logger) {
      $query_start = microtime(TRUE);
    }

    try {
      // @todo Implement argument replacement in #55.
      // @see https://github.com/ec-europa/rdf_entity/issues/55
      $results = $this->easyRdfClient->query($query);
    }
    catch (EasyRdfException $exception) {
      // Re-throw the exception, but with the query as message.
      throw new SparqlQueryException('Execution of query failed: ' . $query);
    }

    if ($this->logger) {
      $query_end = microtime(TRUE);
      $this->log($query, $args, $query_end - $query_start);
    }

    return $results;
  }

  /**
   * {@inheritdoc}
   */
  public function update(string $query, array $args = [], array $options = []): Result {
    // @todo Remove this in #55.
    // @see https://github.com/ec-europa/rdf_entity/issues/55
    if ($args) {
      throw new \InvalidArgumentException('Replacement arguments are not yet supported.');
    }

    if ($this->logger) {
      $query_start = microtime(TRUE);
    }

    try {
      // @todo Implement argument replacement in #55.
      // @see https://github.com/ec-europa/rdf_entity/issues/55
      $result = $this->easyRdfClient->update($query);
    }
    catch (EasyRdfException $exception) {
      // Re-throw the exception, but with the query as message.
      throw new SparqlQueryException('Execution of query failed: ' . $query);
    }

    if ($this->logger) {
      $query_end = microtime(TRUE);
      $this->log($query, $args, $query_end - $query_start);
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getQueryUri(): string {
    return $this->easyRdfClient->getQueryUri();
  }

  /**
   * {@inheritdoc}
   */
  public function setLogger(Log $logger): void {
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public function getLogger(): ?Log {
    return $this->logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function open(array &$connection_options = []): Client {
    $enpoint_path = !empty($connection_options['database']) ? trim($connection_options['database'], ' /') : '';
    // After trimming the value might be ''. Testing again.
    $enpoint_path = $enpoint_path ?: 'sparql';
    $protocol = empty($connection_options['https']) ? 'http' : 'https';

    $connect_string = "{$protocol}://{$connection_options['host']}:{$connection_options['port']}/{$enpoint_path}";

    return new Client($connect_string);
  }

  /**
   * {@inheritdoc}
   */
  public function setTarget(string $target = NULL): void {
    if (!isset($this->target)) {
      $this->target = $target;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getTarget(): ?string {
    return $this->target;
  }

  /**
   * {@inheritdoc}
   */
  public function setKey(string $key): void {
    if (!isset($this->key)) {
      $this->key = $key;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getKey(): ?string {
    return $this->key;
  }

  /**
   * {@inheritdoc}
   */
  public function getConnectionOptions(): array {
    return $this->connectionOptions;
  }

  /**
   * {@inheritdoc}
   */
  public function destroy():void {}

  /**
   * Logs a query duration in the DB logger.
   *
   * @param string $query
   *   The query to be logged.
   * @param array $args
   *   Arguments passed to the query.
   * @param float $duration
   *   The duration of the query run.
   *
   * @throws \RuntimeException
   *   If an attempt to log was made but the logger is not started.
   */
  protected function log(string $query, array $args, float $duration): void {
    if (!$this->logger) {
      throw new \RuntimeException('Cannot log query as the logger is not started.');
    }
    $this->logger->log($this->getStatement()->setQuery($query), $args, $duration);
  }

  /**
   * Returns and statically caches a DB statement stub used to log a query.
   *
   * The Drupal core database logger cannot be swapped because, instead of being
   * injected, is hardcoded in \Drupal\Core\Database\Database::startLog(). But
   * the \Drupal\Core\Database\Log::log() is expecting a database statement of
   * type \Drupal\Core\Database\StatementInterface as first argument and the
   * SPARQL database driver uses no StatementInterface class. We workaround this
   * limitation by faking a database statement object just to honour the logger
   * requirement. We use a statement stub that only stores the connection and
   * the query to be used when logging the event.
   *
   * @return \Drupal\rdf_entity\Database\Driver\sparql\StatementStub
   *   A faked statement object.
   *
   * @see \Drupal\Core\Database\Database::startLog()
   * @see \Drupal\Core\Database\Log
   * @see \Drupal\Core\Database\StatementInterface
   * @see \Drupal\rdf_entity\Database\Driver\sparql\StatementStub
   * @see \Drupal\rdf_entity\Database\Driver\sparql\Connection::log()
   */
  protected function getStatement(): StatementStub {
    if (!isset($this->statementStub)) {
      $this->statementStub = (new StatementStub())->setDatabaseConnection($this);
    }
    return $this->statementStub;
  }

}

/**
 * @} End of "addtogroup database".
 */
