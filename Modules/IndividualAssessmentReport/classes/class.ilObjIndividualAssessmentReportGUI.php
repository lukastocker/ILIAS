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

use ILIAS\UI\Component\Input\Container\Form\Standard as Form;

/**
 * @ilCtrl_Calls ilObjIndividualAssessmentReportGUI: ilPermissionGUI
 * @ilCtrl_Calls ilObjIndividualAssessmentReportGUI: ilInfoScreenGUI
 * @ilCtrl_Calls ilObjIndividualAssessmentReportGUI: ilObjectCopyGUI
 * @ilCtrl_Calls ilObjIndividualAssessmentReportGUI: ilCommonActionDispatcherGUI
 * @ilCtrl_Calls ilObjIndividualAssessmentReportGUI: IARPReportGUI
 */
class ilObjIndividualAssessmentReportGUI extends ilObjectGUI
{
    public const CMD_VIEW = 'view';
    public const CMD_INFO = 'showSummary';
    public const CMD_EDIT = 'edit';
    public const CMD_SAVE = 'save';
    public const CMD_EDIT_ADD_SETTINGS = 'editAdd';
    public const CMD_SAVE_ADD_SETTINGS = 'saveAdd';
    public const CMD_REPORT = 'report';

    public const TAB_INFO = 'info_short';
    public const TAB_PERMISSION = 'perm_settings';
    public const TAB_SETTINGS = 'settings';
    public const TAB_ADDITIONAL_SETTINGS = 'addsettings';
    public const TAB_REPORT = 'report';

    protected ilNavigationHistory $navigation_history;
    protected ilObjUser $usr;
    protected ilErrorHandling $error_object;
    protected ILIAS\Refinery\Factory $refinery;
    protected ILIAS\HTTP\Wrapper\RequestWrapper $request_wrapper;
    protected ?IARPAccessHandler $permissions = null;

    public function __construct($data, int $id = 0, bool $call_by_reference = true, bool $prepare_output = true)
    {
        $this->type = 'iarp';

        global $DIC;
        $this->navigation_history = $DIC['ilNavigationHistory'];
        $this->tpl = $DIC['tpl'];
        $this->ctrl = $DIC['ilCtrl'];
        $this->usr = $DIC['ilUser'];
        $this->error_object = $DIC['ilErr'];
        $this->lng = $DIC['lng'];
        $this->lng->loadLanguageModule('iarp');
        $this->tpl->loadStandardTemplate();
        $this->refinery = $DIC->refinery();
        $this->request_wrapper = $DIC->http()->wrapper()->query();

        parent::__construct($data, $id, $call_by_reference, $prepare_output);
    }

    public function executeCommand(): void
    {
        $next_class = $this->ctrl->getNextClass($this);
        $cmd = $this->ctrl->getCmd(self::CMD_VIEW);

        if (!$this->getCreationMode()) {
            $this->permissions = $this->object->getDic()['access'];
            $this->prepareOutput();
            $this->addToNavigationHistory();
        }

        switch ($next_class) {
            case 'ilinfoscreengui':
                $this->tabs_gui->activateTab(self::TAB_INFO);
                $this->ctrl->forwardCommand(new ilInfoScreenGUI($this));
                break;
            case 'ilpermissiongui':
                $this->tabs_gui->activateTab(self::TAB_PERMISSION);
                $this->ctrl->forwardCommand(new ilPermissionGUI($this));
                break;
            case "ilcommonactiondispatchergui":
                $gui = ilCommonActionDispatcherGUI::getInstanceFromAjaxCall();
                $this->ctrl->forwardCommand($gui);
                break;
            case 'ilobjectcopygui':
                $cp = new ilObjectCopyGUI($this);
                $this->ctrl->forwardCommand(new ilObjectCopyGUI($this));
                break;
            case 'iarpreportgui':
                $this->checkPermission('read');
                $this->tabs_gui->activateTab(self::TAB_REPORT);
                $gui = $this->object->getDic()['gui.report'];
                $this->ctrl->forwardCommand($gui);
                break;

            default:
                switch ($cmd) {
                    case 'create':
                        parent::createObject();
                        break;
                    case 'cancel':
                        parent::cancelObject();
                        break;
                    case self::CMD_VIEW:
                    case self::CMD_INFO:
                        $this->checkPermission('visible');
                        //$this->tabs_gui->activateTab(self::TAB_SETTINGS);
                        //$this->ctrl->redirectByClass('ilinfoscreengui', 'showSummary');
                        $info = new ilInfoScreenGUI($this);
                        $this->fillInfoScreen($info);
                        $this->ctrl->forwardCommand($info);

                        break;
                    case self::CMD_EDIT:
                        $this->checkPermission('write');
                        $this->getSubTabs(self::TAB_SETTINGS);
                        $this->tabs_gui->activateTab(self::TAB_SETTINGS);
                        $this->tabs_gui->activateSubTab(self::TAB_SETTINGS);
                        $this->edit();
                        break;
                    case self::CMD_SAVE:
                        if ($this->getCreationMode()) {
                            parent::saveObject();
                            $this->ctrl->redirectToURL(
                                $this->getLinkTarget(self::CMD_EDIT)
                            );
                        }
                        $this->checkPermission('write');
                        $this->tabs_gui->activateTab(self::TAB_SETTINGS);
                        $this->save();
                        break;

                    case self::CMD_EDIT_ADD_SETTINGS:
                        $this->checkPermission('write');
                        $this->getSubTabs(self::TAB_SETTINGS);
                        $this->tabs_gui->activateTab(self::TAB_SETTINGS);
                        $this->tabs_gui->activateSubTab(self::TAB_ADDITIONAL_SETTINGS);
                        $this->editAdditional();
                        break;
                    case self::CMD_SAVE_ADD_SETTINGS:
                        $this->checkPermission('write');
                        $this->tabs_gui->activateTab(self::TAB_SETTINGS);
                        $this->tabs_gui->activateSubTab(self::TAB_ADDITIONAL_SETTINGS);
                        $this->saveAdditional();

                        break;
                    case self::CMD_REPORT:

                    default:
                        throw new \Exception('no such command: ' . $cmd);
                }
        }
        $this->addHeaderAction();
    }

