<?php

namespace Drupal\rdf_entity\Event;

/**
 * Contains all events thrown by the rdf_entity module.
 */
final class RdfEntityEvents {

  /**
   * The event triggered when determining the graph during parameter conversion.
   *
   * @Event
   *
   * @see \Drupal\rdf_entity\ParamConverter\RdfEntityConverter::convert()
   *
   * @var string
   */
  const GRAPH_ENTITY_CONVERT = 'rdf_graph.entity_convert';

  /**
   * The name of the event triggered when an inbound value is being processed.
   *
   * @Event
   *
   * @see \Drupal\rdf_entity\RdfFieldHandler::getInboundValue()
   *
   * @var string
   */
  const INBOUND_VALUE = 'rdf_entity.inbound_value';

  /**
   * The name of the event triggered when an outbound value is being processed.
   *
   * @Event
   *
   * @see \Drupal\rdf_entity\RdfFieldHandler::getOutboundValue()
   *
   * @var string
   */
  const OUTBOUND_VALUE = 'rdf_entity.outbound_value';

  /**
   * The name of the event triggered when building the list of default graphs.
   *
   * @Event
   *
   * @see \Drupal\rdf_entity\RdfGraphHandler::getEntityTypeDefaultGraphIds()
   *
   * @var string
   */
  const DEFAULT_GRAPHS = 'rdf_entity.default_graphs';

}
