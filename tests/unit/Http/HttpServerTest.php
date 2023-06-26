<?php

namespace Ratchet\Http;

use Ratchet\AbstractMessageComponentTestCase;

/**
 * @covers Ratchet\Http\HttpServer
 */
class HttpServerTest extends AbstractMessageComponentTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->conn->httpHeadersReceived = true;
    }

    public function getConnectionClassString()
    {
        return '\Ratchet\ConnectionInterface';
    }

    public function getDecoratorClassString()
    {
        return '\Ratchet\Http\HttpServer';
    }

    public function getComponentClassString()
    {
        return '\Ratchet\Http\HttpServerInterface';
    }

    public function testOpen()
    {
        $headers = "GET / HTTP/1.1\r\nHost: socketo.me\r\n\r\n";

        $this->conn->httpHeadersReceived = false;
        $this->app->expects($this->once())->method('onOpen')->with($this->isExpectedConnection());
        $this->serv->onMessage($this->conn, $headers);
    }

    public function testOnMessageAfterHeaders()
    {
        $headers = "GET / HTTP/1.1\r\nHost: socketo.me\r\n\r\n";
        $this->conn->httpHeadersReceived = false;
        $this->serv->onMessage($this->conn, $headers);

        $message = "Hello World!";
        $this->app->expects($this->once())->method('onMessage')->with($this->isExpectedConnection(), $message);
        $this->serv->onMessage($this->conn, $message);
    }

    public function testBufferOverflow()
    {
        $this->conn->expects($this->once())->method('close');
        $this->conn->httpHeadersReceived = false;

        $this->serv->onMessage($this->conn, str_repeat('a', 5000));
    }

    public function testCloseIfNotEstablished()
    {
        $this->conn->httpHeadersReceived = false;
        $this->conn->expects($this->once())->method('close');
        $this->serv->onError($this->conn, new \Exception('Whoops!'));
    }

    public function testBufferHeaders()
    {
        $this->conn->httpHeadersReceived = false;
        $this->app->expects($this->never())->method('onOpen');
        $this->app->expects($this->never())->method('onMessage');

        $this->serv->onMessage($this->conn, "GET / HTTP/1.1");
    }
}
