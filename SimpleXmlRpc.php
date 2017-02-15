<?php

/**
 * SimpleXmlRpc - easy to use XMLRPC client.
 *
 * @package SimpleXmlRpc
 * @author Joerg Breitbart <j.breitbart@netzkolchose.de>
 * @license MIT
 */

namespace SimpleXmlRpc;

/**
 * Class ServerProxy - class for interacting with XMLRPC servers.
 *
 * Usage example with automatic remote attribute resolution:
 * ```php
 * $server = new \SimpleXmlRpc\ServerProxy("https://example.com:443/xmlprc");
 * $server->system->listMethods();
 * $server->some_method($arg1, $arg2);
 * $server->some->very->deep->method();
 * ```
 *
 * which is equal to:
 * ```php
 * $server = new \SimpleXmlRpc\ServerProxy("https://example.com:443/xmlprc");
 * $server->_call('system.listMethods');
 * $server->_call('some_method', [$arg1, $arg2]);
 * $server->_call('some.very.deep.method', []);
 * ```
 *
 * Supported schemes are http://, https:// and http+unix://
 *
 * **NOTE:** The path to the socket file for the http+unix:// scheme must be urlencoded
 * to get properly extracted from the url:
 * ```
 * urlencode("/path/to/socket") --> http+unix://%2Fpath%2Fto%2Fsocket
 * ```
 * This class supports basic authentication (username and password).
 * Proxies are not supported. As content encoding gzip is supported.
 *
 * @package SimpleXmlRpc
 *
 * @todo configurable stream_set_timeout()
 * @todo configurable encoding settings
 * @todo auth tests
 */
class ServerProxy
{
    const HEADER_BLUEPRINT = "POST %s HTTP/1.1\r\nHost: %s%s\r\nAccept-Encoding: gzip\r\nUser-Agent: SimpleXmlRpc.php 0.1\r\nContent-Type: text/xml\r\n";

    /**
     * Converts HTTP header entries to "entry" => "value" mapping.
     *
     * @param array $h
     * @return array
     * @throws SimpleXmlRpcException
     */
    static public function _parse_header($h) {
        $header = array();
        try {
            $header_size = count($h);
            for ($i = 1; $i < $header_size; $i++) {
                $header[$h[$i][0]] = $h[$i][1];
            }
            $first_line = explode(" ", $h[0][0], 3);
            $header["protocol"] = $first_line[0];
            $header["status_code"] = (int) $first_line[1];
            $header["status_message"] = $first_line[2];
        } catch (\Exception $e) {
            throw new SimpleXmlRpcException("cannot parse header");
        }
        return $header;
    }

    /**
     * @var array map known schemes to transport and default port
     */
    private $_TRANSPORTS = array(
        "http"      => array("tcp",  80),
        "https"     => array("ssl", 443),
        "http+unix" => array("unix",  0)
        );

    /**
     * @var array expected url parts
     */
    private $_url = array(
        "scheme" => "",
        "host" => "",
        "port" => 0,
        "user" => "",
        "pass" => "",
        "path" => "/",
        "query" => "",
        "fragment" => ""
        );

    // user passed url string
    /**
     * @var string user passed url string
     */
    public $_url_original = "";

    /**
     * @var string used transport for connection
     */
    public $_transport = "";

    /**
     * @var string host for connection
     */
    public $_host = "";

    /**
     * @var int connection port
     */
    public $_port = 0;

    /**
     * @var string url for fsockopen (with translated scheme and port)
     */
    public $_transport_url = "";

    /**
     * @var string path for HTTP header
     */
    public $_path = "";

    /**
     * @var string basic auth token for HTTP header
     */
    public $_authtoken = "";

    /**
     * @var string precached header
     */
    public $_header = "";

    /**
     * @var string response header of last call
     */
    public $_last_header = "";

    /**
     * @var string response content of last call
     */
    public $_last_content = "";

