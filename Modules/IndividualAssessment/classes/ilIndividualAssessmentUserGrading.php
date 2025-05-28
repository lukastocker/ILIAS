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

use ILIAS\UI\Component\Input\Field;
use ILIAS\Refinery\Factory as Refinery;
use ILIAS\Data\Factory as DataFactory;
use ILIAS\FileUpload\Handler\AbstractCtrlAwareUploadHandler;
use ILIAS\IndividualAssessmentFormPool\FieldBuilder;

class ilIndividualAssessmentUserGrading
{
    protected array $custom_fields = [];

    public function __construct(
        protected string $name,
        protected string $record,
        protected string $internal_note,
        protected ?string $file,
        protected int $learning_progress,
        protected string $place,
        protected ?DateTimeImmutable $event_time,
        protected bool $finalized = false
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getRecord(): string
    {
        return $this->record;
    }

    public function getInternalNote(): string
    {
        return $this->internal_note;
    }

    public function getFile(): ?string
    {
        return $this->file;
    }

    public function hasFile(): bool
    {
        return !empty($this->file);
    }

    public function getLearningProgress(): int
    {
        return $this->learning_progress;
    }

    public function getPlace(): string
    {
        return $this->place;
    }

    public function getEventTime(): ?DateTimeImmutable
    {
        return $this->event_time;
    }


    public function isFinalized(): bool
    {
        return $this->finalized;
    }

    public function withFinalized(bool $finalize): ilIndividualAssessmentUserGrading
    {
        $clone = clone $this;
        $clone->finalized = $finalize;
        return $clone;
    }

    public function withFile(?string $file): ilIndividualAssessmentUserGrading
    {
        $clone = clone $this;
        $clone->file = $file;
        return $clone;
    }

    public function getCustomFields(): array
    {
        return $this->custom_fields;
    }

    public function withCustomFields(array $custom_fields): self
    {
        $clone = clone $this;
        $clone->custom_fields = $custom_fields;
        return $clone;
    }

    public function toFormInput(
        Field\Factory $input,
        DataFactory $data_factory,
        ilLanguage $lng,
        Refinery $refinery,
        AbstractCtrlAwareUploadHandler $file_handler,
        \ILIAS\Data\DateFormat\DateFormat $date_format,
        FieldBuilder $field_builder,
        array $grading_options,
        bool $may_publish,
        bool $may_be_edited = true,
        bool $place_required = false,
        bool $file_required = false,
        bool $amend = false
    ): \ILIAS\UI\Component\Input\Container\Form\FormInput {

        $name = $input
            ->text($lng->txt('name'), '')
            ->withDisabled(true)
            ->withValue($this->getName())
        ;

        $record = $input
            ->textarea($lng->txt('iass_record'), $lng->txt('iass_record_info'))
            ->withValue($this->getRecord())
            ->withDisabled(!$may_be_edited)
        ;

        $internal_note = $input
            ->textarea($lng->txt('iass_internal_note'), $lng->txt('iass_internal_note_info'))
            ->withValue($this->getInternalNote())
            ->withDisabled(!$may_be_edited)
        ;

        $file = $input
            ->file($file_handler, $lng->txt('iass_upload_file'), $lng->txt('iass_file_dropzone'))
            ->withValue($this->hasFile() ? [$this->getFile()] : [])
            ->withRequired($file_required)
        ;

        $learning_progress = $input
            ->select($lng->txt('learning_progress'), $grading_options)
            ->withValue($this->getLearningProgress() ?: ilLPStatus::LP_STATUS_NOT_ATTEMPTED_NUM)
            ->withDisabled(!$may_be_edited)
            ->withRequired(true)
        ;

        $place = $input
            ->text($lng->txt('iass_place'))
            ->withValue($this->getPlace())
            ->withRequired($place_required)
            ->withDisabled(!$may_be_edited)
        ;

        $event_time = $input
            ->dateTime($lng->txt('iass_event_time'))
            ->withUseTime(true)
            ->withFormat($date_format)
            ->withRequired($place_required)
            ->withDisabled(!$may_be_edited)
        ;

        if (!is_null($this->getEventTime())) {
            $event_time = $event_time->withValue(
                $this->getEventTime()
            );
        }

        $custom = [];
        $custom_fields = $this->custom_fields;
        foreach ($custom_fields as $cf) {
            $custom[$cf->getFieldId()] = $cf->toFormInput($input, $refinery, $file_handler, $field_builder);
        }

        $fields = [
            'name' => $name,
            'record' => $record,
            'internal_note' => $internal_note,
            'file' => $file,
            'custom' => $input->group($custom),
            'place' => $place,
            'event_time' => $event_time,
            'learning_progress' => $learning_progress,
        ];

        if (!$amend) {
            $disabled = !$may_be_edited;
            if (!$may_publish) {
                $disabled = true;
            }
            $finalized = $input
                ->checkbox($lng->txt('iass_finalize'), $lng->txt('iass_finalize_info'))
                ->withValue($this->isFinalized())
                ->withDisabled($disabled)
            ;

            $fields['finalized'] = $finalized;
        }

        return $input->section(
            $fields,
            $lng->txt('iass_edit_record')
        )->withAdditionalTransformation(
            $refinery->custom()->transformation(function ($values) use ($amend, $custom_fields) {
                $finalized = $this->isFinalized();
                if (!$amend) {
                    $finalized = $values['finalized'];
                }

                $file = null;
                if (
                    isset($values['file'][0]) &&
                    trim($values['file'][0]) != ''
                ) {
                    $file = $values['file'][0];
                }

                $updated_custom = [];
                foreach ($custom_fields as $cf) {
                    $updated_custom[] = $cf->withValue($values['custom'][$cf->getFieldId()]);
                }

                return (new ilIndividualAssessmentUserGrading(
                    $values['name'],
                    $values['record'],
                    $values['internal_note'],
                    $file,
                    (int) $values['learning_progress'],
                    $values['place'],
                    $values['event_time'],
                    $finalized
                ))
                ->withCustomFields($updated_custom);
            })
        );
    }
}
