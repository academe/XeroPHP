<?php

namespace Academe\XeroPHP;

use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

class ResourceCollectionTest extends TestCase
{
    public function testEmpty()
    {
        $empty = new ResourceCollection();

        $this->assertSame(count($empty), 0);

        $this->assertSame($empty->first(), null);
    }

    /**
     * A resource collection cannot be given scalars to hold.
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage ResourceCollection given a scalar "0"=>"string" as a resource; not permitted
     */
    public function testInvalidScalars()
    {
        $invalid = new ResourceCollection(['abc', 'xyz']);
    }

    public function testValidResources()
    {
        $valid = new ResourceCollection([
            ['a' => 'bc'],
            ['x' => 'yz']
        ]);

        $this->assertSame($valid->first()->a, 'bc');
    }
}
