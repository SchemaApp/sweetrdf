<?php

namespace EasyRdf\Http;

/*
 * EasyRdf
 *
 * LICENSE
 *
 * Copyright (c) 2009-2020 Nicholas J Humfrey.  All rights reserved.
 * Copyright (c) 2005-2009 Zend Technologies USA Inc.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright notice,
 *    this list of conditions and the following disclaimer in the documentation
 *    and/or other materials provided with the distribution.
 * 3. The name of the author 'Nicholas J Humfrey" may be used to endorse or
 *    promote products derived from this software without specific prior
 *    written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @package    EasyRdf
 * @copyright  Copyright (c) 2009-2020 Nicholas J Humfrey
 *             Copyright (c) 2005-2009 Zend Technologies USA Inc.
 * @license    https://www.opensource.org/licenses/bsd-license.php
 */
use EasyRdf\Exception;
use EasyRdf\ParsedUri;

/**
 * This class is an implemetation of an HTTP client in PHP.
 * It supports basic HTTP 1.0 and 1.1 requests. For a more complete
 * implementation try Zend_Http_Client.
 *
 * @copyright  Copyright (c) 2009-2020 Nicholas J Humfrey
 * @license    https://www.opensource.org/licenses/bsd-license.php
 */
class Client
{
    /**
     * Configuration array, set using the constructor or using ::setConfig()
     *
     * @var array
     */
    private $config = [
        'maxredirects' => 5,
        'useragent' => 'EasyRdf HTTP Client',
        'timeout' => 10,
    ];

    /**
     * Request URI
     *
     * @var string
     */
    private $uri;

    /**
     * Associative array of request headers
     *
     * @var array
     */
    private $headers = [];

    /**
     * HTTP request method
     *
     * @var string
     */
    private $method = 'GET';

    /**
     * Associative array of GET parameters
     *
     * @var array
     */
    private $paramsGet = [];

    /**
     * The raw post data to send. Could be set by setRawData($data).
     *
     * @var string|null
     */
    private $rawPostData;

    /**
     * Redirection counter
     *
     * @var int
     */
    private $redirectCounter = 0;

    /**
     * Constructor method. Will create a new HTTP client. Accepts the target
     * URL and optionally configuration array.
     *
     * @param string $uri
     * @param array  $config configuration key-value pairs
     */
    public function __construct($uri = null, $config = null)
    {
        if (null !== $uri) {
            $this->setUri($uri);
        }
        if (null !== $config) {
            $this->setConfig($config);
        }
    }

    /**
     * Set the URI for the next request
     *
     * @param string $uri
     *
     * @return self
     *
     * @throws \InvalidArgumentException
     */
    public function setUri($uri)
    {
        if (!\is_string($uri)) {
            $uri = (string) $uri;
        }

        if (!preg_match('/^http(s?):/', $uri)) {
            throw new \InvalidArgumentException("EasyRdf\\Http\\Client only supports the 'http' and 'https' schemes.");
        }

        $this->uri = $uri;

        return $this;
    }

    /**
     * Get the URI for the next request
     *
     * @param bool $asString
     *
     * @return string
     */
    public function getUri($asString = true)
    {
        return $this->uri;
    }

    /**
     * Set configuration parameters for this HTTP client
     *
     * @param array $config
     *
     * @return self
     *
     * @throws \InvalidArgumentException
     */
    public function setConfig($config = [])
    {
        if (null == $config || !\is_array($config)) {
            throw new \InvalidArgumentException('$config should be an array and cannot be null');
        }

        foreach ($config as $k => $v) {
            $this->config[strtolower($k)] = $v;
        }

        return $this;
    }

    /**
     * Get the Configuration Given a Name.
     *
     * @param string $name
     * @return mixed|null
     */
    public function getConfig(string $name)
    {
        if (isset($this->config[$name])) {
            return $this->config[$name];
        } else {
            return null;
        }
    }

    /**
     * Set a request header
     *
     * @param string $name  Header name (e.g. 'Accept')
     * @param string $value Header value or null
     *
     * @return self
     */
    public function setHeaders($name, $value = null)
    {
        $normalizedName = strtolower($name);

        // If $value is null or false, unset the header
        if (null == $value || false == $value) {
            unset($this->headers[$normalizedName]);
        } else {
            // Else, set the header
            $this->headers[$normalizedName] = [$name, $value];
        }

        return $this;
    }

