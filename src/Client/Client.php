<?php

namespace ArneGroskurth\Websocket\Client;

use ArneGroskurth\Url\Url;
use ArneGroskurth\Url\UrlException;
use ArneGroskurth\Websocket\WebsocketException;
use ArneGroskurth\Websocket\Protocol\Registry;
use ArneGroskurth\Websocket\Protocol\RFC6455\RFC6455;
use ArneGroskurth\Websocket\Response;


class Client implements ClientInterface {

    /**
     * @var string
     */
    protected $uri;

    /**
     * @var float
     */
    protected $timeout;

    /**
     * @var bool
     */
    protected $persistent;

    /**
     * @var int
     */
    protected $maxFrameSize;

    /**
     * @var string
     */
    protected $protocol;

    /**
     * @var array
     */
    protected $headers;

    /**
     * @var ClientConnection
     */
    protected $connection;

    /**
     * @var Registry
     */
    protected static $protocolRegistry;


    /**
     * {@inheritdoc}
     */
    public function getUri() {

        return $this->uri;
    }


    /**
     * @return bool
     */
    public function isUriUnixSocket() {

        return substr($this->uri, 0, 7) === 'unix://';
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
    public function isPersistent() {

        return $this->persistent;
    }


    /**
     * {@inheritdoc}
     */
    public function getMaxFrameSize() {

        return $this->maxFrameSize;
    }


    /**
     * @return string
     */
    public function getProtocol() {

        return $this->protocol;
    }


    /**
     * {@inheritdoc}
     */
    public function getConnection() {

        if($this->connection === null) {

            $this->connect();
        }

        return $this->connection;
    }


    /**
     * @param $uri
     * @param array $options
     */
    public function __construct($uri, array $options = array()) {

        $this->parseUri($uri);
        $this->parseOptions($options);
    }


    /**
     * Opens connection to the server.
     *
     * @throws WebsocketException
     */
    protected function connect() {

        $address = $this->uri;
        $path = '/';

        if(!$this->isUriUnixSocket()) {

            $url = new Url($this->uri);
            $url->setScheme('tcp');

            $address = $url->getUrl(Url::SCHEME + Url::PORT);
            $path = $url->getPath();
        }


        $stream = @stream_socket_client($address, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT | ($this->persistent ? STREAM_CLIENT_PERSISTENT : 0));

        if($stream === false || !is_resource($stream)) {

            throw new WebsocketException(sprintf('Failed to connect to %s. Error: [%d] %s', $address, $errno, $errstr));
        }

        stream_set_timeout($stream, $this->timeout);

        // todo: detect reusage of persistent connection and skip handshake
        $connection = new ClientConnection($this, $stream);
        $connection->setProtocol(static::getProtocolRegistry()->getProtocolByName($this->protocol));
        $connection->getProtocol()->request($connection, $path, $this->headers);

        $response = Response::extractFromData($connection->receiveHttpHead());

        $connection->getProtocol()->handleResponse($connection, $response);

        $connection->setHandshakeResponse($response);

        $this->connection = $connection;
    }


    /**
     * Parses given uri and save to attribute.
     *
     * @param string $uri
     *
     * @throws WebsocketException
     */
    protected function parseUri($uri) {

        if(substr($uri, 0, 7) === 'unix://') {

            $path = substr($uri, 7);

            if(!is_file($path)) {

                throw new WebsocketException('Invalid unix socket path given.');
            }

            $this->uri = $uri;
        }

        else {

            try {

                $url = new Url($uri);

                if($url->getScheme() === null) {
                    $url->setScheme('ws');
                }

                if($url->getPort() === null) {
                    $url->setPort(80);
                }

                if(!in_array($url->getScheme(), array('ws', 'wss'))) {

                    throw new WebsocketException(sprintf('Invalid uri scheme: %s.', $url->getScheme()));
                }

                $this->uri = $url->getUrl();

            }
            catch(UrlException $exception) {

                throw new WebsocketException('Malformed uri given.', 0, $exception);
            }
        }
    }


    /**
     * Parses and validates given options array and writes them to corresponding attributes.
     *
     * @param array $options
     *
     * @throws WebsocketException
     */
    protected function parseOptions(array $options) {

        $defaults = array(
            'protocol' => 'RFC6455',
            'persistent' => false,
            'timeout' => ini_get('default_socket_timeout'),
            'maxFrameSize' => pow(2, 15), // 32 kB
            'headers' => array()
        );

        if($invalid = array_diff_key($options, $defaults)) {

            throw new WebsocketException(sprintf('Invalid option(s) given: %s.', implode(', ', $invalid)));
        }

        $options = array_merge($defaults, $options);

        if(static::getProtocolRegistry()->getProtocolByName($options['protocol']) === null) {

            throw new WebsocketException(sprintf('Unsupported protocol requested: %s.', $options['protocol']));
        }

        if(!is_array($options['headers'])) {

            throw new WebsocketException('Option "headers" must be of type array.');
        }

        $this->protocol = $options['protocol'];
        $this->persistent = (bool)$options['persistent'];
        $this->timeout = (float)$options['timeout'];
        $this->maxFrameSize = (int)$options['maxFrameSize'];
        $this->headers = $options['headers'];
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