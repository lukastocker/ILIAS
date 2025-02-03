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

use ILIAS\IARP\UserInfo;
use ILIAS\IARP\IASSInfo;
use ILIAS\IARP\GradingInfo;

class IARPResult
{
    private bool $perm_view_lp = true;
    private bool $perm_view_full = true;
    private bool $perm_view_specific = true;

    public function __construct(
        protected readonly UserInfo $user_info,
        protected readonly IASSInfo $iass_info,
        protected readonly GradingInfo $grading_info,
    ) {
    }

    public function withPermissionFilter(
        int $current_user_id,
        bool $perm_view_lp,
        bool $perm_view_full,
        bool $perm_view_specific
    ) {
        $is_own_record = $this->user_info->getUserId() === $current_user_id;
        $clone = clone $this;
        $clone->perm_view_lp = $perm_view_lp || $is_own_record;
        $clone->perm_view_full = $perm_view_full || $is_own_record;
        $clone->perm_view_specific = $perm_view_specific || $is_own_record;
        return $clone;
    }

    public function getHeadline(): string
    {
        return $this->iass_info->getTitle();
    }

    public function getSubheadline(): string
    {
        return $this->user_info->getRepresentation();
    }

    public function getImportantInfos(
        ilLanguage $lng,
        \ILIAS\Data\DateFormat\DateFormat $date_format
    ): array {
        $ret = [];

        if ($this->perm_view_lp) {
            $ret[$lng->txt('learning_progress')] = $this->getTranslatedLPStatus(
                $lng,
                $this->grading_info->getLPStatus()
            );
        }

        $examiner = $this->grading_info->getExaminer();
        if ($examiner !== null) {
            $ret[$lng->txt('iass_graded_by')] = $examiner->getRepresentation();
        }
        $changer = $this->grading_info->getChangedBy();
        if ($changer !== null) {
            $ret[$lng->txt('iass_changed_by')] = $changer->getRepresentation()
                . ' '
                . $date_format->applyTo($this->grading_info->getLastChange());

        }
        return $ret;
    }

    public function getContent(
        ilLanguage $lng,
        IASSCustomFieldValueRenderer $value_renderer
    ): array {
        if (!$this->perm_view_full && !$this->perm_view_specific) {
            return [];
        }

        $ret = [
            $lng->txt('iass_record') => $this->grading_info->getRecordNote(),
            $lng->txt('iass_internal_note') => $this->grading_info->getInternalNote(),
        ];
        if ($file_id = $this->grading_info->getFileId()) {
            $ret[$lng->txt('iass_file')] = $value_renderer->getFileLinkById($file_id);
        }

        foreach ($this->grading_info->getCustomFields() as $cf) {
            if ($this->perm_view_specific && !$cf->isAvailableForParticipant()) {
                $ret[$cf->getConfig()->getLabel()] = $value_renderer->render($cf);
            }
            if ($this->perm_view_full && $cf->isAvailableForParticipant()) {
                $ret[$cf->getConfig()->getLabel()] = $value_renderer->render($cf);
            }
        }

        return $ret;
    }

    public function getFurtherFields(
        ilLanguage $lng,
        \ILIAS\Data\DateFormat\DateFormat $date_format
    ): array {
        $further_fields = [];
        if ($event_time = $this->grading_info->getEventTime()) {
            $further_fields[$lng->txt('iass_event_time')] = $date_format->applyTo($event_time);
        }
        if ($location = $this->grading_info->getLocation()) {
            $further_fields[$lng->txt('iass_location')] = $location;
        }

        return array_merge(
            $this->getImportantInfos($lng, $date_format),
            $further_fields
        );
    }

    protected function getTranslatedLPStatus(ilLanguage $lng, int $status): string
    {
        switch ($status) {
            case ilLPStatus::LP_STATUS_NOT_ATTEMPTED_NUM:
                return $lng->txt(ilLPStatus::LP_STATUS_NOT_ATTEMPTED);
            case ilLPStatus::LP_STATUS_IN_PROGRESS_NUM:
                return $lng->txt(ilLPStatus::LP_STATUS_IN_PROGRESS);
            case ilLPStatus::LP_STATUS_COMPLETED_NUM:
                return $lng->txt(ilLPStatus::LP_STATUS_COMPLETED);
            case ilLPStatus::LP_STATUS_FAILED_NUM:
                return $lng->txt(ilLPStatus::LP_STATUS_FAILED);
        }
    }
}
