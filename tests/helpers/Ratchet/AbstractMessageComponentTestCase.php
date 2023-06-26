<?php

namespace Ratchet;

abstract class AbstractMessageComponentTestCase extends \PHPUnit\Framework\TestCase
{
    protected $app;
    protected $serv;
    protected $conn;

    abstract public function getConnectionClassString();
    abstract public function getDecoratorClassString();
    abstract public function getComponentClassString();

    public function setUp(): void
    {
        $this->app  = $this->createMock($this->getComponentClassString());
        $decorator   = $this->getDecoratorClassString();
        $this->serv = new $decorator($this->app);
        $this->conn = $this->createMock('\Ratchet\Mock\Connection');

        $this->doOpen($this->conn);
    }

    protected function doOpen($conn)
    {
        $this->serv->onOpen($conn);
    }

    public function isExpectedConnection()
    {
        return new \PHPUnit\Framework\Constraint\IsInstanceOf($this->getConnectionClassString());
    }

    public function testOpen()
    {
        $this->app->expects($this->once())->method('onOpen')->with($this->isExpectedConnection());
        $this->doOpen($this->createMock('\Ratchet\Mock\Connection'));
    }

    public function testOnClose()
    {
        $this->app->expects($this->once())->method('onClose')->with($this->isExpectedConnection());
        $this->serv->onClose($this->conn);
    }

    public function testOnError()
    {
        $e = new \Exception('Whoops!');
        $this->app->expects($this->once())->method('onError')->with($this->isExpectedConnection(), $e);
        $this->serv->onError($this->conn, $e);
    }

    public function passthroughMessageTest($value): void
    {
        $this->app->expects($this->once())->method('onMessage')->with($this->isExpectedConnection(), $value);
        $this->serv->onMessage($this->conn, $value);
    }
}
