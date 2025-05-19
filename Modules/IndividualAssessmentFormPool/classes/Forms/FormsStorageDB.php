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
use ILIAS\UI\Component\Input\Field\Factory as FieldFactory;
use ILIAS\Refinery\Factory as Refinery;

class FormsStorageDB implements FormsStorage, \IAFPCollector
{
    protected const CONFIG_TABLES = [
        'iafp_cfg_singleselect' => FieldType::SINGLESELECT,
        'iafp_cfg_tag' => FieldType::TAG,
    ];

    private const CONFIG_TABLES_COLLECTOR = [
        FieldType::SINGLESELECT->name => ['iafp_cfg_singleselect', 'iass_cfg_singleselect'],
        FieldType::TAG->name => ['iafp_cfg_tag', 'iass_cfg_tag'],
    ];

    public function __construct(
        private readonly \ilDBInterface $db,
        private readonly \ilAccess $access,
        private \ilLanguage $lng
    ) {
        $this->lng = $lng;
    }

    public function getFormsForObjId(
        int $iafp_obj_id,
        ?Range $range = null,
        ?Order $order = null
    ): \Generator {

        $query_order = '';
        $query_range = '';
        if ($order !== null) {
            $query_order = $order->join('ORDER BY', fn(...$o) => implode(' ', $o));
        }
        if ($range !== null) {
            $query_range = sprintf('LIMIT %2$s OFFSET %1$s', ...$range->unpack());
        }

        $query = 'SELECT obj_id, form_id, name, description' . PHP_EOL
            . 'FROM iafp_forms' . PHP_EOL
            . 'WHERE obj_id = ' . $this->db->quote($iafp_obj_id, 'integer') . PHP_EOL
            . $query_order . PHP_EOL
            . $query_range;

        $res = $this->db->query($query);
        while ($row = $this->db->fetchAssoc($res)) {
            $form_id = $row['form_id'];
            $fields = $this->getFieldsForFormId($form_id);
            yield new Form(
                $form_id,
                $iafp_obj_id,
                $row['name'],
                $row['description'],
                $fields
            );
        }
    }

    public function getFormById(int $form_id): ?Form
    {
        $query = 'SELECT obj_id, form_id, name, description' . PHP_EOL
            . 'FROM iafp_forms' . PHP_EOL
            . 'WHERE form_id = ' . $this->db->quote($form_id, 'integer');

        $res = $this->db->query($query);
        if ($this->db->numRows($res) === 0) {
            return null;
        }
        $row = $this->db->fetchAssoc($res);
        $form_id = $row['form_id'];
        $fields = $this->getFieldsForFormId($form_id);
        return new Form(
            $form_id,
            $row['obj_id'],
            $row['name'],
            $row['description'],
            $fields
        );
    }


    private function getSQLPartsFieldConfig(): array
    {
        $opts = [];
        $joins = [];
        foreach (self::CONFIG_TABLES as $table => $type) {
            $opts[] = 'CONCAT(' . $table . '.value)';
            $joins[] = 'LEFT JOIN ' . $table . ' ON iafp_fields.field_id = ' . $table . '.field_id AND type = ' . $type->value ;
        }
        return [$opts, $joins];
    }

    private function getFieldFromRow(array $row): Field
    {
        $field_id = $row['field_id'];
        $type = FieldType::from($row['type']);
        $options = ($row['options'] !== null) ? explode(',', $row['options']) : null;
        $default_value = $row['default_value'];
        //$config = $this->field_config_factory->buildFieldConfig($type, $options, $default_value);

        $config = new FieldConfig(
            $type,
            $row['label'],
            $row['description'],
            $row['default_value'],
            $options,
        );

        return new Field(
            $config,
            $field_id,
            $row['obj_id'],
            $row['name'],
            (bool) $row['with_notes'],
            (bool) $row['available_for_examiners']
        );
    }

    public function createField(FieldType $type, int $iafp_obj_id): Field
    {
        $config = new FieldConfig($type);
        $field = new Field(
            $config,
            -1,
            $iafp_obj_id,
            $name = '',
            $with_notes = false,
            $available_for_examiners = true
        );
        return $this->storeField($field);
    }

