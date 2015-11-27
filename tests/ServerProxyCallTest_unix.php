<?php

require_once "SimpleXmlRpc.php";
require_once "ServerProxyCallTestBase.php";


class ServerProxyCallTest_unix extends ServerProxyCallTestBase
{
    public static $p_handle = NULL;
    public static $pipes = array();

    public static function setUpBeforeClass() {
        // start python test server
        self::$p_handle = proc_open(
            "python -u tests/test_server.py unix ".urlencode("\x00/testsock")." 0 2>&1",
            array(
                0 => array("pipe", "r"),
                1 => array("pipe", "w"),
                2 => array("pipe", "w")
            ),
            $pipes);
        stream_set_blocking($pipes[0], 0);
        stream_set_blocking($pipes[1], 0);
        stream_set_blocking($pipes[2], 0);
        self::$pipes = $pipes;

        // wait for subprocess to appear
        $s = new \SimpleXmlRpc\ServerProxy("http+unix://".urlencode("\x00/testsock"));
        while (TRUE) {
            usleep(50);
            $fp = @fsockopen($s->_transport_url, $s->_port);
            if ($fp) {
                fclose($fp);
                break;
            }
        }
    }

    public $serverproxy = NULL;

    public function setUp() {
        $this->serverproxy = new \SimpleXmlRpc\ServerProxy(
            "http+unix://".urlencode("\x00/testsock")
        );
    }

    public static function tearDownAfterClass() {
        // kill all server processes
        $status = proc_get_status(self::$p_handle);
        $ppid = $status["pid"];
        array_map("fclose", self::$pipes);
        $pids = preg_split('/\s+/', `ps -o pid --no-heading --ppid $ppid`);
        foreach($pids as $pid) {
            if(is_numeric($pid)) {
                posix_kill($pid, 9);
            }
        }
    }
}