<?php

namespace Ratchet\Session\Serialize;

/**
 * @covers Ratchet\Session\Serialize\PhpHandler
 */
class PhpHandlerTest extends \PHPUnit\Framework\TestCase
{
    protected $handler;

    public function setUp(): void
    {
        $this->handler = new PhpHandler();
    }

    public function serializedProvider()
    {
        return array(
            array(
                '_sf2_attributes|a:2:{s:5:"hello";s:5:"world";s:4:"last";i:1332872102;}_sf2_flashes|a:0:{}'
              , array(
                    '_sf2_attributes' => array(
                        'hello' => 'world'
                      , 'last'  => 1332872102
                    )
                  , '_sf2_flashes' => array()
                )
            )
        );
    }

    /**
     * @dataProvider serializedProvider
     */
    public function testUnserialize($in, $expected)
    {
        $this->assertEquals($expected, $this->handler->unserialize($in));
    }

    /**
     * @dataProvider serializedProvider
     */
    public function testSerialize($serialized, $original)
    {
        $this->assertEquals($serialized, $this->handler->serialize($original));
    }
}
