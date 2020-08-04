<?php
/* Copyright (c) 1998-2020 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * @author Luka Stocker <lstocker@concepts-and-training.de>
 */

namespace ILIAS\Tests\Refinery\KindlyTo\Transformation;

require_once('./libs/composer/vendor/autoload.php');

use ILIAS\Refinery\KindlyTo\Transformation\DateTimeTransformation;
use PHPUnit\Framework\TestCase;

/**
 * Tests for DateTimeImmutable and Unix Timetable transformation
 */

class DateTimeTransformationTest extends TestCase
{
    /**
     * @var DateTimeTransformation
     */
    private $transformation;

    public function setUp(): void
    {
        $this->transformation = new DateTimeTransformation();
    }

    /**
     * @dataProvider DateTimeTransformationDataProvider
     * @param $originVal
     * @param $expectedVal
     */
    public function testDateTimeISOTransformation($originVal, $expectedVal)
    {
        $transformedValue = $this->transformation->transform($originVal);
        $this->assertIsObject($transformedValue,'');
        $this->assertEquals($expectedVal, $transformedValue);
    }

    /**
     * @dataProvider UnixTimestampTransformationDataProvider
     * @param $originValue
     * @param $expectedValue
     */
    public function testDateTimeToUnixTimestampTransformation($originValue, $expectedValue)
    {
        $transformedValue = $this->transformation->transform($originValue);
        $this->assertIsNumeric($transformedValue,'');
        $this->assertEquals($expectedValue, $transformedValue);
    }

    public function DateTimeTransformationDataProvider()
    {
        return [
            'iso8601' => ['2020-07-06T12:23:05+0000',\DateTimeImmutable::createFromFormat(\DateTimeImmutable::ISO8601,'2020-07-06T12:23:05+0000')],
            'atom' => ['2020-07-06T12:23:05+00:00',\DateTimeImmutable::createFromFormat(\DateTimeImmutable::ATOM,'2020-07-06T12:23:05+00:00')],
            'rfc3339_ext' => ['2020-07-06T12:23:05.000+00:00',\DateTimeImmutable::createFromFormat(\DateTimeImmutable::RFC3339_EXTENDED,'2020-07-06T12:23:05.000+00:00')],
            'cookie' => ['Monday, 06-Jul-2020 12:23:05 GMT+0000',\DateTimeImmutable::createFromFormat(\DateTimeImmutable::COOKIE,'Monday, 06-Jul-2020 12:23:05 GMT+0000')],
            'rfc822' => ['Mon, 06 Jul 20 12:23:05 +0000',\DateTimeImmutable::createFromFormat(\DateTimeImmutable::RFC822,'Mon, 06 Jul 20 12:23:05 +0000')],
            'rfc7231' => ['Mon, 06 Jul 2020 12:23:05 GMT',\DateTimeImmutable::createFromFormat(\DateTimeImmutable::RFC7231,'Mon, 06 Jul 2020 12:23:05 GMT')]
        ];
    }

    public function UnixTimestampTransformationDataProvider()
    {
        return [
            [20200706122305, strtotime(20200706122305)]
        ];
    }
}