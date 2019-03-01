<?php

namespace Drupal\Tests\rdf_entity\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\rdf_entity\Entity\Rdf;
use Drupal\Tests\rdf_entity\Traits\RdfDatabaseConnectionTrait;

/**
 * Tests the SPARQL serializer.
 *
 * @group rdf_entity
 */
class SparqlSerializerTest extends KernelTestBase {

  use RdfDatabaseConnectionTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'rdf_entity',
    'sparql_entity_serializer_test',
    'rest',
    'serialization',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->setUpSparql();
    $this->installConfig(['rdf_entity', 'sparql_entity_serializer_test']);
  }

  /**
   * Tests content negotiation.
   */
  public function testContentNegotiation() {
    $entity = Rdf::create([
      'rid' => 'fruit',
      'id' => 'http://example.com/apple',
      'label' => 'Apple',
    ]);
    $entity->save();

    $encoders = $this->container->getParameter('sparql_entity.encoders');
    $serializer = $this->container->get('rdf_entity.serializer');
    foreach ($encoders as $format => $content_type) {
      $serialized = trim($serializer->serializeEntity($entity, $format));
      $expected = trim(file_get_contents(__DIR__ . "/../../fixtures/content-negotiation/$format"));
      $this->assertEquals($expected, $serialized);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function tearDown() {
    Rdf::load('http://example.com/apple')->delete();
    parent::tearDown();
  }

}
