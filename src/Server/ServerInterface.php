<?php

namespace ArneGroskurth\Websocket\Server;

use React\EventLoop\LoopInterface;


interface ServerInterface {

    /**
     * @return string
     */
    public function getListen();


    /**
     * @return int
     */
    public function getPort();


    /**
     * @return int
     */
    public function getTimeout();


    /**
     * @return int
     */
    public function getMaxFrameSize();


    /**
     * @param WebSocketApplicationInterface $application
     * @param array $options
     */
    public function __construct(WebSocketApplicationInterface $application, array $options = array());


    /**
     * @param LoopInterface $eventLoop
     */
    public function run(LoopInterface $eventLoop = null);
}