<?php

namespace ArneGroskurth\Websocket;

use ArneGroskurth\Websocket\Server\Server;
use ArneGroskurth\Websocket\Server\ServerInterface;
use ArneGroskurth\Websocket\Server\WebSocketApplicationInterface;


class Factory {

    /**
     * @param WebSocketApplicationInterface $application
     * @param array $options
     *
     * @return ServerInterface
     */
    public static function createServer(WebSocketApplicationInterface $application, array $options = array()) {

        return new Server($application, $options);
    }


    public static function createClient() {


    }
}