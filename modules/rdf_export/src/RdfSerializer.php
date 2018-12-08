<?php

declare(strict_types = 1);

namespace Drupal\rdf_export;

use Drupal\rdf_entity\Database\Driver\sparql\ConnectionInterface;
use Drupal\rdf_entity\RdfInterface;

/**
 * Service to serialise RDF entities into various formats.
 */
class RdfSerializer implements RdfSerializerInterface {

  /**
   * The Sparql connection object.
   *
   * @var \Drupal\rdf_entity\Database\Driver\sparql\ConnectionInterface
   */
  protected $sparqlEndpoint;

  /**
   * Instantiates a new RdfSerializer object.
   *
   * @param \Drupal\rdf_entity\Database\Driver\sparql\ConnectionInterface $sparqlEndpoint
   *   The Sparql connection object.
   */
  public function __construct(ConnectionInterface $sparqlEndpoint) {
    $this->sparqlEndpoint = $sparqlEndpoint;
  }

  /**
   * {@inheritdoc}
   */
  public function serializeEntity(RdfInterface $entity, string $format = 'turtle'): string {
    $query = <<<SPARQL
CONSTRUCT {
  ?s ?p ?o .
}
WHERE {
  {
    ?s ?p ?o .
    VALUES ?s { <{$entity->id()}> }
  } UNION {
     ?s ?p ?o .
     VALUES ?o { <{$entity->id()}> }
  }
}
SPARQL;

    /** @var \EasyRdf\Graph $graph */
    $graph = $this->sparqlEndpoint->constructQuery($query);
    return $graph->serialise($format);
  }

}