    /**
     * Create a new ServerProxy object.
     *
     * @param string $url
     * @throws InvalidUrlException
     */
    function __construct($url) {
        $this->_url_original = $url;

        // get url parts
        $this->_url = array_merge($this->_url, parse_url($url));
        if (!$this->_url || !$this->_url["scheme"] || !$this->_url["host"]) {
            throw new InvalidUrlException("cannot parse url string");
        }

        // translate url scheme to transport
        if (!isset($this->_TRANSPORTS[$this->_url["scheme"]])) {
            throw new InvalidUrlException("unkown scheme or transport");
        }
        $this->_transport = $this->_TRANSPORTS[$this->_url["scheme"]][0];

        // set host
        $this->_host = $this->_url["host"];

        // decode host part of unix transport
        if ($this->_transport == "unix") {
            $this->_host = urldecode($this->_host);
        }

        // apply port settings: 1) from url string 2) default, never for "unix"
        if ($this->_transport != "unix") {
            if ($this->_url["port"]) {
                $this->_port = $this->_url["port"];
            } else {
                $this->_port = $this->_TRANSPORTS[$this->_url["scheme"]][1];
            }
        }

        // create transport url for fsockopen
        $this->_transport_url = $this->_transport."://";
        $this->_transport_url .= $this->_host;

        // create path
        $this->_path = $this->_url["path"];
        if ($this->_url["query"]) {
            $this->_path .= "?".$this->_url["query"];
        }
        if ($this->_url["fragment"]) {
            $this->_path .= "#".$this->_url["fragment"];
        }

        // Basic Auth
        if ($this->_url["user"]) {
            $s = $this->_url["user"];
            if ($this->_url["pass"])
                $s .= ":".$this->_url["pass"];
            $this->_authtoken = base64_encode($s);
        }

        // create cached header, no port for unix transport
        if ($this->_transport == "unix") {
            $this->_header = sprintf(ServerProxy::HEADER_BLUEPRINT,
                $this->_path,
                $this->_host,
                "");
        } else {
            $this->_header = sprintf(ServerProxy::HEADER_BLUEPRINT,
                $this->_path,
                $this->_host,
                ":".$this->_port);
        }

        if ($this->_authtoken) {
            $this->_header .= "Authorization: Basic " . $this->_authtoken . "\r\n";
        }
        $this->_header .= "Content-Length: %d\r\n";
    }

    /**
     * Call a remote method and get the return value.
     *
     * @param string $method method name
     * @param array $args arguments for method
     * @return mixed
     * @throws ConnectionError
     * @throws HttpResponseException
     * @throws Fault
     */
    public function _call($method, $args=array()) {

        // request message body
        $request = xmlrpc_encode_request($method, $args, array("encoding" => "UTF-8"));
        $header = sprintf($this->_header, strlen($request));

        // open socket
        $sock = @fsockopen($this->_transport_url, $this->_port, $errno, $errstr, 30);
        if (!$sock) {
            throw new ConnectionError("could not connect to '" . $this->_url_original . "'");
        }

        // write
        fwrite($sock, $header."\r\n".$request);

        // read header
        $response_header = array();
        while (!feof($sock)) {
            $line = fgets($sock);
            if ($line == "\r\n")
                break;
            $response_header[] = array_map("trim", explode(":", $line, 2));
        }

        // parse header
        $this->_last_header = ServerProxy::_parse_header($response_header);

        // read message body
        $content = "";
        while (strlen($content) < $this->_last_header['Content-Length']) {
            $content .= fgets($sock);
        }
        fclose($sock);

        // gzip encoding
        if (isset($this->_last_header["Content-Encoding"])
            && $this->_last_header["Content-Encoding"] == "gzip") {
            $content = gzdecode($content);
        }

        $this->_last_content = $content;

        // throw Exception on non 200 HTTP status code
        if ($this->_last_header["status_code"] != 200) {
            throw new HttpResponseException(
                $this->_last_header["status_message"],
                $this->_last_header["status_code"]
            );
        }

        // parse content - TODO: multicall Fault
        $result = xmlrpc_decode($content);
        if (is_array($result) && xmlrpc_is_fault($result)) {
            throw new Fault($result['faultString'], $result['faultCode']);
        }
        return $result;
    }

    /**
     * Call remote method for unkown local method.
     *
     * @param string $name method name
     * @param array $args arguments for method
     * @return mixed
     * @throws ConnectionError
     * @throws Fault
     * @throws HttpResponseException
     */
    public function __call($name, $args) {
        return $this->_call($name, $args);
    }

