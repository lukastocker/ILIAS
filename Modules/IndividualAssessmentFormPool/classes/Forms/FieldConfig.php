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

class FieldConfig
{
    public function __construct(
        private FieldType $type,
        private ?string $label = '',
        private ?string $description = '',
        private mixed $default_value = null,
        private mixed $options = null,
    ) {
    }

    public function getType(): FieldType
    {
        return $this->type;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getOptions(): mixed
    {
        return $this->options;
    }

    public function getDefaultValue(): mixed
    {
        return $this->default_value;
    }

    public function toFormInput(
        FieldFactory $factory,
        \ilLanguage $lng,
        Refinery $refinery,
        FieldBuilder $field_builder,
        bool $fixed
    ): FormInput {
        $type_options = $fixed ?
            [$this->type->value => $this->type->name] : FieldType::toArray();

        $inputs = [];
        $inputs = array_filter([
            'label' => $factory->text($lng->txt('field_label'), $lng->txt('field_label_byline'))
                ->withValue($this->getLabel()),
            'description' => $factory->textarea($lng->txt('field_description'), $lng->txt('field_description_byline'))
                ->withValue($this->getDescription()),
            'opts' => $this->getFormInputOptions($factory, $lng, $refinery),
            'default' => $this->getFormInputDefault($field_builder, $lng)
        ]);

        return $factory->group($inputs)->withAdditionalTransformation(
            $refinery->custom()->transformation(
                function ($v) {
                    $opts = array_key_exists('opts', $v) ? $v['opts'] : null;
                    $default = array_key_exists('default', $v) ? $v['default'] : null;
                    if (!is_null($default) && !is_null($opts)) {
                        if ($default === '') {
                            $default = null;
                        }
                    }
                    return new self(
                        $this->getType(),
                        $v['label'],
                        $v['description'],
                        $default,
                        $opts,
                    );
                }
            )
        );

    }

    private function getFormInputOptions(
        FieldFactory $factory,
        \ilLanguage $lng,
        Refinery $refinery,
    ): ?FormInput {
        switch ($this->type) {
            case FieldType::SINGLESELECT:
            case FieldType::TAG:
                return $factory->group([ //group is needed due to exisiting transforms on tag-input
                     $factory->tag($lng->txt('field_options'), [], $lng->txt('field_options_byline'))
                         ->withUserCreatedTagsAllowed(true)
                         ->withRequired(true)
                         ->withValue($this->getOptions())
                         ->withTagMaxLength(512)
                 ])
                 ->withAdditionalTransformation(
                     $refinery->custom()->transformation(
                         fn($v) => array_shift($v)
                     )
                 );
                break;
            default:
                return null;
        }
    }

    private function getFormInputDefault(
        FieldBuilder $field_builder,
        \ilLanguage $lng
    ): ?FormInput {

        switch ($this->type) {
            case FieldType::FILE:
                return null;
            default:
                return $field_builder->build($this)
                    ->withLabel($lng->txt('field_default_value'))
                    ->withByLine($lng->txt('field_default_value_byline'));
        }
    }

}
