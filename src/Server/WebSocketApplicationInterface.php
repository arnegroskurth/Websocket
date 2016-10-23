<?php

namespace ArneGroskurth\Websocket\Server;

use ArneGroskurth\Websocket\MessageInterface;


interface WebSocketApplicationInterface {

    /**
     * @param ServerConnectionInterface $connection
     */
    public function onOpen(ServerConnectionInterface $connection);


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