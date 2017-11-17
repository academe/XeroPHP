<?php

namespace Academe\XeroPHP;

use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

class ResponseMessageTest extends TestCase
{
    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $data must be an array; string supplied
     */
    public function testInvalidInitData()
    {
        $notSet = new ResponseMessage('a string');
    }

    /**
     * Setting simple fields can be detected and the source fields extracted.
     */
    public function testSourceFieldsSet()
    {
        $data = [
            'Foo' => 'Bar',
            'wiggly' => 'woo',
        ];

        $message = new ResponseMessage($data);

        $this->assertSame(true, $message->hasSourceField('foo'));
        $this->assertSame(true, $message->hasSourceField('Foo'));
        $this->assertSame(true, $message->hasSourceField('FOO'));
        $this->assertSame(true, $message->hasSourceField('Wiggly'));

        $this->assertSame(false, $message->hasSourceField('bar'));
        $this->assertSame(false, $message->hasSourceField('brexit'));

        $this->assertSame('Bar', $message->getSourceField('foo'));
        $this->assertSame('Bar', $message->getSourceField('Foo'));
        $this->assertSame('woo', $message->getSourceField('Wiggly'));

        $this->assertNull($message->getSourceField('bumblebee'));

        $this->assertEquals($message->getSource(), $data);
    }
}
