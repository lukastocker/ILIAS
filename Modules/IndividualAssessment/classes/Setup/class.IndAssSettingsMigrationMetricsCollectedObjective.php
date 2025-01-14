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

class ilIndAssSettingsMigrationMetricsCollectedObjective extends Setup\Metrics\CollectedObjective
{
    /**
     * @inheritDoc
     */
    protected function getTentativePreconditions(Setup\Environment $environment): array
    {
        return [
            new ilIniFilesLoadedObjective(),
            new ilDatabaseInitializedObjective(),
//            new ilComponentRepositoryExistsObjective(),
//            new ilComponentFactoryExistsObjective()
        ];
    }

    /**
     * @inheritDoc
     */
    protected function collectFrom(Setup\Environment $environment, Setup\Metrics\Storage $storage): void
    {
        $db = $environment->getResource(Setup\Environment::RESOURCE_DATABASE);

        $query = 'SELECT iass_members.obj_id, ref_id,'
            . ' COUNT(usr_id) AS usr,'
            . ' SUM(user_view_file) AS file,'
            . ' SUM(notify) AS result' . PHP_EOL
            . ' FROM iass_members' . PHP_EOL
            . 'INNER JOIN object_reference ref ON iass_members.obj_id = ref.obj_id' . PHP_EOL
            . ' GROUP BY obj_id';

        $result = $db->query($query);
        var_dump($query);
        $collection = [];
        while ($row = $db->fetchAssoc($result)) {

            if (
                ($row['usr'] === (int) $row['file'] || (int) $row['file'] === 0)
                && ($row['usr'] === (int) $row['result'] || (int) $row['result'] === 0)
            ) {
                continue;
            }

            $count_members = new Setup\Metrics\Metric(
                Setup\Metrics\Metric::STABILITY_VOLATILE,
                Setup\Metrics\Metric::TYPE_GAUGE,
                $row['usr']
            );
            $count_file_visible = new Setup\Metrics\Metric(
                Setup\Metrics\Metric::STABILITY_VOLATILE,
                Setup\Metrics\Metric::TYPE_GAUGE,
                (int) $row['file']
            );
            $count_result_visible = new Setup\Metrics\Metric(
                Setup\Metrics\Metric::STABILITY_VOLATILE,
                Setup\Metrics\Metric::TYPE_GAUGE,
                (int) $row['result']
            );

            $obj = new Setup\Metrics\Metric(
                Setup\Metrics\Metric::STABILITY_VOLATILE,
                Setup\Metrics\Metric::TYPE_COLLECTION,
                [
                    'total members ' => $count_members,
                    'file visible  ' => $count_file_visible,
                    'result visible' => $count_result_visible
                ]
            );

            $k = sprintf('obj_id %s / ref_id %s', $row['obj_id'], $row['ref_id']);
            $collection[$k] = $obj;
        }

        $collected = new Setup\Metrics\Metric(
            Setup\Metrics\Metric::STABILITY_VOLATILE,
            Setup\Metrics\Metric::TYPE_COLLECTION,
            $collection
        );

        $storage->store(
            "Individual Assessments with mixed member settings",
            $collected
        );
    }
}
