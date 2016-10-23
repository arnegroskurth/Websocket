<?php

namespace ArneGroskurth\Websocket;

use ArneGroskurth\Websocket\Protocol\ProtocolInterface;


interface ConnectionInterface {

    const STATE_CONNECTING = 1;
    const STATE_OPEN = 2;
    const STATE_CLOSING = 3;


    /**
     * Returns the remote address (IPv4/6, Unix socket path) of the connected peer.
     *
     * @return string
     */
    public function getRemoteAddress();


    /**
     * Returns the state the connection is in.
     *
     * @return int
     */
    public function getState();


    /**
     * @return ProtocolInterface
     */
    public function getProtocol();


    /**
     * Sends a message to the connection peer.
     *
     * @param string $message
     */
    public function send($message);


    /**
     * Closes the connection.
     *
     * @param string $data
     */
    public function close($data = null);
}