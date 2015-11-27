<?php

class ServerProxyCallTestBase extends PHPUnit_Framework_TestCase
{
    public $serverproxy = NULL;

    public function tearDown() {
        // clear server stdout
        // print it, if SERVERLOGS is set
        $server_logs = getenv("SERVERLOGS");
        if ($server_logs) {
            echo "\n";
        }
        while (TRUE) {
            $server_log_line = fgets(static::$pipes[1]);
            if (!$server_log_line){
                break;
            }
            if ($server_logs) {
                echo "SERVER: ".$server_log_line;
            }
        }
        if ($server_logs) {
            echo "\n";
        }
    }

    public function testSystemListMethods() {
        $res = $this->serverproxy->_call("system.listMethods");
        $this->assertInternalType("array", $res);
        $this->assertContains("system.listMethods", $res);
        $this->assertContains("system.methodHelp", $res);
        $this->assertContains("system.methodSignature", $res);
        $this->assertContains("test_string", $res);
        $this->assertContains("test_none", $res);
        $this->assertContains("test_list", $res);
        $this->assertContains("test_dict", $res);
    }
    public function testMethodResolutionSystemListMethods() {
        $res1 = $this->serverproxy->_call("system.listMethods");
        $res2 = $this->serverproxy->system->listMethods();
        $this->assertEquals($res1, $res2);
    }
    public function testMethodString() {
        $res1 = $this->serverproxy->_call("test_string");
        $res2 = $this->serverproxy->test_string();
        $this->assertEquals($res1, $res2);
        $this->assertEquals("called test_string", $res2);
    }
    public function testMethodNone() {
        $res1 = $this->serverproxy->_call("test_none");
        $res2 = $this->serverproxy->test_none();
        $this->assertEquals($res1, $res2);
        $this->assertEquals(NULL, $res2);
    }
    public function testMethodList() {
        $res1 = $this->serverproxy->_call("test_list");
        $res2 = $this->serverproxy->test_list();
        $this->assertEquals($res1, $res2);
        $this->assertEquals(['called test_list'], $res2);
    }
    public function testMethodDict() {
        $res1 = $this->serverproxy->_call("test_dict");
        $res2 = $this->serverproxy->test_dict();
        $this->assertEquals($res1, $res2);
        $this->assertEquals(['int' => 123, 'list' => [1, 2, 3]], $res2);
    }
    public function testMethodParam() {
        $param = ['int' => 123, 'list' => [1, 2, 3], 'string', 'äöüß€'];
        $res1 = $this->serverproxy->_call("test_param", [$param]);
        $res2 = $this->serverproxy->test_param($param);
        $this->assertEquals($res1, $res2);
        $this->assertEquals($param, $res2);
    }
    public function testMulticallMethods() {
        $multicall = new \SimpleXmlRpc\Multicall($this->serverproxy);
        $multicall->test_string();
        $multicall->test_none();
        $multicall->test_list();
        $multicall->test_dict();
        $res = $multicall();
        $this->assertEquals(
            [
                ["called test_string"],
                [NULL],
                [['called test_list']],
                [['int' => 123, 'list' => [1, 2, 3]]]
            ],
            $res);
    }
    /**
     * @expectedException \SimpleXmlRpc\Fault
     */
    public function testFault() {
        $this->serverproxy->test_error();
    }
    public function testGzip() {
        $this->assertEquals(str_repeat("0123456789", 1000), $this->serverproxy->test_gzip());
    }
}