    public function getFieldsForFormId(int $form_id): array
    {
        list($opts, $joins) = $this->getSQLPartsFieldConfig();
        $query = 'SELECT iafp_fields.field_id, obj_id, type, name, label, description, default_value, with_notes, available_for_examiners' . PHP_EOL
            . ', GROUP_CONCAT(COALESCE(' . implode(',', $opts) . ')) AS options' . PHP_EOL
            . 'FROM iafp_fields' . PHP_EOL
            . 'INNER JOIN iafp_fieldmap ON iafp_fields.field_id = iafp_fieldmap.field_id '
            . 'AND iafp_fieldmap.form_id = ' . $this->db->quote($form_id, 'integer') . PHP_EOL
            . implode(' ', $joins) . PHP_EOL
            . 'GROUP BY iafp_fields.field_id '
            . 'ORDER BY iafp_fieldmap.position ASC';

        $ret = [];
        $res = $this->db->query($query);
        while ($row = $this->db->fetchAssoc($res)) {
            $ret[] = $this->getFieldFromRow($row);
        }

        return $ret;
    }

    public function getFieldsForObjId(
        int $iafp_obj_id,
        ?Range $range = null,
        ?Order $order = null
    ): \Generator {
        $query_order = '';
        $query_range = '';
        if ($order !== null) {
            $query_order = $order->join('ORDER BY', fn(...$o) => implode(' ', $o));
        }
        if ($range !== null) {
            $query_range = sprintf('LIMIT %2$s OFFSET %1$s', ...$range->unpack());
        }

        list($opts, $joins) = $this->getSQLPartsFieldConfig();
        $query = 'SELECT iafp_fields.field_id, obj_id, type, name, label, description, default_value, with_notes, available_for_examiners' . PHP_EOL
            . ', GROUP_CONCAT(COALESCE(' . implode(',', $opts) . ')) AS options' . PHP_EOL
            . 'FROM iafp_fields' . PHP_EOL
            . implode(' ', $joins) . PHP_EOL
            . 'WHERE obj_id = ' . $this->db->quote($iafp_obj_id, 'integer') . PHP_EOL
            . 'GROUP BY iafp_fields.field_id' . PHP_EOL
            . $query_order . PHP_EOL
            . $query_range;

        $res = $this->db->query($query);
        while ($row = $this->db->fetchAssoc($res)) {
            yield $this->getFieldFromRow($row);
        }
    }

    public function getFieldById(int $field_id): ?Field
    {
        list($opts, $joins) = $this->getSQLPartsFieldConfig();
        $query = 'SELECT iafp_fields.field_id, obj_id, type, name, label, description, default_value, with_notes, available_for_examiners' . PHP_EOL
            . ', GROUP_CONCAT(COALESCE(' . implode(',', $opts) . ')) AS options' . PHP_EOL
            . 'FROM iafp_fields' . PHP_EOL
            . implode(' ', $joins) . PHP_EOL
            . 'WHERE iafp_fields.field_id = ' . $this->db->quote($field_id, 'integer');


        $res = $this->db->query($query);
        if ($this->db->numRows($res) === 0) {
            return null;
        }
        return $this->getFieldFromRow($this->db->fetchAssoc($res));
    }


    public function getFormsCountForObjId(int $iafp_obj_id): int
    {
        $query = 'SELECT count(*) as cnt' . PHP_EOL
            . 'FROM iafp_forms' . PHP_EOL
            . 'WHERE obj_id = ' . $this->db->quote($iafp_obj_id, 'integer');
        $res = $this->db->query($query);
        return (int) $this->db->fetchAssoc($res)['cnt'];
    }

    public function getAllFormIdsForObjId(int $iafp_obj_id): array
    {
        $query = 'SELECT form_id FROM iafp_forms' . PHP_EOL
            . 'WHERE obj_id = ' . $this->db->quote($iafp_obj_id, 'integer');
        $res = $this->db->query($query);
        return array_map(
            fn($row) => $row['form_id'],
            $this->db->fetchAll($this->db->query($query))
        );
    }


    public function getNewForm(int $iafp_obj_id): Form
    {
        return new Form(
            -1,
            $iafp_obj_id,
            $name = '',
            $description = '',
            $fields = []
        );
    }

    public function storeForm(Form $form): int
    {
        $id = $form->getFormId();
        if ($form->getFormId() === -1) {
            $id = $this->db->nextId('iafp_forms');
        }

        $query = 'REPLACE INTO iafp_forms (obj_id, form_id, name, description) VALUES (' . PHP_EOL
            . $this->db->quote($form->getObjId(), 'integer') . ','
            . $this->db->quote($id, 'integer') . ','
            . $this->db->quote($form->getName(), 'text') . ','
            . $this->db->quote($form->getDescription(), 'text') . PHP_EOL
            . ')';

        $this->db->manipulate($query);

        $query = 'DELETE FROM iafp_fieldmap WHERE ' . PHP_EOL
            . 'form_id = ' . $this->db->quote($form->getFormId(), 'integer');
        $this->db->manipulate($query);

        $position = 0;
        foreach ($form->getFields() as $field) {
            $position += 10;
            $query = 'INSERT INTO iafp_fieldmap (form_id, field_id, position) VALUES (' . PHP_EOL
            . $this->db->quote($id, 'integer') . ','
            . $this->db->quote($field->getFieldId(), 'integer') . ','
            . $this->db->quote($position, 'integer')
            . ')';

            $this->db->manipulate($query);
        }

        return $id;
    }

