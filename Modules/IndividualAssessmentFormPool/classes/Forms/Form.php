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
use ILIAS\UI\Renderer;

class Form
{
    public function __construct(
        protected int $form_id,
        protected int $iafp_obj_id,
        protected string $name,
        protected string $description,
        /**
         * @var Field[]
         */
        protected array $fields = [],
    ) {
    }

    public function getFormId(): int
    {
        return $this->form_id;
    }

    public function asNew(): self
    {
        $clone = clone $this;
        $clone->form_id = -1;
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

    public function withName(string $name): self
    {
        $clone = clone $this;
        $clone->name = $name;
        return $clone;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function withDescription(string $description): self
    {
        $clone = clone $this;
        $clone->description = $description;
        return $clone;
    }

    public function getFields(): array
    {
        return $this->fields;
    }

    public function withFields(Field ...$fields): self
    {
        $clone = clone $this;
        $clone->fields = $fields;
        return $clone;
    }

    public function toFormInput(
        FieldFactory $factory,
        Renderer $renderer,
        \ilLanguage $lng,
        Refinery $refinery,
        int $iafp_obj_id,
        FormsStorageDB $forms_repo,
        FieldBuilder $field_builder
    ): FormInput {

        $options = [];
        foreach ($this->getFields() as $field) {
            $options[$field->getFieldId()] = $renderer->render(
                $field_builder->build($field->getConfig())->withDisabled(true)
            );
        }

        return $factory->section(
            [
                'id' => $factory->hidden()
                    ->withValue($this->getFormId())
                    ->withAdditionalTransformation($refinery->kindlyTo()->int()),
                'name' => $factory->text($lng->txt('form_name'), $lng->txt('form_name_byline'))
                    ->withRequired(true)
                    ->withValue($this->getName()),
                'desc' => $factory->textarea($lng->txt('form_description'), $lng->txt('form_description_byline'))
                    ->withValue($this->getDescription()),
                'fields' => $factory->multiselect($lng->txt('fields'), $options, $lng->txt('fields_byline'))
                    ->withValue(array_keys($options))
                    ->withAdditionalTransformation(
                        $refinery->custom()->transformation(
                            fn($v) => array_map(
                                fn($fid) => $forms_repo->getFieldById((int) $fid),
                                $v
                            )
                        )
                    )
            ],
            $this->getFormId() === -1 ? $lng->txt('form_section_create') : $lng->txt('form_section_edit'),
            ''//$lng->txt('form_section_byline')
        )
        ->withAdditionalTransformation(
            $refinery->custom()->transformation(
                fn($v) => new self(
                    $v['id'],
                    $iafp_obj_id,
                    $v['name'],
                    $v['desc'],
                    $v['fields'] ?? []
                )
            )
        );

    }

}
