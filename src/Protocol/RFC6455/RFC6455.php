<?php

namespace ArneGroskurth\Websocket\Protocol\RFC6455;

use ArneGroskurth\Websocket\AbstractConnection;
use ArneGroskurth\Websocket\ClosingMessage;
use ArneGroskurth\Websocket\ConnectionInterface;
use ArneGroskurth\Websocket\WebsocketException;
use ArneGroskurth\Websocket\Message;
use ArneGroskurth\Websocket\Protocol\ProtocolInterface;
use ArneGroskurth\Websocket\Request;
use ArneGroskurth\Websocket\Response;
use ArneGroskurth\Websocket\Server\ServerConnection;


/**
 * @internal
 */
class RFC6455 implements ProtocolInterface {

    const OP_CONTINUE =  0;
    const OP_TEXT     =  1;
    const OP_BINARY   =  2;
    const OP_CLOSE    =  8;
    const OP_PING     =  9;
    const OP_PONG     = 10;

    const CLOSE_NORMAL      = 1000;
    const CLOSE_GOING_AWAY  = 1001;
    const CLOSE_PROTOCOL    = 1002;
    const CLOSE_BAD_DATA    = 1003;
    const CLOSE_NO_STATUS   = 1005;
    const CLOSE_ABNORMAL    = 1006;
    const CLOSE_BAD_PAYLOAD = 1007;
    const CLOSE_POLICY      = 1008;
    const CLOSE_TOO_BIG     = 1009;
    const CLOSE_MAND_EXT    = 1010;
    const CLOSE_SRV_ERR     = 1011;
    const CLOSE_TLS         = 1015;

    const SIGNATURE_GUID = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';


    /**
     * @var string
     */
    protected $requestedKey;


    /**
     * {@inheritdoc}
     */
    public function request(AbstractConnection $connection, $path = '/', array $additionalHeaders = array()) {

        $this->requestedKey = base64_encode($this->generateRandomKey());

        $connection->write(Request::create('GET', $path, array_merge($additionalHeaders, array(
            'Upgrade' => 'websocket',
            'Connection' => 'Upgrade',
            'Sec-WebSocket-Version' => '13',
            'Sec-WebSocket-Key' => $this->requestedKey
        ))));
    }


    /**
     * {@inheritdoc}
     */
    public function handleResponse(AbstractConnection $connection, Response $response) {

        if($response->getCode() !== 101) {

            throw new WebsocketException('Wrong http status code returned.');
        }

        if($response->getHeader('Sec-WebSocket-Accept') !== $this->signKey($this->requestedKey)) {

            throw new WebsocketException('Wrong key signature.');
        }
    }


    /**
     * {@inheritdoc}
     */
    public function canHandleRequest(Request $request) {

        return $request->getHeader('Sec-WebSocket-Version') === '13';
    }


    /**
     * {@inheritdoc}
     */
    public function handleRequest(AbstractConnection $connection, Request $request) {

        if(!$this->validate($request)) {

            throw new WebsocketException('Invalid upgrade request given.');
        }

        $connection->write(Response::create(101, array(
            'Upgrade' => 'websocket',
            'Connection' => 'Upgrade',
            'Sec-WebSocket-Accept' => $this->signKey($request->getHeader('Sec-WebSocket-Key'))
        )));
    }


    /**
     * {@inheritdoc}
     */
    public function send(AbstractConnection $connection, $payload, $maxPayloadSize = null) {

        foreach(Frame::createDataFrames($payload, $connection->getDirection(), null, $maxPayloadSize) as $frame) {

            /** @var Frame $frame */
            $connection->write($frame->getData());
        }
    }


