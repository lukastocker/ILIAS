<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

declare(strict_types=1);

/**
 * Class ilStatisticsTest
 * @author Marvin Beym <mbeym@databay.de>
 */
class ilStatisticsTest extends ilTestBaseTestCase
{
    private ilStatistics $testObj;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testObj = new ilStatistics();
    }

    public function test_instantiateObject_shouldReturnInstance(): void
    {
        $this->assertInstanceOf(ilStatistics::class, $this->testObj);
    }

    public function testData(): void
    {
        $input = [
            "1250",
            "125125",
            1518,
            "abasfki",
            -1251
        ];
        $this->testObj->setData($input);

        $expected1 = [
            -1251,
            "1250",
            1518,
            "125125",
        ];
        $this->assertEquals($expected1, $this->testObj->getData());
    }
}
