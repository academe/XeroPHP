<?php

namespace Academe\XeroPHP;

use PHPUnit\Framework\TestCase;

class ResponseDataTest extends TestCase
{
    protected $gbPayrollEmployees;
    protected $gbPayrollEmployee;

    public function setUp()
    {
        $employeesData = json_decode(file_get_contents(__DIR__ . '/data/gbPayrollEmployees.json'), true);
        $this->gbPayrollEmployees = new ResponseData($employeesData);

        $employeeData = json_decode(file_get_contents(__DIR__ . '/data/gbPayrollEmployee.json'), true);
        $this->gbPayrollEmployee = new ResponseData($employeeData);
    }

    //

    public function testGbPayrollEmployeesRoot()
    {
        $employees = $this->gbPayrollEmployees;

        $this->assertEquals($employees->isCollection(), false);
        $this->assertEquals($employees->isEmpty(), false);
        $this->assertEquals($employees->isAssociative(), true);
        $this->assertEquals($employees->hasParent(), false);
        // B: multiple resources with new format header
        $this->assertEquals($employees->getStructureType(), ResponseData::STRUCTURE_B);
    }

    public function testGbPayrollEmployeesResources()
    {
        $resources = $this->gbPayrollEmployees->getResources();

        $this->assertEquals($resources->isCollection(), true);
        $this->assertEquals($resources->isEmpty(), false);
        $this->assertEquals($resources->isAssociative(), false);
        $this->assertEquals($resources->hasParent(), true);
        // E: naked collection of resources
        $this->assertEquals($resources->getStructureType(), ResponseData::STRUCTURE_E);

        $this->assertEquals($resources->count(), 2);
        $this->assertEquals(count($resources), 2);
    }

    public function testGbPayrollEmployeesResource()
    {
        $resource = $this->gbPayrollEmployees->getResource();

        $this->assertEquals($resource->isCollection(), false);
        $this->assertEquals($resource->isEmpty(), false);
        $this->assertEquals($resource->isAssociative(), true);
        $this->assertEquals($resource->hasParent(), true);
        // F: single naked resource
        $this->assertEquals($resource->getStructureType(), ResponseData::STRUCTURE_F);
    }

    //

    public function testGbPayrollEmployeeRoot()
    {
        $employee = $this->gbPayrollEmployee;

        $this->assertEquals($employee->isCollection(), false);
        $this->assertEquals($employee->isEmpty(), false);
        $this->assertEquals($employee->isAssociative(), true);
        $this->assertEquals($employee->hasParent(), false);
        // A: single resource with new format header
        $this->assertEquals($employee->getStructureType(), ResponseData::STRUCTURE_A);
    }

    public function testGbPayrollEmployeeResources()
    {
        $resources = $this->gbPayrollEmployee->getResources();

        $this->assertEquals($resources->isCollection(), true);
        $this->assertEquals($resources->isEmpty(), false);
        $this->assertEquals($resources->isAssociative(), false);
        $this->assertEquals($resources->hasParent(), true);
        // E: naked collection of resources
        $this->assertEquals($resources->getStructureType(), ResponseData::STRUCTURE_E);

        $this->assertEquals($resources->count(), 1);
        $this->assertEquals(count($resources), 1);
    }

    public function testGbPayrollEmployeeResource()
    {
        $resource = $this->gbPayrollEmployee->getResource();

        $this->assertEquals($resource->isCollection(), false);
        $this->assertEquals($resource->isEmpty(), false);
        $this->assertEquals($resource->isAssociative(), true);
        $this->assertEquals($resource->hasParent(), true);
        // F: single naked resource
        $this->assertEquals($resource->getStructureType(), ResponseData::STRUCTURE_F);
    }
}
