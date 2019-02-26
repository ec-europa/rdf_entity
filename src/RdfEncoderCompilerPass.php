<?php

declare(strict_types = 1);

namespace Drupal\rdf_entity;

use EasyRdf\Format;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Collects all RDF encoders and stores them into a service container parameter.
 */
class RdfEncoderCompilerPass implements CompilerPassInterface {

  /**
   * {@inheritdoc}
   */
  public function process(ContainerBuilder $container): void {
    $rdf_formats = array_keys(Format::getFormats());
    $encoders = [];
    foreach ($container->findTaggedServiceIds('encoder') as $id => $attributes) {
      $class = $container->getDefinition($id)->getClass();
      $interfaces = class_implements($class);
      $format = $attributes[0]['format'];
      if (isset($interfaces[RdfEncoderInterface::class]) && in_array($format, $rdf_formats)) {
        $encoders[$format] = $format;
      }
    }
    $container->setParameter('rdf_entity.encoders', $encoders);
  }

}
