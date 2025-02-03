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

class IARPAccessHandler
{
    public const RBAC_VIEW_GENERAL_STATUS = 'iarp_view_general_status';
    public const RBAC_VIEW_RESULTS = 'iarp_view_results';
    public const RBAC_VIEW_FULL_RECORD = 'iarp_view_full_record';
    public const RBAC_VIEW_SPECIFIC_RECORDS = 'iarp_view_specific_records';
    public const OP_VIEW_GENERAL_STATUS = 'ou_view_general_status';
    public const OP_VIEW_RESULTS = 'ou_view_results';
    public const OP_VIEW_FULL_RECORD = 'ou_view_full_record';
    public const OP_VIEW_SPECIFIC_RECORDS = 'ou_view_specific_records';

    public function __construct(
        private readonly ilAccessHandler $access,
        private readonly ilRbacReview $review,
        private readonly ilOrgUnitGlobalSettings $orgu_settings,
        private readonly ilObjectDataCache $data_cache,
        private readonly ilOrgUnitPositionAccess $orgu_access,
        private readonly ilOrgUnitUserAssignmentDBRepository $orgu_assignment_repo,
        private readonly int $current_usr_id,
        private readonly int $iarp_ref_id
    ) {
    }

    public function mayView(): bool
    {
        return $this->isSystemAdmin() ||
            $this->access->checkAccess('visible', '', $this->iarp_ref_id);
    }

    public function mayRead(): bool
    {
        return $this->isSystemAdmin() ||
            $this->access->checkAccess('read', '', $this->iarp_ref_id);
    }

    public function mayEdit(): bool
    {
        return $this->isSystemAdmin() ||
            $this->access->checkAccess('write', '', $this->iarp_ref_id);
    }

    public function mayEditPermissions(): bool
    {
        return $this->isSystemAdmin() ||
            $this->access->checkAccess('edit_permission', '', $this->iarp_ref_id);
    }

    public function mayViewOthersByRBAC(): bool
    {
        return $this->isSystemAdmin() ||
            $this->access->checkAccess(self::RBAC_VIEW_GENERAL_STATUS, '', $this->iarp_ref_id);
    }

    public function mayViewOthersByPosition(): bool
    {
        return $this->access->checkPositionAccess(
            self::OP_VIEW_GENERAL_STATUS,
            $this->iarp_ref_id
        );
    }

    public function mayViewOthersLP(): bool
    {
        return $this->isSystemAdmin() ||
            $this->access->checkRbacOrPositionPermissionAccess(
                self::RBAC_VIEW_RESULTS,
                self::OP_VIEW_RESULTS,
                $this->iarp_ref_id
            );
    }

    public function mayViewOthersFull(): bool
    {
        return $this->isSystemAdmin() ||
            $this->access->checkRbacOrPositionPermissionAccess(
                self::RBAC_VIEW_FULL_RECORD,
                self::OP_VIEW_FULL_RECORD,
                $this->iarp_ref_id
            );
    }

    protected function isSystemAdmin(): bool
    {
        return $this->review->isAssigned($this->current_usr_id, SYSTEM_ROLE_ID);
    }

    public function isOrguAccessEnabledGlobally(): bool
    {
        $type_settings = $this->orgu_settings->getObjectPositionSettingsByType('iarp');
        return $type_settings->isActive() && $type_settings->isChangeableForObject();
    }

    public function isOrguAccessEnabledAtObject(): bool
    {
        $obj_id = $this->data_cache->lookupObjId($this->iarp_ref_id);
        return $this->orgu_settings->isPositionAccessActiveForObject($obj_id);
    }

    public function getUserIdsWhereCurrentUserHasAuthority(): array
    {
        $positions = $this->orgu_assignment_repo->getPositionsByUser($this->current_usr_id);
        $user_ids = [];
        foreach ($positions as $position) {
            foreach ($this->getUserIdsByPositionAndUser($position, $this->current_usr_id) as $u) {
                $user_ids[] = $u;
            }
        }
        return array_unique($user_ids);
    }

    protected function getUserIdsByPositionAndUser(ilOrgUnitPosition $position, int $user_id): array
    {
        $ids = [];
        foreach ($position->getAuthorities() as $authority) {
            $recurse = $authority->getScope() === ilOrgUnitAuthority::SCOPE_SUBSEQUENT_ORGUS;
            if ($authority->getOver() === ilOrgUnitAuthority::OVER_EVERYONE) {
                $ids = array_merge(
                    $ids,
                    $this->orgu_assignment_repo->getUsersByUserAndPosition($position->getId(), $user_id, $recurse)
                );
            } else {
                $ids = array_merge(
                    $ids,
                    $this->orgu_assignment_repo->getFilteredUsersByUserAndPosition($user_id, $position->getId(), $authority->getOver(), $recurse)
                );
            }
        }
        return $ids;
    }

    public function mayViewSpecificRecords(): bool
    {
        return $this->isSystemAdmin() ||
            $this->access->checkRbacOrPositionPermissionAccess(
                self::RBAC_VIEW_SPECIFIC_RECORDS,
                self::OP_VIEW_SPECIFIC_RECORDS,
                $this->iarp_ref_id
            );
    }
}
