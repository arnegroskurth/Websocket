<?php

namespace ArneGroskurth\Websocket\Client;

use ArneGroskurth\Websocket\AbstractConnection;
use ArneGroskurth\Websocket\ClosingMessage;
use ArneGroskurth\Websocket\Response;
use ArneGroskurth\Websocket\WebsocketException;
use ArneGroskurth\Websocket\Protocol\RFC6455\RFC6455;


/**
 * @internal
 */
class ClientConnection extends AbstractConnection implements ClientConnectionInterface {

    /**
     * @var ClientInterface
     */
    protected $client;

    /**
     * @var resource
     */
    protected $stream;

    /**
     * @var Response
     */
    protected $handshakeResponse;

    /**
     * @var \SplQueue
     */
    protected $messageBuffer;


    /**
     * @return ClientInterface
     */
    public function getClient() {

        return $this->client;
    }


    /**
     * @return resource
     */
    public function getStream() {

        return $this->stream;
    }


    /**
     * @return Response
     */
    public function getHandshakeResponse() {

        return $this->handshakeResponse;
    }


    /**
     * @param Response $handshakeResponse
     *
     * @return ClientConnection
     */
    public function setHandshakeResponse($handshakeResponse) {

        $this->handshakeResponse = $handshakeResponse;

        return $this;
    }


    /**
     * @param ClientInterface $client
     * @param resource $stream
     */
    public function __construct(ClientInterface $client, $stream) {

        parent::__construct();

        $this->client = $client;
        $this->stream = $stream;
        $this->messageBuffer = new \SplQueue();
    }


    public function __destruct() {

        $this->close();
    }


    /**
     * {@inheritdoc}
     */
    public function getRemoteAddress() {

        return $this->client->getUri();
    }


    /**
     * {@inheritdoc}
     */
    public function send($message) {

        if($this->protocol instanceof RFC6455) {

            $this->protocol->send($this, $message, $this->client->getMaxFrameSize());
        }

        else {

            $this->protocol->send($this, $message);
        }
    }


    /**
     * {@inheritdoc}
     */
    public function close($data = null) {

        if($this->state !== AbstractConnection::STATE_CLOSING) {

            $this->state = AbstractConnection::STATE_CLOSING;

            if(is_resource($this->stream) && !feof($this->stream)) {

                $this->protocol->close($this, $data);
            }

            fclose($this->stream);
        }
    }


    /**
     * {@inheritdoc}
     */
    public function write($data, $retries = 3) {

        $dataSize = strlen($data);

        $error = array(
            'number' => 0,
            'message' => null
        );

        set_error_handler(function($errno, $errstr) use ($error) {

            $error['number'] = $errno;
            $error['message'] = $errstr;
        });

        for($written = 0, $retry = 0; $written < $dataSize; $written += $result) {

            $fragment = substr($data, $written);

            $result = fwrite($this->stream, $fragment, strlen($fragment));

            if($result === 0) {

                if($error['number'] > 0) {

                    restore_error_handler();

                    throw new WebsocketException(sprintf('Could not write to stream. [%d] %s', $error['number'], $error['message']));
                }

                if($retry === $retries) {

                    restore_error_handler();

                    throw new WebsocketException('Could not write to stream. Retries exceeded.');
                }

                $retry++;
            }

            // reset retry counter for each fragment
            else {

                $retry = 0;
            }
        }

        restore_error_handler();
    }


    /**
     * {@inheritdoc}
     */
    public function receiveMessage() {

        // return buffered message if any left
        if(!$this->messageBuffer->isEmpty()) {

            return $this->messageBuffer->dequeue();
        }

        if(!is_resource($this->stream) || feof($this->stream)) {

            $this->close();

            return new ClosingMessage(null, RFC6455::CLOSE_GOING_AWAY);
        }

        // try to read from stream until at least one message is received or timeout is reached
        while($this->messageBuffer->isEmpty()) {

            $read = fread($this->stream, $chunkSize = 4096);

            if($read === false) {

                $this->close();

                throw new WebsocketException('Error reading from connection.');
            }

            if(stream_get_meta_data($this->stream)['timed_out']) {
                return null;
            }

            if(strlen($read) > 0) {

                $this->messageBuffer = $this->protocol->receive($this, $read);
            }
        }

        return $this->messageBuffer->dequeue();
    }


    /**
     * @returns string
     * @throws WebsocketException
     */
    public function receiveHttpHead() {

        if(ftell($this->stream) > 0) {
            throw new WebsocketException('Trying to read http header from already upgraded connection.');
        }

        return stream_get_line($this->stream, 4096, "\r\n\r\n") . "\r\n\r\n";
    }


    /**
     * {@inheritdoc}
     */
    public function hasBufferedMessages() {

        return !$this->messageBuffer->isEmpty();
    }
}