<?php

namespace Ratchet\Http;

use Ratchet\WebSocket\WsServerInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Matcher\UrlMatcher;

/**
 * @covers Ratchet\Http\Router
 */
class RouterTest extends \PHPUnit\Framework\TestCase
{
    protected $router;
    protected $matcher;
    protected $conn;
    protected $uri;
    protected $req;

    public function setUp(): void
    {
        $this->conn = $this->createMock('\Ratchet\Mock\Connection');
        $this->uri = $this->createMock('Psr\Http\Message\UriInterface');
        $this->uri
            ->expects($this->any())
            ->method('getQuery')
            ->will($this->returnValue('foo=bar&baz=qux'));
        $this->uri
            ->expects($this->any())
            ->method('getHost')
            ->will($this->returnValue('localhost'));
        $this->req = $this->createMock('\Psr\Http\Message\RequestInterface');
        $this->req
            ->expects($this->any())
            ->method('getUri')
            ->will($this->returnValue($this->uri));
        $this->req
            ->expects($this->any())
            ->method('getMethod')
            ->will($this->returnValue('GET'));
        $this->matcher = $this->createMock('Symfony\Component\Routing\Matcher\UrlMatcherInterface');
        $this->matcher
            ->expects($this->any())
            ->method('getContext')
            ->will($this->returnValue($this->createMock('Symfony\Component\Routing\RequestContext')));
        $this->router  = new Router($this->matcher);

        $this->uri->expects($this->any())->method('getPath')->will($this->returnValue('ws://doesnt.matter/'));
        $this->uri->expects($this->any())->method('withQuery')->with($this->callback(function ($val) {
            $this->setResult($val);

            return true;
        }))->will($this->returnSelf());
        $this->uri->expects($this->any())->method('getQuery')->will($this->returnCallback([$this, 'getResult']));
        $this->req->expects($this->any())->method('withUri')->will($this->returnSelf());
    }

    public function testFourOhFour()
    {
        $this->conn->expects($this->once())->method('close');

        $nope = new ResourceNotFoundException();
        $this->matcher->expects($this->any())->method('match')->will($this->throwException($nope));

        $this->router->onOpen($this->conn, $this->req);
    }

    public function testNullRequest()
    {
        $this->expectException('\UnexpectedValueException');
        $this->router->onOpen($this->conn);
    }

    public function testControllerIsMessageComponentInterface()
    {
        $this->expectException('\UnexpectedValueException');
        $this->matcher->expects($this->any())->method('match')->will($this->returnValue(array('_controller' => new \StdClass())));
        $this->router->onOpen($this->conn, $this->req);
    }

    public function testControllerOnOpen()
    {
        $controller = $this->getMockBuilder('\Ratchet\WebSocket\WsServer')->disableOriginalConstructor()->getMock();
        $this->matcher->expects($this->any())->method('match')->will($this->returnValue(array('_controller' => $controller)));
        $this->router->onOpen($this->conn, $this->req);

        $expectedConn = new \PHPUnit\Framework\Constraint\IsInstanceOf('\Ratchet\ConnectionInterface');
        $controller->expects($this->once())->method('onOpen')->with($expectedConn, $this->req);

        $this->matcher->expects($this->any())->method('match')->will($this->returnValue(array('_controller' => $controller)));
        $this->router->onOpen($this->conn, $this->req);
    }

    public function testControllerOnMessageBubbles()
    {
        $message = "The greatest trick the Devil ever pulled was convincing the world he didn't exist";
        $controller = $this->getMockBuilder('\Ratchet\WebSocket\WsServer')->disableOriginalConstructor()->getMock();
        $controller->expects($this->once())->method('onMessage')->with($this->conn, $message);

        $this->conn->controller = $controller;

        $this->router->onMessage($this->conn, $message);
    }

    public function testControllerOnCloseBubbles()
    {
        $controller = $this->getMockBuilder('\Ratchet\WebSocket\WsServer')->disableOriginalConstructor()->getMock();
        $controller->expects($this->once())->method('onClose')->with($this->conn);

        $this->conn->controller = $controller;

        $this->router->onClose($this->conn);
    }

    public function testControllerOnErrorBubbles()
    {
        $e = new \Exception('One cannot be betrayed if one has no exceptions');
        $controller = $this->getMockBuilder('\Ratchet\WebSocket\WsServer')->disableOriginalConstructor()->getMock();
        $controller->expects($this->once())->method('onError')->with($this->conn, $e);

        $this->conn->controller = $controller;

        $this->router->onError($this->conn, $e);
    }

    public function testRouterGeneratesRouteParameters()
    {
        /** @var $controller WsServerInterface */
        $controller = $this->getMockBuilder('\Ratchet\WebSocket\WsServer')->disableOriginalConstructor()->getMock();
        /** @var $matcher UrlMatcherInterface */
        $this->matcher->expects($this->any())->method('match')->will(
            $this->returnValue(['_controller' => $controller, 'foo' => 'bar', 'baz' => 'qux'])
        );
        $conn = $this->createMock('Ratchet\Mock\Connection');

        $router = new Router($this->matcher);

        $router->onOpen($conn, $this->req);

        $this->assertEquals('foo=bar&baz=qux', $this->req->getUri()->getQuery());
    }

    public function testQueryParams()
    {
        $controller = $this->getMockBuilder('\Ratchet\WebSocket\WsServer')->disableOriginalConstructor()->getMock();
        $this->matcher->expects($this->any())->method('match')->will(
            $this->returnValue(['_controller' => $controller, 'foo' => 'bar', 'baz' => 'qux'])
        );

        $conn    = $this->createMock('Ratchet\Mock\Connection');
        $request = $this->createMock('Psr\Http\Message\RequestInterface');
        $uri = new \GuzzleHttp\Psr7\Uri('ws://doesnt.matter/endpoint?hello=world&foo=nope');

        $request->expects($this->any())->method('getUri')->will($this->returnCallback(function () use (&$uri) {
            return $uri;
        }));
        $request->expects($this->any())->method('withUri')->with($this->callback(function ($url) use (&$uri) {
            $uri = $url;

            return true;
        }))->will($this->returnSelf());
        $request->expects($this->any())->method('getMethod')->will($this->returnValue('GET'));

        $router = new Router($this->matcher);
        $router->onOpen($conn, $request);

        $this->assertEquals('foo=nope&baz=qux&hello=world', $request->getUri()->getQuery());
        $this->assertEquals('ws', $request->getUri()->getScheme());
        $this->assertEquals('doesnt.matter', $request->getUri()->getHost());
    }

    public function testImpatientClientOverflow()
    {
        $this->conn->expects($this->once())->method('close');

        $header = "GET /nope HTTP/1.1
Upgrade: websocket                                   
Connection: upgrade                                  
Host: localhost                                 
Origin: http://localhost                        
Sec-WebSocket-Version: 13\r\n\r\n";

        $app = new HttpServer(new Router(new UrlMatcher(new RouteCollection(), new RequestContext())));
        $app->onOpen($this->conn);
        $app->onMessage($this->conn, $header);
        $app->onMessage($this->conn, 'Silly body');
    }
}
