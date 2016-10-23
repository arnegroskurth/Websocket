<?php

namespace ArneGroskurth\Websocket;


interface MessageInterface {

    /**
     * @return string
     */
    public function getPayload();


    /**
     * @return string
     */
    public function __toString();
}