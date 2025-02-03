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


class ilObjIndividualAssessmentAccess extends ilObjectAccess implements ilConditionHandling
{
    /**
     * @inheritdoc
     */
    public static function _getCommands(): array
    {
        return [
            ["permission" => "read", "cmd" => "", "lang_var" => "show", "default" => true],
            ["permission" => "write", "cmd" => "edit", "lang_var" => "settings", "default" => false]
        ];
    }

    /**
     * ilConditionHandling implementation
     *
     * @inheritdoc
     */
    public static function getConditionOperators(): array
    {
        return [
            ilConditionHandler::OPERATOR_PASSED,
            ilConditionHandler::OPERATOR_FAILED
        ];
    }

    /**
     * @inheritdoc
     */
    public static function checkCondition(int $a_trigger_obj_id, string $a_operator, string $a_value, int $a_usr_id): bool
    {
        switch ($a_operator) {
            case ilConditionHandler::OPERATOR_PASSED:
                // cat-tms-patch start iassfeatures
                return ilIndividualAssessmentLPInterface::determineStatusOfMember($a_trigger_obj_id, $a_usr_id)
                    == ilLPStatus::LP_STATUS_COMPLETED_NUM;
                // cat-tms-patch end iassfeatures
            case ilConditionHandler::OPERATOR_FAILED:
                // cat-tms-patch start iassfeatures
                return ilIndividualAssessmentLPInterface::determineStatusOfMember($a_trigger_obj_id, $a_usr_id)
                    == ilLPStatus::LP_STATUS_FAILED_NUM;
                // cat-tms-patch end iassfeatures
            default:
                return false;
        }
    }
}
