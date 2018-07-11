<?php

declare(strict_types = 1);

namespace Drupal\rdf_entity\Database\Driver\sparql;

use Drupal\Core\Database\Log as DatabaseLog;
use EasyRdf\Http\Response;
use EasyRdf\Sparql\Client;
use EasyRdf\Sparql\Result;

/**
 * An interface for the rdf_entity Connection class.
 */
interface ConnectionInterface {

  /**
   * Executes the actual query against the Sparql endpoint.
   *
   * @param string $query
   *   The query to execute.
   *
   * @return \EasyRdf\Sparql\Result
   *   The query result.
   */
  public function query(string $query): Result;

  /**
   * Execute the actual update query against the Sparql endpoint.
   *
   * @param string $query
   *   The query string.
   *
   * @return \EasyRdf\Http\Response
   *   The response object.
   */
  public function update(string $query): Response;

  /**
   * Helper to get the query. Called from the logger.
   *
   * @return string
   *   The query string.
   */
  public function getQueryString(): string;

  /**
   * Returns the database connection string.
   *
   * @return string
   *   The query uri string.
   */
  public function getQueryUri(): string;

  /**
   * Associates a logging object with this connection.
   *
   * @param \Drupal\Core\Database\Log $logger
   *   The logging object we want to use.
   */
  public function setLogger(DatabaseLog $logger): void;

  /**
   * Gets the current logging object for this connection.
   *
   * @return \Drupal\rdf_entity\Database\Driver\sparql\Log|null
   *   The current logging object for this connection. If there isn't one,
   *   NULL is returned.
   */
  public function getLogger(): ?Log;

  /**
   * Initialize the database connection.
   *
   * @param array $connection_options
   *   The connection options as defined in settings.php.
   *
   * @return \EasyRdf\Sparql\Client
   *   The EasyRdf connection.
   */
  public static function open(array &$connection_options = []): Client;

  /**
   * Tells this connection object what its target value is.
   *
   * This is needed for logging and auditing. It's sloppy to do in the
   * constructor because the constructor for child classes has a different
   * signature. We therefore also ensure that this function is only ever
   * called once.
   *
   * @param string $target
   *   (optional) The target this connection is for.
   */
  public function setTarget(string $target = NULL): void;

  /**
   * Returns the target this connection is associated with.
   *
   * @return string|null
   *   The target string of this connection, or NULL if no target is set.
   */
  public function getTarget(): ?string;

  /**
   * Tells this connection object what its key is.
   *
   * @param string $key
   *   The key this connection is for.
   */
  public function setKey(string $key): void;

  /**
   * Returns the key this connection is associated with.
   *
   * @return string|null
   *   The key of this connection, or NULL if no key is set.
   */
  public function getKey(): ?string;

  /**
   * Returns the connection information for this connection object.
   *
   * Note that Database::getConnectionInfo() is for requesting information
   * about an arbitrary database connection that is defined. This method
   * is for requesting the connection information of this specific
   * open connection object.
   *
   * @return array
   *   An array of the connection information. The exact list of
   *   properties is driver-dependent.
   */
  public function getConnectionOptions(): array;

  /**
   * Destroys the db connection.
   */
  public function destroy(): void;

}
