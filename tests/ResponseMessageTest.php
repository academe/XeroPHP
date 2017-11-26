<?php

namespace Academe\XeroPHP;

use PHPUnit\Framework\TestCase;
use InvalidArgumentException;
use Carbon\Carbon;

class ResponseMessageTest extends TestCase
{
    protected $gbPayrollEmployees;
    protected $gbPayrollEmployee;
    protected $accountingPayments;
    protected $accountingPayment;
    protected $accountingPaymentsNoMatch;
    protected $accounting404;
    protected $fileFolders;
    protected $fileFolder;

    /**
     * TODO: 404 from Accounting API.
     * TODO: Zero records in collection (old and new API)
     */
    public function setUp()
    {
        // Two employees from the GB Payroll API v2.0.
        $employeesData = json_decode(file_get_contents(__DIR__ . '/data/gbPayrollEmployees.json'), true);
        $this->gbPayrollEmployees = new ResponseMessage($employeesData);

        // Single employee from the GB Payroll API v2.0.
        $employeeData = json_decode(file_get_contents(__DIR__ . '/data/gbPayrollEmployee.json'), true);
        $this->gbPayrollEmployee = new ResponseMessage($employeeData);

        // Three payments from the Accounting API v2.0.
        $accountingPayments = json_decode(file_get_contents(__DIR__ . '/data/accountingPayments.json'), true);
        $this->accountingPayments = new ResponseMessage($accountingPayments);

        // Single payment from the Accounting API v2.0.
        $accountingPayment = json_decode(file_get_contents(__DIR__ . '/data/accountingPayment.json'), true);
        $this->accountingPayment = new ResponseMessage($accountingPayment);

        // No-match payments from the Accounting API v2.0.
        $accountingPaymentsNoMatch = json_decode(file_get_contents(__DIR__ . '/data/accountingPaymentsNoMatch.json'), true);
        $this->accountingPaymentsNoMatch = new ResponseMessage($accountingPaymentsNoMatch);

        // 404 from the Accounting API v2.0.
        $accounting404 = json_decode(file_get_contents(__DIR__ . '/data/accounting404.json'), true);
        $this->accounting404 = new ResponseMessage($accounting404);

        // Two folders from the Files API v1.0.
        $fileFolders = json_decode(file_get_contents(__DIR__ . '/data/FileFolders.json'), true);
        $this->fileFolders = new ResponseMessage($fileFolders);

        // Single folder selected from the Files API v1.0.
        $fileFolder = json_decode(file_get_contents(__DIR__ . '/data/FileFolder.json'), true);
        $this->fileFolder = new ResponseMessage($fileFolder);
    }

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

