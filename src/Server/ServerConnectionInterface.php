<?php

namespace ArneGroskurth\Websocket\Server;

use ArneGroskurth\Websocket\ConnectionInterface;


interface ServerConnectionInterface extends ConnectionInterface {

    /**
     * Gets the Server instance associated with this connection.
     *
     * @return ServerInterface
     */
    public function getServer();
}