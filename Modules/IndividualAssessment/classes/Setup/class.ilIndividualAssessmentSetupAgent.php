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

class ilIndividualAssessmentSetupAgent implements Setup\Agent
{
    use Setup\Agent\HasNoNamedObjective;

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
        return new Setup\Objective\NullObjective();
    }

    /**
     * @inheritdoc
     */
    public function getUpdateObjective(Setup\Config $config = null): Setup\Objective
    {
        // cat-tms-patch start iassfeatures
        return new Setup\ObjectiveCollection(
            'Individual Assessment',
            true,
            new ilDatabaseUpdateStepsExecutedObjective(
                new ilIndividualAssessmentRectifyMembersTableDBUpdateSteps()
            ),
            new ilDatabaseUpdateStepsExecutedObjective(
                new IndAssSettingsTableDBUpdateSteps()
            ),
            new IndAssMembersTableDBAfterMigrationObjective(
                new IndAssmembersTableDBUpdateSteps(),
                new IndAssSettingsMigration()
            ),
            ...$this->getPermissionObjectives()
        );
        // cat-tms-patch end iassfeatures
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
        // cat-tms-patch start iassfeatures
        return new Setup\ObjectiveCollection(
            'Component Individual Assessment ',
            true,
            new ilDatabaseUpdateStepsMetricsCollectedObjective($storage, new ilIndividualAssessmentRectifyMembersTableDBUpdateSteps()),
            new ilDatabaseUpdateStepsMetricsCollectedObjective($storage, new IndAssSettingsTableDBUpdateSteps())
        );
        // cat-tms-patch end iassfeatures
    }

    /**
     * @inheritDoc
     */
    public function getMigrations(): array
    {
        return [];
    }

    // cat-tms-patch start iassfeatures
    protected function getPermissionObjectives(): array
    {
        return [
            new ilAccessCustomRBACOperationAddedObjective(
                ilIndividualAssessmentAccessHandler::RBAC_OP_CREATE_RECORDS,
                "Create Records for Users",
                "object",
                9010,
                ["iass"]
            ),
            new \ilOrgUnitOperationRegisteredObjective(
                ilIndividualAssessmentAccessHandler::ORGU_OP_CREATE_RECORDS,
                'Create Records for Users',
                ilOrgUnitOperationContext::CONTEXT_IASS
            ),
            new ilAccessCustomRBACOperationAddedObjective(
                ilIndividualAssessmentAccessHandler::RBAC_OP_PUBLISH_RECORDS,
                "Publish Records",
                "object",
                9020,
                ["iass"]
            ),
            new \ilOrgUnitOperationRegisteredObjective(
                ilIndividualAssessmentAccessHandler::ORGU_OP_PUBLISH_RECORDS,
                'Publish Records',
                ilOrgUnitOperationContext::CONTEXT_IASS
            )
        ];
    }
    // cat-tms-patch end iassfeatures
}
