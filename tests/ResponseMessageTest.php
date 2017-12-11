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
        $accountingPaymentsNoMatch = json_decode(
            file_get_contents(__DIR__ . '/data/accountingPaymentsNoMatch.json'),
            true
        );
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

        foreach ($message as $key => $value) {
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
        foreach ($message as $key => $item) {
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
        foreach ($collection as $key => $item) {
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

    /**
     * Properties of the GB Payroll Employees resource collection.
     */
    public function testGbPayrollEmployeesResources()
    {
        $message = $this->gbPayrollEmployees;
        $collection = $message->getCollection();

        $this->assertEquals($message->isCollection(), true);
        $this->assertEquals($message->isEmpty(), false);
        //$this->assertEquals($message->hasParent(), true); // TBC

        $this->assertEquals($message->count(), 2);
        $this->assertEquals(count($message), 2);

        $this->assertEquals($collection->count(), 2);
        $this->assertEquals(count($collection), 2);
    }

    /**
     * Properties of the GB Payroll Employees resource collection as a single employee.
     */
    public function testGbPayrollEmployeesResource()
    {
        $message = $this->gbPayrollEmployees;
        $resource = $message->getResource();

        $this->assertEquals($message->isCollection(), true);
        $this->assertEquals($message->isEmpty(), false);

        $this->assertEquals($resource->isCollection(), false);

        //$this->assertEquals($resource->hasParent(), true); // TBC

        // Although there are two resources in the message collection...
        $this->assertEquals($message->count(), 2);
        $this->assertEquals(count($message), 2);

        // ...counting the first collection alone gives us a count of just one.
        $this->assertEquals($resource->count(), 1);
        $this->assertEquals(count($resource), 1);
    }

    /**
     * Properties of the GB Payroll single Employee resource as a collection.
     */
    public function testGbPayrollEmployeeResources()
    {
        $resources = $this->gbPayrollEmployee->getCollection();

        $this->assertEquals($resources->isCollection(), true);
        $this->assertEquals($resources->isEmpty(), false);
        //$this->assertEquals($resources->hasParent(), true); // TBC

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
        //$this->assertEquals($resource->hasParent(), true); // TBC

        $this->assertEquals($resource->count(), 1);
        $this->assertEquals(count($resource), 1);
    }

    //

    /**
     * Properties of the root Accounting Payments collection response.
     */
    public function testAccountingPaymentsRoot2()
    {
        $payments = $this->accountingPayments;

        $this->assertEquals($payments->isCollection(), true);
        $this->assertEquals($payments->isEmpty(), false);
        //$this->assertEquals($payments->hasParent(), false); // TBC

        $this->assertEquals($payments->count(), 3);
        $this->assertEquals(count($payments), 3);
    }

    /**
     * Properties of the Accounting 2.0 Payments resource as a collection.
     */
    public function testAccountingPaymentsResources()
    {
        $resources = $this->accountingPayments->getCollection();

        $this->assertEquals($resources->isCollection(), true);
        $this->assertEquals($resources->isEmpty(), false);
        //$this->assertEquals($resources->hasParent(), true); // TBC

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
        //$this->assertEquals($resource->hasParent(), true); // TBC

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

        // The response puts a single payment into an array, so it will always
        // look like a collection, even though it is not. No other metdata gives
        // any clues that this is the result from a single payment request.
        // Now, if the message contains a single resource in an ambigyous collection,
        // then should we ALSO return true for isResource()? This would only apply if
        // there is no evidence of a pagination object.
        $this->assertEquals($payment->isCollection(), true);
        $this->assertEquals($payment->isEmpty(), false);
        //$this->assertEquals($payment->hasParent(), false); // TBC

        $this->assertEquals($payment->count(), 1);
        $this->assertEquals(count($payment), 1);
    }

    /**
     * Properties of the Accounting 2.0 singlePayments resource as a collection.
     */
    public function testAccountingPaymentResources()
    {
        $resources = $this->accountingPayment->getCollection();

        $this->assertEquals($resources->isCollection(), true);
        $this->assertEquals($resources->isEmpty(), false);
        //$this->assertEquals($resources->hasParent(), true); // TBC

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
        //$this->assertEquals($resource->hasParent(), true); // TBC

        $this->assertEquals($resource->count(), 1);
        $this->assertEquals(count($resource), 1);
    }

    //

    /**
     * Fetching payments with a filter matching no results.
     */
    public function testAccountingPaymentNoMatchBase()
    {
        $accountingPaymentsNoMatch = $this->accountingPaymentsNoMatch;

        // The response contains an empty resource array, so we know it is a
        // collection, even if it is empty.
        $this->assertEquals($accountingPaymentsNoMatch->isCollection(), true);
        // Note: the message is not empty...
        $this->assertEquals($accountingPaymentsNoMatch->isEmpty(), false);
        // ...but the collection it carries IS empty.
        $this->assertEquals($accountingPaymentsNoMatch->getCollection()->isEmpty(), true);
        //$this->assertEquals($accountingPaymentsNoMatch->hasParent(), false); // TBC

        $this->assertEquals($accountingPaymentsNoMatch->count(), 0);
        $this->assertEquals(count($accountingPaymentsNoMatch), 0);
    }

    /**
     * Properties of the Accounting 2.0 non-matching Payments resource collection.
     */
    public function testAccountingPaymentNoMatchResources()
    {
        $resources = $this->accountingPaymentsNoMatch->getCollection();

        $this->assertEquals($resources->isCollection(), true);
        $this->assertEquals($resources->isEmpty(), true);
        //$this->assertEquals($resources->hasParent(), true); // TBC

        $this->assertEquals($resources->count(), 0);
        $this->assertEquals(count($resources), 0);
    }

    /**
     * Properties of the Accounting 2.0 non-matching Payments resource collection as a single payment.
     */
    public function testAccountingPaymentNoMatchResource()
    {
        $resource = $this->accountingPaymentsNoMatch->getResource();

        // The resource here is null, because we have fetched a resource from
        // an empty collection. Should we do this? Or should we return an
        // empty resource? Just not sure how it is going to be used.
        // We'll plant the flag here now, and revisit at some point.

        $this->assertNull($resource);

        //$this->assertEquals($resource->isCollection(), false);
        //$this->assertEquals($resource->isEmpty(), true);
        // $this->assertEquals($resources->hasParent(), true); // TBC

        //$this->assertEquals($resources->count(), 0);
        //$this->assertEquals(count($resources), 0);
    }
}
