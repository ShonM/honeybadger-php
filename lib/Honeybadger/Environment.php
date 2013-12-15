<?php

namespace Honeybadger;

use Honeybadger\Util\Arr;
use Honeybadger\Errors\NonExistentProperty;
use Honeybadger\Errors\ReadOnly;

/**
 * Retrieves, stores, and normalizes environment data from `$_SERVER` to prepare
 * information for serialization in [Notice]s. Additionally, provides
 * convenience methods for determining the URL of the request trigging an error.
 *
 * TODO: Refactor to follow better, consistent standards (Rack).
 *
 * @package  Honeybadger
 */
class Environment implements \ArrayAccess, \IteratorAggregate
{
    private $_attribute_methods = array(
        'protocol', 'host', 'port', 'fullpath', 'url',
    );

    /**
     * @var  array  List of `$_SERVER` keys to allow when building an
     *              environment automatically. Keys prefixed with `HTTP_`
     *              are also included.
     */
    private $allowed_php_environment_keys = array(
        'PHP_SELF'             => NULL,
        'argv'                 => NULL,
        'argc'                 => NULL,
        'GATEWAY_INTERFACE'    => NULL,
        'SERVER_ADDR'          => NULL,
        'SERVER_NAME'          => NULL,
        'SERVER_SOFTWARE'      => NULL,
        'SERVER_PROTOCOL'      => NULL,
        'REQUEST_METHOD'       => NULL,
        'REQUEST_TIME'         => NULL,
        'REQUEST_TIME_FLOAT'   => NULL,
        'QUERY_STRING'         => NULL,
        'DOCUMENT_ROOT'        => NULL,
        'HTTPS'                => NULL,
        'REMOTE_ADDR'          => NULL,
        'REMOTE_HOST'          => NULL,
        'REMOTE_PORT'          => NULL,
        'REMOTE_USER'          => NULL,
        'REDIRECT_REMOTE_USER' => NULL,
        'SCRIPT_FILENAME'      => NULL,
        'SERVER_ADMIN'         => NULL,
        'SERVER_PORT'          => NULL,
        'SERVER_SIGNATURE'     => NULL,
        'PATH_TRANSLATED'      => NULL,
        'SCRIPT_NAME'          => NULL,
        'REQUEST_URI'          => NULL,
        'PHP_AUTH_DIGEST'      => NULL,
        'PHP_AUTH_USER'        => NULL,
        'PHP_AUTH_PW'          => NULL,
        'AUTH_TYPE'            => NULL,
        'PATH_INFO'            => NULL,
        'ORIG_PATH_INFO'       => NULL,
    );

    /**
     * @var  array  The environment data.
     */
    private $data = array();

    /**
     * Constructs a new environment with the supplied data or attempts to detect
     * the environment using `sanitized_php_environment`.
     *
     * @param array $data The environment data.
     */
    public function __construct($data = NULL)
    {
        if ($data === NULL) {
            $data = $this->sanitized_php_environment();
        }

        $this->data = $data;
    }

    /**
     * Determines the protocol of the request.
     *
     * @return string Either `http` or `https`.
     */
    public function protocol()
    {
        return (empty($this['HTTPS']) OR $this['HTTPS'] == 'off') ? 'http' : 'https';
    }

    /**
     * Determines whether the request was made over HTTPS.
     *
     * @return boolean `TRUE` if the request is secure, `FALSE` otherwise.
     */
    public function is_secure()
    {
        return ($this->protocol() === 'https');
    }

    /**
     * Determines the host of the request, using the `Host` header, falling back
     * to `SERVER_NAME` if none was set.
     *
     * @return string The request host.
     */
    public function host()
    {
        return (empty($this['HTTP_HOST'])) ? $this['SERVER_NAME'] : $this['HTTP_HOST'];
    }

    /**
     * Determines the port of the web server. If none was found, defaults to
     * either `443` or `80` depending on whether the connection is secure.
     *
     * @return integer The server port.
     */
    public function port()
    {
        if (empty($this['SERVER_PORT'])) {
            return $this->is_secure() ? 443 : 80;
        } else {
            return $this['SERVER_PORT'];
        }
    }

    /**
     * Determines whether the connection is using a non-standard port.
     *
     * @return boolean `TRUE` if non-standard port is used, `FALSE` otherwise.
     */
    public function is_non_standard_port()
    {
        if ($this->is_secure()) {
            return ($this->port() != 443);
        } else {
            return ($this->port() != 80);
        }
    }

    /**
     * Attempts to detect the full path of the request (including query string).
     *
     * @return string The full path of the request.
     */
    public function fullpath()
    {
        $uri = $this['REQUEST_URI'] ?: $this['PATH_INFO'];
        $uri = preg_replace('/\?.*$/', '', $uri);
        $uri = '/'.ltrim($uri, '/');

        if ( ! empty($this['QUERY_STRING'])) {
            $uri .= '?'.$this['QUERY_STRING'];
        }

        return $uri;
    }

    /**
     * Returns the full request URL including protocol, host, port
     * (if non-standard), URI, and query string.
     */
    public function url()
    {
        if (isset($this->data['url']) AND ! empty($this->data['url']))
            return $this->data['url'];

        $url = $this->protocol.'://'.$this->host;

        if ($this->is_non_standard_port()) {
            $url .= ':'.$this->port;
        }

        $url .= $this->fullpath;

        if ( ! preg_match('/^https?:\/{3}$/', $url))

            return $url;
    }

    /**
     * Returns the environment data as an array.
     */
    public function as_array()
    {
        return $this->data;
    }

    /**
     * Alias for `as_array`.
     */
    public function to_array()
    {
        return $this->as_array();
    }

    /**
     * Returns the JSON-encoded environment data.
     *
     * @param  integer $options Options to pass to `json_encode()`.
     * @return string  The JSON-encoded object attributes.
     */
    public function to_json($options = 0)
    {
        return json_encode($this->as_json(), $options);
    }

    public function __get($key)
    {
        if (in_array($key, $this->_attribute_methods)) {
            return $this->$key();
        } else {
            throw new NonExistentProperty($this, $key);
        }
    }

    public function offsetGet($key)
    {
        if (array_key_exists($key, $this->data)) {
            return $this->data[$key];
        } elseif (in_array($key, $this->_attribute_methods)) {
            return $this->$key();
        } else {
            return NULL;
        }
    }

    public function offsetSet($key, $value)
    {
        throw new ReadOnly($this);
    }

    public function offsetExists($key)
    {
        return (array_key_exists($key, $this->data) OR
                in_array($key, $this->_attribute_methods));
    }

    public function offsetUnset($key)
    {
        throw new ReadOnly($this);
    }

    public function getIterator()
    {
        return new \ArrayIterator($this->data);
    }

    /**
     * Unfortunately, PHP has no separation between the shell and
     * request environments. This means sensitive data such as database
     * information (it's common practice to set these when using services like
     * Heroku and Pagoda Box) must be filtered out.
     *
     * The following steps are taken to alleviate this issue:
     *
     * * Only allow the
     *   [predefined variables](http://php.net/manual/en/reserved.variables.server.php)
     *   in `$_SERVER`.
     *
     * * Allow variables prefixed with `HTTP_` (HTTP headers).
     *
     * @return array The filtered PHP request environment.
     */
    private function sanitized_php_environment()
    {
        $env = Arr::overwrite($this->allowed_php_environment_keys, $_SERVER);

        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $env[$key] = $value;
            }
        }

        if ( ! empty($_COOKIE)) {
            // Add cookies
            $env['rack.request.cookie_hash'] = $_COOKIE;
        }

        return array_filter($env);
    }
}
