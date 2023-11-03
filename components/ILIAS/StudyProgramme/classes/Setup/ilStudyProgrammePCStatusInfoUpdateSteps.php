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

class ilStudyProgrammePCStatusInfoUpdateSteps implements ilDatabaseUpdateSteps
{
    public const TABLE_NAME = 'copg_pobj_def';

    protected ilDBInterface $db;

    public function prepare(ilDBInterface $db): void
    {
        $this->db = $db;
    }

    public function step_1(): void
    {
        $this->db->manipulate(
            "INSERT INTO " . self::TABLE_NAME . " VALUES ('prg','ilContainerPage','classes','components/ILIAS/StudyProgramme')"
        );
    }

    public function step_2(): void
    {
        if ($this->db->tableExists(self::TABLE_NAME)) {
            $query = "UPDATE " . self::TABLE_NAME . " SET " . PHP_EOL
                . " component = REPLACE(component, 'Modules', 'components/ILIAS') " . PHP_EOL
                . " WHERE component LIKE ('Modules/%')";

            $this->db->manipulate($query);
        }
    }

    public function step_3(): void
    {
        if ($this->db->tableExists("copg_pc_def")) {
            $query = "UPDATE " . self::TABLE_NAME . " SET " . PHP_EOL
                . " component = REPLACE(component, 'Services', 'components/ILIAS') " . PHP_EOL
                . " WHERE component LIKE ('Services/%')";

            $this->db->manipulate($query);
        }
    }
}
