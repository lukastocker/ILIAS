<?php declare(strict_types=1);

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
 
class ilObjLearningSequence extends ilContainer
{
    const OBJ_TYPE = 'lso';

    const E_CREATE = 'create';
    const E_UPDATE = 'update';
    const E_DELETE = 'delete';

    protected ?ilLSItemsDB $items_db = null;
    protected ?ilLSPostConditionDB $conditions_db = null;
    protected ?ilLearnerProgressDB $learner_progress_db = null;
    protected ?ilLearningSequenceParticipants $ls_participants = null;
    protected ?ilLearningSequenceSettings $ls_settings = null;
    protected ?ilLSStateDB $state_db = null;
    protected ?ilLearningSequenceRoles $ls_roles = null;
    protected ?ilLearningSequenceSettingsDB $settings_db = null;
    protected ?ilLearningSequenceActivationDB $activation_db = null;
    protected ?ilLearningSequenceActivation $ls_activation = null;
    protected ?ArrayAccess $di = null;
    protected ?ArrayAccess $local_di = null;
    protected ?ilObjLearningSequenceAccess $ls_access = null;
    protected ArrayAccess $dic;
    protected ilCtrl $ctrl;
    protected ilNewsService $il_news;
    protected ilConditionHandler $il_condition_handler;


    public function __construct(int $id = 0, bool $call_by_reference = true)
    {
        global $DIC;
        $this->dic = $DIC;

        $this->type = self::OBJ_TYPE;
        $this->lng = $DIC['lng'];
        $this->ctrl = $DIC['ilCtrl'];
        $this->user = $DIC['ilUser'];
        $this->tree = $DIC['tree'];
        $this->log = $DIC["ilLoggerFactory"]->getRootLogger();
        $this->app_event_handler = $DIC['ilAppEventHandler'];
        $this->il_news = $DIC->news();
        $this->il_condition_handler = new ilConditionHandler();

        parent::__construct($id, $call_by_reference);
    }

    public static function getInstanceByRefId(int $ref_id) : ?\ilObject
    {
        return ilObjectFactory::getInstanceByRefId($ref_id, false);
    }

    public function read() : void
    {
        $this->getLSSettings();
        if ($this->getRefId()) {
            $this->getLSActivation();
        }
        parent::read();
    }

    public function create() : int
    {
        $id = parent::create();
        if (!$id) {
            return 0;
        }
        $this->raiseEvent(self::E_CREATE);

        return $this->getId();
    }

    public function update() : bool
    {
        if (!parent::update()) {
            return false;
        }
        $this->raiseEvent(self::E_UPDATE);

        return true;
    }

    public function delete() : bool
    {
        if (!parent::delete()) {
            return false;
        }

        ilLearningSequenceParticipants::_deleteAllEntries($this->getId());
        $this->getSettingsDB()->delete($this->getId());
        $this->getStateDB()->deleteFor($this->getRefId());
        $this->getActivationDB()->deleteForRefId($this->getRefId());

        $this->raiseEvent(self::E_DELETE);

        return true;
    }

    protected function raiseEvent(string $event_type) : void
    {
        $this->app_event_handler->raise(
            'Modules/LearningSequence',
            $event_type,
            array(
                'obj_id' => $this->getId(),
                'appointments' => null
            )
        );
    }

    public function cloneObject(int $target_id, int $copy_id = 0, bool $omit_tree = false) : ?ilObject
    {
        /** @var ilObjLearningSequence $new_obj */
        $new_obj = parent::cloneObject($target_id, $copy_id, $omit_tree);

        $this->cloneAutoGeneratedRoles($new_obj);
        $this->cloneMetaData($new_obj);
        $this->cloneSettings($new_obj);
        $this->cloneLPSettings($new_obj->getId());
        $this->cloneActivation($new_obj, $copy_id);

        $roles = $new_obj->getLSRoles();
        $roles->addLSMember(
            $this->user->getId(),
            $roles->getDefaultAdminRole()
        );
        return $new_obj;
    }


