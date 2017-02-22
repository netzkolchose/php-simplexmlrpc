<?php

require_once "SimpleXmlRpc.php";
require_once "ServerProxyCallTestBase.php";


class ServerProxyCallTest_https extends ServerProxyCallTestBase
{
    public static $p_handle = NULL;
    public static $pipes = array();

    public static function setUpBeforeClass() {
        // start python test server
        self::$p_handle = proc_open(
            "python -u tests/test_server.py https localhost 8080 2>&1",
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
        $opts = array("ssl" => array("peer_name" => "mooder.ein.topf", "verify_peer" => false));
        $ctx = stream_context_create($opts);
        while (TRUE) {
            usleep(50);
            $fp = @stream_socket_client("ssl://localhost:8080", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $ctx);
            if ($fp) {
                fclose($fp);
                break;
            }
        }
    }

    /**
     * @expectedException \SimpleXmlRpc\HttpResponseException
     * @expectedExceptionMessage Not Found
     * @expectedExceptionCode 404
     */
    public function testError404() {
        $opts = array("ssl" => array("peer_name" => "mooder.ein.topf", "verify_peer" => false));
        $ctx = stream_context_create($opts);
        $s = new \SimpleXmlRpc\ServerProxy("https://localhost:8080/wrong/path?with=params", $ctx);
        $s->system->listMethods();
    }

    public $serverproxy = NULL;

    public function setUp() {
        $opts = array("ssl" => array("peer_name" => "mooder.ein.topf", "verify_peer" => false));
        $ctx = stream_context_create($opts);
        $this->serverproxy = new \SimpleXmlRpc\ServerProxy("https://localhost:8080", $ctx);
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
