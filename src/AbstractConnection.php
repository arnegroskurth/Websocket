<?php

namespace ArneGroskurth\Websocket;

use ArneGroskurth\Websocket\Protocol\ProtocolInterface;
use ArneGroskurth\Websocket\Server\ServerConnection;


/**
 * @internal
 */
abstract class AbstractConnection implements ConnectionInterface {

    /**
     * @var int
     */
    protected $state;

    /**
     * @var ProtocolInterface
     */
    protected $protocol;

    /**
     * This is reserved for the protocol handling this connection.
     *
     * @var mixed
     */
    protected $protocolBuffer;

    /**
     * This can be used to store the fragment of a message currently being received.
     *
     * @var Message
     */
    protected $message;


    /**
     * @return int
     */
    public function getState() {

        return $this->state;
    }


    /**
     * @param int $state
     *
     * @return AbstractConnection
     */
    public function setState($state) {

        $this->state = $state;

        return $this;
    }


    /**
     * @return ProtocolInterface
     */
    public function getProtocol() {

        return $this->protocol;
    }


    /**
     * @param ProtocolInterface $protocol
     *
     * @return AbstractConnection
     */
    public function setProtocol($protocol) {

        $this->protocol = $protocol;

        return $this;
    }


    /**
     * @return mixed
     */
    public function getProtocolBuffer() {

        return $this->protocolBuffer;
    }


    /**
     * @param mixed $protocolBuffer
     *
     * @return AbstractConnection
     */
    public function setProtocolBuffer($protocolBuffer) {

        $this->protocolBuffer = $protocolBuffer;

        return $this;
    }


    /**
     * @return Message
     */
    public function getMessage() {

        return $this->message;
    }


    /**
     * @param Message $message
     *
     * @return AbstractConnection
     */
    public function setMessage($message) {

        $this->message = $message;

        return $this;
    }


    public function __construct() {

        $this->state = static::STATE_CONNECTING;
    }


    /**
     * @return int
     */
    public function getDirection() {

        return ($this instanceof ServerConnection) ? ProtocolInterface::DIRECTION_SERVER_TO_CLIENT : ProtocolInterface::DIRECTION_CLIENT_TO_SERVER;
    }


    /**
     * @return int
     */
    public function getOtherDirection() {

        return ($this instanceof ServerConnection) ? ProtocolInterface::DIRECTION_CLIENT_TO_SERVER : ProtocolInterface::DIRECTION_SERVER_TO_CLIENT;
    }


    /**
     * Directly writes data to the underlying link.
     *
     * @param string $data
     */
    abstract function write($data);
}