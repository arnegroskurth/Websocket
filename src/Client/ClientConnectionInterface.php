<?php

namespace ArneGroskurth\Websocket\Client;

use ArneGroskurth\Websocket\ConnectionInterface;
use ArneGroskurth\Websocket\Message;
use ArneGroskurth\Websocket\Response;


interface ClientConnectionInterface extends ConnectionInterface {

    /**
     * @return ClientInterface
     */
    public function getClient();


    /**
     * Tries to receive a message and returns the message or null on timeout.
     *
     * @return Message
     */
    public function receiveMessage();


    /**
     * Returns true if there are messages in the receive buffer.
     *
     * @return bool
     */
    public function hasBufferedMessages();


    /**
     * Returns the http response received during initial handshake.
     *
     * @return Response
     */
    public function getHandshakeResponse();
}