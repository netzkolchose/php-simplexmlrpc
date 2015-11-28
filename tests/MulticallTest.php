<?php

require_once "SimpleXmlRpc.php";

class MulticallTest extends PHPUnit_Framework_TestCase
{
    public function testMulticallCallstack() {
        $s = new \SimpleXmlRpc\ServerProxy("http://localhost");
        $multicall = new \SimpleXmlRpc\Multicall($s);
        $multicall->_call("test_string", array());
        $multicall->test_string();
        $this->assertEquals(
            array(
                array("methodName" => "test_string", "params" => array()),
                array("methodName" => "test_string", "params" => array()),
            ),
            $multicall->_callstack);
    }
    public function testMulticallDeepCallstack() {
        $s = new \SimpleXmlRpc\ServerProxy("http://localhost");
        $multicall = new \SimpleXmlRpc\Multicall($s);
        $multicall->_call("a.b.c", array());
        $multicall->a->b->c();
        $multicall->system->listMethods();
        $this->assertEquals(
            array(
                array("methodName" => "a.b.c", "params" => array()),
                array("methodName" => "a.b.c", "params" => array()),
                array("methodName" => "system.listMethods", "params" => array())
            ),
            $multicall->_callstack);
    }
}