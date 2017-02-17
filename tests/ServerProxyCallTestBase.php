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
        $this->assertEquals(array('called test_list'), $res2);
    }
    public function testMethodDict() {
        $res1 = $this->serverproxy->_call("test_dict");
        $res2 = $this->serverproxy->test_dict();
        $this->assertEquals($res1, $res2);
        $this->assertEquals(array('int' => 123, 'list' => array(1, 2, 3)), $res2);
    }
    public function testMethodParam() {
        $param = array('int' => 123, 'list' => array(1, 2, 3), 'string', 'äöüß€');
        $res1 = $this->serverproxy->_call("test_param", array($param));
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
            array(
                "called test_string",
                NULL,
                array('called test_list'),
                array('int' => 123, 'list' => array(1, 2, 3))
            ),
            iterator_to_array($res));
    }
    /**
     * @expectedException \SimpleXmlRpc\Fault
     */
    public function testMulticallMethodsFault() {
        $multicall = new \SimpleXmlRpc\Multicall($this->serverproxy);
        $multicall->test_string();
        $multicall->test_none();
        $multicall->test_list();
        $multicall->test_dict();
        $multicall->test_error();
        $res = $multicall();
        iterator_to_array($res);
    }
    public function testMulticallMethodsFaultCatched() {
        $multicall = new \SimpleXmlRpc\Multicall($this->serverproxy);
        $multicall->test_string();
        $multicall->test_none();
        $multicall->test_list();
        $multicall->test_dict();
        $multicall->test_error();
        $res = $multicall();
        $ar = array();
        try {
            $ar = iterator_to_array($res);
        } catch (\SimpleXmlRpc\Fault $e) {};
        $this->assertEquals(array(), $ar);
    }
    public function testMulticallMethodsFaultPartly() {
        $multicall = new \SimpleXmlRpc\Multicall($this->serverproxy);
        $multicall->test_string();
        $multicall->test_none();
        $multicall->test_list();
        $multicall->test_dict();
        $multicall->test_error();
        $multicall->test_dict();
        $res = $multicall();
        $ar = array();
        try {
            foreach ($res as $result)
                $ar[] = $result;
        } catch (\SimpleXmlRpc\Fault $e) {};
        $this->assertEquals(
            array(
                "called test_string",
                NULL,
                array('called test_list'),
                array('int' => 123, 'list' => array(1, 2, 3))
            ),
            $ar);
    }
    public function testMulticallMethodsFaultStripped() {
        $multicall = new \SimpleXmlRpc\Multicall($this->serverproxy);
        $multicall->test_string();
        $multicall->test_none();
        $multicall->test_list();
        $multicall->test_dict();
        $multicall->test_error();
        $multicall->test_dict();
        $res = $multicall();
        // test for Fault instances
        $ar = array();
        foreach ($res->rawData() as $result) {
            if ($result instanceof \SimpleXmlRpc\Fault)
                continue;
            $ar[] = $result;
        }
        $this->assertEquals(
            array(
                "called test_string",
                NULL,
                array('called test_list'),
                array('int' => 123, 'list' => array(1, 2, 3)),
                // fault here skipped
                array('int' => 123, 'list' => array(1, 2, 3))
            ),
            $ar);
        // low level iterator interface with automatic exception
        $ar2 = array();
        while ($res->valid()) {
            try {
                $ar2[] = $res->current();
            } catch (\SimpleXmlRpc\Fault $e) {};
            $res->next();
        }
        $this->assertEquals($ar, $ar2);
    }
    public function testMulticallMethodsRawData() {
        $multicall = new \SimpleXmlRpc\Multicall($this->serverproxy);
        $multicall->test_string();
        $multicall->test_none();
        $multicall->test_list();
        $multicall->test_dict();
        $res = $multicall();
        $this->assertEquals(
            array(
                "called test_string",
                NULL,
                array('called test_list'),
                array('int' => 123, 'list' => array(1, 2, 3))
            ),
            $res->rawData());
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