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

use ILIAS\User\UserGUIRequest;

/**
 * TableGUI class for custom defined user fields
 * @author Alexander Killing <killing@leifos.de>
 */
class ilCustomUserFieldSettingsTableGUI extends ilTable2GUI
{
    private bool $confirm_change = false;
    private ilClaimingPermissionHelper $permissions;
    /**
     * @var array<string,int>
     */
    private array $perm_map;
    protected UserGUIRequest $request;

    public function __construct(
        object $a_parent_obj,
        string $a_parent_cmd,
        ilClaimingPermissionHelper $a_permissions
    ) {
        global $DIC;

        $ilCtrl = $DIC['ilCtrl'];
        $lng = $DIC['lng'];

        $this->permissions = $a_permissions;
        $this->perm_map = ilCustomUserFieldsGUI::getAccessPermissions();

        parent::__construct($a_parent_obj, $a_parent_cmd);
        $this->setTitle($lng->txt('user_defined_list'));
        $this->setLimit(9999);

        $this->addColumn('', '', '1');
        $this->addColumn($this->lng->txt('user_field'), '');
        $this->addColumn($this->lng->txt('access'), '');
        $this->addColumn($this->lng->txt('export') . ' / ' . $this->lng->txt('search') .
            ' / ' . $this->lng->txt('certificate'), '');
        $this->addColumn($this->lng->txt('actions'), '');

        $this->setEnableHeader(true);
        $this->setFormAction($ilCtrl->getFormAction($a_parent_obj));
        $this->setRowTemplate('tpl.std_fields_settings_row.html', 'components/ILIAS/User');
        $this->disable('footer');
        $this->setEnableTitle(true);

        $user_field_definitions = ilUserDefinedFields::_getInstance();
        $fds = $user_field_definitions->getDefinitions();

        foreach ($fds as $k => $f) {
            $fds[$k]['key'] = $k;
        }
        $this->setData($fds);
        $this->addCommandButton('updateFields', $lng->txt('save'));
        $this->addMultiCommand('askDeleteField', $lng->txt('delete'));
        $this->request = new UserGUIRequest(
            $DIC->http(),
            $DIC->refinery()
        );
    }

    /**
     * @param array<string,string> $a_set
     * @throws ilTemplateException
     */
    protected function fillRow(array $a_set): void
    {
        $field = $a_set['field_id'];

        $props = [
            'visible' => 'user_visible_in_profile',
            'changeable' => 'changeable',
            'searchable' => 'header_searchable',
            'required' => 'required_field',
            'export' => 'export',
            'course_export' => 'course_export',
            'group_export' => 'group_export',
            'visib_reg' => 'header_visible_registration',
            'visib_lua' => 'usr_settings_visib_lua',
            'changeable_lua' => 'usr_settings_changeable_lua',
            'certificate' => 'certificate'
        ];

        $perms = $this->permissions->hasPermissions(
            ilUDFPermissionHelper::CONTEXT_FIELD,
            (string) $field,
            [
                ilUDFPermissionHelper::ACTION_FIELD_EDIT,
                [
                    ilUDFPermissionHelper::ACTION_FIELD_EDIT_ACCESS,
                    ilUDFPermissionHelper::SUBACTION_FIELD_ACCESS_VISIBLE_PERSONAL
                ],
                [
                    ilUDFPermissionHelper::ACTION_FIELD_EDIT_ACCESS,
                    ilUDFPermissionHelper::SUBACTION_FIELD_ACCESS_VISIBLE_REGISTRATION
                ],
                [
                    ilUDFPermissionHelper::ACTION_FIELD_EDIT_ACCESS,
                    ilUDFPermissionHelper::SUBACTION_FIELD_ACCESS_VISIBLE_LOCAL
                ],
                [
                    ilUDFPermissionHelper::ACTION_FIELD_EDIT_ACCESS,
                    ilUDFPermissionHelper::SUBACTION_FIELD_ACCESS_VISIBLE_COURSES
                ],
                [
                    ilUDFPermissionHelper::ACTION_FIELD_EDIT_ACCESS,
                    ilUDFPermissionHelper::SUBACTION_FIELD_ACCESS_VISIBLE_GROUPS
                ],
                [
                    ilUDFPermissionHelper::ACTION_FIELD_EDIT_ACCESS,
                    ilUDFPermissionHelper::SUBACTION_FIELD_ACCESS_CHANGEABLE_PERSONAL
                ],
                [
                    ilUDFPermissionHelper::ACTION_FIELD_EDIT_ACCESS,
                    ilUDFPermissionHelper::SUBACTION_FIELD_ACCESS_CHANGEABLE_LOCAL
                ],
                [
                    ilUDFPermissionHelper::ACTION_FIELD_EDIT_ACCESS,
                    ilUDFPermissionHelper::SUBACTION_FIELD_ACCESS_REQUIRED
                ],
                [
                    ilUDFPermissionHelper::ACTION_FIELD_EDIT_ACCESS,
                    ilUDFPermissionHelper::SUBACTION_FIELD_ACCESS_EXPORT
                ],
                [
                    ilUDFPermissionHelper::ACTION_FIELD_EDIT_ACCESS,
                    ilUDFPermissionHelper::SUBACTION_FIELD_ACCESS_SEARCHABLE
                ],
                [
                    ilUDFPermissionHelper::ACTION_FIELD_EDIT_ACCESS,
                    ilUDFPermissionHelper::SUBACTION_FIELD_ACCESS_CERTIFICATE
                ]
            ]
        );

        $req_checked = $this->request->getChecked();

        foreach ($props as $prop => $lv) {
            $up_prop = strtoupper($prop);

            if ($a_set['field_type'] != UDF_TYPE_WYSIWYG ||
                ($prop != 'searchable')) {
                $this->tpl->setCurrentBlock($prop);
                $this->tpl->setVariable(
                    'HEADER_' . $up_prop,
                    $this->lng->txt($lv)
                );
                $this->tpl->setVariable('PROFILE_OPTION_' . $up_prop, $prop . '_' . $field);

                // determine checked status
                $checked = false;
                if ($a_set[$prop]) {
                    $checked = true;
                }
                if ($this->confirm_change === 1) {
                    $checked = $req_checked[$prop . '_' . $field] ?? false;
                }

                if ($checked) {
                    $this->tpl->setVariable('CHECKED_' . $up_prop, ' checked=\"checked\"');
                }

                if (!$perms[ilUDFPermissionHelper::ACTION_FIELD_EDIT_ACCESS][$this->perm_map[$prop]]) {
                    $this->tpl->setVariable('DISABLE_' . $up_prop, ' disabled=\"disabled\"');
                }

                $this->tpl->parseCurrentBlock();
            }
        }

        // actions
        if ($perms[ilUDFPermissionHelper::ACTION_FIELD_EDIT]) {
            $this->ctrl->setParameter($this->parent_obj, 'field_id', $a_set['field_id']);
            $this->tpl->setCurrentBlock('action');
            $this->tpl->setVariable(
                'HREF_CMD',
                $this->ctrl->getLinkTarget($this->parent_obj, 'edit')
            );
            $this->tpl->setVariable('TXT_CMD', $this->lng->txt('edit'));
            $this->tpl->parseCurrentBlock();
        }

        // field name
        $this->tpl->setCurrentBlock('cb');
        $this->tpl->setVariable('FIELD_ID', $a_set['field_id']);
        $this->tpl->parseCurrentBlock();
        $this->tpl->setVariable('TXT_FIELD', $a_set['field_name']);
    }

    public function setConfirmChange(): void
    {
        $this->confirm_change = true;
    }
}