    public function edit(): void
    {
        $form = $this->initPropertiesForm();
        $this->tpl->setContent($this->ui_renderer->render($form));
    }

    public function save(): void
    {
        $form = $this->initPropertiesForm()->withRequest($this->request);
        $data = $form->getData();

        if ($data === null) {
            $this->tpl->setOnScreenMessage('failure', $this->lng->txt("form_input_not_valid"), true);
        } else {
            list($title_and_desc, $online) = $data;
            $this->object->getObjectProperties()->storePropertyTitleAndDescription($title_and_desc);
            $this->object->getObjectProperties()->storePropertyIsOnline($online);

            $this->tpl->setOnScreenMessage('success', $this->lng->txt("msg_obj_modified"), true);
        }
        $this->tpl->setContent($this->ui_renderer->render($form));
    }


    public function editAdditional(?ilPropertyFormGUI $form = null): void
    {
        if ($form === null) {
            $form = $this->initAdditionalSettingsForm();
        }
        $this->tpl->setContent($form->getHTML());
    }

    public function saveAdditional(): void
    {
        $form = $this->initAdditionalSettingsForm();
        $form->setValuesByPost();
        if ($form->checkInput()) {
            ilObjectServiceSettingsGUI::updateServiceSettingsForm(
                $this->object->getId(),
                $form,
                [
                    ilObjectServiceSettingsGUI::ORGU_POSITION_ACCESS
                ]
            );
            $this->tpl->setOnScreenMessage("success", $this->lng->txt('msg_obj_modified'), true);
        }
        $this->editAdditional($form);
    }

    protected function initPropertiesForm(): Form
    {
        $shift = $this->refinery->custom()->transformation(
            fn($v) => array_shift($v)
        );

        $title_and_description = $this->object->getObjectProperties()->getPropertyTitleAndDescription()->toForm(
            $this->lng,
            $this->ui_factory->input()->field(),
            $this->refinery
        );

        $online = $this->object->getObjectProperties()->getPropertyIsOnline()->toForm(
            $this->lng,
            $this->ui_factory->input()->field(),
            $this->refinery
        );

        $settings = $this->ui_factory->input()->field()->section(
            [$title_and_description],
            $this->lng->txt('iarp_settings')
        )->withAdditionalTransformation(
            $shift
        );

        $availability = $this->ui_factory->input()->field()->section(
            [$online],
            $this->lng->txt('iarp_settings_availability')
        )->withAdditionalTransformation(
            $shift
        );

        return $this->ui_factory->input()->container()->form()->standard(
            $this->ctrl->getLinkTargetByClass(self::class, self::CMD_SAVE),
            [$settings, $availability]
        );
    }

    protected function initAdditionalSettingsForm(): ilPropertyFormGUI
    {
        $form = new ilPropertyFormGUI();
        $form->setFormAction($this->ctrl->getFormAction($this));
        $form->setTitle($this->txt('obj_features'));
        $form->addCommandButton(self::CMD_SAVE_ADD_SETTINGS, $this->txt('save'));
        $form->addCommandButton(self::CMD_EDIT_ADD_SETTINGS, $this->txt('cancel'));

        ilObjectServiceSettingsGUI::initServiceSettingsForm(
            $this->object->getId(),
            $form,
            [
                ilObjectServiceSettingsGUI::ORGU_POSITION_ACCESS
            ]
        );
        return $form;
    }

