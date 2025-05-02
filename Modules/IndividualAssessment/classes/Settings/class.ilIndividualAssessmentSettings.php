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

/**
 * An object carrying settings of an Individual Assessment obj
 * beyond the standard information
 */
class ilIndividualAssessmentSettings
{
    public function __construct(
        protected int $obj_id,
        protected string $title,
        protected string $description,
        protected string $content,
        protected string $record_template,
        protected bool $event_time_place_required,
        protected bool $file_required,
        protected bool $file_visible,
        protected bool $result_visible,
        protected bool $available_in_report = true,
        protected ?\DateTimeImmutable $available_in_report_from = null,
        protected ?\DateTimeImmutable $available_in_report_to = null
    ) {
    }

    /**
     * Get the id of corresponding iass-object
     */
    public function getObjId(): int
    {
        return $this->obj_id;
    }

    /**
     * Get the content of this assessment, e.g. corresponding topics...
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Get the content of this assessment, e.g. corresponding topics...
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Get the content of this assessment, e.g. corresponding topics...
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Get the record template to be used as default record with
     * corresponding object
     */
    public function getRecordTemplate(): string
    {
        return $this->record_template;
    }

    /**
     * Get the value of the checkbox event_time_place_require
     */
    public function isEventTimePlaceRequired(): bool
    {
        return $this->event_time_place_required;
    }

    /**
     * Get the value of the checkbox file_required
     */
    public function isFileRequired(): bool
    {
        return $this->file_required;
    }

    public function isFileVisible(): bool
    {
        return $this->file_visible;
    }

    public function isResultVisible(): bool
    {
        return $this->result_visible;
    }

    public function toFormInput(
        Field\Factory $input,
        ilLanguage $lng,
        Refinery $refinery
    ): \ILIAS\UI\Component\Input\Container\Form\FormInput {
        return $input->section(
            [
                $input->text($lng->txt("title"))
                    ->withValue($this->getTitle())
                    ->withRequired(true),
                $input->textarea($lng->txt("description"))
                    ->withValue($this->getDescription()),
                $input->textarea($lng->txt("iass_content"), $lng->txt("iass_content_explanation"))
                    ->withValue($this->getContent()),
                $input->textarea($lng->txt("iass_record_template"), $lng->txt("iass_record_template_explanation"))
                    ->withValue($this->getRecordTemplate()),
                $input->checkbox($lng->txt("iass_event_time_place_required"), $lng->txt("iass_event_time_place_required_info"))
                    ->withValue($this->isEventTimePlaceRequired()),
                $input->checkbox($lng->txt("iass_file_required"), $lng->txt("iass_file_required_info"))
                    ->withValue($this->isFileRequired()),
                $input->checkbox($lng->txt("iass_file_visible_examinee"), '')
                    ->withValue($this->isFileVisible()),
                $input->checkbox($lng->txt("iass_notify"), $lng->txt("iass_notify_explanation"))
                    ->withValue($this->isResultVisible()),
            ],
            $lng->txt("settings")
        )->withAdditionalTransformation(
            $refinery->custom()->transformation(function ($value) {
                return new ilIndividualAssessmentSettings(
                    $this->getObjId(),
                    ...$value
                );
            })
        );
    }

    public function withReportSettings(
        bool $available = true,
        ?\DateTimeImmutable $from = null,
        ?\DateTimeImmutable $to = null
    ): self {
        $clone = clone $this;
        $clone->available_in_report = $available;
        $clone->available_in_report_from = $from;
        $clone->available_in_report_to = $to;
        return $clone;
    }

    public function getReportSettings(): array
    {
        return [
            $this->available_in_report,
            $this->available_in_report_from,
            $this->available_in_report_to
        ];
    }

    public function reportSettingsToForm(
        Field\Factory $input,
        ilLanguage $lng,
        Refinery $refinery
    ): \ILIAS\UI\Component\Input\Container\Form\FormInput {
        $val = [$this->available_in_report_to, $this->available_in_report_from];
        $period = $input->duration(
            $lng->txt("setting_report_availability_period_label"),
            $lng->txt("setting_report_availability_period_byline"),
        );

        return $input->optionalGroup(
            [$period],
            $lng->txt("setting_report_availability_label"),
            $lng->txt("setting_report_availability_byline")
        )->withValue(
            $this->available_in_report ? [$val] : null
        )
        ->withAdditionalTransformation(
            $refinery->custom()->transformation(function ($value) {
                $available = $value !== null;
                $to = $value[0]['start'] ?? null;
                $from = $value[0]['end'] ?? null;
                return [$available, $to, $from];
            })
        );
    }

}
