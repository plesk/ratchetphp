<?php

namespace Ratchet\Http;

use Ratchet\AbstractMessageComponentTestCase;

/**
 * @covers Ratchet\Http\OriginCheck
 */
class OriginCheckTest extends AbstractMessageComponentTestCase
{
    protected $reqStub;

    public function setUp(): void
    {
        $this->reqStub = $this->createMock('Psr\Http\Message\RequestInterface');
        $this->reqStub->expects($this->any())->method('getHeader')->will($this->returnValue(['localhost']));

        parent::setUp();

        $this->serv->allowedOrigins[] = 'localhost';
    }

    protected function doOpen($conn)
    {
        $this->serv->onOpen($conn, $this->reqStub);
    }

    public function getConnectionClassString()
    {
        return '\Ratchet\ConnectionInterface';
    }

    public function getDecoratorClassString()
    {
        return '\Ratchet\Http\OriginCheck';
    }

    public function getComponentClassString()
    {
        return '\Ratchet\Http\HttpServerInterface';
    }

    public function testCloseOnNonMatchingOrigin()
    {
        $this->serv->allowedOrigins = ['socketo.me'];
        $this->conn->expects($this->once())->method('close');

        $this->serv->onOpen($this->conn, $this->reqStub);
    }

    public function testOnMessage()
    {
        $this->passthroughMessageTest('Hello World!');
    }
}
