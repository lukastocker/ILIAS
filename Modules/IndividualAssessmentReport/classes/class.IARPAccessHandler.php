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
    public function __construct(
        private readonly ilAccessHandler $access,
        private readonly ilRbacReview $review,
        private readonly ilOrgUnitGlobalSettings $orgu_settings,
        private readonly ilObjectDataCache $data_cache,
        private readonly ilOrgUnitPositionAccess $orgu_access,
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

    protected function isSystemAdmin(): bool
    {
        return $this->review->isAssigned($this->current_usr_id, SYSTEM_ROLE_ID);
    }

    public function isOrguAccessEnabledGlobally(): bool
    {
        return true;
        $obj_id = $this->data_cache->lookupObjId($this->iarp_ref_id);
        $type_settings = $this->orgu_settings->getObjectPositionSettingsByType('iarp');
        return $type_settings->isActive() && $type_settings->isChangeableForObject();
    }
}
