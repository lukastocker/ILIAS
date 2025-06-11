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

class ilIndividualAssessmentReportSetupAgent implements Setup\Agent
{
    use Setup\Agent\HasNoNamedObjective;
    private const TYPE = 'iarp';
    private const TYPE_TITLE = 'Individual Assessment Report';

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
            'Type is registered and database is updated for Module/IndividualAssessmentReport',
            true,
            ...$this->getObjectives()
        );
    }

    /**
     * @inheritdoc
     */
    public function getUpdateObjective(Setup\Config $config = null): Setup\Objective
    {
        return new Setup\ObjectiveCollection(
            'Type is registered and database is updated for Module/IndividualAssessmentReport',
            true,
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
            'Component IndividualAssessmentReport',
            true,
            /*
            ...$this->getObjectives()
            */
            new ilObjectNewTypeAddedObjective(
                self::TYPE,
                self::TYPE_TITLE,
            ),
            new ilDatabaseUpdateStepsMetricsCollectedObjective($storage, new IARPTablesDBUpdateSteps()),
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
                new IARPTablesDBUpdateSteps()
            ),
            new ilAccessRbacStandardOperationsAddedObjective(self::TYPE),
            new ilOrgUnitOperationContextRegisteredObjective(
                ilOrgUnitOperationContext::CONTEXT_IARP,
                ilOrgUnitOperationContext::CONTEXT_OBJECT
            ),

            new ilAccessCustomRBACOperationAddedObjective(
                IARPAccessHandler::RBAC_VIEW_GENERAL_STATUS,
                'View general status of other users',
                "object",
                9010,
                ["iarp"]
            ),
            new ilAccessCustomRBACOperationAddedObjective(
                IARPAccessHandler::RBAC_VIEW_RESULTS,
                'View results of other users',
                "object",
                9020,
                ["iarp"]
            ),
            new ilAccessCustomRBACOperationAddedObjective(
                IARPAccessHandler::RBAC_VIEW_FULL_RECORD,
                'View full record of other users',
                "object",
                9030,
                ["iarp"]
            ),

            new ilOrgUnitOperationRegisteredObjective(
                IARPAccessHandler::OP_VIEW_GENERAL_STATUS,
                'View general status of other users',
                ilOrgUnitOperationContext::CONTEXT_IARP
            ),
            new ilOrgUnitOperationRegisteredObjective(
                IARPAccessHandler::OP_VIEW_RESULTS,
                'View results of other users',
                ilOrgUnitOperationContext::CONTEXT_IARP
            ),
            new ilOrgUnitOperationRegisteredObjective(
                IARPAccessHandler::OP_VIEW_FULL_RECORD,
                'View full record of other users',
                ilOrgUnitOperationContext::CONTEXT_IARP
            ),
            new ilAccessCustomRBACOperationAddedObjective(
                IARPAccessHandler::RBAC_VIEW_SPECIFIC_RECORDS,
                'View specific results of other users',
                "object",
                9040,
                ["iarp"]
            ),
            new ilOrgUnitOperationRegisteredObjective(
                IARPAccessHandler::OP_VIEW_SPECIFIC_RECORDS,
                'View specific records of other users',
                ilOrgUnitOperationContext::CONTEXT_IARP
            ),
        ];
    }
}
