<?php

require_once "SimpleXmlRpc.php";

class ServerProxyStartUpTest extends PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \SimpleXmlRpc\InvalidUrlException
     */
    public function testWrongUrl() {
        new \SimpleXmlRpc\ServerProxy(";;;surn:123%&$;::sfds");
    }
    /**
     * @expectedException \SimpleXmlRpc\InvalidUrlException
     */
    public function testUnkownScheme() {
        new \SimpleXmlRpc\ServerProxy("ftp://example.com");
    }
    public function testHttpUrlDefault() {
        $s = new \SimpleXmlRpc\ServerProxy("http://example.com");
        $this->assertEquals("tcp", $s->_transport);
        $this->assertEquals(NULL, $s->_authtoken);
        $this->assertEquals("example.com", $s->_host);
        $this->assertEquals(80, $s->_port);
        $this->assertEquals("/", $s->_path);
        $this->assertEquals("tcp://example.com", $s->_transport_url);
    }
    public function testHttpUrlFull() {
        $s = new \SimpleXmlRpc\ServerProxy("http://name:password@example.com:123/path/to/xmlrpc?a=1&b=test#fragment");
        $this->assertEquals("tcp", $s->_transport);
        $this->assertEquals(base64_encode("name:password"), $s->_authtoken);
        $this->assertEquals("example.com", $s->_host);
        $this->assertEquals(123, $s->_port);
        $this->assertEquals("/path/to/xmlrpc?a=1&b=test#fragment", $s->_path);
        $this->assertEquals("tcp://example.com", $s->_transport_url);
    }
    public function testHttpsUrlDefault() {
        $s = new \SimpleXmlRpc\ServerProxy("https://example.com");
        $this->assertEquals("ssl", $s->_transport);
        $this->assertEquals(NULL, $s->_authtoken);
        $this->assertEquals("example.com", $s->_host);
        $this->assertEquals(443, $s->_port);
        $this->assertEquals("/", $s->_path);
        $this->assertEquals("ssl://example.com", $s->_transport_url);
    }
    public function testHttpsUrlFull() {
        $s = new \SimpleXmlRpc\ServerProxy("https://name:password@example.com:123/path/to/xmlrpc?a=1&b=test#fragment");
        $this->assertEquals("ssl", $s->_transport);
        $this->assertEquals(base64_encode("name:password"), $s->_authtoken);
        $this->assertEquals("example.com", $s->_host);
        $this->assertEquals(123, $s->_port);
        $this->assertEquals("/path/to/xmlrpc?a=1&b=test#fragment", $s->_path);
        $this->assertEquals("ssl://example.com", $s->_transport_url);
    }
    public function testUnixUrlDefault() {
        $s = new \SimpleXmlRpc\ServerProxy("http+unix://".urlencode("/path/to/sock.file"));
        $this->assertEquals("unix", $s->_transport);
        $this->assertEquals(NULL, $s->_authtoken);
        $this->assertEquals("/path/to/sock.file", $s->_host);
        $this->assertEquals(0, $s->_port);
        $this->assertEquals("/", $s->_path);
        $this->assertEquals("unix:///path/to/sock.file", $s->_transport_url);
    }
    public function testUnixUrlFull() {
        $s = new \SimpleXmlRpc\ServerProxy("http+unix://name:password@".urlencode("/path/to/sock.file").":123/path/to/xmlrpc?a=1&b=test#fragment");
        $this->assertEquals("unix", $s->_transport);
        $this->assertEquals(base64_encode("name:password"), $s->_authtoken);
        $this->assertEquals("/path/to/sock.file", $s->_host);
        $this->assertEquals(0, $s->_port);
        $this->assertEquals("/path/to/xmlrpc?a=1&b=test#fragment", $s->_path);
        $this->assertEquals("unix:///path/to/sock.file", $s->_transport_url);
    }
    public function testUnixUrlFullAbstract() {
        $s = new \SimpleXmlRpc\ServerProxy("http+unix://name:password@" . urlencode("\x00/path/to/sock.file") . ":123/path/to/xmlrpc?a=1&b=test#fragment");
        $this->assertEquals("unix", $s->_transport);
        $this->assertEquals(base64_encode("name:password"), $s->_authtoken);
        $this->assertEquals("\x00/path/to/sock.file", $s->_host);
        $this->assertEquals(0, $s->_port);
        $this->assertEquals("/path/to/xmlrpc?a=1&b=test#fragment", $s->_path);
        $this->assertEquals("unix://\x00/path/to/sock.file", $s->_transport_url);
    }
}