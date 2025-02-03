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

use ILIAS\Data\Range;
use ILIAS\Data\Order;

interface FormsStorage
{
    /**
     * @return Form[]
     */
    public function getFormsForObjId(
        int $iafp_obj_id,
        Range $range,
        Order $order
    ): \Generator;

    /**
     * @return Field[]
     */

    public function getNewForm(int $iafp_obj_id): Form;
    public function storeForm(Form $form): ?int;
    public function getFormById(int $form_id): ?Form;
    public function deleteFormsByIds(array $form_ids): void;
    public function getAllFormIdsForObjId(int $iafp_obj_id): array;
    public function getFormsCountForObjId(int $iafp_obj_id): int;

    public function createField(int $iafp_obj_id, string $title, FieldType $type): Field;
    public function storeField(Field $field): Field;
    public function getFieldById(int $field_id): ?Field;
    public function deleteFieldsByIds(array $field_ids): void;
    public function getAllFieldIdsForObjId(int $iafp_obj_id): array;
    public function getFieldsForObjId(
        int $iafp_obj_id,
        ?Range $range = null,
        ?Order $order = null
    ): \Generator;
    public function getFieldsCountForObjId(int $iafp_obj_id): int;
    public function getFieldsForFormId(int $form_id): array;
    public function getMappedFieldIds(): array;
}
