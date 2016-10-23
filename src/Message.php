<?php

namespace ArneGroskurth\Websocket;


class Message implements MessageInterface {

    /**
     * @var bool
     */
    protected $complete;

    /**
     * @var string
     */
    protected $payload;


    /**
     * @return boolean
     */
    public function isComplete() {

        return $this->complete;
    }


    /**
     * {@inheritdoc}
     */
    public function getPayload() {

        return $this->payload;
    }


    /**
     * @param string $payload
     * @param bool $isComplete
     */
    public function __construct($payload, $isComplete) {

        $this->complete = $isComplete;
        $this->payload = $payload;
    }


    /**
     * @param string $payloadFragment
     * @param bool $isComplete
     *
     * @return $this
     */
    public function appendToPayload($payloadFragment, $isComplete) {

        $this->complete = $isComplete;
        $this->payload .= $payloadFragment;

        return $this;
    }


    /**
     * @return string
     */
    public function __toString() {

        if(empty($this->payload)) {

            return '[Empty Message]';
        }

        else {

            return sprintf('[Message] %s', $this->payload);
        }
    }
}