<?php

namespace Academe\XeroPHP;

use PHPUnit\Framework\TestCase;
//use InvalidArgumentException;
use Carbon\Carbon;

class HelperTest extends TestCase
{
    /**
     * Resource properties that will always be converted to a Carbon object.
     */
    public function testToCarbonConverted()
    {
        $dates = [
            // String formats.
            '2017-01-01T00:00:00+00:00' => '2017-01-01',
            '2017-01-01T23:59:59+00:00' => '2017-01-01 23:59:59',
            '2017-01-01T00:00:00+00:00' => '2017-01-01 00:00:00',
            '2017-10-31T12:47:42+00:00' => '/Date(1509454062181)/',
            '2015-08-17T12:15:04+00:00' => '/Date(1439813704613+0000)/',
            '2017-10-20T16:04:50+00:00' => '2017-10-20T16:04:50',
            '2017-10-31T12:50:15+00:00' => '2017-10-31T12:50:15.9920037',
            '2017-09-24T23:00:00+00:00' => '2017-09-25T00:00:00',
            // Carbon.
            '2017-01-01T23:59:59+00:00' => Carbon::parse('2017-01-01 23:59:59', 'UTC'),
            // Datetime
            '2017-09-24T23:00:00+00:00' => new \DateTime('2017-09-25T00:00:00'),
            // Integer
            '2017-10-31T12:47:42+00:00' => 1509454062,
        ];

        foreach($dates as $formatted => $date) {
            $dateCarbon = Helper::toCarbon($date);

            $this->assertInstanceOf(Carbon::class, $dateCarbon);
            $this->assertSame($formatted, $dateCarbon->timezone('UTC')->toRfc3339String());
        }
    }

    /**
     * Resource properties that will never be converted to a Carbon object.
     */
    public function testToCarbonNotConverted()
    {
        $dates = [
            // String formats.
            '1234567',
            'foobar',
            // float
            123.456,
            // Other objects
            new static,
        ];

        foreach($dates as $value) {
            $nonDate = Helper::toCarbon($value);

            $this->assertNotInstanceOf(Carbon::class, $nonDate);
        }
    }
}