    /**
     * Return a proxy object for unkown local attribute.
     *
     * @param string $name attribute name
     * @return AttributeProxy
     */
    public function __get($name) {
        return new AttributeProxy($name, $this);
    }
}

/**
 * Class AttributeProxy
 *
 * Helper object for mapping local attribute names to remote objects.
 * Any attribute access will return a new child AttributeProxy.
 * A call to a virtual method will ascent back to the root proxy
 * while prepending the attribute name. The root proxy is either
 * a ServerProxy or Multicall object.
 *
 * @package SimpleXmlRpc
 */
class AttributeProxy
{
    /**
     * @var ServerProxy|Multicall|AttributeProxy parent proxy object
     */
    private $_proxy = NULL;

    /**
     * @var string remote attribute name
     */
    public $_name = "";

    /**
     * Create new attribute proxy.
     *
     * @param string $name remote attribute name
     * @param ServerProxy|Multicall|AttributeProxy $proxy parent proxy object
     */
    function __construct($name, $proxy) {
        $this->_name = $name;
        $this->_proxy = $proxy;
    }

    /**
     * Call parent proxy _call with attribute name prepended.
     *
     * @param $name
     * @param $args
     * @return mixed
     */
    public function __call($name, $args) {
        return $this->_proxy->_call($this->_name.".".$name, $args);
    }

    /**
     * Return a proxy object for unkown local attribute.
     *
     * @param $name
     * @return AttributeProxy
     */
    public function __get($name) {
        return new AttributeProxy($this->_name.".".$name, $this->_proxy);
    }
}

/**
 * Class Multicall
 *
 * This class provides an encapsulation for multiple calls into a single request.
 * The server has to support the `system.multicall` XMLRPC extension.
 * This class is heavily inspired by python's XMLRPC multicall object.
 *
 * Example:
 * ```php
 * $server = new \SimpleXmlRpc\ServerProxy("https://example.com:443/xmlprc");
 * $multicall = new \SimpleXmlRpc\Multicall($server);
 * $multicall->system->listMethods();
 * $multicall->some_func();
 * $multicall->some_other_func($arg1, $arg2);
 * $result = $multicall();
 * ```
 * The final result is an array of return values of the single calls in invocation order.
 *
 * @package SimpleXmlRpc
 */
class Multicall
{
    /**
     * @var ServerProxy server proxy object
     */
    private $_proxy = NULL;

    /**
     * @var array stack for call invocations
     */
    public $_callstack = array();

    /**
     * Create a new multicall object.
     *
     * @param ServerProxy $proxy
     */
    function __construct($proxy) {
        $this->_proxy = $proxy;
    }

    /**
     * Append call to callstack and return immediately.
     *
     * @param string $method
     * @param array $args
     * @return null
     */
    public function _call($method, $args=array()) {
        $this->_callstack[] = array("methodName" => $method, "params" => $args);
        return NULL;
    }

    /**
     * Call remote method for unkown local method.
     *
     * @param $name
     * @param $args
     * @return null
     */
    public function __call($name, $args) {
        return $this->_call($name, $args);
    }

    /**
     * Return a proxy object for unkown local attribute.
     *
     * @param $name
     * @return AttributeProxy
     */
    public function __get($name) {
        return new AttributeProxy($name, $this);
    }

    /**
     * Execute the multicall with the calls in callstack.
     * Return an array of results for the calls in invocation order.
     *
     * @return array
     * @throws ConnectionError
     * @throws Fault
     * @throws HttpResponseException
     */
    public function __invoke() {
        return $this->_proxy->_call("system.multicall", array($this->_callstack));
    }
}


/**
 * Class SimpleXmlRpcException
 * @package SimpleXmlRpc
 */
class SimpleXmlRpcException extends \Exception {}

/**
 * Class InvalidUrlException
 * @package SimpleXmlRpc
 */
class InvalidUrlException extends SimpleXmlRpcException {}

/**
 * Class ConnectionError
 * @package SimpleXmlRpc
 */
class ConnectionError extends SimpleXmlRpcException {}

/**
 * Class HttpResponseException
 * @package SimpleXmlRpc
 */
class HttpResponseException extends SimpleXmlRpcException {}

/**
 * Class Fault
 * @package SimpleXmlRpc
 */
class Fault extends SimpleXmlRpcException {}
