<?php

namespace ArneGroskurth\Websocket;


class ClosingMessage extends Message {

    /**
     * @var int
     */
    protected $closeCode;


    /**
     * @return int
     */
    public function getCloseCode() {

        return $this->closeCode;
    }


    /**
     * @param string $payload
     * @param int $closeCode
     */
    public function __construct($payload, $closeCode) {

        parent::__construct($payload, true);

        $this->closeCode = $closeCode;
    }


    /**
     * @return string
     */
    public function __toString() {

        if($this->payload === null) {

            return sprintf('[Empty ClosingMessage %d]', $this->closeCode);
        }

        else {

            return sprintf('[ClosingMessage %d] %s', $this->closeCode, $this->payload);
        }
    }
}