    public function deleteFormsByIds(array $form_ids): void
    {
        $ids = $this->db->in('form_id', $form_ids, false, 'integer');
        $query = 'DELETE FROM iafp_forms  WHERE ' . $ids;
        $this->db->manipulate($query);
        $query = 'DELETE FROM iafp_fieldmap  WHERE ' . $ids;
        $this->db->manipulate($query);
    }


    public function getFieldsCountForObjId(int $iafp_obj_id): int
    {
        $query = 'SELECT count(*) as cnt' . PHP_EOL
            . 'FROM iafp_fields' . PHP_EOL
            . 'WHERE obj_id = ' . $this->db->quote($iafp_obj_id, 'integer');
        $res = $this->db->query($query);
        return (int) $this->db->fetchAssoc($res)['cnt'];
    }

    public function storeField(Field $field): Field
    {
        if ($field->getFieldId() === -1) {
            $field = $field->withFieldId($this->db->nextId('iafp_fields'));
        }
        $id = $field->getFieldId();
        $config = $field->getConfig();

        $default_value = $config->getDefaultValue();
        if (is_array($default_value)) {
            $default_value = implode(\SpecifiedFormStorageDB::VALUE_DELIMITER, $default_value);
        }

        $query = 'REPLACE INTO iafp_fields (obj_id, field_id, type, name, label, description, default_value, with_notes, available_for_examiners) VALUES (' . PHP_EOL
            . $this->db->quote($field->getObjId(), 'integer') . ','
            . $this->db->quote($id, 'integer') . ','
            . $this->db->quote($field->getConfig()->getType()->value, 'integer') . ','
            . $this->db->quote($field->getName(), 'text') . ','
            . $this->db->quote($config->getLabel(), 'text') . ','
            . $this->db->quote($config->getDescription(), 'text') . ','
            . $this->db->quote($default_value, 'text') . ','
            . $this->db->quote($field->hasNotes(), 'integer') . ','
            . $this->db->quote($field->isAvailableForExaminers(), 'integer') . PHP_EOL
            . ')';
        $this->db->manipulate($query);
        if ($config->getOptions() !== null) {
            $this->storeFieldConfig($id, $config);
        }
        return $field;
    }

    public function getAllFieldIdsForObjId(int $iafp_obj_id): array
    {
        $query = 'SELECT field_id FROM iafp_fields' . PHP_EOL
            . 'WHERE obj_id = ' . $this->db->quote($iafp_obj_id, 'integer');
        $res = $this->db->query($query);
        return array_map(
            fn($row) => $row['field_id'],
            $this->db->fetchAll($this->db->query($query))
        );
    }

    public function deleteFieldsByIds(array $field_ids): void
    {
        $query = 'DELETE FROM iafp_fields WHERE '
            . $this->db->in('field_id', $field_ids, false, 'integer');
        $this->db->manipulate($query);

        $query = 'DELETE FROM iafp_fieldmap WHERE '
            . $this->db->in('field_id', $field_ids, false, 'integer');
        $this->db->manipulate($query);

        foreach (array_keys(self::CONFIG_TABLES) as $cfg_table) {
            $query = 'DELETE FROM ' . $cfg_table . ' WHERE '
                . $this->db->in('field_id', $field_ids, false, 'integer');
            $this->db->manipulate($query);
        }
    }

    public function getMappedFieldIds(): array
    {
        $query = 'SELECT DISTINCT field_id FROM iafp_fieldmap';
        $res = $this->db->query($query);
        return array_map(
            fn($row) => $row['field_id'],
            $this->db->fetchAll($this->db->query($query))
        );
    }

    private function storeFieldConfig(int $field_id, FieldConfig $config): void
    {
        $table = array_filter(
            self::CONFIG_TABLES,
            fn($v) => $v === $config->getType(),
        );

        if ($table === []) {
            var_dump($config->getOptions());
            die();
        }

        $table = array_key_first($table);

        $delete = 'DELETE FROM ' . $table . ' WHERE field_id = ' . $this->db->quote($field_id, 'integer');
        $this->db->manipulate($delete);

        if ($config->getOptions() !== []) {
            $options = [];
            foreach ($config->getOptions() as $opt) {
                $options[] = '('
                    . implode(',', [$this->db->quote($field_id, 'integer'), $this->db->quote($opt, 'text')])
                    . ')' . PHP_EOL;
            }
            $insert = 'INSERT INTO ' . $table . ' (field_id, value) VALUES' . PHP_EOL
            . implode(', ', $options);

            $this->db->manipulate($insert);
        }
    }


