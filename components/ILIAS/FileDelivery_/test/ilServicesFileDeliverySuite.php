<?php

declare(strict_types=1);

use PHPUnit\Framework\TestSuite;

/******************************************************************************
 *
 * This file is part of ILIAS, a powerful learning management system.
 *
 * ILIAS is licensed with the GPL-3.0, you should have received a copy
 * of said license along with the source code.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 *      https://www.ilias.de
 *      https://github.com/ILIAS-eLearning
 *
 *****************************************************************************/
/**
 * Class ilServicesFileDeliverySuite
 *
 * @author  Nicolas Schäfli <ns@studer-raimann.ch>
 */
class ilServicesFileDeliverySuite extends TestSuite
{
    public static function suite(): \ilServicesFileDeliverySuite
    {
        $suite = new self();

        $suite->addTestFiles([
            './components/ILIAS/FileDelivery_/test/FileDeliveryTypes/XSendfileTest.php',
            './components/ILIAS/FileDelivery_/test/FileDeliveryTypes/XAccelTest.php',
            './components/ILIAS/FileDelivery_/test/FileDeliveryTypes/FileDeliveryTypeFactoryTest.php'
        ]);

        return $suite;
    }
}
