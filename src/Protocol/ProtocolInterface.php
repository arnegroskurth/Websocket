<?php

namespace ArneGroskurth\Websocket\Protocol;

use ArneGroskurth\Websocket\AbstractConnection;
use Zend\Http\Request;
use Zend\Http\Response;


/**
 * @internal
 */
interface ProtocolInterface {

    const DIRECTION_CLIENT_TO_SERVER = 1;
    const DIRECTION_SERVER_TO_CLIENT = 2;


    /**
     * @param AbstractConnection $connection
     * @param string $path
     * @param array $additionalHeaders
     */
    public function request(AbstractConnection $connection, $path = '/', array $additionalHeaders = array());


    /**
     * @param AbstractConnection $connection
     * @param Response $response
     */
    public function handleResponse(AbstractConnection $connection, Response $response);


    /**
     * Returns true if protocol can handle received request.
     *
     * @param Request $request
     *
     * @return bool
     */
    public function canHandleRequest(Request $request);


    /**
     * Performs protocol specific handshake on received request.
     *
     * @param AbstractConnection $connection
     * @param Request $request
     */
    public function handleRequest(AbstractConnection $connection, Request $request);


    /**
     * Encodes the given data according to protocol and sends them to peer.
     *
     * @param AbstractConnection $connection
     * @param string $payload
     */
    public function send(AbstractConnection $connection, $payload);


    /**
     * Decodes the given data according to protocol and returns its payload if possible (yet).
     *
     * @param AbstractConnection $connection
     * @param string $data
     *
     * @return \SplQueue Received messages
     */
    public function receive(AbstractConnection $connection, $data);


    /**
     * Returns true if after a preceding call to receive() further data is expected.
     * This might happen e.g. when the last transmission ended not on a message- or frame-border.
     * This function is only meaningful in a synchronous connection handling.
     *
     * @param AbstractConnection $connection
     *
     * @return bool
     */
    public function expectingData(AbstractConnection $connection);


    /**
     * Closes the connection according to protocol.
     *
     * @param AbstractConnection $connection
     * @param string $data
     */
    public function close(AbstractConnection $connection, $data = null);
}