    protected function cloneAutoGeneratedRoles(ilObjLearningSequence $new_obj) : bool
    {
        $admin = $this->getDefaultAdminRole();
        $new_admin = $new_obj->getDefaultAdminRole();

        if (!$admin || !$new_admin || !$this->getRefId() || !$new_obj->getRefId()) {
            $this->log->write(__METHOD__ . ' : Error cloning auto generated role: il_lso_admin');
        }

        $this->rbac_admin->copyRolePermissions($admin, $this->getRefId(), $new_obj->getRefId(), $new_admin, true);
        $this->log->write(__METHOD__ . ' : Finished copying of role lso_admin.');

        $member = $this->getDefaultMemberRole();
        $new_member = $new_obj->getDefaultMemberRole();

        if (!$member || !$new_member) {
            $this->log->write(__METHOD__ . ' : Error cloning auto generated role: il_lso_member');
        }

        $this->rbac_admin->copyRolePermissions($member, $this->getRefId(), $new_obj->getRefId(), $new_member, true);
        $this->log->write(__METHOD__ . ' : Finished copying of role lso_member.');

        return true;
    }

    protected function cloneSettings(ilObjLearningSequence $new_obj) : void
    {
        $source = $this->getLSSettings();
        $target = $new_obj->getLSSettings();

        foreach ($source->getUploads() as $key => $upload_info) {
            $target = $target->withUpload($upload_info, $key);
        }

        foreach ($source->getDeletions() as $deletion) {
            $target = $target->withDeletion($deletion);
        }

        $target = $target
            ->withAbstract($source->getAbstract())
            ->withExtro($source->getExtro())
            ->withAbstractImage($source->getAbstractImage())
            ->withExtroImage($source->getExtroImage())
        ;

        $new_obj->updateSettings($target);
    }

    protected function cloneLPSettings(int $obj_id) : void
    {
        $lp_settings = new ilLPObjSettings($this->getId());
        $lp_settings->cloneSettings($obj_id);
    }

    protected function cloneActivation(ilObjLearningSequence $new_obj, int $a_copy_id) : void
    {
        // #14596
        $cwo = ilCopyWizardOptions::_getInstance($a_copy_id);
        if ($cwo->isRootNode($this->getRefId())) {
            $activation = $new_obj->getLSActivation()->withIsOnline(false);
        } else {
            $activation = $new_obj->getLSActivation()
                ->withIsOnline($this->getLSActivation()->getIsOnline())
                ->withActivationStart($this->getLSActivation()->getActivationStart())
                ->withActivationEnd($this->getLSActivation()->getActivationEnd());
        }

        $new_obj->getActivationDB()->store(
            $activation
        );
    }

    protected function getDIC() : ArrayAccess
    {
        return $this->dic;
    }

    public function getDI() : ArrayAccess
    {
        if (is_null($this->di)) {
            $di = new ilLSDI();
            $di->init($this->getDIC());
            $this->di = $di;
        }
        return $this->di;
    }

    public function getLocalDI() : ArrayAccess
    {
        if (is_null($this->local_di)) {
            $di = new ilLSLocalDI();
            $di->init(
                $this->getDIC(),
                $this->getDI(),
                new \ILIAS\Data\Factory(),
                $this
            );
            $this->local_di = $di;
        }
        return $this->local_di;
    }

    protected function getSettingsDB() : ilLearningSequenceSettingsDB
    {
        if (!$this->settings_db) {
            $this->settings_db = $this->getDI()['db.settings'];
        }
        return $this->settings_db;
    }

    protected function getActivationDB() : ilLearningSequenceActivationDB
    {
        if (!$this->activation_db) {
            $this->activation_db = $this->getDI()['db.activation'];
        }
        return $this->activation_db;
    }

    public function getLSActivation() : ilLearningSequenceActivation
    {
        if (!$this->ls_activation) {
            $this->ls_activation = $this->getActivationDB()->getActivationForRefId($this->getRefId());
        }

        return $this->ls_activation;
    }

    public function updateActivation(ilLearningSequenceActivation $settings): void
    {
        $this->getActivationDB()->store($settings);
        $this->ls_activation = $settings;
    }

