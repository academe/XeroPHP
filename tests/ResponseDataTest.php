<?php

namespace Academe\XeroPHP;

use PHPUnit\Framework\TestCase;

class ResponseDataTest extends TestCase
{
    public function setUp()
    {
        $employeesData = json_decode(file_get_contents(__DIR__ . '/data/employees.json'), true);
        $this->employees = new ResponseData($employeesData);
    }

    public function testEmployeesRoot()
    {
        $this->assertEquals($this->employees->isCollection(), false);
        $this->assertEquals($this->employees->isEmpty(), false);
        $this->assertEquals($this->employees->isAssociative(), true);
        $this->assertEquals($this->employees->hasParent(), false);
        // B: resource or resources with new format header
        $this->assertEquals($this->employees->getStructureType(), ResponseData::STRUCTURE_B);
    }

    public function testEmployeesResources()
    {
        $resources = $this->employees->getResources();

        $this->assertEquals($resources->isCollection(), true);
        $this->assertEquals($resources->isEmpty(), false);
        $this->assertEquals($resources->isAssociative(), false);
        $this->assertEquals($resources->hasParent(), true);
        // E: naked collection of resources
        $this->assertEquals($resources->getStructureType(), ResponseData::STRUCTURE_E);

        $this->assertEquals($resources->count(), 2);
        $this->assertEquals(count($resources), 2);
    }

    public function testEmployeesResource()
    {
        $resource = $this->employees->getResource();

        $this->assertEquals($resource->isCollection(), false);
        $this->assertEquals($resource->isEmpty(), false);
        $this->assertEquals($resource->isAssociative(), true);
        $this->assertEquals($resource->hasParent(), true);
        // F: single naked resource
        $this->assertEquals($resource->getStructureType(), ResponseData::STRUCTURE_F);
    }
}
