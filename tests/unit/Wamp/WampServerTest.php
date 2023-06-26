<?php

namespace Ratchet\Wamp;

use Ratchet\AbstractMessageComponentTestCase;

/**
 * @covers Ratchet\Wamp\WampServer
 */
class WampServerTest extends AbstractMessageComponentTestCase
{
    public function getConnectionClassString()
    {
        return '\Ratchet\Wamp\WampConnection';
    }

    public function getDecoratorClassString()
    {
        return 'Ratchet\Wamp\WampServer';
    }

    public function getComponentClassString()
    {
        return '\Ratchet\Wamp\WampServerInterface';
    }

    public function testOnMessageToEvent()
    {
        $published = 'Client published this message';

        $this->app->expects($this->once())->method('onPublish')->with(
            $this->isExpectedConnection(),
            new \PHPUnit\Framework\Constraint\IsInstanceOf('\Ratchet\Wamp\Topic'),
            $published,
            [],
            [],
        );

        $this->serv->onMessage($this->conn, json_encode(array(7, 'topic', $published)));
    }

    public function testGetSubProtocols()
    {
        // todo: could expand on this
        $this->assertIsArray($this->serv->getSubProtocols());
    }

    public function testConnectionClosesOnInvalidJson()
    {
        $this->conn->expects($this->once())->method('close');
        $this->serv->onMessage($this->conn, 'invalid json');
    }

    public function testConnectionClosesOnProtocolError()
    {
        $this->conn->expects($this->once())->method('close');
        $this->serv->onMessage($this->conn, json_encode(array('valid' => 'json', 'invalid' => 'protocol')));
    }
}
