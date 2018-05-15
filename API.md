# RDF Entity API

@todo This document is under development.

## RDF Graphs

Entities using the SPARQL storage can be stored in different graphs. Graphs can
be used to store different versions or states of the same entity. Depending on
the use case you can use graphs to store a draft version of the entity.

### RDF graphs storage

Graphs are handled by the `RdfGraphHandler` service which is injected in the
`Query` and the `RdfEntitySparqlStorage` classes. There are a number of methods
offered to handle the graphs.

### Graphs CRUD

Graphs are config entities of type `rdf_entity_graph` and are supported by the
Drupal API. You can add, edit, delete graphs also by using the UI provided at
`/admin/config/rdf_entity/graph`. The `default` graph, shipped with `rdf_entity`
module cannot be deleted or restricted to specific entity types. However, you
can still edit its name and description. Only enabled graphs are taken into
account by the SPARQL backend.

The order of graph entities is important. You can configure a priority by
settings the `weight` property. Also this could be done in the UI.

### Handling entities and graphs

#### Entity creation

Set a specific graph to a new entity:

```php
$storage = \Drupal::entityTypeManager()->getStorage('food');
$entity = $storage->create([
  'id' => 'http://example.com',
  'type' => 'fruit',
  'graph' => 'draft',
]);
$entity->save();
```

If no `'graph'` is set, the entity will be saved in the topmost graph. The
topmost graph is the graph witch has the lowest `weight` property.

#### Reading the graph of an entity

```php
$graph_id = $entity->get('graph')->value;
// or...
$graph_id = $entity->graph->value;

```

#### Loading an entity from a specific graph

```php
$storage = \Drupal::entityTypeManager()->getStorage('food');

// Load from the default graph (the tompost graph in the list).
$entity = $storage->load($id);

// Load from the 'draft' graph.
$entity = $storage->load($id, ['draft']);

// Load from the first graph where the entity exists. First, the storage will
// attempt to load the entity from the 'draft' graph. If this entity doesn't
// exist in the 'draft' graph, will fallback to the next one which is 'sync' and
// so on. If the entity is not found in any of the graphs, normal behaviour is
// in place: will return NULL.
$entity = $storage->load($id, ['draft', 'sync', 'obsolete', ...]);

// Load multiple entities using a graph candidate list.
$entities = $storage->loadMultiple($ids, ['draft', 'sync', 'obsolete', ...]);
```

**Note**: When the list of graph candidates is not specified (first example),
the candidates are all enabled graph entities, ordered by the weight property.

#### Saving in a different graph

```php
$storage = \Drupal::entityTypeManager()->getStorage('food');
$entity = $storage->load($id, ['draft']);
$entity->set('graph', 'default')->save();
```

#### Using graphs with entity query

```php
$storage = \Drupal::entityTypeManager()->getStorage('food');
$query = $storage->getQuery;
$ids = $query
  ->condition('type', 'fruit')
  ->graphs(['default', 'draft'])
  ->execute();
```