    // --- collector ---

    public function getFormsSelection(): array
    {
        $options = [-1 => [$this->lng->txt('iass_std_form'), $this->lng->txt('iass_no_form_pool')]]; //add default option ('standard'), form only shows for entries > 1
        foreach ($this->getFormPools() as $pool_info) {
            list($ref_id, $obj_id, $title) = $pool_info;
            $forms = $this->getFormsForObjId($obj_id);
            foreach ($forms as $form) {
                $options['iass_' . $form->getFormId()] = [$form->getName(), $form->getDescription()];
            }
        }
        return $options;
    }

    public function copyFieldsToIASS(int $iafp_form_id, int $iass_obj_id): void
    {
        $fields = $this->getFieldsForFormId($iafp_form_id);
        $position = 0;
        foreach ($fields as $field) {
            $config = $field->getConfig();
            $position = $position + 10;
            $query = 'REPLACE INTO iass_formfields (obj_id, field_id, type, name, label, description, default_value, with_notes, available_for_examiners, position) VALUES (' . PHP_EOL
                . $this->db->quote($iass_obj_id, 'integer') . ','
                . $this->db->quote($field->getFieldId(), 'integer') . ','
                . $this->db->quote($config->getType()->value, 'integer') . ','
                . $this->db->quote($field->getName(), 'text') . ','
                . $this->db->quote($config->getLabel(), 'text') . ','
                . $this->db->quote($config->getDescription(), 'text') . ','
                . $this->db->quote($config->getDefaultValue(), 'text') . ','
                . $this->db->quote($field->hasNotes(), 'integer') . ','
                . $this->db->quote($field->isAvailableForExaminers(), 'integer') . ','
                . $this->db->quote($position, 'integer') . PHP_EOL
                . ')';
            $this->db->manipulate($query);

            if (array_key_exists($config->getType()->name, self::CONFIG_TABLES_COLLECTOR)) {
                list($source_table, $target_table) = self::CONFIG_TABLES_COLLECTOR[$field->getConfig()->getType()->name];

                $fields = $this->getFieldsFromTable($source_table);
                $query = 'REPLACE INTO ' . $target_table . '(obj_id, ' . $fields . ')' . PHP_EOL
                    . 'SELECT ' . $this->db->quote($iass_obj_id, 'integer') . ',' . $fields . PHP_EOL
                    . 'FROM ' . $source_table . PHP_EOL
                    . 'WHERE field_id = ' . $field->getFieldId();

                $this->db->manipulate($query);
            }
        }
    }

    protected function getFieldsFromTable(string $table_name): string
    {
        $query = 'SELECT GROUP_CONCAT(COLUMN_NAME) as fields FROM INFORMATION_SCHEMA.COLUMNS' . PHP_EOL
            . 'WHERE COLUMN_NAME <> "obj_id" AND TABLE_NAME = ' . $this->db->quote($table_name, 'text');
        $res = $this->db->query($query);
        return  $this->db->fetchAssoc($res)['fields'];
    }

    public function cloneFields(int $source_iass_obj_id, int $target_iass_obj_id): void
    {
        $tables = [
            'iass_formfields',
            'iass_cfg_singleselect',
            'iass_cfg_tag',
        ];
        foreach ($tables as $table) {
            $fields = $this->getFieldsFromTable($table);
            $query = 'INSERT INTO ' . $table . ' (obj_id,' . $fields . ')' . PHP_EOL
                . 'SELECT '
                . $this->db->quote($target_iass_obj_id, 'integer') . ',' . $fields . PHP_EOL
                . 'FROM ' . $table . ' WHERE obj_id = ' . $this->db->quote($source_iass_obj_id, 'integer');
            $this->db->manipulate($query);
        }
    }

    private function getFormPools(): array
    {
        $query = 'SELECT ref.obj_id, ref.ref_id, od.title FROM object_data od' . PHP_EOL
            . 'JOIN object_reference ref ON od.obj_id = ref.obj_id' . PHP_EOL
            . 'WHERE ref.deleted IS NULL AND od.type = "iafp" AND od.offline = FALSE';

        $ret = [];
        $res = $this->db->query($query);
        while ($row = $this->db->fetchAssoc($res)) {
            $ref_id = (int) $row['ref_id'];
            $obj_id = (int) $row['obj_id'];
            if ($this->access->checkAccess('read', '', $ref_id)) {
                $ret[] = [
                    $ref_id,
                    $obj_id,
                    $row['title']
                ];
            };
        }
        return $ret;
    }
}
