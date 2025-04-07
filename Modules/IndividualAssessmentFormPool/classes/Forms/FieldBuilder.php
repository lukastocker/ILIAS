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
use ILIAS\UI\Component\Input\Field\UploadHandler;
use ILIAS\Refinery\Factory as Refinery;
use ILIAS\IndividualAssessmentFormPool\Field;
use ILIAS\Data\FiveStarRatingScale;

class FieldBuilder
{
    public function __construct(
        protected FieldFactory $factory,
        protected Refinery $refinery,
        protected \ilLanguage $lng,
        protected UploadHandler $default_uploadhandler,
        protected \ilUIMarkdownPreviewGUI $default_mdrenderer
    ) {
    }

    public function build(
        FieldConfig $config,
        mixed $value = null,
        UploadHandler $upload_handler = null,
        \ilUIMarkdownPreviewGUI $md_renderer = null
    ): FormInput {
        $label = $config->getLabel();
        $description = $config->getDescription();

        $value = $value ?? $config->getDefaultValue();

        $factory = $this->factory;
        switch ($config->getType()) {
            case FieldType::MARKDOWN:
                $md_renderer = $md_renderer ?? $this->default_mdrenderer;
                return $factory->markdown($md_renderer, $label, $description)
                    ->withValue((string) $value)
                    ->withMaxLimit(512);

            case FieldType::FILE:
                $value = ($value === null || $value === '') ?
                    [] : [$value];

                $upload_handler = $upload_handler ?? $this->default_uploadhandler;
                return $factory->file($upload_handler, $label, $description)
                    ->withValue($value);

            case FieldType::DATETIME:
                $value = ($value === null || $value === '') ?
                    null : \DateTimeImmutable::createFromFormat('U', $value);

                return $factory->datetime($label, $description)
                    ->withTimezone('UTC')
                    ->withAdditionalTransformation(
                        $this->refinery->custom()->transformation(
                            fn($v) => (string) $v->format('U')
                        )
                    )
                    ->withValue($value);

            case FieldType::SINGLESELECT:
                $options = $config->getOptions() ?? [];
                $options = array_combine($options, $options);
                $value = in_array($value, $options) ? $value : $config->getDefaultValue();
                return $factory->select($label, $options, $description)
                    ->withValue($value);

            case FieldType::TAG:
                $options = $config->getOptions() ?? [];
                $value = ($value === null || $value === '') ?
                    null : explode(\SpecifiedFormStorageDB::VALUE_DELIMITER, $value);
                return $factory->tag($label, $options, $description)
                    ->withUserCreatedTagsAllowed(false)
                    ->withValue($value);

            case FieldType::RATING:
                $value = ($value === null || $value === '') ? null : FiveStarRatingScale::from((int)$value);
                return $factory->rating($label, $description)
                    ->withAdditionalTransformation(
                        $this->refinery->custom()->transformation(
                            fn($v) => (string) $v->value
                        )
                    )
                    ->withValue($value);
        }
    }
}
