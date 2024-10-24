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
 * User action administration GUI class
 * @author Alexander Killing <killing@leifos.de>
 */
class ilUserActionAdminGUI
{
    private ilRbacSystem $rbabsystem;
    private int $ref_id;
    private UserGUIRequest $request;
    private ilCtrl $ctrl;
    private ilGlobalTemplateInterface $tpl;
    private ilLanguage $lng;
    private ilUserActionContext $action_context;
    private ilUserActionAdmin $user_action_admin;

    public function __construct(int $ref_id)
    {
        global $DIC;

        $this->ctrl = $DIC->ctrl();
        $this->lng = $DIC->language();
        $this->tpl = $DIC["tpl"];
        $this->ref_id = $ref_id;
        $this->rbabsystem = $DIC->rbac()->system();
        $this->lng->loadLanguageModule("usr");
        $this->request = new UserGUIRequest(
            $DIC->http(),
            $DIC->refinery()
        );
        $this->user_action_admin = new ilUserActionAdmin($DIC['ilDB']);
    }

    public function setActionContext(ilUserActionContext $a_val = null): void
    {
        $this->action_context = $a_val;
    }

    public function getActionContext(): ilUserActionContext
    {
        return $this->action_context;
    }

    public function executeCommand(): void
    {
        $next_class = $this->ctrl->getNextClass($this);
        $cmd = $this->ctrl->getCmd("show");

        switch ($next_class) {
            default:
                if (in_array($cmd, ["show", "save"])) {
                    $this->$cmd();
                }
        }
    }

    public function show(): void
    {
        $this->tpl->setOnScreenMessage('info', $this->lng->txt("user_actions_activation_info"));

        $tab = new ilUserActionAdminTableGUI(
            $this,
            "show",
            $this->getActions(),
            $this->rbabsystem->checkAccess("write", $this->ref_id)
        );
        $this->tpl->setContent($tab->getHTML());
    }

    /**
     * Save !!!! note in the future this must depend on the context, currently we only have one
     */
    public function save(): void
    {
        if (!$this->rbabsystem->checkAccess("write", $this->ref_id)) {
            $this->ctrl->redirect($this, "show");
        }

        $active = $this->request->getActionActive();
        foreach ($this->getActions() as $a) {
            $this->user_action_admin->activateAction(
                $this->action_context->getComponentId(),
                $this->action_context->getContextId(),
                $a["action_comp_id"],
                $a["action_type_id"],
                (bool) ($active[$a["action_comp_id"] . ":" . $a["action_type_id"]] ?? false)
            );
        }
        $this->tpl->setOnScreenMessage('success', $this->lng->txt("msg_obj_modified"), true);
        $this->ctrl->redirect($this, "show");
    }

    /**
     * Get actions, !!!! note in the future this must depend on the context, currently we only have one
     * @return array[]
     */
    public function getActions(): array
    {
        $data = [];
        $action_provider_factory = new ilUserActionProviderFactory();
        foreach ($action_provider_factory->getProviders() as $p) {
            foreach ($p->getActionTypes() as $id => $name) {
                $data[] = [
                    "action_comp_id" => $p->getComponentId(),
                    "action_type_id" => $id,
                    "action_type_name" => $name,
                    "active" => $this->user_action_admin->isActionActive(
                        $this->action_context->getComponentId(),
                        $this->action_context->getContextId(),
                        $p->getComponentId(),
                        $id
                    )
                ];
            }
        }
        return $data;
    }
}
