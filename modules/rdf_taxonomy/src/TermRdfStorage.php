<?php

declare(strict_types = 1);

namespace Drupal\rdf_taxonomy;

use Drupal\Core\Entity\EntityInterface;
use Drupal\rdf_entity\Entity\RdfEntityMapping;
use Drupal\rdf_entity\Entity\RdfEntitySparqlStorage;
use Drupal\taxonomy\TermInterface;
use Drupal\taxonomy\TermStorageInterface;
use EasyRdf\Graph;

/**
 * Defines a Controller class for taxonomy terms.
 */
class TermRdfStorage extends RdfEntitySparqlStorage implements TermStorageInterface {

  /**
   * Bundle predicate array.
   *
   * SKOS has two predicates used on concepts to point to their vocabulary.
   * this depends on their level in the hierarchy.
   *
   * @var array
   */
  protected $bundlePredicate = [
    'http://www.w3.org/2004/02/skos/core#inScheme',
    'http://www.w3.org/2004/02/skos/core#topConceptOf',
  ];

  /**
   * {@inheritdoc}
   */
  protected $rdfBundlePredicate = 'http://www.w3.org/2004/02/skos/core#inScheme';

  /**
   * Array of loaded parents keyed by child term ID.
   *
   * @var array
   */
  protected $parents = [];

  /**
   * Array of all loaded term ancestry keyed by ancestor term ID.
   *
   * @var array
   */
  protected $parentsAll = [];

  /**
   * Array of child terms keyed by parent term ID.
   *
   * @var array
   */
  protected $children = [];

  /**
   * Array of term parents keyed by vocabulary ID and child term ID.
   *
   * @var array
   */
  protected $treeParents = [];

  /**
   * Array of term ancestors keyed by vocabulary ID and parent term ID.
   *
   * @var array
   */
  protected $treeChildren = [];

  /**
   * Array of terms in a tree keyed by vocabulary ID and term ID.
   *
   * @var array
   */
  protected $treeTerms = [];

  /**
   * Array of loaded trees keyed by a cache id matching tree arguments.
   *
   * @var array
   */
  protected $trees = [];

  /**
   * Ancestor entities.
   *
   * @var \Drupal\taxonomy\TermInterface[][]
   */
  protected $ancestors;

