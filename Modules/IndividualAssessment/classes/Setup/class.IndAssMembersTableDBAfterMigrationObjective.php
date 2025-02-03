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

use ILIAS\Setup\Environment;

class IndAssMembersTableDBAfterMigrationObjective extends \ilDatabaseUpdateStepsExecutedObjective
{
    public function __construct(
        ilDatabaseUpdateSteps $steps,
        protected IndAssSettingsMigration $migration
    ) {
        parent::__construct($steps);
    }

    /**
     * @inheritdocs
     */
    public function getPreconditions(Environment $environment): array
    {
        return [
            new ilDBStepExecutionDBExistsObjective(),
            new ilDatabaseUpdatedObjective(),
            new ilDBStepReaderExistsObjective()
        ];
    }

    public function isApplicable(Environment $environment): bool
    {
        $this->migration->prepare($environment);
        return $this->migration->getRemainingAmountOfSteps() === 0
            && parent::isApplicable($environment);
    }
}
