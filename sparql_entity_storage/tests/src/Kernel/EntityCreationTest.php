<?php

declare(strict_types = 1);

namespace Drupal\Tests\sparql_entity_storage\Kernel;

use Drupal\sparql_entity_storage\Exception\DuplicatedIdException;
use Drupal\sparql_test\Entity\SparqlTest;

/**
 * Tests the creation of entities based on SPARQL entity storage.
 *
 * @coversDefaultClass \Drupal\sparql_entity_storage\SparqlEntityStorage
 *
 * @group sparql_entity_storage
 */
class EntityCreationTest extends SparqlKernelTestBase {

  /**
   * Tests overlapping IDs.
   *
   * @covers ::doSave
   */
  public function testOverlappingIds(): void {
    // Create a sparql_test entity.
    SparqlTest::create([
      'type' => 'fruit',
      'id' => 'http://example.com/apple',
      'title' => 'Apple',
    ])->save();

    // Check that on saving an existing entity no exception is thrown.
    SparqlTest::load('http://example.com/apple')->save();

    // Check that new rdf_entity, with its own ID, don't raise any exception.
    SparqlTest::create([
      'type' => 'fruit',
      'id' => 'http://example.com/berry',
      'title' => 'Fruit with a different ID',
    ])->save();

    // Check that the expected exception is throw when trying to create a new
    // entity with the same ID.
    $this->setExpectedException(DuplicatedIdException::class, "Attempting to create a new entity with the ID 'http://example.com/apple' already taken.");
    SparqlTest::create([
      'type' => 'fruit',
      'id' => 'http://example.com/apple',
      'title' => "This fruit tries to steal the Apple's ID",
    ])->save();
  }

}