    protected function getTabs(): void
    {
        $access = $this->object->getDic()['access'];
        $this->tabs_gui->addTab(
            self::TAB_INFO,
            $this->txt('info_short'),
            $this->ctrl->getLinkTargetByClass('ilinfoscreengui', 'showSummary'),
        );
        if ($access->mayEdit()) {
            $this->tabs_gui->addTab(
                self::TAB_SETTINGS,
                $this->txt('settings'),
                $this->ctrl->getLinkTarget($this, self::CMD_EDIT)
            );
        }

        //        if ($access->mayEdit()) {
        $this->tabs_gui->addTab(
            self::TAB_REPORT,
            $this->txt(self::TAB_REPORT),
            $this->ctrl->getLinkTargetByClass('iarpreportgui', IARPReportGUI::CMD_VIEW),
        );
        //        }

        if ($access->mayEditPermissions()) {
            $this->tabs_gui->addTab(
                self::TAB_PERMISSION,
                $this->txt('perm_settings'),
                $this->ctrl->getLinkTargetByClass('ilpermissiongui', 'perm'),
            );
        }
    }

    protected function getSubTabs(string $parent_tab): void
    {

        switch ($parent_tab) {
            case self::TAB_SETTINGS:
                $this->tabs_gui->addSubTab(
                    self::TAB_SETTINGS,
                    $this->lng->txt("settings"),
                    $this->ctrl->getLinkTarget($this, self::CMD_EDIT)
                );
                if ($this->permissions->isOrguAccessEnabledGlobally()) {
                    $this->tabs_gui->addSubTab(
                        self::TAB_ADDITIONAL_SETTINGS,
                        $this->lng->txt("obj_features"),
                        $this->ctrl->getLinkTarget($this, self::CMD_EDIT_ADD_SETTINGS)
                    );
                }
        }
    }


    public function handleAccessViolation(): void
    {
        $this->error_object->raiseError($this->txt("msg_no_perm_read"), $this->error_object->WARNING);
    }

    public static function _goto(string $a_target, string $a_add = ''): void
    {
        global $DIC;
        $a_target = (int) $a_target;
        if ($DIC['ilAccess']->checkAccess('write', '', $a_target)) {
            ilObjectGUI::_gotoRepositoryNode($a_target, 'edit');
        }
        if ($DIC['ilAccess']->checkAccess('read', '', $a_target)) {
            ilObjectGUI::_gotoRepositoryNode($a_target);
        }
    }

    protected function addLocatorItems(): void
    {
        if (is_object($this->object)) {
            $this->locator->addItem(
                $this->object->getTitle(),
                $this->ctrl->getLinkTarget($this, self::CMD_VIEW),
                "",
                $this->object->getRefId()
            );
        }
    }

    public function viewObject(): void
    {
        $this->tabs_gui->activateTab(self::TAB_INFO);
        $this->ctrl->setCmd('showSummary');
        $this->ctrl->setCmdClass('ilinfoscreengui');
        $info = $this->buildInfoScreen();
        $this->ctrl->forwardCommand($info);
        $this->recordIndividualAssessmentRead();
    }

    public function membersObject(): void
    {
        $this->tabs_gui->activateTab(self::TAB_MEMBERS);
        $gui = $this->object->getMembersGUI();
        $this->ctrl->forwardCommand($gui);
    }

    protected function getLinkTarget(string $cmd): string
    {
        if ($cmd == 'settings') {
            return $this->ctrl->getLinkTargetByClass('ilindividualassessmentsettingsgui', 'edit');
        }
        if ($cmd == 'info') {
            return $this->ctrl->getLinkTarget($this, self::CMD_VIEW);
        }
        if ($cmd == 'members') {
            return $this->ctrl->getLinkTargetByClass('ilindividualassessmentmembersgui', 'view');
        }
        return $this->ctrl->getLinkTarget($this, $cmd);
    }

    public function XeditObject(): void
    {
        $link = $this->getLinkTarget('settings');
        $this->ctrl->redirectToURL($link);
    }

    public function getBaseEditForm(): ilPropertyFormGUI
    {
        return $this->initEditForm();
    }

    protected function afterSave(ilObject $new_object): void
    {
        $this->tpl->setOnScreenMessage("success", $this->txt("iarp_added"), true);
        $this->ctrl->setParameter($this, "ref_id", $new_object->getRefId());
    }

    private function addToNavigationHistory(): void
    {
        if (!$this->getCreationMode()) {
            $ref_id = $this->request_wrapper->retrieve("ref_id", $this->refinery->kindlyTo()->int());
            $link = ilLink::_getLink($ref_id, ilObjIndividualAssessmentReport::OBJ_TYPE);
            $this->navigation_history->addItem($ref_id, $link, ilObjIndividualAssessmentReport::OBJ_TYPE);
        }
    }

    protected function txt(string $code): string
    {
        return $this->lng->txt($code);
    }
}