  /**
   * {@inheritdoc}
   */
  protected function alterGraph(Graph &$graph, EntityInterface $entity): void {
    parent::alterGraph($graph, $entity);
    // @todo Document this. I have no idea what this is for, I only know that
    //   taxonomy terms require this.
    $graph->addResource($entity->id(), 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type', 'http://www.w3.org/2004/02/skos/core#Concept');

    // Remove reference to root. A taxonomy term with no reference to a parent
    // means that is under root.
    $rdf_php = $graph->toRdfPhp();
    foreach ($rdf_php as $resource_uri => $properties) {
      foreach ($properties as $property => $values) {
        // Check only for parent field.
        if ($resource_uri === $entity->id() && $property === 'http://www.w3.org/2004/02/skos/core#broaderTransitive') {
          foreach ($values as $delta => $value) {
            if ($value['value'] === '0') {
              unset($rdf_php[$resource_uri][$property][$delta]);
              break 2;
            }
          }
        }
      }
    }
    // Recreate the graph with new data.
    $graph = new Graph($graph->getUri(), $rdf_php);
  }

  /**
   * {@inheritdoc}
   */
  public function resetCache(array $ids = NULL, array $graph_ids = NULL): void {
    drupal_static_reset('taxonomy_term_count_nodes');
    $this->parents = [];
    $this->parentsAll = [];
    $this->children = [];
    $this->treeChildren = [];
    $this->treeParents = [];
    $this->treeTerms = [];
    $this->trees = [];
    parent::resetCache($ids);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteTermHierarchy($tids) {}

  /**
   * {@inheritdoc}
   */
  public function updateTermHierarchy(EntityInterface $term) {}

  /**
   * {@inheritdoc}
   */
  public function loadParents($tid) {
    $terms = [];
    /** @var \Drupal\taxonomy\TermInterface $term */
    if ($tid && $term = $this->load($tid)) {
      foreach ($this->getParents($term) as $id => $parent) {
        // This method currently doesn't return the <root> parent.
        // @see https://www.drupal.org/node/2019905
        if (!empty($id)) {
          $terms[$id] = $parent;
        }
      }
    }

    return $terms;
  }

  /**
   * Returns a list of parents of this term.
   *
   * @return \Drupal\taxonomy\TermInterface[]
   *   The parent taxonomy term entities keyed by term ID. If this term has a
   *   <root> parent, that item is keyed with 0 and will have NULL as value.
   */
  protected function getParents(TermInterface $term) {
    $parent = $term->get('parent');
    if ($parent->isEmpty()) {
      return [0 => NULL];
    }

    $ids = [];
    foreach ($term->get('parent') as $item) {
      $ids[] = $item->target_id;
    }

    if ($ids) {
      $query = \Drupal::entityQuery('taxonomy_term')
        ->condition('tid', $ids, 'IN');

      return static::loadMultiple($query->execute());
    }

    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function loadAllParents($tid) {
    /** @var \Drupal\taxonomy\TermInterface $term */
    return (!empty($tid) && $term = $this->load($tid)) ? $this->getAncestors($term) : [];
  }

  /**
   * Returns all ancestors of this term.
   *
   * @return \Drupal\taxonomy\TermInterface[]
   *   A list of ancestor taxonomy term entities keyed by term ID.
   *
   * @internal
   * @todo Refactor away when TreeInterface is introduced.
   */
  protected function getAncestors(TermInterface $term) {
    if (!isset($this->ancestors[$term->id()])) {
      $this->ancestors[$term->id()] = [$term->id() => $term];
      $search[] = $term->id();

      while ($tid = array_shift($search)) {
        foreach ($this->getParents(static::load($tid)) as $id => $parent) {
          if ($parent && !isset($this->ancestors[$term->id()][$id])) {
            $this->ancestors[$term->id()][$id] = $parent;
            $search[] = $id;
          }
        }
      }
    }
    return $this->ancestors[$term->id()];
  }

  /**
   * {@inheritdoc}
   */
  public function loadChildren($tid, $vid = NULL) {
    /** @var \Drupal\taxonomy\TermInterface $term */
    return (!empty($tid) && $term = $this->load($tid)) ? $this->getChildren($term) : [];
  }

  /**
   * Returns all children terms of this term.
   *
   * @return \Drupal\taxonomy\TermInterface[]
   *   A list of children taxonomy term entities keyed by term ID.
   *
   * @internal
   * @todo Refactor away when TreeInterface is introduced.
   */
  public function getChildren(TermInterface $term) {
    $query = \Drupal::entityQuery('taxonomy_term')
      ->condition('parent', $term->id());
    return static::loadMultiple($query->execute());
  }

  /**
   * {@inheritdoc}
   */
  public function loadTree($vid, $parent = 0, $max_depth = NULL, $load_entities = FALSE) {
    // The parent is either the root (0 or '') or a non-empty value. If NULL has
    // been passed, means that tree under a non-saved term was requested but
    // a non-saved term cannot have children.
    if ($parent === NULL) {
      return [];
    }
    // Core term uses 0 (as integer) for root level. RDF Taxonomy has string IDs
    // thus we convert 0 to '' (empty string) to denote the root level.
    $parent = $parent === 0 ? '' : $parent;

    $cache_key = implode(':', func_get_args());
    if (empty($this->trees[$cache_key])) {

      // We cache trees, so it's not CPU-intensive to call on a term and its
      // children, too.
      if (empty($this->treeChildren[$vid])) {
        $mapping = RdfEntityMapping::loadByName('taxonomy_term', $vid);
        $concept_schema = $mapping->getRdfType();
        $this->treeChildren[$vid] = [];
        $this->treeParents[$vid] = [];
        $this->treeTerms[$vid] = [];
        $query = <<<QUERY
SELECT DISTINCT ?tid ?label ?parent
WHERE {
  ?tid ?relation <$concept_schema> .
  ?tid <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://www.w3.org/2004/02/skos/core#Concept> .
  ?tid <http://www.w3.org/2004/02/skos/core#prefLabel> ?label .
  FILTER (?relation IN (<http://www.w3.org/2004/02/skos/core#inScheme>, <http://www.w3.org/2004/02/skos/core#topConceptOf>) ) .
  FILTER (lang(?label) = 'en') .
  OPTIONAL {?tid <http://www.w3.org/2004/02/skos/core#broaderTransitive> ?parent }
}
ORDER BY (STR(?label))
QUERY;
        $result = $this->sparql->query($query);
        foreach ($result as $term_res) {
          $term_parent = isset($term_res->parent) ? (string) $term_res->parent : '';
          $term = (object) [
            'tid' => (string) $term_res->tid,
            'vid' => $vid,
            'name' => (string) $term_res->label,
            'parent' => $term_parent,
            'weight' => 0,
          ];
          $this->treeChildren[$vid][$term_parent][] = $term->tid;
          $this->treeParents[$vid][$term->tid][] = $term_parent;
          $this->treeTerms[$vid][$term->tid] = $term;
        }
      }

      // Load full entities, if necessary. The entity controller statically
      // caches the results.
      $term_entities = [];
      if ($load_entities) {
        $term_entities = $this->loadMultiple(array_keys($this->treeTerms[$vid]));
      }

      $max_depth = (!isset($max_depth)) ? count($this->treeChildren[$vid]) : $max_depth;
      $tree = [];

      // Keeps track of the parents we have to process, the last entry is used
      // for the next processing step.
      $process_parents = [];
      $process_parents[] = $parent;
      // Loops over the parent terms and adds its children to the tree array.
      // Uses a loop instead of a recursion, because it's more efficient.
      while (count($process_parents)) {
        $parent = array_pop($process_parents);
        // The number of parents determines the current depth.
        $depth = count($process_parents);
        if ($max_depth > $depth && !empty($this->treeChildren[$vid][$parent])) {
          $has_children = FALSE;
          $child = current($this->treeChildren[$vid][$parent]);
          do {
            if (empty($child)) {
              break;
            }
            $term = $load_entities ? $term_entities[$child] : $this->treeTerms[$vid][$child];
            if (isset($this->treeParents[$vid][$load_entities ? $term->id() : $term->tid])) {
              // Clone the term so that the depth attribute remains correct
              // in the event of multiple parents.
              $term = clone $term;
            }
            $term->depth = $depth;
            unset($term->parent);
            $tid = $load_entities ? $term->id() : $term->tid;
            $term->parents = $this->treeParents[$vid][$tid];
            $tree[] = $term;
            if (!empty($this->treeChildren[$vid][$tid])) {
              $has_children = TRUE;

              // We have to continue with this parent later.
              $process_parents[] = $parent;
              // Use the current term as parent for the next iteration.
              $process_parents[] = $tid;

              // Reset pointers for child lists because we step in there more
              // often with multi parents.
              reset($this->treeChildren[$vid][$tid]);
              // Move pointer so that we get the correct term the next time.
              next($this->treeChildren[$vid][$parent]);
              break;
            }
          } while ($child = next($this->treeChildren[$vid][$parent]));

          if (!$has_children) {
            // We processed all terms in this hierarchy-level, reset pointer
            // so that this function works the next time it gets called.
            reset($this->treeChildren[$vid][$parent]);
          }
        }
      }
      $this->trees[$cache_key] = $tree;
    }
    return $this->trees[$cache_key];
  }

  /**
   * {@inheritdoc}
   */
  public function nodeCount($vid) {
    // @todo Is this possible to determine?
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public function resetWeights($vid) {}

  /**
   * {@inheritdoc}
   */
  public function getNodeTerms(array $nids, array $vocabs = [], $langcode = NULL) {
    // @todo Test this.
    $query = db_select('taxonomy_index', 'tn');
    $query->fields('tn', ['tid']);
    $query->addField('tn', 'nid', 'node_nid');
    $query->condition('tn.nid', $nids, 'IN');

    $results = [];
    $all_tids = [];
    foreach ($query->execute() as $term_record) {
      $results[$term_record->node_nid][] = $term_record->tid;
      $all_tids[] = $term_record->tid;
    }

    $all_terms = $this->loadMultiple($all_tids);
    $terms = [];
    foreach ($results as $nid => $tids) {
      foreach ($tids as $tid) {
        $terms[$nid][$tid] = $all_terms[$tid];
      }
    }
    return $terms;
  }

  /**
   * {@inheritdoc}
   */
  public function __sleep() {
    $vars = parent::__sleep();
    // Do not serialize static cache.
    unset($vars['parents'], $vars['parentsAll'], $vars['children'], $vars['treeChildren'], $vars['treeParents'], $vars['treeTerms'], $vars['trees']);
    return $vars;
  }

  /**
   * {@inheritdoc}
   */
  public function __wakeup() {
    parent::__wakeup();
    // Initialize static caches.
    $this->parents = [];
    $this->parentsAll = [];
    $this->children = [];
    $this->treeChildren = [];
    $this->treeParents = [];
    $this->treeTerms = [];
    $this->trees = [];
  }

}
