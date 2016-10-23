<?php

namespace ArneGroskurth\Websocket\Protocol;

use ArneGroskurth\Websocket\Request;


/**
 * @internal
 */
class Registry {

    /**
     * @var ProtocolInterface[]
     */
    protected $protocols = array();


    /**
     * @param ProtocolInterface $protocol
     *
     * @return $this
     */
    public function addProtocol(ProtocolInterface $protocol) {

        $this->protocols[] = $protocol;

        return $this;
    }


    /**
     * @param string $name
     *
     * @return ProtocolInterface|null
     */
    public function getProtocolByName($name) {

        foreach($this->protocols as $protocol) {

            $reflection = new \ReflectionClass($protocol);

            if($reflection->getShortName() === $name) {

                return $protocol;
            }
        }

        return null;
    }


    /**
     * @param Request $request
     *
     * @return ProtocolInterface
     */
    public function findProtocol(Request $request) {

        foreach($this->protocols as $protocol) {

            if($protocol->canHandleRequest($request)) {

                return $protocol;
            }
        }

        return null;
    }
}