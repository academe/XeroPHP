<?php

namespace Academe\XeroPHP;

use PHPUnit\Framework\TestCase;

class ResponseDataTest extends TestCase
{
    protected $gbPayrollEmployees;
    protected $gbPayrollEmployee;
    protected $accountingPayments;
    protected $accountingPayment;

    /**
     * TODO: 404 from Accounting API.
     * TODO: Zero records in collection (old and new API)
     */
    public function setUp()
    {
        // Two employees from the GB Payroll API v2.0.
        $employeesData = json_decode(file_get_contents(__DIR__ . '/data/gbPayrollEmployees.json'), true);
        $this->gbPayrollEmployees = new ResponseData($employeesData);

        // Single employee from the GB Payroll API v2.0.
        $employeeData = json_decode(file_get_contents(__DIR__ . '/data/gbPayrollEmployee.json'), true);
        $this->gbPayrollEmployee = new ResponseData($employeeData);

        // Three bank payments from the Accounting API v2.0.
        $accountingPayments = json_decode(file_get_contents(__DIR__ . '/data/accountingPayments.json'), true);
        $this->accountingPayments = new ResponseData($accountingPayments);

        // Single bank payment from the Accounting API v2.0.
        $accountingPayment = json_decode(file_get_contents(__DIR__ . '/data/accountingPayment.json'), true);
        $this->accountingPayment = new ResponseData($accountingPayment);
    }

    // 

    /**
     * Properties of the root GB Payroll Employees collection response.
     */
    public function testGbPayrollEmployeesRoot()
    {
        $employees = $this->gbPayrollEmployees;

        $this->assertEquals($employees->isCollection(), false);
        $this->assertEquals($employees->isEmpty(), false);
        $this->assertEquals($employees->isAssociative(), true);
        $this->assertEquals($employees->hasParent(), false);
        // B: multiple resources with new format header
        $this->assertEquals($employees->getStructureType(), ResponseData::STRUCTURE_B);

        $this->assertEquals($employees->count(), 2);
        $this->assertEquals(count($employees), 2);
    }

    /**
     * Properties of the GB Payroll Employees resource collection.
     */
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

    /**
     * Properties of the GB Payroll Employees resource collection as a single employee.
     */
    public function testGbPayrollEmployeesResource()
    {
        $resource = $this->gbPayrollEmployees->getResource();

        $this->assertEquals($resource->isCollection(), false);
        $this->assertEquals($resource->isEmpty(), false);
        $this->assertEquals($resource->isAssociative(), true);
        $this->assertEquals($resource->hasParent(), true);
        // F: single naked resource
        $this->assertEquals($resource->getStructureType(), ResponseData::STRUCTURE_F);

        $this->assertEquals($resource->count(), 1);
        $this->assertEquals(count($resource), 1);
    }

    //

    /**
     * Properties of the root GB Payroll single Employee response.
     */
    public function testGbPayrollEmployeeRoot()
    {
        $employee = $this->gbPayrollEmployee;

        $this->assertEquals($employee->isCollection(), false);
        $this->assertEquals($employee->isEmpty(), false);
        $this->assertEquals($employee->isAssociative(), true);
        $this->assertEquals($employee->hasParent(), false);
        // A: single resource with new format header
        $this->assertEquals($employee->getStructureType(), ResponseData::STRUCTURE_A);

        $this->assertEquals($employee->count(), 1);
        $this->assertEquals(count($employee), 1);
    }

    /**
     * Properties of the GB Payroll single Employee resource as a collection.
     */
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

    /**
     * Properties of the GB Payroll single Employee resource.
     */
    public function testGbPayrollEmployeeResource()
    {
        $resource = $this->gbPayrollEmployee->getResource();

        $this->assertEquals($resource->isCollection(), false);
        $this->assertEquals($resource->isEmpty(), false);
        $this->assertEquals($resource->isAssociative(), true);
        $this->assertEquals($resource->hasParent(), true);
        // F: single naked resource
        $this->assertEquals($resource->getStructureType(), ResponseData::STRUCTURE_F);

        $this->assertEquals($resource->count(), 1);
        $this->assertEquals(count($resource), 1);
    }

