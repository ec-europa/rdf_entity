<?php

declare(strict_types = 1);

namespace Drupal\rdf_export;

use Drupal\Component\Utility\SortArray;
use Drupal\rdf_entity\Database\Driver\sparql\ConnectionInterface;
use Drupal\rdf_entity\RdfInterface;
use EasyRdf\Graph;

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

    $graph = $this->sparqlEndpoint->constructQuery($query);
    return $this->getSortedGraph($graph)->serialise($format);
  }

  /**
   * Returns the graph sorted by predicate and object.
   *
   * Construct queries are not returning the values in a predictable order even
   * ORDER BY is used. Thus we have to ensure that the serialized object is
   * always the same.
   *
   * @param \EasyRdf\Graph $graph
   *   The graph to be sorted.
   *
   * @return \EasyRdf\Graph
   *   The resulting sorted graph.
   */
  protected function getSortedGraph(Graph $graph): Graph {
    $data = $graph->toRdfPhp();
    $type_key = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type';

    // The sequence of the fetched entries is not guaranteed, yet, the query
    // will fetch only one full entity, so we can find the main id using the
    // type.
    while ($entity_id = key($data)) {
      if (isset($data[$entity_id][$type_key])) {
        break;
      }
      next($data);
    }

    // Sort objects inside each predicate.
    foreach ($data[$entity_id] as $predicate => &$items) {
      uasort($items, function (array $a, array $b): int {
        return SortArray::sortByKeyInt($a, $b, 'value');
      });
    }

    // Sort by predicate.
    ksort($data[$entity_id]);
    $data[$entity_id] = [$type_key => $data[$entity_id][$type_key]] + $data[$entity_id];

    return new Graph($entity_id, $data, 'php');
  }

}
