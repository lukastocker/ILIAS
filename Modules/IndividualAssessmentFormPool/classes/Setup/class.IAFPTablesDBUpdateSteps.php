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

class IAFPTablesDBUpdateSteps implements \ilDatabaseUpdateSteps
{
    protected \ilDBInterface $db;

    public function prepare(\ilDBInterface $db): void
    {
        $this->db = $db;
    }

    public function step_1(): void
    {
        $this->db->createTable(
            'iafp_forms',
            [
                'obj_id' => [
                    'type' => 'integer',
                    'length' => 8,
                    'notnull' => true
                ],
                'form_id' => [
                    'type' => 'integer',
                    'length' => 8,
                    'notnull' => true
                ],
                'name' => [
                    'type' => 'text',
                    'length' => 128,
                    'notnull' => true
                ],
                'description' => [
                    'type' => 'text',
                    'length' => 255,
                    'notnull' => true,
                    'default' => '',
                ],
            ]
        );

        $this->db->addPrimaryKey('iafp_forms', ['obj_id', 'form_id']);
        $this->db->addIndex('iafp_forms', ['obj_id'], 'oid');
        $this->db->addIndex('iafp_forms', ['form_id'], 'fid');

        $this->db->createSequence('iafp_forms');
    }

    public function step_2(): void
    {
        $this->db->createTable(
            'iafp_fields',
            [
                'obj_id' => [
                    'type' => 'integer',
                    'length' => 8,
                    'notnull' => true
                ],
                'field_id' => [
                    'type' => 'integer',
                    'length' => 8,
                    'notnull' => true
                ],
                'type' => [
                    'type' => 'integer',
                    'length' => 1,
                    'notnull' => true
                ],
                'name' => [
                    'type' => 'text',
                    'length' => 128,
                    'notnull' => true
                ],
                'label' => [
                    'type' => 'text',
                    'length' => 128,
                    'notnull' => true
                ],
                'description' => [
                    'type' => 'text',
                    'length' => 512,
                    'notnull' => true,
                    'default' => '',
                ],
                'default_value' => [
                    'type' => 'text',
                    'length' => 512,
                    'notnull' => false,
                ],
                'with_notes' => [
                    'type' => 'integer',
                    'length' => 1,
                    'notnull' => true,
                    'default' => 0,
                ],
                'available_for_examiners' => [
                    'type' => 'integer',
                    'length' => 1,
                    'notnull' => true,
                    'default' => 1,
                ],
            ]
        );

        $this->db->addPrimaryKey('iafp_fields', ['obj_id', 'field_id']);
        $this->db->addIndex('iafp_fields', ['obj_id'], 'oid');
        $this->db->addIndex('iafp_fields', ['field_id'], 'fid');

        $this->db->createSequence('iafp_fields');
    }

    public function step_3(): void
    {
        $this->db->createTable(
            'iafp_fieldmap',
            [
                'form_id' => [
                    'type' => 'integer',
                    'length' => 8,
                    'notnull' => true
                ],
                'field_id' => [
                    'type' => 'integer',
                    'length' => 8,
                    'notnull' => true
                ],
                'position' => [
                    'type' => 'integer',
                    'length' => 4,
                    'notnull' => true
                ],
            ]
        );

        $this->db->addPrimaryKey('iafp_fieldmap', ['form_id', 'field_id']);
        $this->db->addIndex('iafp_fieldmap', ['form_id'], 'fo');
        $this->db->addIndex('iafp_fieldmap', ['field_id'], 'fi');
    }

    public function step_4(): void
    {
        $this->db->createTable(
            'iafp_cfg_singleselect',
            [
                'field_id' => [
                    'type' => 'integer',
                    'length' => 8,
                    'notnull' => true
                ],
                'value' => [
                    'type' => 'text',
                    'length' => 512,
                    'notnull' => true
                ],
            ]
        );
    }

    public function step_5(): void
    {
        $this->db->createTable(
            'iafp_cfg_tag',
            [
                'field_id' => [
                    'type' => 'integer',
                    'length' => 8,
                    'notnull' => true
                ],
                'value' => [
                    'type' => 'text',
                    'length' => 512,
                    'notnull' => true
                ]
            ]
        );
    }