    /**
     * Set the next request's method
     *
     * Validated the passed method and sets it.
     *
     * @param string $method
     *
     * @return self
     *
     * @throws \InvalidArgumentException
     */
    public function setMethod($method)
    {
        if (!\is_string($method) || !preg_match('/^[A-Z]+$/', $method)) {
            throw new \InvalidArgumentException('Invalid HTTP request method.');
        }

        $this->method = $method;

        return $this;
    }

    /**
     * Get the method for the next request
     *
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * Get the value of a specific header
     *
     * Note that if the header has more than one value, an array
     * will be returned.
     *
     * @param string $key
     *
     * @return string|array|null The header value or null if it is not set
     */
    public function getHeader($key)
    {
        $key = strtolower($key);
        if (isset($this->headers[$key])) {
            return $this->headers[$key][1];
        } else {
            return null;
        }
    }

    /**
     * Set a GET parameter for the request.
     *
     * @param string $name
     * @param string $value
     *
     * @return self
     */
    public function setParameterGet($name, $value = null)
    {
        if (null === $value) {
            if (isset($this->paramsGet[$name])) {
                unset($this->paramsGet[$name]);
            }
        } else {
            $this->paramsGet[$name] = $value;
        }

        return $this;
    }

    /**
     * Get a GET parameter for the request.
     *
     * @param string $name
     *
     * @return string|null value
     */
    public function getParameterGet($name)
    {
        if (isset($this->paramsGet[$name])) {
            return $this->paramsGet[$name];
        } else {
            return null;
        }
    }

    /**
     * Get all the GET parameters
     *
     * @return array
     */
    public function getParametersGet()
    {
        return $this->paramsGet;
    }

    /**
     * Get the number of redirections done on the last request
     *
     * @return int
     */
    public function getRedirectionsCount()
    {
        return $this->redirectCounter;
    }

    /**
     * Set the raw (already encoded) POST data.
     *
     * This function is here for two reasons:
     * 1. For advanced user who would like to set their own data, already encoded
     * 2. For backwards compatibilty: If someone uses the old post($data) method.
     *    this method will be used to set the encoded data.
     *
     * $data can also be stream (such as file) from which the data will be read.
     *
     * @param string|resource $data
     *
     * @return self
     */
    public function setRawData($data)
    {
        $this->rawPostData = $data;

        return $this;
    }

    /**
     * Get the raw (already encoded) POST data.
     *
     * @return string
     */
    public function getRawData()
    {
        return $this->rawPostData;
    }

    /**
     * Clear all GET and POST parameters
     *
     * Should be used to reset the request parameters if the client is
     * used for several concurrent requests.
     *
     * clearAll parameter controls if we clean just parameters or also
     * headers
     *
     * @param bool $clearAll Should all data be cleared?
     *
     * @return self
     */
    public function resetParameters($clearAll = false)
    {
        // Reset parameter data
        $this->paramsGet = [];
        $this->rawPostData = null;
        $this->method = 'GET';

        if ($clearAll) {
            $this->headers = [];
        } else {
            // Clear outdated headers
            if (isset($this->headers['content-type'])) {
                unset($this->headers['content-type']);
            }
            if (isset($this->headers['content-length'])) {
                unset($this->headers['content-length']);
            }
        }

        return $this;
    }

