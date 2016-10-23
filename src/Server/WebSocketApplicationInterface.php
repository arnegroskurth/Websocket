<?php

namespace ArneGroskurth\Websocket\Server;

use ArneGroskurth\Websocket\MessageInterface;
use Zend\Http\Request;


interface WebSocketApplicationInterface {

    /**
     * @param ServerConnectionInterface $connection
     * @param Request $handshakeRequest
     */
    public function onOpen(ServerConnectionInterface $connection, Request $handshakeRequest);


    /**
     * @param ServerConnectionInterface $connection
     * @param MessageInterface $message
     */
    public function onMessage(ServerConnectionInterface $connection, MessageInterface $message);


    /**
     * @param ServerConnectionInterface $connection
     * @param \Exception $exception
     */
    public function onError(ServerConnectionInterface $connection, \Exception $exception);


    /**
     * @param ServerConnectionInterface $connection
     * @param MessageInterface $message
     */
    public function onClose(ServerConnectionInterface $connection, MessageInterface $message);
}