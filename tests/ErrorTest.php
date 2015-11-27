<?php

require_once "SimpleXmlRpc.php";

class ErrorTest extends PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \SimpleXmlRpc\ConnectionError
     */
    public function testFailedToOpen() {
        $s = new \SimpleXmlRpc\ServerProxy("http://localhost");
        $s->system->listMethods();
    }

    /**
     * @expectedException \SimpleXmlRpc\SimpleXmlRpcException
     */
    public function testHeaderParseError() {
        \SimpleXmlRpc\ServerProxy::_parse_header(["Test" => "abc", "fail"]);
    }

    //public function testGzip() {
    //    $s = new \SimpleXmlRpc\ServerProxy("http://phpxmlrpc.sourceforge.net/server.php");
    //    $this->assertNotEquals(NULL, $s->system->listMethods());
    //}
}