import os
import sys
import fcntl
import ssl
from SocketServer import UnixStreamServer, TCPServer
from SimpleXMLRPCServer import (SimpleXMLRPCServer, SimpleXMLRPCDispatcher,
                                SimpleXMLRPCRequestHandler)
import urllib


class UnixStreamSimpleXMLRPCRequestHandler(SimpleXMLRPCRequestHandler):
    def setup(self):
        self.connection = self.request
        if self.timeout is not None:
            self.connection.settimeout(self.timeout)

        self.rfile = self.connection.makefile('rb', self.rbufsize)
        self.wfile = self.connection.makefile('wb', self.wbufsize)

    def log_message(self, format, *args):
        sys.stderr.write("%s - - [%s] %s\n" %
                         (self.client_address,
                          self.log_date_time_string(),
                          format%args))


class UnixStreamXMLRPCServer(UnixStreamServer, SimpleXMLRPCDispatcher):
    """
    Unix domain socket version of the simple XMLRPC server.
    """
    def __init__(self, server_address,
                 requestHandler=UnixStreamSimpleXMLRPCRequestHandler,
                 allow_none=False, encoding=None, bind_and_activate=True):

        # cleanup leftover address
        if not server_address.startswith('\x00'):
            try:
                os.unlink(server_address)
            except OSError:
                if os.path.exists(server_address):
                    raise

        # logging fails with UnixStreamServer
        self.logRequests = True
        SimpleXMLRPCDispatcher.__init__(self, allow_none, encoding)
        UnixStreamServer.__init__(
            self, server_address, requestHandler, bind_and_activate)

        if fcntl is not None and hasattr(fcntl, 'FD_CLOEXEC'):
            flags = fcntl.fcntl(self.fileno(), fcntl.F_GETFD)
            flags |= fcntl.FD_CLOEXEC
            fcntl.fcntl(self.fileno(), fcntl.F_SETFD, flags)


class SecureXMLRPCServer(TCPServer, SimpleXMLRPCDispatcher):
    """
    SSL version of the simple XMLRPC server.
    """
    def __init__(self, server_address,
                 keyfile, certfile, ssl_version=ssl.PROTOCOL_TLSv1,
                 requestHandler=SimpleXMLRPCRequestHandler,
                 logRequests=True, allow_none=False, encoding=None,
                 bind_and_activate=True):
        self.logRequests = logRequests

        self.keyfile = keyfile
        self.certfile = certfile
        self.ssl_version = ssl_version

        SimpleXMLRPCDispatcher.__init__(self, allow_none, encoding)
        TCPServer.__init__(
            self, server_address, requestHandler, bind_and_activate)

        if fcntl is not None and hasattr(fcntl, 'FD_CLOEXEC'):
            flags = fcntl.fcntl(self.fileno(), fcntl.F_GETFD)
            flags |= fcntl.FD_CLOEXEC
            fcntl.fcntl(self.fileno(), fcntl.F_SETFD, flags)

    def server_bind(self):
        TCPServer.server_bind(self)
        self.socket = ssl.wrap_socket(
            self.socket, server_side=True, certfile=self.certfile,
            keyfile=self.keyfile, ssl_version=self.ssl_version,
            do_handshake_on_connect=False)

    def get_request(self):
        (socket, addr) = TCPServer.get_request(self)
        socket.do_handshake()
        return socket, addr



class Handler(object):
    def test_string(self):
        """
        Return a test string.
        """
        return 'called test_string'

    def test_none(self):
        """
        Return a test string.
        """
        return None

    def test_list(self):
        """
        Return a test string.
        """
        return ['called test_list']

    def test_dict(self):
        return {'int': 123, 'list': [1, 2, 3]}

    def test_param(self, param):
        return param

    def test_error(self):
        1/0
        return ''

    def test_gzip(self):
        return '0123456789' * 1000


def start_http(host, port):
    return SimpleXMLRPCServer((host, port), bind_and_activate=False)

def start_https(host, port):
    return SecureXMLRPCServer(
        (host, port),
        os.path.join('tests', 'certs', 'server.key'),
        os.path.join('tests', 'certs', 'server.crt'),
        bind_and_activate=False)

def start_unix(host, port):
    return UnixStreamXMLRPCServer(urllib.unquote(host), bind_and_activate=False)


SERVERS = {
    'http': start_http,
    'https': start_https,
    'unix': start_unix
}

if __name__ == '__main__':
    server = SERVERS[sys.argv[1]](sys.argv[2], int(sys.argv[3]))
    server.allow_none = True
    server.register_introspection_functions()
    server.register_multicall_functions()
    server.register_instance(Handler())

    server.allow_reuse_address = True
    server.server_bind()
    server.server_activate()
    server.serve_forever()