    public function getLSSettings() : ilLearningSequenceSettings
    {
        if (!$this->ls_settings) {
            $this->ls_settings = $this->getSettingsDB()->getSettingsFor($this->getId());
        }

        return $this->ls_settings;
    }

    public function updateSettings(ilLearningSequenceSettings $settings): void
    {
        $this->getSettingsDB()->store($settings);
        $this->ls_settings = $settings;
    }

    protected function getLSItemsDB() : ilLSItemsDB
    {
        if (!$this->items_db) {
            $this->items_db = $this->getLocalDI()['db.lsitems'];
        }
        return $this->items_db;
    }

    protected function getPostConditionDB() : ilLSPostConditionDB
    {
        if (!$this->conditions_db) {
            $this->conditions_db = $this->getDI()["db.postconditions"];
        }
        return $this->conditions_db;
    }

    public function getLSParticipants() : ilLearningSequenceParticipants
    {
        if (!$this->ls_participants) {
            $this->ls_participants = $this->getLocalDI()['participants'];
        }

        return $this->ls_participants;
    }
    public function getMembersObject(): \ilLearningSequenceParticipants //used by Services/Membership/classes/class.ilMembershipGUI.php
    {
        return $this->getLSParticipants();
    }

    public function getLSAccess() : ilObjLearningSequenceAccess
    {
        if (is_null($this->ls_access)) {
            $this->ls_access = new ilObjLearningSequenceAccess();
        }

        return $this->ls_access;
    }

    /**
     * @return LSItem[]
     */
    public function getLSItems() : array
    {
        $db = $this->getLSItemsDB();
        return $db->getLSItems($this->getRefId());
    }

    /**
     * Update LSItems
     * @param LSItem[]
     */
    public function storeLSItems(array $ls_items) : void
    {
        $db = $this->getLSItemsDB();
        $db->storeItems($ls_items);
    }

    /**
     * Delete post conditions for ref ids.
     * @param int[]
     */
    public function deletePostConditionsForSubObjects(array $ref_ids) : void
    {
        $rep_utils = new ilRepUtil();
        $rep_utils->deleteObjects($this->getRefId(), $ref_ids);
        $db = $this->getPostConditionDB();
        $db->delete($ref_ids);
    }

    /**
     * @return array<"value" => "option_text">
     */
    public function getPossiblePostConditionsForType(string $type) : array
    {
        $condition_types = $this->il_condition_handler->getOperatorsByTriggerType($type);
        $conditions = [
            $this->getPostConditionDB()::STD_ALWAYS_OPERATOR => $this->lng->txt('condition_always')
        ];
        foreach ($condition_types as $cond_type) {
            $conditions[$cond_type] = $this->lng->txt($cond_type);
        }
        return $conditions;
    }

    protected function getLearnerProgressDB() : ilLearnerProgressDB
    {
        if (!$this->learner_progress_db) {
            $this->learner_progress_db = $this->getLocalDI()['db.progress'];
        }
        return $this->learner_progress_db;
    }

    public function getStateDB() : ilLSStateDB
    {
        if (!$this->state_db) {
            $this->state_db = $this->getDI()['db.states'];
        }
        return $this->state_db;
    }

    /**
     * @return LSLearnerItem[]|[]
     */
    public function getLSLearnerItems(int $usr_id) : array
    {
        $db = $this->getLearnerProgressDB();
        return $db->getLearnerItems($usr_id, $this->getRefId());
    }

    public function getLSRoles() : ilLearningSequenceRoles
    {
        if (!$this->ls_roles) {
            $this->ls_roles = $this->getLocalDI()['roles'];
        }
        return $this->ls_roles;
    }

    /**
     * Get mail to members type
     */
    public function getMailToMembersType() : int
    {
        return 0;
    }