        $this->assertEquals($message->getSourceData(), $data);
    }

    public function testEmptyData()
    {
        $message = new ResponseMessage([]);

        $this->assertEquals($message->getSourceData(), []);

        $this->assertSame($message->isEmpty(), true);
        $this->assertSame($message->isCollection(), false);
        $this->assertSame($message->isResource(), false);

        $this->assertSame($message->count(), 0);
        $this->assertSame(count($message), 0);

        foreach($message as $key => $value) {
            $this->fail('Iterator still loops over empty array');
        }

        $collection = $message->getCollection();

        $this->assertSame($collection->count(), 0);
        $this->assertSame(count($collection), 0);
    }

    /**
     * The response types that contain a simple array of resources
     * with no metadata.
     */
    public function testNakedResourcesArray()
    {
        $message = $this->fileFolders;

        $this->assertSame($message->isEmpty(), false);
        $this->assertSame($message->isCollection(), true);
        $this->assertSame($message->isResource(), false);

        $this->assertSame($message->count(), 2);
        $this->assertSame(count($message), 2);

        // Test looping over the message response performs the loop
        // over the resource collection.

        $found = 0;
        foreach($message as $key => $item) {
            if ($key === 0) {
                $this->assertSame($item->name, 'Inbox');
                $found++;
            }

            if ($key === 1) {
                $this->assertSame($item->name, 'Contracts');
                $found++;
            }
        }
        $this->assertSame($found, 2);

        $collection = $message->getCollection();

        $this->assertSame($collection->count(), 2);
        $this->assertSame(count($collection), 2);

        // Test looping over the fetched resource collection direwctly.

        $found = 0;
        foreach($collection as $key => $item) {
            if ($key === 0) {
                $this->assertSame($item->name, 'Inbox');
                $found++;
            }

            if ($key === 1) {
                $this->assertSame($item->name, 'Contracts');
                $found++;
            }
        }
        $this->assertSame($found, 2);

        $resource = $message->getResource();
    }

    /**
     * The response types that contain a simple array of resources
     * with no metadata.
     */
    public function testNakedResource()
    {
        $message = $this->fileFolder;

        $this->assertSame($message->isEmpty(), false);
        $this->assertSame($message->isCollection(), false);
        $this->assertSame($message->isResource(), true);

        $this->assertSame($message->count(), 1);
        $this->assertSame(count($message), 1);

        $resource = $message->getResource();

        $this->assertSame($resource->name, 'Inbox');
    }

    // 

    /**
     * Properties of the root GB Payroll Employees collection response.
     */
    public function testGbPayrollEmployeesRoot()
    {
        $employees = $this->gbPayrollEmployees;

        $this->assertEquals($employees->isCollection(), true);
        $this->assertEquals($employees->isEmpty(), false);

        $this->assertEquals($employees->count(), 2);
        $this->assertEquals(count($employees), 2);

        // Three different ways to get the first resource.
        $this->assertEquals($employees->first()->firstName, 'Employee-One');
        $this->assertEquals($employees->getCollection()->first()->firstName, 'Employee-One');
        $this->assertEquals($employees->getResource()->firstName, 'Employee-One');

        // Some pagination details are available.

        $pagination = $employees->getPagination();

        $this->assertEquals($pagination->page, 1);
        $this->assertEquals($pagination->pagecount, 1);
        $this->assertEquals($pagination->pageCount, 1);
        $this->assertEquals($pagination->itemCount, 2);

        // Taka a peek at the metadata.

        $metadata = $employees->getMetadata();

        $this->assertEquals('5d68e2c9-175b-41d8-8a9e-c2527110945f', $metadata->id);
        $this->assertEquals('My Application', $metadata->providerName);
        $this->assertInstanceOf(Carbon::class, $metadata->dateTimeUTC);
        $this->assertEquals('2017-11-12 13:07:04', (string)$metadata->dateTimeUTC);

        // The pagination is separate and should not be in the metadata.
        // Various ways of checking that.
        $this->assertEquals(false, $metadata->has('pagination'));
        $this->assertEquals(false, isset($metadata->pagination));
        $this->assertEquals(true, $metadata->pagination->isEmpty());
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

        $this->assertEquals($employee->count(), 1);
        $this->assertEquals(count($employee), 1);

        $this->assertEquals($employee->first()->firstName, 'Employee-One');
        $this->assertEquals($employee->getCollection()->first()->firstName, 'Employee-One');
        $this->assertEquals($employee->getResource()->firstName, 'Employee-One');

        // Pagination details for a single resource are fixed at one.

        $pagination = $employee->getPagination();

        $this->assertEquals($pagination->page, 1);
        $this->assertEquals($pagination->pageCount, 1);
        $this->assertEquals($pagination->itemCount, 1);
    }

    // 

    /**
     * Properties of the root Accounting Payments collection response.
     */
    public function testAccountingPaymentsRoot()
    {
        $payments = $this->accountingPayments;

        $this->assertEquals($payments->isCollection(), true);
        $this->assertEquals($payments->isEmpty(), false);

        $this->assertEquals($payments->count(), 3);
        $this->assertEquals(count($payments), 3);

        // Dive deeper into the response data.
        $this->assertEquals('87fa00b1-5ac5-402c-b7df-cd3f327be75b', $payments->first()->PaymentID);
        $this->assertEquals('30f29d44-1623-4bde-83a9-354a2d1cffcd', $payments->first()->invoice->InvoiceID);
        $this->assertEquals('CONSIL', $payments->first()->Account->Code);
        $this->assertEquals('9ca7f25c-bf76-4aff-99dc-585d6822b172', $payments->first()->Invoice->Contact->contactid);

        // The Invoice is a child resource.
        $this->assertInstanceOf(Resource::class, $payments->first()->invoice);

        // The CreditNotes are an array, so will be a collectinon, but happen to be empty.
        $this->assertInstanceOf(ResourceCollection::class, $payments->first()->Invoice->CreditNotes);
        $this->assertEquals(true, $payments->first()->Invoice->CreditNotes->isEmpty());
    }

    /**
     * Properties of the root Accounting Payments collection response.
     */
    public function testAccounting404()
    {
        $accounting404 = $this->accounting404;

        $this->assertEquals($accounting404->isCollection(), false);
        $this->assertEquals($accounting404->isResource(), true);
        $this->assertEquals($accounting404->isEmpty(), false);
        //$this->assertEquals($accounting404->isError(), true);

        //var_dump($accounting404->toArray());
        // ["message"]=>
        // string(47) "The resource you're looking for cannot be found"
        // ["httpStatusCode"]=>
        // int(404)
        //
        // Some errors will contain a collection of individual errors for all the fields that have
        // an input issue.
    }
}
