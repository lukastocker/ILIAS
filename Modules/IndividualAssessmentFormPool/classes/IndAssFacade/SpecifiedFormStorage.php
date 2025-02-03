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

use ILIAS\ResourceStorage\Services as IRSS;

/**
 * Upon creating an Individual Assessment with a configured Form from a FormPool,
 * the fields are cloned into the IASS and are no longer changeable on the IASS.
 * It becomes a "specified Form".
 * This is the storage layer of those forms to a) build the UI from the specified
 * field settings and b) store and retreive values per participant.
 */
interface SpecifiedFormStorage
{
    /**
     * used in ilIndividualAssessmentMembersStorage to get the specified fields
     * and issue custom fields for a member's grading.
     */
    public function getSpecifiedFormFields(int $iass_obj_id, int $member_usr_id): array;

    /**
     * Store the member values for specified forms' fields.
     */
    public function storeSpecifiedUserValues(IASSCustomField ...$fields): void;

    /**
     * Delete the member values for a specified form's fields.
     */
    public function deleteSpecifiedUserValues(
        IRSS $irss,
        ilIndividualAssessmentGradingStakeholder $stakeholder,
        int $iass_obj_id,
        int $member_usr_id
    ): void;

    /**
     * Upon deletion of the IASS object, delete the member values along.
     */
    public function deleteAllUserValuesAndFields(
        IRSS $irss,
        ilIndividualAssessmentGradingStakeholder $stakeholder,
        int $iass_obj_id
    ): void;

    public function checkForAvailableFormFields(int $obj_id): bool;
}
