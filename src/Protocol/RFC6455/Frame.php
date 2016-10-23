<?php

namespace ArneGroskurth\Websocket\Protocol\RFC6455;

use ArneGroskurth\Websocket\Protocol\ProtocolInterface;
use ArneGroskurth\Websocket\WebsocketException;


/**
 * @internal
 */
class Frame {

    const DEFAULT_MAX_PAYLOAD_LENGTH = 1 << 15;


    /**
     * @var bool
     */
    protected $fin;

    /**
     * @var int
     */
    protected $opCode;

    /**
     * @var int
     */
    protected $mask;

    /**
     * This is relevant to know when the frame is complete.
     *
     * @var int
     */
    protected $payloadLength;

    /**
     * (Un-masked) frame payload.
     *
     * @var string
     */
    protected $payload;

    /**
     * Raw frame data.
     *
     * @var string
     */
    protected $data;


    /**
     * @todo: is modification of fixed size data buffer faster than appending to an initially empty string??
     * @return string
     */
    public function getData() {

        if($this->data !== null) {

            return $this->data;
        }

        $headerSize = 2 + (($this->mask === null) ? 0 : 4);

        $secondByte = $this->payloadLength;

        if($this->payloadLength > pow(2, 16)) {

            $secondByte = 127;
            $headerSize += 8;
        }

        elseif($this->payloadLength > 125) {

            $secondByte = 126;
            $headerSize += 2;
        }

        $data = str_repeat("\0", $headerSize + $this->payloadLength);

        $data[0] = pack('C', $this->opCode + ($this->fin ? 128 : 0));
        $data[1] = pack('C', $secondByte + (($this->mask === null) ? 0 : 128));

        if($this->payloadLength > pow(2, 16)) {

            $data = substr_replace($data, pack('J', $this->payloadLength), 2, 8);
        }

        elseif($this->payloadLength > 125) {

            $data = substr_replace($data, pack('n', $this->payloadLength), 2, 2);
        }


        if($this->mask === null) {

            $data = substr_replace($data, $this->payload, $headerSize, $this->payloadLength);
        }

        else {

            $data = substr_replace($data, $this->mask, $headerSize - 4, 4);

            for($i = 0; $i < $this->payloadLength; $i++) {

                $data[$headerSize + $i] = $this->payload[$i] ^ $this->mask[$i % 4];
            }
        }

        // is done here to be able to throw exception without having changed the object
        $this->data = $data;

        return $this->data;
    }


    /**
     * @return bool
     */
    public function isFin() {

        return $this->fin;
    }


    /**
     * @return int
     */
    public function getOpCode() {

        return $this->opCode;
    }


    /**
     * @return bool
     */
    public function isMasked() {

        return $this->mask !== null;
    }


    /**
     * @return int
     */
    public function getPayloadLength() {

        return $this->payloadLength;
    }


    /**
     * @return bool
     */
    public function isComplete() {

        return strlen($this->payload) === $this->payloadLength;
    }


    /**
     * @return string
     * @throws WebsocketException
     */
    public function getPayload() {

        if(!$this->isComplete()) {

            throw new WebsocketException('This frame is not yet complete.');
        }

        return $this->payload;
    }


    /**
     * Appends data to payload until payloadLength is reached. Returns overhang.
     *
     * @param string $data
     *
     * @return string
     */
    public function appendPayload($data) {

        $payloadMissing = $this->payloadLength - strlen($this->payload);

        $this->payload .= substr($data, 0, $payloadMissing);

        // unmask payload
        if($this->isComplete() && $this->isMasked()) {

            $this->maskPayload();
        }

        return (string)substr($data, $payloadMissing);
    }


    /**
     * @return int
     */
    public function getMaskingKey() {

        return $this->mask;
    }


    /**
     * The constructor is protected as the factory pattern is implemented.
     */
    protected function __construct() {}


    /**
     * Masks/unmasks payload.
     */
    protected function maskPayload() {

        for($i = 0; $i < $this->payloadLength; $i++) {

            $this->payload[$i] = $this->payload[$i] ^ $this->mask[$i % 4];
        }
    }


