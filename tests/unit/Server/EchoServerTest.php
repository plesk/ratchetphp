<?php

namespace Ratchet\Server;

/**
 * @covers Ratchet\Server\EchoServer
 */
class EchoServerTest extends \PHPUnit\Framework\TestCase
{
    protected $conn;
    protected $comp;

    public function setUp(): void
    {
        $this->conn = $this->createMock('\Ratchet\ConnectionInterface');
        $this->comp = new EchoServer();
    }

    public function testMessageEchod()
    {
        $message = 'Tillsonburg, my back still aches when I hear that word.';
        $this->conn->expects($this->once())->method('send')->with($message);
        $this->comp->onMessage($this->conn, $message);
    }

    public function testErrorClosesConnection()
    {
        ob_start();
        $this->conn->expects($this->once())->method('close');
        $this->comp->onError($this->conn, new \Exception());
        ob_end_clean();
    }
}
