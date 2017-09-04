<?php

namespace Drupal\Tests\rdf_entity\Kernel;

use Drupal\rdf_entity\Entity\Query\Sparql\SparqlArg;
use Drupal\rdf_entity\Entity\Rdf;
use Drupal\Tests\joinup_core\Kernel\JoinupKernelTestBase;

/**
 * Tests date fields in rdf entities.
 *
 * @group rdf_entity
 */
class RdfDateFieldsTest extends JoinupKernelTestBase {

  /**
   * Tests handling of timestamp field properties.
   */
  public function testTimestampFieldProperties() {
    // Drupal tests explicitly set the time zone to Australia/Sydney.
    // @see web/core/tests/bootstrap.php:157
    // @see web/core/lib/Drupal/Core/Test/FunctionalTestSetupTrait.php:348
    $created_iso = '2017-09-01T18:09:22+10:00';
    $changed_iso = '2017-09-02T18:09:22+10:00';
    $created_unix = strtotime($created_iso);
    $changed_unix = strtotime($changed_iso);

    $entity = Rdf::create([
      'rid' => 'dummy',
      'label' => $this->randomMachineName(),
      'created' => $created_unix,
      'changed' => $changed_unix,
    ]);
    $entity->save();

    $loaded = $this->entityManager->getStorage('rdf_entity')->loadUnchanged($entity->id());

    $this->assertTripleDataType($loaded->id(), 'http://purl.org/dc/terms/issued', 'http://www.w3.org/2001/XMLSchema#dateTime');
    $this->assertTripleDataType($loaded->id(), 'http://example.com/modified', 'http://www.w3.org/2001/XMLSchema#integer');

    // Verify that the retrieved values are presented as timestamp.
    $this->assertEquals($created_unix, $loaded->getCreatedTime());
    $this->assertEquals($changed_unix, $loaded->getChangedTime());

    // Assert that timestamp properties mapped as integer are stored as such.
    $this->assertTripleValue($loaded->id(), 'http://example.com/modified', $changed_unix);
    // Assert the stored value of timestamps mapped as xsd:dateTime.
    $this->assertTripleValue($loaded->id(), 'http://purl.org/dc/terms/issued', $created_iso);
  }

  /**
   * Asserts the data type of a triple.
   *
   * @param string $subject
   *   The triple subject.
   * @param string $predicate
   *   The triple predicate.
   * @param string $object_data_type
   *   The expected triple object data type.
   */
  protected function assertTripleDataType($subject, $predicate, $object_data_type) {
    $subject = SparqlArg::uri($subject);
    $predicate = SparqlArg::uri($predicate);
    $object_data_type = SparqlArg::uri($object_data_type);

    $query = <<<AskQuery
ASK WHERE {
  $subject $predicate ?o .
  filter (datatype(?o) = $object_data_type)
}
AskQuery;

    $this->assertTrue($this->sparql->query($query)->getBoolean(), "Incorrect data type '$object_data_type' for predicate '$predicate'.");
  }

  /**
   * Asserts the stored value of a triple.
   *
   * @param string $subject
   *   The triple subject.
   * @param string $predicate
   *   The triple predicate.
   * @param string $expected_value
   *   The expected triple value.
   */
  protected function assertTripleValue($subject, $predicate, $expected_value) {
    $subject = SparqlArg::uri($subject);
    $predicate = SparqlArg::uri($predicate);

    $query = <<<SelectQuery
SELECT ?object WHERE {
  $subject $predicate ?object
}
SelectQuery;

    $result = $this->sparql->query($query);
    $this->assertCount(1, $result, 'Expected a single result, but got ' . $result->count());
    $this->assertEquals($expected_value, (string) $result[0]->object);
  }

}
