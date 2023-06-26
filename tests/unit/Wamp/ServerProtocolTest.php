<?php

namespace Ratchet\Wamp;

use Ratchet\Mock\Connection;
use Ratchet\Mock\WampComponent as TestComponent;

/**
 * @covers \Ratchet\Wamp\ServerProtocol
 * @covers \Ratchet\Wamp\WampServerInterface
 * @covers \Ratchet\Wamp\WampConnection
 */
class ServerProtocolTest extends \PHPUnit\Framework\TestCase
{
    protected $comp;

    protected $app;

    public function setUp(): void
    {
        $this->app = new TestComponent();
        $this->comp = new ServerProtocol($this->app);
    }

    protected function newConn()
    {
        return new Connection();
    }

    public function invalidMessageProvider()
    {
        return [
            [0],
            [3],
            [4],
            [8],
            [9],
        ];
    }

    /**
     * @dataProvider invalidMessageProvider
     */
    public function testInvalidMessages($type)
    {
        $this->expectException('\Ratchet\Wamp\Exception');

        $conn = $this->newConn();
        $this->comp->onOpen($conn);
        $this->comp->onMessage($conn, json_encode([$type]));
    }

    public function testWelcomeMessage()
    {
        $conn = $this->newConn();

        $this->comp->onOpen($conn);

        $message = $conn->last['send'];
        $json    = json_decode($message);

        $this->assertEquals(4, count($json));
        $this->assertEquals(0, $json[0]);
        $this->assertTrue(is_string($json[1]));
        $this->assertEquals(1, $json[2]);
    }

    public function testSubscribe()
    {
        $uri = 'http://example.com';
        $clientMessage = array(5, $uri);

        $conn = $this->newConn();

        $this->comp->onOpen($conn);
        $this->comp->onMessage($conn, json_encode($clientMessage));

        $this->assertEquals($uri, $this->app->last['onSubscribe'][1]);
    }

    public function testUnSubscribe()
    {
        $uri = 'http://example.com/endpoint';
        $clientMessage = array(6, $uri);

        $conn = $this->newConn();

        $this->comp->onOpen($conn);
        $this->comp->onMessage($conn, json_encode($clientMessage));

        $this->assertEquals($uri, $this->app->last['onUnSubscribe'][1]);
    }

    public function callProvider()
    {
        return [
            [2, 'a', 'b']
          , [2, ['a', 'b']]
          , [1, 'one']
          , [3, 'one', 'two', 'three']
          , [3, ['un', 'deux', 'trois']]
          , [2, 'hi', ['hello', 'world']]
          , [2, ['hello', 'world'], 'hi']
          , [2, ['hello' => 'world', 'herp' => 'derp']]
        ];
    }

    /**
     * @dataProvider callProvider
     */
    public function testCall()
    {
        $args     = func_get_args();
        $paramNum = array_shift($args);

        $uri = 'http://example.com/endpoint/' . rand(1, 100);
        $id  = uniqid('', false);
        $clientMessage = array_merge(array(2, $id, $uri), $args);

        $conn = $this->newConn();

        $this->comp->onOpen($conn);
        $this->comp->onMessage($conn, json_encode($clientMessage));

        $this->assertEquals($id, $this->app->last['onCall'][1]);
        $this->assertEquals($uri, $this->app->last['onCall'][2]);

        $this->assertEquals($paramNum, count($this->app->last['onCall'][3]));
    }

    public function testPublish()
    {
        $conn = $this->newConn();

        $topic = 'pubsubhubbub';
        $event = 'Here I am, publishing data';

        $clientMessage = array(7, $topic, $event);

        $this->comp->onOpen($conn);
        $this->comp->onMessage($conn, json_encode($clientMessage));

        $this->assertEquals($topic, $this->app->last['onPublish'][1]);
        $this->assertEquals($event, $this->app->last['onPublish'][2]);
        $this->assertEquals(array(), $this->app->last['onPublish'][3]);
        $this->assertEquals(array(), $this->app->last['onPublish'][4]);
    }

    public function testPublishAndExcludeMe()
    {
        $conn = $this->newConn();

        $this->comp->onOpen($conn);
        $this->comp->onMessage($conn, json_encode(array(7, 'topic', 'event', true)));

        $this->assertEquals($conn->WAMP->sessionId, $this->app->last['onPublish'][3][0]);
    }

    public function testPublishAndEligible()
    {
        $conn = $this->newConn();

        $buddy  = uniqid('', false);
        $friend = uniqid('', false);

        $this->comp->onOpen($conn);
        $this->comp->onMessage($conn, json_encode(array(7, 'topic', 'event', false, array($buddy, $friend))));

        $this->assertEquals(array(), $this->app->last['onPublish'][3]);
        $this->assertEquals(2, count($this->app->last['onPublish'][4]));
    }

