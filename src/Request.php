<?php

namespace ArneGroskurth\Websocket;


/**
 * @internal
 */
class Request {

    /**
     * @var string
     */
    protected $method;

    /**
     * @var string
     */
    protected $path;

    /**
     * @var string
     */
    protected $httpVersion;

    /**
     * @var array
     */
    protected $headerFields;

    /**
     * @var string
     */
    protected $body;


    /**
     * @return string
     */
    public function getMethod() {

        return $this->method;
    }


    /**
     * @return string
     */
    public function getPath() {

        return $this->path;
    }


    /**
     * @return string
     */
    public function getHttpVersion() {

        return $this->httpVersion;
    }


    /**
     * @param string $name
     *
     * @return bool
     */
    public function hasHeader($name) {

        return isset($this->headerFields[strtolower($name)]);
    }


    /**
     * @param string $name
     *
     * @return string
     */
    public function getHeader($name) {

        return isset($this->headerFields[strtolower($name)]) ? $this->headerFields[strtolower($name)][1] : null;
    }


    /**
     * @return array
     */
    public function getHeaders() {

        $return = array();

        foreach($this->headerFields as $headerField) {

            $return[$headerField[0]] = $headerField[1];
        }

        return $return;
    }


    /**
     * @return string
     */
    public function getBody() {

        return $this->body;
    }


    /**
     * The constructor is protected as the factory pattern is implemented.
     */
    protected function __construct() {}


    /**
     * @return string
     */
    public function __toString() {

        $lines = array(sprintf('%s %s HTTP/%s', $this->method, $this->path, $this->httpVersion));

        foreach($this->headerFields as $key => $value) {

            $lines[] = sprintf('%s: %s', $key, $value);
        }

        return implode("\r\n", $lines) . "\r\n\r\n" . $this->body;
    }


    /**
     * Parses given http request.
     *
     * @param string $data
     *
     * @return Request
     * @throws WebsocketException
     */
    public static function extractFromData($data) {

        try {

            $request = new static();

            $pos = strpos($data, "\r\n\r\n");

            $header = substr($data, 0, $pos);
            $request->body = substr($data, $pos + 4);

            $request->headerFields = array();
            foreach(explode("\r\n", $header) as $lineNumber => $line) {

                $pos = strpos($line, ':');

                if($lineNumber === 0) {

                    if(!preg_match('/^([a-z]+) ([^ ]+) HTTP\/([^ ]+)$/i', $line, $match)) {

                        throw new WebsocketException();
                    }

                    $request->method = strtoupper($match[1]);
                    $request->path = $match[2];
                    $request->httpVersion = $match[3];
                }

                elseif(($name = trim(substr($line, 0, $pos))) && ($content = trim(substr($line, $pos + 1)))) {

                    $request->headerFields[strtolower($name)] = array($name, $content);
                }

                else {

                    throw new WebsocketException();
                }
            }

            return $request;
        }
        catch(\Exception $e) {

            throw new WebsocketException('Malformed HTTP request given.', 0, $e);
        }
    }


    /**
     * @param string $method
     * @param string $path
     * @param array $headerFields
     * @param string $body
     *
     * @return static
     */
    public static function create($method, $path = '/', array $headerFields = array(), $body = '') {

        $request = new static();
        $request->httpVersion = '1.1';
        $request->method = $method;
        $request->path = $path;
        $request->headerFields = $headerFields;
        $request->body = $body;

        return $request;
    }
}