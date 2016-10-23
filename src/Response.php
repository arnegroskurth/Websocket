<?php

namespace ArneGroskurth\Websocket;


class Response {

    /**
     * @var int
     */
    protected $code;

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
     * @var array
     */
    protected static $statusTexts = array(
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',
        208 => 'Already Reported',
        226 => 'IM Used',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Reserved for WebDAV advanced collections expired proposal',
        426 => 'Upgrade required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates (Experimental)',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        510 => 'Not Extended',
        511 => 'Network Authentication Required',
    );


    /**
     * @return int
     */
    public function getCode() {

        return $this->code;
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

        $lines = array(sprintf('HTTP/%s %d %s', $this->httpVersion, $this->code, isset(static::$statusTexts[$this->code]) ? static::$statusTexts[$this->code] : 'Unknown'));

        foreach($this->headerFields as $key => $value) {

            $lines[] = sprintf('%s: %s', $key, $value);
        }

        return implode("\r\n", $lines) . "\r\n\r\n" . $this->body;
    }


    /**
     * @param string $data
     *
     * @return Response
     * @throws WebsocketException
     */
    public static function extractFromData($data) {

        try {

            $response = new static();

            $pos = strpos($data, "\r\n\r\n");

            $header = substr($data, 0, $pos);
            $response->body = substr($data, $pos + 4);

            $response->headerFields = array();
            foreach(explode("\r\n", $header) as $lineNumber => $line) {

                $pos = strpos($line, ':');

                if($lineNumber === 0) {

                    if(!preg_match('/^HTTP\/([^ ]+) ([0-9]+) (.+)$/i', $line, $match)) {

                        throw new WebsocketException();
                    }

                    $response->httpVersion = $match[1];
                    $response->code = (int)$match[2];
                }

                elseif(($name = trim(substr($line, 0, $pos))) && ($content = trim(substr($line, $pos + 1)))) {

                    $response->headerFields[strtolower($name)] = array($name, $content);
                }

                else {

                    throw new WebsocketException();
                }
            }

            return $response;
        }
        catch(\Exception $e) {

            throw new WebsocketException('Malformed HTTP response given.', 0, $e);
        }
    }


    /**
     * @param int $code
     * @param array $headerFields
     * @param string $body
     *
     * @return Response
     */
    public static function create($code, $headerFields = array(), $body = '') {

        $response = new static();

        $response->httpVersion = '1.1';
        $response->code = $code;
        $response->headerFields = $headerFields;
        $response->body = $body;

        return $response;
    }
}