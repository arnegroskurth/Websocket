<?php

namespace ArneGroskurth\Websocket\Server;

use ArneGroskurth\Websocket\AbstractConnection;
use ArneGroskurth\Websocket\Protocol\RFC6455\RFC6455;
use React\Socket\ConnectionInterface as ReactConnectionInterface;


/**
 * @internal
 */
class ServerConnection extends AbstractConnection implements ServerConnectionInterface {

    /**
     * @var Server
     */
    protected $server;

    /**
     * @var ReactConnectionInterface
     */
    protected $reactConnection;

    /**
     * @var string
     */
    protected $remoteAddress;


    /**
     * {@inheritdoc}
     */
    public function getServer() {

        return $this->server;
    }


    /**
     * @return ReactConnectionInterface
     */
    public function getReactConnection() {

        return $this->reactConnection;
    }


    /**
     * @param ReactConnectionInterface $reactConnection
     *
     * @return AbstractConnection
     */
    public function setReactConnection($reactConnection) {

        $this->reactConnection = $reactConnection;

        return $this;
    }


    /**
     * @param Server $server
     * @param ReactConnectionInterface $reactConnection
     */
    public function __construct(Server $server, ReactConnectionInterface $reactConnection) {

        parent::__construct();

        $this->reactConnection = $reactConnection;
        $this->server = $server;

        // caching prevents warning on calling when connection has gone away
        $this->remoteAddress = $reactConnection->getRemoteAddress();
    }


    /**
     * {@inheritdoc}
     */
    public function getRemoteAddress() {

        return $this->remoteAddress;
    }


    /**
     * {@inheritdoc}
     */
    public function send($message) {

        if($this->protocol instanceof RFC6455) {

            $this->protocol->send($this, $message, $this->server->getMaxFrameSize());
        }

        else {

            $this->protocol->send($this, $message);
        }
    }


    /**
     * {@inheritdoc}
     */
    public function close($data = null) {

        $this->protocol->close($this, $data);

        $this->reactConnection->close();
    }


    /**
     * {@inheritdoc}
     */
    public function write($data) {

        $this->reactConnection->write($data);
    }
}