    public function step_6(): void
    {
        $this->db->createTable(
            'iass_formfields',
            [
                'obj_id' => [
                    'type' => 'integer',
                    'length' => 8,
                    'notnull' => true
                ],
                'field_id' => [
                    'type' => 'integer',
                    'length' => 8,
                    'notnull' => true
                ],
                'type' => [
                    'type' => 'integer',
                    'length' => 1,
                    'notnull' => true
                ],
                'name' => [
                    'type' => 'text',
                    'length' => 128,
                    'notnull' => true
                ],
                'label' => [
                    'type' => 'text',
                    'length' => 128,
                    'notnull' => true
                ],
                'description' => [
                    'type' => 'clob',
                    'notnull' => true,
                    'default' => '',
                ],
                'default_value' => [
                    'type' => 'text',
                    'length' => 512,
                    'notnull' => false,
                ],
                'with_notes' => [
                    'type' => 'integer',
                    'length' => 1,
                    'notnull' => true,
                    'default' => 0,
                ],
                'available_for_examiners' => [
                    'type' => 'integer',
                    'length' => 1,
                    'notnull' => true,
                    'default' => 1,
                ],
                'position' => [
                    'type' => 'integer',
                    'length' => 4,
                    'notnull' => true
                ],
            ]
        );
        $this->db->addPrimaryKey('iass_formfields', ['obj_id', 'field_id']);
        $this->db->addIndex('iass_formfields', ['obj_id'], 'oid');
        $this->db->addIndex('iass_formfields', ['field_id'], 'fid');
    }

    public function step_7(): void
    {
        $this->db->createTable(
            'iass_cfg_singleselect',
            [
                'obj_id' => [
                    'type' => 'integer',
                    'length' => 8,
                    'notnull' => true
                ],
                'field_id' => [
                    'type' => 'integer',
                    'length' => 8,
                    'notnull' => true
                ],
                'value' => [
                    'type' => 'text',
                    'length' => 512,
                    'notnull' => true
                ],
            ]
        );
    }

    public function step_8(): void
    {
        $this->db->createTable(
            'iass_cfg_tag',
            [
                'obj_id' => [
                    'type' => 'integer',
                    'length' => 8,
                    'notnull' => true
                ],
                'field_id' => [
                    'type' => 'integer',
                    'length' => 8,
                    'notnull' => true
                ],
                'value' => [
                    'type' => 'text',
                    'length' => 512,
                    'notnull' => true
                ]
            ]
        );
    }

    public function step_9(): void
    {
        $this->db->createTable(
            'iass_cust_values',
            [
                'obj_id' => [
                    'type' => 'integer',
                    'length' => 8,
                    'notnull' => true
                ],
                'field_id' => [
                    'type' => 'integer',
                    'length' => 8,
                    'notnull' => true
                ],
                'usr_id' => [
                    'type' => 'integer',
                    'length' => 8,
                    'notnull' => true
                ],
                'value' => [
                    'type' => 'text',
                    'length' => 512,
                    'notnull' => false
                ]
            ]
        );
        $this->db->addPrimaryKey('iass_cust_values', ['obj_id', 'field_id', 'usr_id']);
        $this->db->addIndex('iass_cust_values', ['obj_id'], 'oid');
        $this->db->addIndex('iass_cust_values', ['field_id'], 'fid');
        $this->db->addIndex('iass_cust_values', ['usr_id'], 'uid');
    }

    public function step_10(): void
    {
        $this->db->createTable(
            'iass_cust_notes',
            [
                'obj_id' => [
                    'type' => 'integer',
                    'length' => 8,
                    'notnull' => true
                ],
                'field_id' => [
                    'type' => 'integer',
                    'length' => 8,
                    'notnull' => true
                ],
                'usr_id' => [
                    'type' => 'integer',
                    'length' => 8,
                    'notnull' => true
                ],
                'value' => [
                    'type' => 'clob',
                    'notnull' => true
                ]
            ]
        );
        $this->db->addPrimaryKey('iass_cust_notes', ['obj_id', 'field_id', 'usr_id']);
        $this->db->addIndex('iass_cust_notes', ['obj_id'], 'oid');
        $this->db->addIndex('iass_cust_notes', ['field_id'], 'fid');
        $this->db->addIndex('iass_cust_notes', ['usr_id'], 'uid');
    }

}