    /**
     * @param string $data
     * @param \SplQueue $protocolBuffer
     *
     * @return \SplQueue
     * @throws \RuntimeException
     */
    public static function extractFrom($data, \SplQueue $protocolBuffer) {

        // data might be required to complete last frame
        if(!$protocolBuffer->isEmpty()) {

            /** @var Frame $lastFrame */
            $lastFrame = $protocolBuffer[$protocolBuffer->count() - 1];

            if(!$lastFrame->isComplete()) {

                $overhang = $lastFrame->appendPayload($data);

                if(empty($overhang)) {

                    return $protocolBuffer;
                }

                $data = $overhang;
            }
        }


        $minDataSize = 2;
        $dataSize = strlen($data);

        if($dataSize < 2) {
            throw new \UnderflowException('Frame with length < 2 received.');
        }

        $firstByte = ord($data[0]);
        $secondByte = ord($data[1]);

        $frame = new static();
        $frame->fin = ($firstByte & 128) === 128;
        $frame->opCode = $firstByte & 15;
        $frame->payloadLength = $secondByte & 127;


        // extended payload length (2 bytes)
        if($frame->payloadLength === 126) {

            $minDataSize += 2;

            if($dataSize < $minDataSize) {
                throw new \UnderflowException(sprintf('Data frame with 2-byte payload length and total size %d (< %d min) received.', $dataSize, $minDataSize));
            }

            $frame->payloadLength = unpack('n', substr($data, 2, 2))[1];
        }

        // extended payload length (8 bytes)
        elseif($frame->payloadLength === 127) {

            $minDataSize += 8;

            if($dataSize < $minDataSize) {
                throw new \UnderflowException(sprintf('Data frame with 8-byte payload length and total size %d (< %d min) received.', $dataSize, $minDataSize));
            }

            $frame->payloadLength = unpack('J', substr($data, 2, 8))[1];
        }


        // extract frame mask
        if($isMasked = ($secondByte & 128) === 128) {

            $minDataSize += 4;

            if($dataSize < $minDataSize) {
                throw new \UnderflowException('Received data is less then required for a valid frame header.');
            }

            $frame->mask = substr($data, $minDataSize - 4, 4);
        }


        // save payload (may be only first fragment)
        $frame->payload = substr($data, $minDataSize, $frame->payloadLength);

        // unmask payload if complete
        if($isMasked && $frame->isComplete()) {

            $frame->maskPayload();
        }


        $protocolBuffer->enqueue($frame);


        $expectedDataSize = $minDataSize + $frame->payloadLength;

        // encoded payload length is larger than given data
        // try to extract more frames from given data
        if($dataSize > $expectedDataSize) {

            $overhang = substr($data, $expectedDataSize);

            static::extractFrom($overhang, $protocolBuffer);
        }

        return $protocolBuffer;
    }


    /**
     * @param string $payload
     * @param int $direction
     * @param int $opCode
     * @param bool $fin
     *
     * @return Frame
     */
    public static function create($payload, $direction, $opCode, $fin = true) {

        assert(is_null($payload) || is_string($payload));
        assert(in_array($direction, array(ProtocolInterface::DIRECTION_CLIENT_TO_SERVER, ProtocolInterface::DIRECTION_SERVER_TO_CLIENT)));
        assert(in_array($opCode, array(RFC6455::OP_CONTINUE, RFC6455::OP_TEXT, RFC6455::OP_BINARY, RFC6455::OP_CLOSE, RFC6455::OP_PING, RFC6455::OP_PONG)));

        $frame = new static();
        $frame->fin = $fin;
        $frame->opCode = $opCode;
        $frame->mask = ($direction === ProtocolInterface::DIRECTION_SERVER_TO_CLIENT) ? null : openssl_random_pseudo_bytes(4);
        $frame->payload = $payload;
        $frame->payloadLength = strlen($payload);

        return $frame;
    }


    /**
     * @param string $payload
     * @param int $direction
     * @param int $firstOpCode
     * @param int $maxPayloadLength
     *
     * @return \SplFixedArray
     */
    public static function createDataFrames($payload, $direction, $firstOpCode = null, $maxPayloadLength = null) {

        assert(is_string($payload));
        assert(is_null($maxPayloadLength) || (is_int($maxPayloadLength) && $maxPayloadLength > 0));

        $maxPayloadLength = ($maxPayloadLength === null) ? static::DEFAULT_MAX_PAYLOAD_LENGTH : $maxPayloadLength;
        $firstOpCode = ($firstOpCode === null) ? (mb_check_encoding($payload, 'UTF-8') ? RFC6455::OP_TEXT : RFC6455::OP_BINARY) : $firstOpCode;

        // split payload into parts of maxPayloadLength size
        $payloadFragments = str_split($payload, $maxPayloadLength);
        $payloadFragmentCount = count($payloadFragments);

        $return = new \SplFixedArray($payloadFragmentCount);
        foreach($payloadFragments as $fragmentNumber => $payloadFragment) {

            $opCode = ($fragmentNumber === 0) ? $firstOpCode : RFC6455::OP_CONTINUE;
            $fin = ($fragmentNumber + 1) === $payloadFragmentCount;

            $frame = static::create($payloadFragment, $direction, $opCode, $fin);

            $return[$fragmentNumber] = $frame;
        }

        return $return;
    }
}