    /**
     * Goto target learning sequence.
     */
    public static function _goto(int $target, string $add = "") : void
    {
        global $DIC;
        $main_tpl = $DIC->ui()->mainTemplate();

        $ilAccess = $DIC['ilAccess'];
        $ilErr = $DIC['ilErr'];
        $lng = $DIC['lng'];
        $ilUser = $DIC['ilUser'];
        $request_wrapper = $DIC->http()->wrapper()->query();
        $refinery = $DIC->refinery();

        if (substr($add, 0, 5) == 'rcode') {
            if ($ilUser->getId() == ANONYMOUS_USER_ID) {
                $request_target = $request_wrapper->retrieve("target", $refinery->kindlyTo()->string());
                // Redirect to login for anonymous
                ilUtil::redirect(
                    "login.php?target=" . $request_target . "&cmd=force_login&lang=" .
                    $ilUser->getCurrentLanguage()
                );
            }

            // Redirects to target location after assigning user to learning sequence
            ilMembershipRegistrationCodeUtils::handleCode(
                $target,
                ilObject::_lookupType(ilObject::_lookupObjId($target)),
                substr($add, 5)
            );
        }

        if ($add == "mem" && $ilAccess->checkAccess("manage_members", "", $target)) {
            ilObjectGUI::_gotoRepositoryNode($target, "members");
        }

        if ($ilAccess->checkAccess("read", "", $target)) {
            ilObjectGUI::_gotoRepositoryNode($target);
        } else {
            // to do: force flat view
            if ($ilAccess->checkAccess("visible", "", $target)) {
                ilObjectGUI::_gotoRepositoryNode($target, "infoScreenGoto");
            } else {
                if ($ilAccess->checkAccess("read", "", ROOT_FOLDER_ID)) {
                    $main_tpl->setOnScreenMessage('failure', sprintf(
                        $lng->txt("msg_no_perm_read_item"),
                        ilObject::_lookupTitle(ilObject::_lookupObjId($target))
                    ), true);
                    ilObjectGUI::_gotoRepositoryRoot();
                }
            }
        }

        $ilErr->raiseError($lng->txt("msg_no_perm_read"), $ilErr->FATAL);
    }

    public function getShowMembers() : bool
    {
        return $this->getLSSettings()->getMembersGallery();
    }

    public function announceLSOOnline() : void
    {
        $ns = $this->il_news;
        $context = $ns->contextForRefId($this->getRefId());
        $item = $ns->item($context);
        $item->setContentIsLangVar(true);
        $item->setContentTextIsLangVar(true);
        $item->setTitle("lso_news_online_title");
        $item->setContent("lso_news_online_txt");
        $ns->data()->save($item);
    }
    public function announceLSOOffline() : void
    {
        //NYI
    }

    public function setEffectiveOnlineStatus(bool $status) : void
    {
        $act_db = $this->getActivationDB();
        $act_db->setEffectiveOnlineStatus($this->getRefId(), $status);
    }

    /***************************************************************************
    * Role Stuff
    ***************************************************************************/
    /**
     * @return array<string, int>
     */
    public function getLocalLearningSequenceRoles(bool $translate = false) : array
    {
        return $this->getLSRoles()->getLocalLearningSequenceRoles($translate);
    }

    public function getDefaultMemberRole() : int
    {
        return $this->getLSRoles()->getDefaultMemberRole();
    }

    public function getDefaultAdminRole() : int
    {
        return $this->getLSRoles()->getDefaultAdminRole();
    }

    /**
     * @return array<string, int>|[]
     */
    public function getDefaultLearningSequenceRoles(string $a_grp_id = "") : array
    {
        return $this->getLSRoles()->getDefaultLearningSequenceRoles($a_grp_id);
    }

    public function initDefaultRoles() : void
    {
        $this->getLSRoles()->initDefaultRoles();
    }

    /**
     * @param array<int|string> $user_ids
     * @param string[] $columns
     * @return array<int|string, array>
     */
    public function readMemberData(array $user_ids, array $columns = null) : array
    {
        return $this->getLsRoles()->readMemberData($user_ids, $columns);
    }

    public function getParentObjectInfo(int $ref_id, array $search_types) : ?array
    {
        foreach ($this->tree->getPathFull($ref_id) as $hop) {
            if (in_array($hop['type'], $search_types)) {
                return $hop;
            }
        }
        return null;
    }

    /**
     * @return int[]
     */
    public function getLPCompletionStates() : array
    {
        return [
            ilLPStatus::LP_STATUS_COMPLETED_NUM
        ];
    }
}
