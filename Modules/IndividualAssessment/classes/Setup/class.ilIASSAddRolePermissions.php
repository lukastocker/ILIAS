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
use ILIAS\Setup\NullConfig;

class ilIASSAddRolePermissions extends ilSetupObjective
{
    public function __construct()
    {
        parent::__construct(new NullConfig());
    }

    public function getHash(): string
    {
        return hash('sha256', self::class);
    }

    public function getLabel(): string
    {
        return 'Add new permissions create_records and publish_records to IASS roles if role has writing permission';
    }

    public function isNotable(): bool
    {
        return true;
    }

    public function getPreconditions(Environment $environment): array
    {
        return [
            new ilDatabaseInitializedObjective(),
            new ilSettingsFactoryExistsObjective()
        ];
    }

    public function achieve(Environment $environment): Environment
    {
        $db = $environment->getResource(Environment::RESOURCE_DATABASE);
        $permission = $db->fetchAssoc($db->query("SELECT ops_id FROM rbac_operations WHERE operation = 'write'"));
        $create_perm = $db->fetchAssoc($db->query("SELECT ops_id FROM rbac_operations WHERE operation = 'create_records'"));
        $publish_perm = $db->fetchAssoc($db->query("SELECT ops_id FROM rbac_operations WHERE operation = 'publish_records'"));

        $rbac_pa = "SELECT rol_id, ops_id, ref_id FROM rbac_pa WHERE ref_id IN (SELECT oref.ref_id FROM object_reference oref " . PHP_EOL
            . " JOIN iass_settings iass ON (iass.obj_id = oref.obj_id))";

        $res = $db->query($rbac_pa);
        while ($row = $db->fetchAssoc($res)) {
            $ops_ids = unserialize($row['ops_id']);
            if (in_array((int) $permission['ops_id'], $ops_ids)) {
                if (!in_array((int) $create_perm['ops_id'], $ops_ids)) {
                    array_push($ops_ids, $create_perm['ops_id']);
                }
                if (!in_array((int) $publish_perm['ops_id'], $ops_ids)) {
                    array_push($ops_ids, $publish_perm['ops_id']);
                }

                $update = "UPDATE rbac_pa SET ops_id = " . $db->quote(serialize($ops_ids), "text") . PHP_EOL
                    . " WHERE rol_id = " . $db->quote($row['rol_id'], "integer") . PHP_EOL
                    . " AND ref_id = " . $db->quote($row['ref_id'], "integer")
                ;
                $db->manipulate($update);
            }
        }

        return $environment;
    }

    public function isApplicable(Environment $environment): bool
    {
        $db = $environment->getResource(Environment::RESOURCE_DATABASE);
        $create_perm = $db->fetchAssoc($db->query("SELECT ops_id FROM rbac_operations WHERE operation = 'create_records'"));
        $publish_perm = $db->fetchAssoc($db->query("SELECT ops_id FROM rbac_operations WHERE operation = 'publish_records'"));

        if ($create_perm === null && $publish_perm === null) {
            return false;
        }

        $settings_factory = $environment->getResource(Environment::RESOURCE_SETTINGS_FACTORY);
        /** @var ilSetting $settings */
        $settings = $settings_factory->settingsFor("common");
        $setting_available = $settings->get("iass_record_perm_for_writing_perm");

        if ($setting_available != null) {
            return false;
        }

        $settings->set("iass_record_perm_for_writing_perm", "1");
        return true;
    }
}
