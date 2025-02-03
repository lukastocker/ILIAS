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

use ILIAS\Setup;
use ILIAS\Setup\Environment;
use ILIAS\Setup\CLI\IOWrapper;

class IndAssSettingsMigration implements Setup\Migration
{
    private const DEFAULT_AMOUNT_OF_STEPS = 200;
    private ilDBInterface $db;
    private ILIAS\DI\Container $dic;

    /**
     * @var IOWrapper
     */
    private mixed $io;

    public function getLabel(): string
    {
        return "Migrate Settings from Member to Object";
    }

    public function getDefaultAmountOfStepsPerRun(): int
    {
        return self::DEFAULT_AMOUNT_OF_STEPS;
    }

    public function getPreconditions(Environment $environment): array
    {
        return [
            new ilDatabaseUpdateStepsExecutedObjective(
                new IndAssSettingsTableDBUpdateSteps()
            )
        ];
    }

    public function prepare(Environment $environment): void
    {
        $this->db = $environment->getResource(Setup\Environment::RESOURCE_DATABASE);
    }

    /**
     * @throws Exception
     */
    public function step(Environment $environment): void
    {
        $query = "SELECT DISTINCT iass_settings.obj_id" . PHP_EOL
            . "FROM iass_settings" . PHP_EOL
            . "INNER JOIN iass_members ON iass_settings.obj_id = iass_members.obj_id" . PHP_EOL
            . "WHERE iass_members.obj_id NOT IN (" . PHP_EOL
            . "SELECT obj_id FROM iass_members WHERE user_view_file = 0" . PHP_EOL
            . ")" . PHP_EOL
            . "AND iass_members.obj_id IN (" . PHP_EOL
            . "SELECT obj_id FROM iass_members WHERE user_view_file = 1" . PHP_EOL
            . ")" . PHP_EOL
            . "AND iass_settings.file_visible = 0" . PHP_EOL
            . "LIMIT 1";

        $result = $this->db->query($query);
        if ($result->numRows() > 0) {
            $row = $this->db->fetchAssoc($result);
            $obj_id = (int) $row['obj_id'];
            $query = "UPDATE iass_settings SET file_visible = 1 WHERE obj_id = $obj_id";
            $this->db->manipulate($query);
        }

        $query = "SELECT DISTINCT iass_settings.obj_id" . PHP_EOL
            . "FROM iass_settings" . PHP_EOL
            . "INNER JOIN iass_members ON iass_settings.obj_id = iass_members.obj_id" . PHP_EOL
            . "WHERE iass_members.obj_id NOT IN (" . PHP_EOL
            . "SELECT obj_id FROM iass_members WHERE notify = 0" . PHP_EOL
            . ")" . PHP_EOL
            . "AND iass_members.obj_id IN (" . PHP_EOL
            . "SELECT obj_id FROM iass_members WHERE notify = 1" . PHP_EOL
            . ")" . PHP_EOL
            . "AND iass_settings.result_visible = 0" . PHP_EOL
            . "LIMIT 1";

        $result = $this->db->query($query);
        if ($result->numRows() > 0) {
            $row = $this->db->fetchAssoc($result);
            $obj_id = (int) $row['obj_id'];
            $query = "UPDATE iass_settings SET result_visible = 1 WHERE obj_id = $obj_id";
            $this->db->manipulate($query);
        }

    }

    public function getRemainingAmountOfSteps(): int
    {
        if (! $this->db->tableColumnExists('iass_members', 'user_view_file')) {
            return 0;
        }

        $query = "SELECT COUNT(DISTINCT iass_settings.obj_id) AS amount" . PHP_EOL
            . "FROM iass_settings" . PHP_EOL
            . "INNER JOIN iass_members ON iass_settings.obj_id = iass_members.obj_id" . PHP_EOL
            . "WHERE " . PHP_EOL
            . "(" . PHP_EOL
                . "iass_members.obj_id NOT IN (" . PHP_EOL
                    . "SELECT obj_id FROM iass_members WHERE user_view_file = 0" . PHP_EOL
                . ")" . PHP_EOL
                . "AND iass_members.obj_id IN (" . PHP_EOL
                    . "SELECT obj_id FROM iass_members WHERE user_view_file = 1" . PHP_EOL
                . ")" . PHP_EOL
                . "AND iass_settings.file_visible = 0" . PHP_EOL
            . ") OR (" . PHP_EOL
                . "iass_members.obj_id NOT IN (" . PHP_EOL
                    . "SELECT obj_id FROM iass_members WHERE notify = 0" . PHP_EOL
                . ")" . PHP_EOL
                . "AND iass_members.obj_id IN (" . PHP_EOL
                    . "SELECT obj_id FROM iass_members WHERE notify = 1" . PHP_EOL
                . ")" . PHP_EOL
                . "AND iass_settings.result_visible = 0" . PHP_EOL
            . ")" . PHP_EOL;
        $result = $this->db->query($query);
        $row = $this->db->fetchAssoc($result);
        return (int) $row['amount'];
    }
}