    // 

    /**
     * Properties of the root Accounting Payments collection response.
     */
    public function testAccountingPaymentsRoot()
    {
        $payments = $this->accountingPayments;

        $this->assertEquals($payments->isCollection(), false);
        $this->assertEquals($payments->isEmpty(), false);
        $this->assertEquals($payments->isAssociative(), true);
        $this->assertEquals($payments->hasParent(), false);
        // C: multiple resources with an old (Accounting 2.0) format header
        $this->assertEquals($payments->getStructureType(), ResponseData::STRUCTURE_C);

        $this->assertEquals($payments->count(), 3);
        $this->assertEquals(count($payments), 3);
    }

    /**
     * Properties of the Accounting 2.0 Payments resource as a collection.
     */
    public function testAccountingPaymentsResources()
    {
        $resources = $this->accountingPayments->getResources();

        $this->assertEquals($resources->isCollection(), true);
        $this->assertEquals($resources->isEmpty(), false);
        $this->assertEquals($resources->isAssociative(), false);
        $this->assertEquals($resources->hasParent(), true);
        // E: naked collection of resources
        $this->assertEquals($resources->getStructureType(), ResponseData::STRUCTURE_E);

        $this->assertEquals($resources->count(), 3);
        $this->assertEquals(count($resources), 3);
    }

    /**
     * Properties of the Accounting 2.0 single Payment resource.
     */
    public function testAccountingPaymentsResource()
    {
        $resource = $this->accountingPayments->getResource();

        $this->assertEquals($resource->isCollection(), false);
        $this->assertEquals($resource->isEmpty(), false);
        $this->assertEquals($resource->isAssociative(), true);
        $this->assertEquals($resource->hasParent(), true);
        // F: single naked resource
        $this->assertEquals($resource->getStructureType(), ResponseData::STRUCTURE_F);

        $this->assertEquals($resource->count(), 1);
        $this->assertEquals(count($resource), 1);
    }

    // 

    /**
     * Properties of the root Accounting single Payment response.
     */
    public function testAccountingPaymentRoot()
    {
        $payment = $this->accountingPayment;

        $this->assertEquals($payment->isCollection(), false);
        $this->assertEquals($payment->isEmpty(), false);
        $this->assertEquals($payment->isAssociative(), true);
        $this->assertEquals($payment->hasParent(), false);
        // C: multiple resources with an old (Accounting 2.0) format header
        $this->assertEquals($payment->getStructureType(), ResponseData::STRUCTURE_C);

        $this->assertEquals($payment->count(), 1);
        $this->assertEquals(count($payment), 1);
    }

    /**
     * Properties of the Accounting 2.0 singlePayments resource as a collection.
     */
    public function testAccountingPaymentResources()
    {
        $resources = $this->accountingPayment->getResources();

        $this->assertEquals($resources->isCollection(), true);
        $this->assertEquals($resources->isEmpty(), false);
        $this->assertEquals($resources->isAssociative(), false);
        $this->assertEquals($resources->hasParent(), true);
        // E: naked collection of resources
        $this->assertEquals($resources->getStructureType(), ResponseData::STRUCTURE_E);

        $this->assertEquals($resources->count(), 1);
        $this->assertEquals(count($resources), 1);
    }

    /**
     * Properties of the Accounting 2.0 single Payment resource.
     */
    public function testAccountingPaymentResource()
    {
        $resource = $this->accountingPayment->getResource();

        $this->assertEquals($resource->isCollection(), false);
        $this->assertEquals($resource->isEmpty(), false);
        $this->assertEquals($resource->isAssociative(), true);
        $this->assertEquals($resource->hasParent(), true);
        // F: single naked resource
        $this->assertEquals($resource->getStructureType(), ResponseData::STRUCTURE_F);

        $this->assertEquals($resource->count(), 1);
        $this->assertEquals(count($resource), 1);
    }
}