    /**
     * {@inheritdoc}
     */
    public function receive(AbstractConnection $connection, $data) {

        $messageQueue = new \SplQueue();

        // ignore any received data if in closing state
        if($connection->getState() === ConnectionInterface::STATE_CLOSING) {
            return $messageQueue;
        }


        // setup protocol specific buffer used to buffer frames
        if($connection->getProtocolBuffer() === null) {

            $connection->setProtocolBuffer(new \SplQueue());
        }

        // setup message fragment in any case to avoid branching
        if($connection->getMessage() === null) {

            $connection->setMessage(new Message('', false));
        }


        try {

            $frameQueue = Frame::extractFrom($data, $connection->getProtocolBuffer());
        }
        catch(WebsocketException $exception) {

            $this->close($connection, static::CLOSE_PROTOCOL);

            $connection->setState(ConnectionInterface::STATE_CLOSING);

            throw new WebsocketException('Could not extract websocket protocol frame(s) from received data.', 0, $exception);
        }


        // handle each received frame as multiple frames might be received at once
        while(!$frameQueue->isEmpty()) {

            /** @var Frame $frame */
            $frame = $frameQueue->dequeue();

            switch($frame->getOpCode()) {

                case static::OP_CONTINUE:
                case static::OP_TEXT:
                case static::OP_BINARY:

                    if($connection->getOtherDirection() === ProtocolInterface::DIRECTION_CLIENT_TO_SERVER && !$frame->isMasked()) {

                        throw new WebsocketException('Received un-masked frame from client.');
                    }

                    $connection->getMessage()->appendToPayload($frame->getPayload(), $frame->isFin());

                    if($connection->getMessage()->isComplete()) {

                        $messageQueue->enqueue($connection->getMessage());

                        $connection->setMessage(new Message('', false));
                    }

                    break;


                case static::OP_CLOSE:

                    $payload = $frame->getPayload();
                    $closeCode = (strlen($payload) >= 2) ? unpack('n', substr($payload, 0, 2))[1] : null;
                    $data = (strlen($payload) > 2) ? substr($payload, 2) : null;

                    $messageQueue->enqueue(new ClosingMessage($data, $closeCode));

                    $connection->setState(ConnectionInterface::STATE_CLOSING);

                    $this->close($connection, pack('n', static::CLOSE_NORMAL));

                    // close connection directly if acting as server
                    if($connection instanceof ServerConnection) {

                        $connection->getReactConnection()->close();
                    }

                    // all further frames can be ignored
                    break 2;


                case static::OP_PING:

                    $connection->write(Frame::create($frame->getPayload(), $connection->getDirection(), static::OP_PONG)->getData());

                    break;


                case static::OP_PONG:

                    break;


                default:

                    $this->close($connection, static::CLOSE_PROTOCOL);

                    $connection->setState(ConnectionInterface::STATE_CLOSING);

                    throw new WebsocketException('Unknown websocket frame obcode received.');
            }
        }

        return $messageQueue;
    }


    /**
     * {@inheritdoc}
     */
    public function expectingData(AbstractConnection $connection) {

        return !$connection->getMessage()->isComplete() || !$connection->getProtocolBuffer()->isEmpty();
    }


    /**
     * {@inheritdoc}
     */
    public function close(AbstractConnection $connection, $data = null) {

        $frame = Frame::create($data, $connection->getDirection(), static::OP_CLOSE);

        $connection->write($frame->getData());
    }


    /**
     * @param string $key
     *
     * @return string
     */
    protected function signKey($key) {

        return base64_encode(sha1($key . static::SIGNATURE_GUID, true));
    }


    /**
     * @param Request $request
     *
     * @return bool
     */
    protected function validate(Request $request) {

        if($request->getMethod() !== 'GET') return false;
        if((float)$request->getHttpVersion() < 1.1) return false;
        if(!mb_check_encoding($request->getPath(), 'US-ASCII')) return false;

        if(!$request->hasHeader('Connection')) return false;
        if(strpos(strtolower($request->getHeader('Connection')), 'upgrade') === false) return false;

        if(!$request->hasHeader('Sec-WebSocket-Key')) return false;
        if(mb_strlen(base64_decode($request->getHeader('Sec-WebSocket-Key')), '8bit') !== 16) return false;

        if(!$request->hasHeader('Sec-WebSocket-Version')) return false;
        if($request->getHeader('Sec-WebSocket-Version') !== '13') return false;

        return true;
    }


    /**
     * @param int $length
     *
     * @return string
     */
    protected function generateRandomKey($length = 16) {

        return openssl_random_pseudo_bytes($length);
    }
}