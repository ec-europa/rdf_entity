<?php

declare(strict_types = 1);

namespace Drupal\Tests\sparql_entity_storage\Kernel;

use Drupal\sparql_test\Entity\SparqlTest;

/**
 * Tests the support of saving various encoded stings in the triple store.
 *
 * @group sparql_entity_storage
 */
class EncodingTest extends SparqlKernelTestBase {

  /**
   * Test that naughty strings can safely be saved to the database.
   */
  public function testEncoding(): void {
    $path = DRUPAL_ROOT . "/../vendor/minimaxir/big-list-of-naughty-strings/blns.json";
    if (!file_exists($path)) {
      // Retry with the vendor directory in the Drupal root.
      $path = DRUPAL_ROOT . "/vendor/minimaxir/big-list-of-naughty-strings/blns.json";
      if (!file_exists($path)) {
        $this->markTestSkipped('Library minimaxir/big-list-of-naughty-strings is required.');
        return;
      }
    }
    $json = file_get_contents($path);
    $naughty_strings = json_decode($json);
    foreach ($naughty_strings as $naughty_string) {
      // Ignore the empty string test, as the field won't be set.
      if ($naughty_string === "") {
        continue;
      }
      $rdf = SparqlTest::create([
        'type' => 'fruit',
        'title' => 'Berry',
        'text' => $naughty_string,
      ]);
      try {
        $rdf->save();
      }
      catch (\Exception $e) {
        fwrite(STDOUT, $e->getMessage() . "\n");
        fwrite(STDOUT, $e->getTraceAsString() . "\n");
        $msg = sprintf("Entity saved for naughty string '%s'.", $naughty_string);
        $this->assertTrue(FALSE, $msg);
      }

      $query = \Drupal::entityQuery('sparql_test')
        ->condition('title', 'Berry')
        ->condition('type', 'fruit')
        ->range(0, 1);

      $result = $query->execute();
      $msg = sprintf("Loaded naughty object '%s'.", $naughty_string);
      $this->assertFalse(empty($result), $msg);

      $loaded_rdf = NULL;
      try {
        $loaded_rdf = SparqlTest::load(reset($result));
      }
      catch (\Exception $e) {
        fwrite(STDOUT, $e->getMessage() . "\n");
        fwrite(STDOUT, $e->getTraceAsString() . "\n");
        $msg = sprintf("Entity loaded for naughty string '%s'.", $naughty_string);
        $this->assertTrue(FALSE, $msg);
      }

      $field = $loaded_rdf->get('text');
      $msg = sprintf("Field was empty for naughty string '%s'.", $naughty_string);
      $this->assertTrue($field, $msg);
      $first = $field->first();
      $msg = sprintf("First value set for naughty string '%s'.", $naughty_string);
      $this->assertTrue($first, $msg);
      $text = $first->getValue();

      $msg = sprintf("Naughty string '%s' was correctly read back.", $naughty_string);
      $this->assertEquals($text['value'], $naughty_string, $msg);
      $rdf->delete();
    }
  }

}
