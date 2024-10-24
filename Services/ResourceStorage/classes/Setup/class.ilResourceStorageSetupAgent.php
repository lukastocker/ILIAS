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

use ILIAS\Refinery\Transformation;
use ILIAS\Setup\Agent;
use ILIAS\Setup\Config;
use ILIAS\Setup\Metrics;
use ILIAS\Setup\Objective;
use ILIAS\Setup\ObjectiveCollection;

/**
 * Class ilResourceStorageSetupAgent
 * @author Fabian Schmid <fabian@sr.solutions.ch>
 */
class ilResourceStorageSetupAgent implements Agent
{
    use Agent\HasNoNamedObjective;

    public function hasConfig(): bool
    {
        return false;
    }

    public function getArrayToConfigTransformation(): Transformation
    {
        throw new \LogicException("Agent has no config.");
    }

    public function getInstallObjective(Config $config = null): Objective
    {
        return new ObjectiveCollection(
            'IRSS Installation',
            false,
            new ilStorageContainersExistingObjective(),
            new ilDatabaseUpdateStepsExecutedObjective(
                new ilResourceStorageDB80()
            )
        );
    }

    public function getUpdateObjective(Config $config = null): Objective
    {
        return new ObjectiveCollection(
            'IRSS Update',
            false,
            new ilStorageContainersExistingObjective(),
            new ilDatabaseUpdateStepsExecutedObjective(
                new ilResourceStorageDB80()
            ),
            new ilDatabaseUpdateStepsExecutedObjective(
                new ilResourceStorageDB90()
            )
        );
    }

    public function getBuildArtifactObjective(): Objective
    {
        return new ilResourceStorageFlavourArtifact();
    }

    public function getStatusObjective(Metrics\Storage $storage): Objective
    {
        return new ObjectiveCollection(
            'Component ResourceStorage',
            true,
            new ilDatabaseUpdateStepsMetricsCollectedObjective($storage, new ilResourceStorageDB80()),
            new ilDatabaseUpdateStepsMetricsCollectedObjective($storage, new ilResourceStorageDB90())
        );
    }

    /**
     * @return \ilStorageHandlerV1Migration[]
     */
    public function getMigrations(): array
    {
        return [];
    }
}
