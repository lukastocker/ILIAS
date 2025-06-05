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

use ILIAS\UI\Component\Input\Field\Factory as FieldFactory;
use ILIAS\Refinery\Factory as Refinery;
use ILIAS\UI\Implementation\Component\Input\Field\FormInput;
use ILIAS\IndividualAssessmentFormPool\FieldType;
use ILIAS\IndividualAssessmentFormPool\FieldConfig;
use ILIAS\IndividualAssessmentFormPool\FieldBuilder;
use ILIAS\UI\Component\Input\Field\UploadHandler;

class IASSCustomField
{
    public function __construct(
        protected FieldConfig $config,
        protected int $obj_id,
        protected int $field_id,
        protected int $usr_id,
        protected bool $has_note,
        protected bool $for_examiners_only,
        protected mixed $value,
        protected ?string $note
    ) {
    }

    public function getConfig(): FieldConfig
    {
        return $this->config;
    }

    public function getFieldId(): int
    {
        return $this->field_id;
    }

    public function getIds(): array
    {
        return [
            $this->obj_id,
            $this->field_id,
            $this->usr_id
        ];
    }

    public function hasNotes(): bool
    {
        return $this->has_note;
    }

    public function withValue(null|string|array $value): self
    {
        $clone = clone $this;
        $clone->value = $value;
        return $clone;
    }

    public function getValue(): null|string|array
    {
        return $this->value;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function withNote(?string $note): self
    {
        $clone = clone $this;
        $clone->note = $note;
        return $clone;
    }

    public function isAvailableForParticipant(): bool
    {
        return $this->for_examiners_only === false;
    }

    public function toFormInput(
        FieldFactory $factory,
        Refinery $refinery,
        UploadHandler $upload_handler,
        FieldBuilder $field_builder
    ): FormInput {
        $ui_field = $field_builder->build(
            $this->getConfig(),
            $this->getValue(),
            $upload_handler
        );

        if ($this->hasNotes()) {
            $note = $factory->textarea('', '')
                ->withValue($this->note)
                ->withMaxLimit(512);
            $ui_field = $factory->group([$ui_field, $note]);
        }
        return $ui_field;
    }
}
