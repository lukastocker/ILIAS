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

namespace ILIAS\IndividualAssessmentFormPool;

use ILIAS\UI\Component\Input\Container\Form\FormInput;
use ILIAS\UI\Component\Input\Field\Factory as FieldFactory;
use ILIAS\Refinery\Factory as Refinery;

class Field
{
    public function __construct(
        protected readonly FieldConfig $config,
        protected int $field_id,
        protected int $iafp_obj_id,
        protected string $name,
        protected bool $with_notes,
        protected bool $available_for_examiners
    ) {
    }

    public function getFieldId(): int
    {
        return $this->field_id;
    }

    public function withFieldId(int $field_id): self
    {
        $clone = clone $this;
        $clone->field_id = $field_id;
        return $clone;
    }

    public function asNew(): self
    {
        $clone = clone $this;
        $clone->field_id = -1;
        return $clone;
    }

    public function getObjId(): int
    {
        return $this->iafp_obj_id;
    }

    public function withObjId(int $iafp_obj_id): self
    {
        $clone = clone $this;
        $clone->iafp_obj_id = $iafp_obj_id;
        return $clone;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function hasNotes(): bool
    {
        return $this->with_notes;
    }

    public function isAvailableForExaminers(): bool
    {
        return $this->available_for_examiners;
    }

    public function getConfig(): FieldConfig
    {
        return $this->config;
    }

    public function toFormInput(
        FieldFactory $factory,
        \ilLanguage $lng,
        Refinery $refinery,
        int $iafp_obj_id,
        FieldBuilder $field_builder,
        array $current_field_names
    ): FormInput {
        $config = $this->getConfig();

        $inputs = [];
        $inputs['id'] = $factory->hidden()
            ->withAdditionalTransformation($refinery->kindlyTo()->int())
            ->withValue($this->getFieldId());

        $inputs['type'] = $factory->select(
            $lng->txt('field_type'),
            [$config->getType()->value => $config->getType()->name],
            ''
        )
        ->withValue($config->getType()->value)
        ->withRequired(true);

        $inputs['name'] = $factory->text($lng->txt('field_name'), $lng->txt('field_name_byline'))
            ->withRequired(true)
            ->withValue($this->getName())
        ->withAdditionalTransformation(
            $refinery->custom()->constraint(
                fn($val) => !in_array($val, $current_field_names),
                $lng->txt("field_name_used")
            )
        );

        $inputs['type_config'] = $config->toFormInput(
            $factory,
            $lng,
            $refinery,
            $field_builder,
            ($this->getFieldId() !== -1)
        );

        $inputs['with_notes'] = $factory->checkbox($lng->txt('field_with_notes'), $lng->txt('field_with_notes_byline'))
            ->withValue($this->hasNotes());
        $inputs['available_for_examiners'] = $factory->checkbox($lng->txt('field_available_for_examiners'), $lng->txt('field_available_for_examiners_byline'))
            ->withValue($this->isAvailableForExaminers());

        return $factory->section(
            $inputs,
            $this->getFieldId() === -1 ? $lng->txt('field_section_create') : $lng->txt('field_section_edit'),
            ''
        )
        ->withAdditionalTransformation(
            $refinery->custom()->transformation(
                fn($v) => new self(
                    $v['type_config'],
                    $v['id'],
                    $iafp_obj_id,
                    $v['name'],
                    $v['with_notes'],
                    $v['available_for_examiners'],
                )
            )
        );
    }
}
