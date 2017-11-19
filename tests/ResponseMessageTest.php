<?php

namespace Academe\XeroPHP;

use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

class ResponseMessageTest extends TestCase
{
    protected $gbPayrollEmployees;
    protected $gbPayrollEmployee;
    protected $accountingPayments;
    protected $accountingPayment;
    protected $accountingPaymentsNoMatch;
    protected $fileFolders;

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

        // Two folders from the Files API v1.0.
        $fileFolders = json_decode(file_get_contents(__DIR__ . '/data/FileFolders.json'), true);
        $this->fileFolders = new ResponseMessage($fileFolders);
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

        $this->assertEquals($message->getSource(), $data);
    }

    public function testEmptyData()
    {
        $message = new ResponseMessage([]);

        $this->assertEquals($message->getSource(), []);

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
    public function testNakedArray()
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
    }
}
