<?php

namespace JackedPhp\JackedServer;

use JackedPhp\JackedServer\Constants as JackedServerConstants;

class Constants
{
    // OpenSwoole

    /**
     * Equivalent to: OpenSwoole\Constant::SOCK_TCP
     */
    public const OPENSWOOLE_SOCK_TCP = 1;

    /**
     * Equivalent to: OpenSwoole\Constant::SSL
     */
    public const OPENSWOOLE_SSL = 512;

    // Actions

    /**
     *
     */
    public const PRE_SERVER_ACTION = 'js-action.pre_server_action';

    // Filters

    /**
     * Filter for intercepting requests.
     * If you need to intercept requests by its conditions, you can use this filter.
     * The intercepted requests won't be handled by the default handler, and the request
     * you have available will be the default OpenSwoole Request object.
     *
     * E.g.: Hook\Filter::addFilter(
     *           tag: JackedServerConstants::INTERCEPT_REQUEST,
     *           functionToAdd: fn(array $interceptedUris) => $interceptedUris,
     *       );
     */
    public const INTERCEPT_REQUEST = 'js-filter.jacked_intercept_requests';

    /**
     * Filter for routing requests.
     * If you need to route requests by its conditions, you can use this filter.
     * The routed requests will be handled by the default handler, and the requests
     * will be routed according to the routing specifications.
     *
     * E.g.: Hook\Filter::addFilter(
     *           tag: JackedServerConstants::ROUTING_FILTER,
     *           functionToAdd: fn(array $locations) => $locations,
     *       );
     */
    public const ROUTING_FILTER = 'js-filter.jacked_routing_filter';
}
