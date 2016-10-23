<?php

namespace ArneGroskurth\Websocket;

use ArneGroskurth\Websocket\Client\Client;
use ArneGroskurth\Websocket\Client\ClientInterface;
use ArneGroskurth\Websocket\Server\Server;
use ArneGroskurth\Websocket\Server\ServerInterface;
use ArneGroskurth\Websocket\Server\WebSocketApplicationInterface;


class Factory {

    // prevent instantiation
    protected function __construct() {}


    /**
     * @param WebSocketApplicationInterface $application
     * @param array $options
     *
     * @return ServerInterface
     */
    public static function createServer(WebSocketApplicationInterface $application, array $options = array()) {

        return new Server($application, $options);
    }


    /**
     * @param string $uri
     * @param array $options
     *
     * @return ClientInterface
     */
    public static function createClient($uri, array $options = array()) {

        return new Client($uri, $options);
    }
}