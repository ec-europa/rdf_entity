<?php

declare(strict_types = 1);

namespace Drupal\rdf_entity\Database\Driver\sparql;

use Drupal\Core\Database\Log as DatabaseLog;
use Drupal\rdf_entity\Exception\SparqlQueryException;
use EasyRdf\Http\Exception as EasyRdfException;
use EasyRdf\Sparql\Client;
use EasyRdf\Sparql\Result;

/**
 * @addtogroup database
 * @{
 */
class Connection implements ConnectionInterface {

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
   * @var \Drupal\rdf_entity\Database\Driver\sparql\Log|null
   */
  protected $logger = NULL;

  /**
   * Constructs a Connection object.
   *
   * @param \EasyRdf\Sparql\Client $connection
   *   Object of \EasyRdf\Sparql\Client which is a database connection.
   * @param array $connection_options
   *   An array of options for the connection. May include the following:
   *   - prefix
   *   - namespace
   *   - Other driver-specific options.
   */
  public function __construct(Client $connection, array $connection_options) {
    $this->connection = $connection;
    $this->connectionOptions = $connection_options;
  }

  /**
   * {@inheritdoc}
   */
  public function query(string $query): Result {
    if (!empty($this->logger)) {
      // @todo Fix this. Logger should have been auto started.
      // Probably related to the overwritten log object in $this->setLogger.
      // Look at
      // \Drupal\webprofiler\StackMiddleware\WebprofilerMiddleware::handle.
      $this->logger->start('webprofiler');
      $query_start = microtime(TRUE);
    }

    try {
      $results = $this->connection->query($query);
    }
    catch (EasyRdfException $e) {
      // Re-throw the exception, but with the query as message.
      throw new SparqlQueryException('Execution of query failed: ' . $query);
    }
    catch (\Exception $e) {
      throw $e;
    }

    if (!empty($this->logger)) {
      $query_end = microtime(TRUE);
      $this->query = $query;
      // @fixme Passing in an incorrect but seemingly compatible object.
      // This will most likely break in PHP7 (incorrect type hinting).
      // Replace array($query) with the placeholder version.
      // I should probably implement the statement interface...
      $this->logger->log($this, [$query], $query_end - $query_start);
    }

    return $results;
  }

  /**
   * {@inheritdoc}
   */
  public function update(string $query): Result {
    if (!empty($this->logger)) {
      // @todo Fix this. Logger should have been auto started.
      // Probably related to the overwritten log object in $this->setLogger.
      // Look at
      // \Drupal\webprofiler\StackMiddleware\WebprofilerMiddleware::handle.
      $this->logger->start('webprofiler');
      $query_start = microtime(TRUE);
    }

    try {
      $results = $this->connection->update($query);
    }
    catch (EasyRdfException $e) {
      // Re-throw the exception, but with the query as message.
      throw new SparqlQueryException('Execution of query failed: ' . $query);
    }
    catch (\Exception $e) {
      throw $e;
    }

    if (!empty($this->logger)) {
      $query_end = microtime(TRUE);
      $this->query = $query;
      // @fixme Passing in an incorrect but seemingly compatible object.
      // This will most likely break in PHP7 (incorrect type hinting).
      // Replace array($query) with the placeholder version.
      // I should probably implement the statement interface...
      $this->logger->log($this, [$query], $query_end - $query_start);
    }

    return $results;
  }

  /**
   * {@inheritdoc}
   */
  public function getQueryString(): string {
    return $this->query;
  }

  /**
   * {@inheritdoc}
   */
  public function getQueryUri(): string {
    return $this->connection->getQueryUri();
  }

  /**
   * {@inheritdoc}
   */
  public function setLogger(DatabaseLog $logger): void {
    // Because we're incompatible with the PDO logger,
    // we ignore this, and create our own object.
    // @todo Avoid doing this. It's not ok...
    $this->logger = new Log($this->getKey());
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
    // @todo Get endpoint string from settings file.
    $connect_string = 'http://' . $connection_options['host'] . ':' . $connection_options['port'] . '/sparql';
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

}

/**
 * @} End of "addtogroup database".
 */