    /**
     * Send the HTTP request and return an HTTP response object
     *
     * @param string|null $method
     *
     * @return Response
     *
     * @throws \EasyRdf\Exception
     */
    public function request($method = null)
    {
        if (!$this->uri) {
            throw new Exception('Set URI before calling Client->request()');
        }

        if ($method) {
            $this->setMethod($method);
        }
        $this->redirectCounter = 0;
        $response = null;

        // Send the first request. If redirected, continue.
        do {
            // Clone the URI and add the additional GET parameters to it
            $uri = parse_url($this->uri);
            if ('http' === $uri['scheme']) {
                $host = $uri['host'];
            } elseif ('https' === $uri['scheme']) {
                $host = 'ssl://'.$uri['host'];
            } else {
                throw new Exception('Unsupported URI scheme: '.$uri['scheme']);
            }

            if (isset($uri['port'])) {
                $port = $uri['port'];
            } else {
                if ('https' === $uri['scheme']) {
                    $port = 443;
                } else {
                    $port = 80;
                }
            }

            if (!empty($this->paramsGet)) {
                if (!empty($uri['query'])) {
                    $uri['query'] .= '&';
                } else {
                    $uri['query'] = '';
                }
                $uri['query'] .= http_build_query($this->paramsGet, '', '&');
            }

            $headers = $this->prepareHeaders($uri['host'], $port);

            // Open socket to remote server
            $socket = fsockopen($host, $port, $errno, $errstr, $this->config['timeout']);
            if (!$socket) {
                throw new Exception("Unable to connect to $host:$port ($errstr)");
            }
            stream_set_timeout($socket, $this->config['timeout']);
            $info = stream_get_meta_data($socket);

            /*
             * Write the request
             *
             * Because parse_url only sets up keys for parts present in the URI,
             * key 'path' might be unset. The following structure uses an empty
             * string instead in that case.
             *
             * FYI: https://github.com/easyrdf/easyrdf/issues/271#issuecomment-713372010
             */
            $path = $uri['path'] ?? '';
            if (empty($path)) {
                $path = '/';
            }
            if (isset($uri['query'])) {
                $path .= '?'.$uri['query'];
            }
            fwrite($socket, "{$this->method} {$path} HTTP/1.1\r\n");
            foreach ($headers as $k => $v) {
                if (\is_string($k)) {
                    $v = ucfirst($k).": $v";
                }
                fwrite($socket, "$v\r\n");
            }
            fwrite($socket, "\r\n");

            // Send the request body, if there is one set
            if (isset($this->rawPostData)) {
                fwrite($socket, $this->rawPostData);
            }

            // Read in the response
            $content = '';
            while (!feof($socket) && !$info['timed_out']) {
                $content .= fgets($socket);
                $info = stream_get_meta_data($socket);
            }

            if ($info['timed_out']) {
                throw new Exception("Request to $host:$port timed out");
            }

            // FIXME: support HTTP/1.1 100 Continue

            // Close the socket
            fclose($socket);

            // Parse the response string
            $response = Response::fromString($content);

            // If we got redirected, look for the Location header
            if ($response->isRedirect()
                   && ($location = $response->getHeader('location'))
            ) {
                // Avoid problems with buggy servers that add whitespace at the
                // end of some headers (See ZF-11283)
                $location = trim($location);

                // Some servers return relative URLs in the location header
                // resolve it in relation to previous request
                $baseUri = new ParsedUri($this->uri);
                $location = $baseUri->resolve($location)->toString();

                // If it is a 303 then drop the parameters and send a GET request
                if (303 == $response->getStatus()) {
                    $this->resetParameters();
                    $this->setMethod('GET');
                }

                // If we got a well formed absolute URI
                if (parse_url($location)) {
                    $this->setHeaders('host', null);
                    $this->setUri($location);
                } else {
                    throw new Exception('Failed to parse Location header returned by '.$this->uri);
                }
                ++$this->redirectCounter;
            } else {
                // If we didn't get any location, stop redirecting
                break;
            }
        } while ($this->redirectCounter < $this->config['maxredirects']);

        return $response;
    }

    /**
     * Prepare the request headers
     *
     * @ignore
     *
     * @param string $host
     * @param int    $port
     *
     * @return array
     */
    protected function prepareHeaders($host, $port)
    {
        $headers = [];

        // Set the host header
        if (!isset($this->headers['host'])) {
            // If the port is not default, add it
            if (80 !== $port && 443 !== $port) {
                $host .= ':'.$port;
            }
            $headers[] = "Host: {$host}";
        }

        // Set the connection header
        if (!isset($this->headers['connection'])) {
            $headers[] = 'Connection: close';
        }

        // Set the user agent header
        if (!isset($this->headers['user-agent'])) {
            $headers[] = "User-Agent: {$this->config['useragent']}";
        }

        // If we have rawPostData set, set the content-length header
        if (isset($this->rawPostData)) {
            $headers[] = 'Content-Length: '.\strlen($this->rawPostData);
        }

        // Add all other user defined headers
        foreach ($this->headers as $header) {
            list($name, $value) = $header;
            if (\is_array($value)) {
                $value = implode(', ', $value);
            }

            $headers[] = "$name: $value";
        }

        return $headers;
    }
}
