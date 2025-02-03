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

use ILIAS\UI\Factory as UIFactory;
use ILIAS\Data\Range;
use ILIAS\Data\Order;
use ILIAS\UI\URLBuilder;
use ILIAS\UI\Component\Table\DataRetrieval;
use ILIAS\UI\Component\Table\DataRowBuilder;
use DateTimeImmutable;

/**
 *
 */
class FieldsDataRetrieval implements DataRetrieval
{
    public function __construct(
        private FormsStorageDB $forms_repo,
        private UIFactory $ui_factory,
        private \ilLanguage $lng,
        private int $obj_id
    ) {
    }

    public function getColumns()
    {
        $icon_true = $this->ui_factory->symbol()->icon()->custom(\ilUtil::getImagePath('standard/icon_checked.svg'), '', 'small');
        $icon_false = $this->ui_factory->symbol()->icon()->custom(\ilUtil::getImagePath('standard/icon_unchecked.svg'), '', 'small');
        return [
            'name' => $this->ui_factory->table()->column()->text($this->lng->txt('name')),
            'label' => $this->ui_factory->table()->column()->text($this->lng->txt('label')),
            'description' => $this->ui_factory->table()->column()->text($this->lng->txt('description'))
                ->withIsSortable(false),
            'type' => $this->ui_factory->table()->column()->text($this->lng->txt('type'))
                                       ->withIsSortable(false),
            'default_value' => $this->ui_factory->table()->column()->text($this->lng->txt('default_value')),
            'with_notes' => $this->ui_factory->table()->column()->boolean($this->lng->txt('with_notes'), $icon_true, $icon_false),
            'available_for_examiners' => $this->ui_factory->table()->column()->boolean($this->lng->txt('available_for_examiners'), $icon_true, $icon_false),
        ];
    }

    public function getRows(
        DataRowBuilder $row_builder,
        array $visible_column_ids,
        Range $range,
        Order $order,
        ?array $filter_data,
        ?array $additional_parameters
    ): \Generator {
        $fields = $this->forms_repo->getFieldsForObjId($this->obj_id, $range, $order);
        $used_in_forms = $this->forms_repo->getMappedFieldIds();
        foreach ($fields as $field) {
            $row_id = (string) $field->getFieldId();
            $config = $field->getConfig();
            $default_value = (string) $config->getDefaultValue();
            if ($config->getType() === FieldType::DATETIME && $default_value != '') {
                $default_value = DateTimeImmutable::createFromFormat('U', $config->getDefaultValue())->format('d.m.Y H:m');
            }
            $record = [
                'name' => $field->getName(),
                'label' => $config->getLabel(),
                'description' => $config->getDescription(),
                'type' => $this->lng->txt(strtolower($config->getType()->name)),
                'default_value' => $default_value,
                'with_notes' => $field->hasNotes(),
                'available_for_examiners' => $field->isAvailableForExaminers(),
            ];
            yield $row_builder->buildDataRow($row_id, $record)
                ->withDisabledAction(
                    'delete',
                    in_array($field->getFieldId(), $used_in_forms)
                );
        }
    }

    public function getTotalRowCount(
        ?array $filter_data,
        ?array $additional_parameters
    ): ?int {
        return $this->forms_repo->getFieldsCountForObjId($this->obj_id);
    }
}