    public function eventProvider()
    {
        return array(
            array('http://example.com', array('one', 'two'))
          , array('curie', array(array('hello' => 'world', 'herp' => 'derp')))
        );
    }

    /**
     * @dataProvider eventProvider
     */
    public function testEvent($topic, $payload)
    {
        $conn = new WampConnection($this->newConn());
        $conn->event($topic, $payload);

        $eventString = $conn->last['send'];

        $this->assertSame(array(8, $topic, $payload), json_decode($eventString, true));
    }

    public function testOnClosePropagation()
    {
        $conn = new Connection();

        $this->comp->onOpen($conn);
        $this->comp->onClose($conn);

        $class  = new \ReflectionClass('\\Ratchet\\Wamp\\WampConnection');
        $method = $class->getMethod('getConnection');
        $method->setAccessible(true);

        $check = $method->invokeArgs($this->app->last['onClose'][0], array());

        $this->assertSame($conn, $check);
    }

    public function testOnErrorPropagation()
    {
        $conn = new Connection();

        $e = new \Exception('Nope');

        $this->comp->onOpen($conn);
        $this->comp->onError($conn, $e);

        $class  = new \ReflectionClass('\\Ratchet\\Wamp\\WampConnection');
        $method = $class->getMethod('getConnection');
        $method->setAccessible(true);

        $check = $method->invokeArgs($this->app->last['onError'][0], array());

        $this->assertSame($conn, $check);
        $this->assertSame($e, $this->app->last['onError'][1]);
    }

    public function testPrefix()
    {
        $conn = new WampConnection($this->newConn());
        $this->comp->onOpen($conn);

        $prefix  = 'incoming';
        $fullURI   = "http://example.com/$prefix";
        $method = 'call';

        $this->comp->onMessage($conn, json_encode(array(1, $prefix, $fullURI)));

        $this->assertEquals($fullURI, $conn->WAMP->prefixes[$prefix]);
        $this->assertEquals("$fullURI#$method", $conn->getUri("$prefix:$method"));
    }

    public function testMessageMustBeJson()
    {
        $this->expectException('\\Ratchet\\Wamp\\JsonException');

        $conn = new Connection();

        $this->comp->onOpen($conn);
        $this->comp->onMessage($conn, 'Hello World!');
    }

    public function testGetSubProtocolsReturnsArray()
    {
        $this->assertTrue(is_array($this->comp->getSubProtocols()));
    }

    public function testGetSubProtocolsGetFromApp()
    {
        $this->app->protocols = array('hello', 'world');

        $this->assertGreaterThanOrEqual(3, count($this->comp->getSubProtocols()));
    }

    public function testWampOnMessageApp()
    {
        $app = $this->createMock('\\Ratchet\\Wamp\\WampServerInterface');
        $wamp = new ServerProtocol($app);

        $this->assertContains('wamp', $wamp->getSubProtocols());
    }

    public function badFormatProvider()
    {
        return array(
            array(json_encode(true))
          , array('{"valid":"json", "invalid": "message"}')
          , array('{"0": "fail", "hello": "world"}')
        );
    }

    /**
     * @dataProvider badFormatProvider
     */
    public function testValidJsonButInvalidProtocol($message)
    {
        $this->expectException('\Ratchet\Wamp\Exception');

        $conn = $this->newConn();
        $this->comp->onOpen($conn);
        $this->comp->onMessage($conn, $message);
    }

    public function testBadClientInputFromNonStringTopic()
    {
        $this->expectException('\Ratchet\Wamp\Exception');

        $conn = new WampConnection($this->newConn());
        $this->comp->onOpen($conn);

        $this->comp->onMessage($conn, json_encode([5, ['hells', 'nope']]));
    }

    public function testBadPrefixWithNonStringTopic()
    {
        $this->expectException('\Ratchet\Wamp\Exception');

        $conn = new WampConnection($this->newConn());
        $this->comp->onOpen($conn);

        $this->comp->onMessage($conn, json_encode([1, ['hells', 'nope'], ['bad', 'input']]));
    }

    public function testBadPublishWithNonStringTopic()
    {
        $this->expectException('\Ratchet\Wamp\Exception');

        $conn = new WampConnection($this->newConn());
        $this->comp->onOpen($conn);

        $this->comp->onMessage($conn, json_encode([7, ['bad', 'input'], 'Hider']));
    }
}
