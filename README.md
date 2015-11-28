[![Build Status](https://travis-ci.org/netzkolchose/php-simplexmlrpc.svg?branch=master)](https://travis-ci.org/netzkolchose/php-simplexmlrpc)
[![Coverage Status](https://coveralls.io/repos/netzkolchose/php-simplexmlrpc/badge.svg?branch=master&service=github)](https://coveralls.io/github/netzkolchose/php-simplexmlrpc?branch=master)

Simple XMLRPC client library for PHP.

Supports:

 * HTTP, HTTPS and HTTP+UNIX (HTTP over unix domain sockets)
 * Basic Auth
 * multicall

Example:
```php
$server = new \SimpleXmlRpc\ServerProxy("https://example.com:443/xmlprc");
$server->system->listMethods();
$server->some_method($arg1, $arg2);
$server->some->very->deep->method();
```

Multicall example:
```php
$server = new \SimpleXmlRpc\ServerProxy("https://example.com:443/xmlprc");
$multicall = new \SimpleXmlRpc\Multicall($server);
$multicall->system->listMethods();
$multicall->some_func();
$multicall->some_other_func($arg1, $arg2);
$result = $multicall();
```