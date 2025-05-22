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

use PHPUnit\Framework\TestCase;
use ILIAS\IndividualAssessmentFormPool\FormsStorageDB;
use ILIAS\IndividualAssessmentFormPool\Field;
use ILIAS\IndividualAssessmentFormPool\FieldConfig;
use ILIAS\IndividualAssessmentFormPool\FieldType;
use ILIAS\IndividualAssessmentFormPool\Form;

class IAFPFormsStorageTest extends TestCase
{
    protected FormsStorageDB $db;

    public function setUp(): void
    {
        $ildb = $this->createMock(\ilDBInterface::class);
        $ildb->method('nextId')
            ->willReturn(-2);
        $this->db = new FormsStorageDB(
            $ildb,
            $this->createMock(\ilAccess::class),
            $this->createMock(\ilLanguage::class)
        );
    }

    public function testIAFPGetNewForm(): void
    {
        $form = $this->db->getNewForm(777);
        $this->assertInstanceOf(Form::class, $form);
        $this->assertEquals(777, $form->getObjId());
        $this->assertEquals(-1, $form->getFormId());
    }

    public function getFieldTypes(): array
    {
        return array_map(
            fn($t) => [$t],
            FieldType::cases()
        );
    }

    /**
     * @dataProvider getFieldTypes
     */
    public function testIAFPCreateField(FieldType $type): void
    {
        $field = $this->db->createField($type, 777);
        $this->assertInstanceOf(FieldConfig::class, $field->getConfig());
        $this->assertEquals(-2, $field->getFieldId()); //next id
        $this->assertEquals(777, $field->getObjId());
        $this->assertEquals($type, $field->getConfig()->getType());
    }

}
