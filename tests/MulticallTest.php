<?php

require_once "SimpleXmlRpc.php";

class MulticallTest extends PHPUnit_Framework_TestCase
{
    public function testMulticallCallstack() {
        $s = new \SimpleXmlRpc\ServerProxy("http://localhost");
        $multicall = new \SimpleXmlRpc\Multicall($s);
        $multicall->_call("test_string", []);
        $multicall->test_string();
        $this->assertEquals(
            [
                ["methodName" => "test_string", "params" => []],
                ["methodName" => "test_string", "params" => []],
            ],
            $multicall->_callstack);
    }
    public function testMulticallDeepCallstack() {
        $s = new \SimpleXmlRpc\ServerProxy("http://localhost");
        $multicall = new \SimpleXmlRpc\Multicall($s);
        $multicall->_call("a.b.c", []);
        $multicall->a->b->c();
        $multicall->system->listMethods();
        $this->assertEquals(
            [
                ["methodName" => "a.b.c", "params" => []],
                ["methodName" => "a.b.c", "params" => []],
                ["methodName" => "system.listMethods", "params" => []]
            ],
            $multicall->_callstack);
    }
}