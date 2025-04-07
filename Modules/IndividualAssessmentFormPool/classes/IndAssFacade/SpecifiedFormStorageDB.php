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
use ILIAS\IndividualAssessmentFormPool\FieldConfig;
use ILIAS\ResourceStorage\Services as IRSS;

class SpecifiedFormStorageDB implements SpecifiedFormStorage
{
    public const VALUE_DELIMITER = '#:#';

    public function __construct(
        protected ilDBInterface $db
    ) {
    }

    /**
     * @return IASSCustomField[]
     */
    public function getSpecifiedFormFields(
        int $obj_id,
        int $member_usr_id
    ): array {
        $query = 'SELECT ff.field_id, type, name, ff.label, description, default_value, with_notes, available_for_examiners, position' . PHP_EOL
        . ',val.value, notes.value as note' . PHP_EOL
        . ', GROUP_CONCAT(
                COALESCE(
                    CONCAT(cfg_s.value), 
                    CONCAT(cfg_t.value)
                )
        ) AS options' . PHP_EOL

        . 'FROM iass_formfields ff' . PHP_EOL

        . 'LEFT JOIN iass_cfg_singleselect cfg_s ON ff.field_id = cfg_s.field_id AND type = ' . FieldType::SINGLESELECT->value . PHP_EOL
        . 'LEFT JOIN iass_cfg_tag cfg_t ON ff.field_id = cfg_t.field_id AND type = ' . FieldType::TAG->value . PHP_EOL

        . 'LEFT JOIN iass_cust_values val ON ff.obj_id = val.obj_id' . PHP_EOL
        . 'AND ff.field_id = val.field_id' . PHP_EOL
        . 'AND val.usr_id = ' . $this->db->quote($member_usr_id, 'integer') . PHP_EOL

        . 'LEFT JOIN iass_cust_notes notes ON ff.obj_id = notes.obj_id' . PHP_EOL
        . 'AND ff.field_id = notes.field_id' . PHP_EOL
        . 'AND notes.usr_id = ' . $this->db->quote($member_usr_id, 'integer') . PHP_EOL

        . 'WHERE ff.obj_id = ' . $this->db->quote($obj_id, 'integer') . PHP_EOL
        . 'GROUP BY field_id' . PHP_EOL
        . 'ORDER BY position';

        $ret = [];
        $res = $this->db->query($query);
        while ($row = $this->db->fetchAssoc($res)) {
            $options = [];
            if ($row['options'] !== null) {
                $options = explode(',', $row['options']);
            }

            $config = new FieldConfig(
                FieldType::from($row['type']),
                $row['label'],
                $row['description'],
                $row['default_value'],
                $options
            );
            $ret[] = new IASSCustomField(
                $config,
                $obj_id,
                $row['field_id'],
                $member_usr_id,
                (bool) $row['with_notes'],
                (bool) $row['available_for_examiners'],
                (string) $row['value'],
                (string) $row['note'],
            );
        }
        return $ret;
    }

    public function storeSpecifiedUserValues(IASSCustomField ...$fields): void
    {
        foreach ($fields as $field) {
            list($obj_id, $field_id, $usr_id) = $field->getIds();
            $value = $field->getValue();

            if ($field->hasNotes()) {
                list($value, $note) = $value;
                $query = 'REPLACE INTO iass_cust_notes (obj_id, field_id, usr_id, value)' . PHP_EOL
                    . 'VALUES (' . PHP_EOL
                    . $obj_id . ','
                    . $field_id . ','
                    . $usr_id . ','
                    . $this->db->quote((string) $note, 'text') . PHP_EOL
                    . ')';
                $this->db->manipulate($query);
            }

            if (is_array($value)) {
                $value = implode(self::VALUE_DELIMITER, $value);
            }
            $query = 'REPLACE INTO iass_cust_values (obj_id, field_id, usr_id, value)' . PHP_EOL
                . 'VALUES (' . PHP_EOL
                . $obj_id . ','
                . $field_id . ','
                . $usr_id . ','
                . $this->db->quote($value, 'text') . PHP_EOL
                . ')';
            $this->db->manipulate($query);
        }
    }

    public function deleteSpecifiedUserValues(
        IRSS $irss,
        ilIndividualAssessmentGradingStakeholder $stakeholder,
        int $iass_obj_id,
        int $member_usr_id,
    ): void {
        $this->deleteFileResources(
            $irss,
            $stakeholder,
            ...$this->getResourceIdentifiers($iass_obj_id, $member_usr_id)
        );

        $tables = [
            'iass_cust_values',
            'iass_cust_notes'
        ];
        foreach ($tables as $table) {
            $query = 'DELETE FROM ' . $table . PHP_EOL
            . 'WHERE obj_id = ' . $this->db->quote($iass_obj_id, 'integer') . PHP_EOL
            . 'AND usr_id = ' . $this->db->quote($member_usr_id, 'integer');
            $this->db->manipulate($query);
        }
    }

    public function deleteAllUserValuesAndFields(
        IRSS $irss,
        ilIndividualAssessmentGradingStakeholder $stakeholder,
        int $iass_obj_id
    ): void {
        $this->deleteFileResources(
            $irss,
            $stakeholder,
            ...$this->getResourceIdentifiers($iass_obj_id)
        );

        $tables = [
            'iass_cfg_singleselect',
            'iass_cfg_tag',
            'iass_cust_values',
            'iass_cust_notes',
            'iass_formfields',
        ];
        foreach ($tables as $table) {
            $query = 'DELETE FROM ' . $table . PHP_EOL
            . 'WHERE obj_id = ' . $this->db->quote($iass_obj_id, 'integer');
            $this->db->manipulate($query);
        }
    }

    protected function deleteFileResources(
        IRSS $irss,
        ilIndividualAssessmentGradingStakeholder $stakeholder,
        string ...$ids
    ): void {
        foreach ($ids as $identifier) {
            $resource_id = $irss->manage()->find($identifier);
            if ($resource_id !== null) {
                $irss->manage()->remove($resource_id, $stakeholder);
            }
        }
    }

    protected function getResourceIdentifiers(int $obj_id, int ...$usr_ids): array
    {
        $query = 'SELECT value FROM iass_cust_values ival' . PHP_EOL
            . 'JOIN iass_formfields ifield' . PHP_EOL
            . 'ON ival.obj_id = ifield.obj_id' . PHP_EOL
            . 'AND ival.field_id = ifield.field_id' . PHP_EOL
            . 'WHERE ival.obj_id = ' . $this->db->quote($obj_id, 'integer') . PHP_EOL
            . 'AND ifield.type = ' . FieldType::FILE->value;

        if ($usr_ids !== []) {
            $query .= ' AND ' . $this->db->in('ival.usr_id', $usr_ids, false, 'integer');
        }

        $ret = [];
        $res = $this->db->query($query);
        while ($row = $this->db->fetchAssoc($res)) {
            $ret[] = $row['value'];
        }
        return $ret;
    }

}
