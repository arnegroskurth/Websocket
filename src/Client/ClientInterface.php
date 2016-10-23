<?php

namespace ArneGroskurth\Websocket\Client;


interface ClientInterface {

    /**
     * @return string
     */
    public function getUri();


    /**
     * @return bool
     */
    public function isPersistent();


    /**
     * @return float
     */
    public function getTimeout();


    /**
     * @return int
     */
    public function getMaxFrameSize();


    /**
     * @return ClientConnectionInterface
     */
    public function getConnection();
}