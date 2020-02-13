<?php

namespace Drupal\Tests\rdf_entity\Kernel;

use Drupal\pathauto\Entity\PathautoPattern;
use Drupal\Tests\pathauto\Functional\PathautoTestHelperTrait;
use Drupal\Tests\rdf_entity\Traits\RdfEntityCreationTrait;

/**
 * Tests rdf_entity url alias assignment.
 *
 * @group rdf_entity
 */
class PathAliasTest extends RdfKernelTestBase {

  use RdfEntityCreationTrait;
  use PathautoTestHelperTrait;

  public static $modules = [
    'datetime',
    'path',
    'path_alias',
    'pathauto',
    'system',
    'token'
  ];

  protected function setUp() {
    parent::setUp();
    $this->installEntitySchema('path_alias');
    $this->installConfig('system');
    $pattern = PathautoPattern::create([
      'id' => mb_strtolower($this->randomMachineName()),
      'type' => 'rdf_entity',
      'pattern' => '/some_base_path/[rdf_entity:label]',
      'weight' => 10,
    ]);
    $pattern->save();
  }

  /**
   * Tests rdf_entity owner functionality.
   */
  public function testAliasGeneration() {
    $rdf_entity = $this->createRdfEntity([
      'rid' => 'dummy',
      'label' => $this->randomMachineName(),
    ]);

    /** @var \Drupal\path_alias\AliasManagerInterface $alias_manager */
    $alias_manager = \Drupal::service('path_alias.manager');
    $internal_path = '/' . $rdf_entity->toUrl()->getInternalPath();
    $alias = $alias_manager->getAliasByPath($internal_path);

    $this->assertNotEquals($internal_path, $alias);
    $this->assertRegExp('#^/some_base_path/(^/)*#', $alias);
  }

}
