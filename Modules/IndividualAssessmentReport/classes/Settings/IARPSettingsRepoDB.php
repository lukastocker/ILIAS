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

use ILIAS\IARP\Settings;

class IARPSettingsRepoDB implements IARPSettingsRepo
{
    public const TABLE = 'iarp_settings';

    public function __construct(
        protected \ilDBInterface $db
    ) {
    }

    public function create(int $obj_id): Settings
    {
        $settings = new Settings($obj_id, false);
        $query = 'INSERT INTO ' . self::TABLE . '() VALUES (' . PHP_EOL
            . $this->db->quote($settings->getObjId(), 'integer') . ', '
            . $this->db->quote($settings->isGlobal(), 'integer') . PHP_EOL
            . ')';
        $this->db->manipulate($query);
        return $settings;
    }

    public function get(int $obj_id): ?Settings
    {
        $query = 'SELECT is_global FROM ' . self::TABLE . ' WHERE'
            . ' obj_id = ' . $this->db->quote($obj_id, 'integer');

        $res = $this->db->query($query);
        if ($this->db->numRows($res) === 0) {
            return null;
        }
        $row = $this->db->fetchAssoc($res);
        return new Settings(
            $obj_id,
            (bool) $row['is_global']
        );
    }

    public function update(Settings $settings): void
    {
        $query = 'UPDATE ' . self::TABLE . ' SET' . PHP_EOL
            . 'is_global = ' . $this->db->quote($settings->isGlobal(), 'integer') . PHP_EOL
            . 'WHERE obj_id = ' . $settings->getObjId();
        $this->db->manipulate($query);
    }

    public function delete(int $obj_id): void
    {
        $query = 'DELETE FROM ' . self::TABLE . ' WHERE obj_id = ' . $this->db->quote($obj_id, 'integer');
        $this->db->manipulate($query);
    }

}
