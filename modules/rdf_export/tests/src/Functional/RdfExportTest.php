<?php

namespace Drupal\Tests\rdf_export\Functional;

use Drupal\rdf_entity\Entity\Rdf;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\rdf_entity\Traits\RdfDatabaseConnectionTrait;

/**
 * Tests the RDF export functionality.
 *
 * @group rdf_entity
 */
class RdfExportTest extends BrowserTestBase {

  use RdfDatabaseConnectionTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['rdf_export_test'];

  /**
   * Testing entity.
   *
   * @var \Drupal\rdf_entity\RdfInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    $this->setUpSparql();
    parent::setUp();

    $this->entity = Rdf::create([
      'rid' => 'fruit',
      'id' => 'http://example.com/apple',
      'label' => 'Apple',
    ]);
    $this->entity->save();
  }

  /**
   * Tests the RDF export functionality.
   */
  public function testRdfExport() {
    $this->drupalLogin($this->drupalCreateUser(['export rdf metadata']));

    $this->drupalGet($this->entity->toUrl('rdf-export'));
    $page = $this->getSession()->getPage();
    $page->clickLink('Turtle Terse RDF Triple Language');
    $this->assertSession()->statusCodeEquals(200);
    $actual_content = $page->getContent();
    $expected_content = trim(file_get_contents(__DIR__ . "/../../fixtures/content-negotiation/turtle"));
    $this->assertEquals($expected_content, $actual_content);

    $this->drupalGet($this->entity->toUrl('rdf-export'));
    $page = $this->getSession()->getPage();
    $page->clickLink('RDF/XML');
    $this->assertSession()->statusCodeEquals(200);
    $actual_content = $page->getContent();
    $expected_content = trim(file_get_contents(__DIR__ . "/../../fixtures/content-negotiation/rdfxml"));
    $this->assertEquals($expected_content, $actual_content);
  }

  /**
   * Tests content negotiation.
   */
  public function testContentNegotiation() {
    $serializers = [
      'jsonld' => 'application/ld+json',
      'n3' => 'text/n3; charset=UTF-8',
      'ntriples' => 'application/n-triples',
      'rdfxml' => 'application/rdf+xml',
      'turtle' => 'text/turtle; charset=UTF-8',
    ];

    $this->drupalLogin($this->drupalCreateUser(['view rdf entity']));

    foreach ($serializers as $format => $content_type) {
      $url = $this->entity->toUrl('canonical', ['query' => ['_format' => $format]]);
      $this->drupalGet($url);
      $content = $this->getSession()->getPage()->getContent();
      $expected_content = trim(file_get_contents(__DIR__ . "/../../fixtures/content-negotiation/$format"));
      $this->assertEquals($expected_content, $content);
      $this->assertSession()->responseHeaderEquals('Content-Type', $content_type);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function tearDown() {
    $this->entity->delete();
    parent::tearDown();
  }

}
