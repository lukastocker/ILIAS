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

use ILIAS\IndividualAssessmentReport\FormsStorageDB;
use ILIAS\IndividualAssessmentReport\Field;

class ilObjIndividualAssessmentReport extends ilObject
{
    use ilIndividualAssessmentReportDIC;

    public const OBJ_TYPE = 'iarp';

    protected string $type;
    protected ?Pimple\Container $dic = null;
    protected ?FormsStorageDB $repo = null;

    public function __construct(int $id = 0, bool $call_by_reference = true)
    {
        $this->type = self::OBJ_TYPE;
        parent::__construct($id, $call_by_reference);
    }

    public static function getRepository(): FormsStorageDB
    {
        global $DIC;
        return self::getGeneralDIC($DIC)['repo.forms'];
    }

    /**
     * @inheritdoc
     */
    public function create(): int
    {
        $id = parent::create();
        return $id;
    }

    /**
     * @inheritdoc
     */
    public function read(): void
    {
        parent::read();
    }

    /**
     * @inheritdoc
     */
    public function delete(): bool
    {
        /*
        $repo = $this->getRepo();
        $form_ids = $repo->getAllFormIdsForObjId($this->getId());
        $fields = $repo->getFieldsForObjId($this->getId());
        $field_ids = array_map(
            static fn(Field $field): int => $field->getFieldId(),
            iterator_to_array($fields)
        );
        $repo->deleteFormsByIds($form_ids);
        $repo->deleteFieldsByIds($field_ids);
        */
        return parent::delete();
    }

    /**
     * @inheritdoc
     */
    public function update(): bool
    {
        parent::update();
        return true;
    }

    /**
     * @inheritdoc
     */
    public function initDefaultRoles(): void
    {
        //there is no default role for the pools
    }

    /**
     * @inheritdoc
     */
    public function cloneObject(int $target_id, int $copy_id = 0, bool $omit_tree = false): ?ilObject
    {
        $repo = $this->getRepo();
        $new_obj = parent::cloneObject($target_id, $copy_id, $omit_tree);
        $forms = $repo->getFormsForObjId($this->getId());
        $fields = $repo->getFieldsForObjId($this->getId());

        $field_mapping = [];
        foreach ($fields as $field) {
            $nu_field = $repo->storeField(
                $field
                    ->asNew()
                    ->withObjId($new_obj->getId())
            );
            $field_mapping[$field->getFieldId()] = $nu_field->getFieldId();
        }


        $nu_forms = [];
        foreach ($forms as $form) {
            $fields = array_map(
                static fn(Field $field): Field => $field
                    ->withFieldId($field_mapping[$field->getFieldId()])
                    ->withObjId($new_obj->getId()),
                $form->getFields()
            );

            $repo->storeForm(
                $form
                    ->asNew()
                    ->withObjId($new_obj->getId())
                    ->withFields(...$fields)
            );
        }

        return $new_obj;
    }

    private function getRepo(): FormsStorageDB
    {
        if ($this->repo === null) {
            $this->repo = $this->getDic()['repo.forms'];
        }
        return $this->repo;
    }

    public function getDic(): Pimple\Container
    {
        if ($this->dic === null) {
            global $DIC;
            $this->dic = $this->getObjectDIC($this, $DIC);
        }
        return $this->dic;
    }

}
