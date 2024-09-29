<?php

namespace JackedPhp\JackedServer;

class Constants
{
    // ============================================================
    // OpenSwoole
    // ============================================================

    /**
     * Equivalent to: OpenSwoole\Constant::SOCK_TCP
     */
    public const OPENSWOOLE_SOCK_TCP = 1;

    /**
     * Equivalent to: OpenSwoole\Constant::SSL
     */
    public const OPENSWOOLE_SSL = 512;

    // ============================================================
    // Actions
    // ============================================================

    /**
     * Actions that are executed before the server starts.
     *
     * E.g.: Hook\Action::addAction(
     *           tag: Constants::PRE_SERVER_ACTION,
     *           functionToAdd: fn(\JackedPhp\JackedServer\Services\Server $server) => null,
     *       );
     */
    public const PRE_SERVER_ACTION = 'js-action.pre_server_action';

    /**
     * Actions that are executed after WebSocket Handshake.
     *
     * E.g.: Hook\Action::addAction(
     *           tag: Constants::HANDSHAKE_CONCLUSION,
     *           functionToAdd: fn(\JackedPhp\JackedServer\Services\Server $server) => null,
     *       );
     */
    public const HANDSHAKE_CONCLUSION = 'js-action.handshake_conclusion';

    /**
     * Actions that are executed after the server requests is concluded and response sent back.
     *
     * E.g.: Hook\Action::addAction(
     *           tag: Constants::REQUEST_CONCLUSION,
     *           functionToAdd: fn(\JackedPhp\JackedServer\Services\Server $server) => null,
     *       );
     */
    public const REQUEST_CONCLUSION = 'js-action.request_conclusion';

    // ============================================================
    // Filters
    // ============================================================

    /**
     * Filter for intercepting requests.
     * If you need to intercept requests by its conditions, you can use this filter.
     * The intercepted requests won't be handled by the default handler, and the request
     * you have available will be the default OpenSwoole Request object.
     *
     * E.g.: Hook\Filter::addFilter(
     *           tag: Constants::INTERCEPT_REQUEST,
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
     *           tag: Constants::ROUTING_FILTER,
     *           functionToAdd: fn(array $locations) => $locations,
     *       );
     */
    public const ROUTING_FILTER = 'js-filter.jacked_routing_filter';
}
