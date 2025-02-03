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

/**
 *
 */
class FormsDataRetrieval implements DataRetrieval
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
        return [
            'name' => $this->ui_factory->table()->column()->text($this->lng->txt('name')),
            'description' => $this->ui_factory->table()->column()->text($this->lng->txt('description'))
                ->withIsSortable(false),
            'fields_count' => $this->ui_factory->table()->column()->number($this->lng->txt('fields_count'))
                ->withIsSortable(false),
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
        $forms = $this->forms_repo->getFormsForObjId($this->obj_id, $range, $order);
        foreach ($forms as $form) {
            $row_id = (string) $form->getFormId();
            $record = [
                'name' => $form->getName(),
                'description' => $form->getDescription(),
                'fields_count' => count($form->getFields()),
            ];
            yield $row_builder->buildDataRow($row_id, $record);
        }
    }

    public function getTotalRowCount(
        ?array $filter_data,
        ?array $additional_parameters
    ): ?int {
        return $this->forms_repo->getFormsCountForObjId($this->obj_id);
    }
}
