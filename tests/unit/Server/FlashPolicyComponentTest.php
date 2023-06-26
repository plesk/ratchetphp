<?php

namespace Ratchet\Application\Server;

use Ratchet\Server\FlashPolicy;

/**
 * @covers Ratchet\Server\FlashPolicy
 */
class FlashPolicyTest extends \PHPUnit\Framework\TestCase
{
    protected $policy;

    public function setUp(): void
    {
        $this->policy = new FlashPolicy();
    }

    public function testPolicyRender()
    {
        $this->policy->setSiteControl('all');
        $this->policy->addAllowedAccess('example.com', '*');
        $this->policy->addAllowedAccess('dev.example.com', '*');

        $this->assertInstanceOf('SimpleXMLElement', $this->policy->renderPolicy());
    }

    public function testInvalidPolicyReader()
    {
        $this->expectException('UnexpectedValueException');
        $this->policy->renderPolicy();
    }

    public function testInvalidDomainPolicyReader()
    {
        $this->expectException('UnexpectedValueException');
        $this->policy->setSiteControl('all');
        $this->policy->addAllowedAccess('dev.example.*', '*');
        $this->policy->renderPolicy();
    }

    /**
     * @dataProvider siteControl
     */
    public function testSiteControlValidation($accept, $permittedCrossDomainPolicies)
    {
        $this->assertEquals($accept, $this->policy->validateSiteControl($permittedCrossDomainPolicies));
    }

    public static function siteControl()
    {
        return array(
            array(true, 'all')
          , array(true, 'none')
          , array(true, 'master-only')
          , array(false, 'by-content-type')
          , array(false, 'by-ftp-filename')
          , array(false, '')
          , array(false, 'all ')
          , array(false, 'asdf')
          , array(false, '@893830')
          , array(false, '*')
        );
    }

    /**
     * @dataProvider URI
     */
    public function testDomainValidation($accept, $domain)
    {
        $this->assertEquals($accept, $this->policy->validateDomain($domain));
    }

    public static function URI()
    {
        return array(
            array(true, '*')
          , array(true, 'example.com')
          , array(true, 'exam-ple.com')
          , array(true, '*.example.com')
          , array(true, 'www.example.com')
          , array(true, 'dev.dev.example.com')
          , array(true, 'http://example.com')
          , array(true, 'https://example.com')
          , array(true, 'http://*.example.com')
          , array(false, 'exam*ple.com')
          , array(true, '127.0.255.1')
          , array(true, 'localhost')
          , array(false, 'www.example.*')
          , array(false, 'www.exa*le.com')
          , array(false, 'www.example.*com')
          , array(false, '*.example.*')
          , array(false, 'gasldf*$#a0sdf0a8sdf')
        );
    }

    /**
     * @dataProvider ports
     */
    public function testPortValidation($accept, $ports)
    {
        $this->assertEquals($accept, $this->policy->validatePorts($ports));
    }

    public static function ports()
    {
        return array(
            array(true, '*')
          , array(true, '80')
          , array(true, '80,443')
          , array(true, '507,516-523')
          , array(true, '507,516-523,333')
          , array(true, '507,516-523,507,516-523')
          , array(false, '516-')
          , array(true, '516-523,11')
          , array(false, '516,-523,11')
          , array(false, 'example')
          , array(false, 'asdf,123')
          , array(false, '--')
          , array(false, ',,,')
          , array(false, '838*')
        );
    }

    public function testAddAllowedAccessOnlyAcceptsValidPorts()
    {
        $this->expectException('UnexpectedValueException');

        $this->policy->addAllowedAccess('*', 'nope');
    }

    public function testSetSiteControlThrowsException()
    {
        $this->expectException('UnexpectedValueException');

        $this->policy->setSiteControl('nope');
    }

    public function testErrorClosesConnection()
    {
        $conn = $this->createMock('\\Ratchet\\ConnectionInterface');
        $conn->expects($this->once())->method('close');

        $this->policy->onError($conn, new \Exception());
    }

    public function testOnMessageSendsString()
    {
        $this->policy->addAllowedAccess('*', '*');

        $conn = $this->createMock('\\Ratchet\\ConnectionInterface');
        $conn->expects($this->once())->method('send')->with($this->isType('string'));

        $this->policy->onMessage($conn, ' ');
    }

    public function testOnOpenExists()
    {
        $this->assertTrue(method_exists($this->policy, 'onOpen'));
        $conn = $this->createMock('\Ratchet\ConnectionInterface');
        $this->policy->onOpen($conn);
    }

    public function testOnCloseExists()
    {
        $this->assertTrue(method_exists($this->policy, 'onClose'));
        $conn = $this->createMock('\Ratchet\ConnectionInterface');
        $this->policy->onClose($conn);
    }
}
