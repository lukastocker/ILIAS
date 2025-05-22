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

use ILIAS\IndividualAssessmentFormPool\FieldType;
use ILIAS\Refinery\Factory as Refinery;
use ILIAS\Services\ResourceStorage\Resources\UI\ResourceToComponent;
use ILIAS\UI\Factory as UIFactory;
use ILIAS\UI\Renderer as UIRenderer;

class IASSCustomFieldValueRenderer
{
    public function __construct(
        protected ilObjUser $current_user,
        protected ilIndividualAssessmentDateFormatter $date_formatter,
        protected Refinery $refinery,
        protected \ILIAS\ResourceStorage\Services $irss,
        protected UIFactory $ui_factory,
        protected UIRenderer $ui_renderer,
        protected ilCtrl $ctrl,
    ) {
    }

    public function render(IASSCustomField $field): string
    {
        $renderer = $this->getRenderMethodForType($field->getConfig()->getType());
        $out = $this->$renderer($field);
        if ($field->hasNotes()) {
            $layout = $this->ui_factory->layout()->alignment()->horizontal()->evenlyDistributed(
                $this->ui_factory->legacy($out),
                $this->ui_factory->legacy($field->getNote())
            );
            $out = $this->ui_renderer->render($layout);
        }
        return $out;
    }

    protected function getRenderMethodForType(FieldType $type): string
    {
        return 'render' . ucfirst(strtolower($type->name));
    }

    protected function renderMarkdown(IASSCustomField $field): string
    {
        return $this->refinery->string()->markdown()->toHTML()->transform(
            (string) $field->getValue()
        );
    }

    protected function renderFile(IASSCustomField $field): string
    {
        $v = $field->getValue();
        if (! $v) {
            return '-';
        }
        return $this->getFileLinkById($v);
    }

    public function getFileLinkById(string $id): string
    {
        $resource_id = $this->irss->manage()->find($id);
        $resource = $this->irss->manage()->getResource($resource_id);
        $resource_to_component = new ResourceToComponent($resource);

        $info = $resource->getCurrentRevision()->getInformation();
        list($dat, $size) = $resource_to_component->getImportantProperties();

        $url = $this->irss->consume()->src($resource_id)->getSrc();
        $this->ctrl->setParameterByClass(
            ilIndividualAssessmentMemberGUI::class,
            ilIndividualAssessmentMemberGUI::F_CUST_FILE_RID,
            $resource_id
        );
        $url = $this->ctrl->getLinkTargetByClass(
            ilIndividualAssessmentMemberGUI::class,
            ilIndividualAssessmentMemberGUI::CMD_DOWNLOAD_CUST_FILE
        );
        $this->ctrl->setParameterByClass(
            ilIndividualAssessmentMemberGUI::class,
            ilIndividualAssessmentMemberGUI::F_CUST_FILE_RID,
            null
        );

        $label = sprintf(
            '%s (%s, %s)',
            $info->getTitle(),
            $info->getMimeType(),
            $size,
        );

        return $this->ui_renderer->render(
            $this->ui_factory->link()->standard($label, $url)
        );
    }

    protected function renderDatetime(IASSCustomField $field): string
    {
        $v = $field->getValue();
        if (! $v) {
            return '-';
        }
        $v = \DateTimeImmutable::createFromFormat('U', $v);
        return $this->date_formatter->format($this->current_user, $v);
    }

    protected function renderSingleselect(IASSCustomField $field): string
    {
        return (string) $field->getValue();
    }

    protected function renderTag(IASSCustomField $field): string
    {
        return str_replace(SpecifiedFormStorageDB::VALUE_DELIMITER, ', ', (string) $field->getValue());
    }

    protected function renderRating(IASSCustomField $field): string
    {
        $v = $field->getValue();
        if (! $v) {
            return '-';
        }
        return $v . '/5';
    }
}
