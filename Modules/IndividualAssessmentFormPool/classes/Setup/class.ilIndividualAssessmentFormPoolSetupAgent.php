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
use ILIAS\Refinery;

class ilIndividualAssessmentFormPoolSetupAgent implements Setup\Agent
{
    use Setup\Agent\HasNoNamedObjective;
    private const TYPE = 'iafp';
    private const TYPE_TITLE = 'Individual Assessment Form Pool';

    /**
     * @inheritdoc
     */
    public function hasConfig(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function getArrayToConfigTransformation(): Refinery\Transformation
    {
        throw new \LogicException("Agent has no config.");
    }

    /**
     * @inheritdoc
     */
    public function getInstallObjective(Setup\Config $config = null): Setup\Objective
    {
        return new Setup\ObjectiveCollection(
            'Type is registered and database is updated for Module/IndividualAssessmentFormPool',
            false,
            ...$this->getObjectives()
        );
    }

    /**
     * @inheritdoc
     */
    public function getUpdateObjective(Setup\Config $config = null): Setup\Objective
    {
        return new Setup\ObjectiveCollection(
            'Type is registered and database is updated for Module/IndividualAssessmentFormPool',
            false,
            ...$this->getObjectives()
        );
    }

    /**
     * @inheritdoc
     */
    public function getBuildArtifactObjective(): Setup\Objective
    {
        return new Setup\Objective\NullObjective();
    }

    /**
     * @inheritdoc
     */
    public function getStatusObjective(Setup\Metrics\Storage $storage): Setup\Objective
    {
        return new Setup\ObjectiveCollection(
            'Component IndividualAssessmentFormPool',
            true,
            new ilObjectNewTypeAddedObjective(
                self::TYPE,
                self::TYPE_TITLE,
            ),
            new ilDatabaseUpdateStepsMetricsCollectedObjective($storage, new IAFPTablesDBUpdateSteps()),
        );
    }

    /**
     * @inheritDoc
     */
    public function getMigrations(): array
    {
        return [];
    }

    private function getObjectives(): array
    {
        return [
            new ilObjectNewTypeAddedObjective(
                self::TYPE,
                self::TYPE_TITLE,
            ),
            new ilDatabaseUpdateStepsExecutedObjective(
                new IAFPTablesDBUpdateSteps()
            ),
            new ilAccessRbacStandardOperationsAddedObjective(self::TYPE),
        ];
    }

}
