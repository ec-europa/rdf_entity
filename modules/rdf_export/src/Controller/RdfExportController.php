<?php

namespace Drupal\rdf_export\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\rdf_entity\RdfSerializerInterface;
use EasyRdf\Format;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provides route responses for rdf_entity.module.
 */
class RdfExportController extends ControllerBase {

  /**
   * The RDF serializer service.
   *
   * @var \Drupal\rdf_entity\RdfSerializerInterface
   */
  protected $rdfSerializer;

  /**
   * Instantiates a new RdfExportController object.
   *
   * @param \Drupal\rdf_entity\RdfSerializerInterface $rdf_serializer
   *   The RDF serializer interface.
   */
  public function __construct(RdfSerializerInterface $rdf_serializer) {
    $this->rdfSerializer = $rdf_serializer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('rdf_entity.serializer')
    );
  }

  /**
   * Build an list of possible download links to RDF serialization methods.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The RouteMatch object.
   *
   * @return array
   *   Render array with list of download links.
   */
  public function downloadLinks(RouteMatchInterface $route_match) {
    $parameter_name = $route_match->getRouteObject()
      ->getOption('entity_type_id');
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $route_match->getParameter($parameter_name);
    $list = ['#theme' => 'item_list'];
    foreach ($this->getSerializerFormats() as $format_type => $format) {
      $link = Url::fromRoute("entity.$parameter_name.rdf_export_download", [
        'export_format' => $format_type,
        $parameter_name => $entity->id(),
      ]);
      $list['#items'][] = ['#markup' => $this->l($format->getLabel(), $link)];
    }

    $output = [
      'list' => $list,
    ];

    return $output;
  }

  /**
   * Download callback for the exported RDF.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The RouteMatch object.
   * @param string $export_format
   *   The serialization format (e.g. turtle, rdfxml, ...).
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Response object with correct headers set.
   */
  public function download(RouteMatchInterface $route_match, $export_format) {
    $formats = $this->getSerializerFormats();
    if (!isset($formats[$export_format])) {
      throw new AccessDeniedHttpException();
    }
    $format = $formats[$export_format];
    $entity_type_id = $route_match->getRouteObject()
      ->getOption('entity_type_id');
    $entity = $route_match->getParameter($entity_type_id);
    if (!$entity || !$entity instanceof EntityInterface) {
      throw new AccessDeniedHttpException();
    }
    $output = $this->rdfSerializer->serializeEntity($entity, $export_format);

    $response = new Response();
    $response->setContent($output);
    $response->headers->set('Content-Type', $format->getDefaultMimeType());
    $disposition = $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, 'export.' . $format->getDefaultExtension());
    $response->headers->set('Content-Disposition', $disposition);
    return $response;
  }

  /**
   * Builds a list of supported serialization formats.
   */
  protected function getSerializerFormats() {
    // Many more are supported...
    // @todo Move this to a settings form.
    $white_list = $this->config('rdf_export.settings')->get('export_types');
    $list = [];
    $formats = Format::getFormats();
    /** @var \EasyRdf\Format $format */
    foreach ($formats as $format) {
      if (!in_array($format->getName(), $white_list)) {
        continue;
      }
      if ($format->getSerialiserClass()) {
        $list[$format->getName()] = $format;
      }
    }
    return $list;
  }

}
