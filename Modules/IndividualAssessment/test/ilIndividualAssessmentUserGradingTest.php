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
use ILIAS\UI\Component\Input\Field\Section;
use ILIAS\FileUpload\Handler\AbstractCtrlAwareUploadHandler;
use ILIAS\IndividualAssessmentFormPool\Testing\FieldBuilderMockFactory;

require_once(__DIR__ . "/../../IndividualAssessmentFormPool/test/FieldBuilderMockFactory.php");

/**
 * @backupGlobals disabled
 */
class ilIndividualAssessmentUserGradingTest extends TestCase
{
    use FieldBuilderMockFactory;

    public function test_create_instance()
    {
        $name = 'Hans Günther';
        $record = 'The guy was really good';
        $internal_note = 'This is a node just for me.';
        $file = null;
        $learning_progress = ilLPStatus::LP_STATUS_IN_PROGRESS_NUM;
        $place = 'Area 51';
        $event_time = new DateTimeImmutable();
        $finalized = false;
        $grading = new ilIndividualAssessmentUserGrading(
            $name,
            $record,
            $internal_note,
            $file,
            $learning_progress,
            $place,
            $event_time,
            $finalized
        );

        $this->assertInstanceOf(ilIndividualAssessmentUserGrading::class, $grading);
        $this->assertEquals($name, $grading->getName());
        $this->assertEquals($record, $grading->getRecord());
        $this->assertEquals($internal_note, $grading->getInternalNote());
        $this->assertNull($grading->getFile());
        $this->assertEquals($learning_progress, $grading->getLearningProgress());
        $this->assertEquals($place, $grading->getPlace());
        $this->assertEquals($event_time, $grading->getEventTime());
        $this->assertFalse($grading->isFinalized());
    }

    public function test_with_finalized_changed()
    {
        $name = 'Hans Günther';
        $record = 'The guy was really good';
        $internal_note = 'This is a node just for me.';
        $file = 'report.pdf';
        $learning_progress = ilLPStatus::LP_STATUS_IN_PROGRESS_NUM;
        $place = 'Area 51 Underground';
        $event_time = new DateTimeImmutable();
        $finalized = false;
        $grading = new ilIndividualAssessmentUserGrading(
            $name,
            $record,
            $internal_note,
            $file,
            $learning_progress,
            $place,
            $event_time,
            $finalized
        );

        $this->assertInstanceOf(ilIndividualAssessmentUserGrading::class, $grading);
        $this->assertEquals($name, $grading->getName());
        $this->assertEquals($record, $grading->getRecord());
        $this->assertEquals($internal_note, $grading->getInternalNote());
        $this->assertEquals($file, $grading->getFile());
        $this->assertEquals($learning_progress, $grading->getLearningProgress());
        $this->assertEquals($place, $grading->getPlace());
        $this->assertEquals($event_time, $grading->getEventTime());
        $this->assertFalse($grading->isFinalized());

        $n_grading = $grading->withFinalized(true);
        $this->assertEquals($name, $n_grading->getName());
        $this->assertEquals($record, $n_grading->getRecord());
        $this->assertEquals($internal_note, $n_grading->getInternalNote());
        $this->assertEquals($file, $n_grading->getFile());
        $this->assertEquals($learning_progress, $n_grading->getLearningProgress());
        $this->assertEquals($place, $n_grading->getPlace());
        $this->assertEquals($event_time, $n_grading->getEventTime());
        $this->assertTrue($n_grading->isFinalized());

        $this->assertNotSame($n_grading, $grading);
    }

    public function testToFormInput(): void
    {
        $lng = $this->createMock(ilLanguage::class);
        $lng->expects($this->atLeastOnce())
            ->method('txt')
            ->willReturn("label")
        ;
        $file_handler = $this->createMock(AbstractCtrlAwareUploadHandler::class);
        $df = new ILIAS\Data\Factory();
        $refinery = new ILIAS\Refinery\Factory($df, $lng);
        $f = new ILIAS\UI\Implementation\Component\Input\Field\Factory(
            $this->createMock(\ILIAS\UI\Implementation\Component\Input\UploadLimitResolver::class),
            new ILIAS\UI\Implementation\Component\SignalGenerator(),
            $df,
            $refinery,
            $lng
        );

        $name = 'Hans Günther';
        $record = 'The guy was really good';
        $internal_note = 'This is a node just for me.';
        $file = 'report.pdf';
        $learning_progress = ilLPStatus::LP_STATUS_IN_PROGRESS_NUM;
        $place = 'Area 51 Underground';
        $event_time = new DateTimeImmutable();
        $finalized = false;
        $may_publish = true;
        $grading = new ilIndividualAssessmentUserGrading(
            $name,
            $record,
            $internal_note,
            $file,
            $learning_progress,
            $place,
            $event_time,
            $finalized
        );

        $field_builder = $this->getFieldBuilder();

        $input = $grading->toFormInput(
            $f,
            $df,
            $lng,
            $refinery,
            $file_handler,
            $df->dateFormat()->standard(),
            $field_builder,
            [
                ilLPStatus::LP_STATUS_NOT_ATTEMPTED_NUM,
                ilLPStatus::LP_STATUS_IN_PROGRESS_NUM,
                ilLPStatus::LP_STATUS_FAILED_NUM,
                ilLPStatus::LP_STATUS_COMPLETED_NUM
            ],
            $may_publish
        );

        $this->assertInstanceOf(Section::class, $input);
    }
}
