<?php

namespace ArneGroskurth\Websocket\Server;

use ArneGroskurth\Websocket\ClosingMessage;
use ArneGroskurth\Websocket\ConnectionInterface;
use ArneGroskurth\Websocket\WebsocketException;
use ArneGroskurth\Websocket\Protocol\Registry;
use ArneGroskurth\Websocket\Protocol\RFC6455\RFC6455;
use ArneGroskurth\Websocket\Request;
use ArneGroskurth\Websocket\Response;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectionInterface as ReactConnectionInterface;
use React\Socket\ServerInterface as ReactServerInterface;


/**
 * @internal
 */
class Server implements ServerInterface  {

    /**
     * @var WebSocketApplicationInterface
     */
    protected $application;

    /**
     * @var LoopInterface
     */
    protected $eventLoop;

    /**
     * @var ReactServerInterface
     */
    protected $reactServer;

    /**
     * @var string
     */
    protected $listen;

    /**
     * @var int
     */
    protected $port;

    /**
     * @var float
     */
    protected $timeout;

    /**
     * @var int
     */
    protected $maxFrameSize;

    /**
     * @var \SplObjectStorage
     */
    protected $connections;

    /**
     * @var Registry
     */
    protected static $protocolRegistry;


    /**
     * {@inheritdoc}
     */
    public function getListen() {

        return $this->listen;
    }


    /**
     * {@inheritdoc}
     */
    public function getPort() {

        return ($this->reactServer === null) ? $this->port : $this->reactServer->getPort();
    }


    /**
     * {@inheritdoc}
     */
    public function getTimeout() {

        return $this->timeout;
    }


    /**
     * {@inheritdoc}
     */
    public function getMaxFrameSize() {

        return $this->maxFrameSize;
    }


    /**
     * {@inheritdoc}
     */
    public function __construct(WebSocketApplicationInterface $application, array $options = array()) {

        $this->application = $application;

        $this->parseOptions($options);
    }


    /**
     * {@inheritdoc}
     */
    public function run(LoopInterface $eventLoop = null) {

        if($this->reactServer !== null) {
            return;
        }

        $this->connections = new \SplObjectStorage();

        $this->eventLoop = ($eventLoop === null) ? Factory::create() : $eventLoop;

        $this->reactServer = new \React\Socket\Server($this->eventLoop);
        $this->reactServer->on('connection', array($this, 'handleConnection'));
        $this->reactServer->listen($this->port, $this->listen);

        $this->eventLoop->run();
    }


    /**
     * @internal
     * @param ReactConnectionInterface $reactConnection
     */
    public function handleConnection(ReactConnectionInterface $reactConnection) {

        $this->connections->attach($reactConnection, new ServerConnection($this, $reactConnection));

        $reactConnection->on('data', array($this, 'handleData'));
        $reactConnection->on('error', array($this, 'handleError'));
        $reactConnection->on('end', array($this, 'handleEnd'));
    }


    /**
     * @internal
     * @param string $data
     * @param ReactConnectionInterface $reactConnection
     */
    public function handleData($data, ReactConnectionInterface $reactConnection) {

        /** @var ServerConnection $connection */
        $connection = $this->connections[$reactConnection];

        // interpret received data and eventually emit message to app
        if($connection->getState() === ConnectionInterface::STATE_OPEN) {

            try {

                $messageQueue = $connection->getProtocol()->receive($connection, $data);
            }
            catch(\Exception $exception) {

                $this->application->onError($connection, $exception);

                return;
            }

            while(!$messageQueue->isEmpty()) {

                $message = $messageQueue->dequeue();

                if($message instanceof ClosingMessage) {

                    $connection->setState(ConnectionInterface::STATE_CLOSING);

                    $this->application->onClose($connection, $message);

                    break;
                }

                else {

                    $this->application->onMessage($connection, $message);
                }
            }
        }

        // http request header received
        elseif($connection->getState() === ConnectionInterface::STATE_CONNECTING) {

            $request = Request::extractFromData($data);
            $protocol = static::getProtocolRegistry()->findProtocol($request);

            // unsupported protocol
            if($protocol === null) {

                $reactConnection->write(Response::create(400));
                $reactConnection->end();

                return;
            }

            try {

                $reactConnection->write($protocol->handleRequest($connection, $request));
            }
            catch(WebsocketException $exception) {

                $reactConnection->write(Response::create(400));
                $reactConnection->end();

                return;
            }

            $connection->setProtocol($protocol);
            $connection->setState(ConnectionInterface::STATE_OPEN);

            $this->application->onOpen($connection);
        }
    }


    /**
     * @internal
     * @param \Exception $exception
     * @param ReactConnectionInterface $reactConnection
     */
    public function handleError(\Exception $exception, ReactConnectionInterface $reactConnection) {

        /** @var ServerConnection $connection */
        $connection = $this->connections[$reactConnection];

        // give app a chance to handle error if coming from open connection
        if($connection->getState() === ConnectionInterface::STATE_OPEN) {

            $this->application->onError($connection, $exception);
        }

        // abort connection setup
        elseif($connection->getState() === ConnectionInterface::STATE_CONNECTING) {

            $reactConnection->end();
        }
    }


    /**
     * @internal
     * @param ReactConnectionInterface $reactConnection
     */
    public function handleEnd(ReactConnectionInterface $reactConnection) {

        /** @var ServerConnection $connection */
        $connection = $this->connections[$reactConnection];

        // inform app of abnormally closed connection
        if($connection->getState() === ConnectionInterface::STATE_OPEN) {

            $connection->setState(ConnectionInterface::STATE_CLOSING);

            $this->application->onClose($connection, new ClosingMessage(null, RFC6455::CLOSE_ABNORMAL));
        }

        $reactConnection->close();

        $this->connections->detach($reactConnection);
    }


    /**
     * @param array $options
     *
     * @throws WebsocketException
     */
    protected function parseOptions(array $options) {

        $defaults = array(
            'listen' => '0.0.0.0',
            'port' => 8080,
            'timeout' => ini_get('default_socket_timeout'),
            'maxFrameSize' => 1 << 15 // 32kB
        );

        if($invalid = array_diff_key($options, $defaults)) {

            throw new WebsocketException(sprintf('Invalid option(s) given: %s.', implode(', ', $invalid)));
        }

        $options = array_merge($defaults, $options);

        $this->listen = $options['listen'];
        $this->port = $options['port'];
        $this->timeout = $options['timeout'];
        $this->maxFrameSize = $options['maxFrameSize'];
    }


    /**
     * @return Registry
     */
    protected static function getProtocolRegistry() {

        if(static::$protocolRegistry === null) {

            static::$protocolRegistry = new Registry();
            static::$protocolRegistry->addProtocol(new RFC6455());
        }

        return static::$protocolRegistry;
    }
}