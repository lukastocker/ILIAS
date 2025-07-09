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

use ILIAS\UI\Component\Table as DataTableInterface;
use ILIAS\UI\Implementation\Component\Table as DataTable;
use ILIAS\UI\Factory as UIFactory;
use ILIAS\UI\Renderer;
use ILIAS\UI\URLBuilder;
use Psr\Http\Message\ServerRequestInterface;
use ILIAS\Data\Factory as DataFactory;
use ILIAS\Data\Range;
use ILIAS\Data\Order;
use ILIAS\UI\URLBuilderToken;
use ILIAS\IndividualAssessmentFormPool\FieldType;
use ILIAS\FileUpload\Handler\AbstractCtrlAwareUploadHandler;
use ILIAS\IndividualAssessmentFormPool\FieldBuilder;
use ILIAS\UI\Component\Input\Container\Form\FormInput;

/**
 * @ilCtrl_Calls ilIndividualAssessmentMembersDataTableGUI: ilRepositorySearchGUI
 */
class ilIndividualAssessmentMembersDataTableGUI implements DataTableInterface\DataRetrieval
{
    private const TABLE_TYPE = "iass_type";
    private const FIELD_ID = "iass_id";

    public const CMD_VIEW = "view";
    private const ACTION_DELETE = "delete";
    private const ACTION_EDIT = "edit";
    private const ACTION_MULTI_DELETE = "multiDelete";
    private const ACTION_VIEW_DETAILS = "view_details";

    private const STD_FIELD_FULL_NAME = "user_firstname";
    private const STD_FIELD_LOGIN = "user_login";
    private const STD_FIELD_LP = "learning_progress";
    private const STD_FIELD_STATUS = "finalized";
    private const STD_FIELD_DATETIME = "datetime";
    private const STD_CHANGED_BY = "changer_login";
    private const STD_CHANGED_DT = "change_time";
    private const STD_GRADED_BY = "examiner_login";

    private const C_FIELD_DATETIME = "datetime";
    private const C_FIELD_SELECT = "singleselect";

    protected URLBuilder $url_builder;
    protected URLBuilderToken $action_token;
    protected URLBuilderToken $id_token;

    public function __construct(
        protected ilObjIndividualAssessment $object,
        protected ilCtrl $ctrl,
        protected IndividualAssessmentAccessHandler $iass_access,
        protected \ilDBInterface $db,
        protected ilObjUser $user,
        protected \ilLanguage $lng,
        protected UIFactory $ui_factory,
        protected Renderer $ui_renderer,
        protected ServerRequestInterface $request,
        protected ILIAS\Refinery\Factory $refinery,
        protected \ILIAS\HTTP\Wrapper\RequestWrapper $request_wrapper,
        protected DataFactory $data_factory,
        public ilGlobalTemplateInterface $tpl,
        protected ilIndividualAssessmentDateFormatter $date_formatter,
        protected bool $custom_form_fields_available,
        protected ilRbacReview $rbac_review,
        protected ilIndividualAssessmentMemberGUI $member_gui,
        protected ilTabsGUI $tabs,
        protected ilToolbarGUI $toolbar,
        protected FieldBuilder $field_builder
    ) {
        $this->data_factory = $data_factory;
        $here_uri = $this->data_factory->uri($this->request->getUri()->__toString());
        $url_builder = new URLBuilder($here_uri);
        $namespace = ['iass_members_datatable'];
        list($url_builder, $action_token, $id_token) =
            $url_builder->acquireParameters(
                $namespace,
                "table_action",
                "ids"
            );
        $this->url_builder = $url_builder;
        $this->action_token = $action_token;
        $this->id_token = $id_token;

        $this->tabs = $tabs;
        $this->toolbar = $this->toolbar;
    }

    public function executeCommand(): void
    {
        $cmd = $this->getCommandFromQueryToken('view');
        switch ($cmd) {
            case "ilrepositorysearchgui":
                $rep_search = new ilRepositorySearchGUI();
                $rep_search->setCallback($this, "addUsersFromSearch");
                $rep_search->addUserAccessFilterCallable(
                    function ($a_user_ids) {
                        return $a_user_ids;
                    }
                );
                $this->ctrl->forwardCommand($rep_search);
                break;
            case self::CMD_VIEW:
                $this->view();
                break;
            case self::ACTION_EDIT:
                $this->edit($this->getIdFromQueryToken());
                break;
            case self::ACTION_DELETE:
                $this->removeUserConfirmation();
                break;
            case 'removeUser':
                $this->removeUser();
                break;
            case self::ACTION_MULTI_DELETE:
                $this->removeUsersConfirmation();
                break;
            case 'removeUsers':
                $this->removeUsers();
                break;
            case self::ACTION_VIEW_DETAILS:
                $section = $this->viewDetails($this->getIdFromQueryToken());
                echo $this->ui_renderer->render($section);
                exit();
            default:
                $cmd = self::CMD_VIEW;
                break;
        }
    }

    protected function getCommandFromQueryToken(string $default): string
    {
        if ($this->request_wrapper->has($this->action_token->getName())) {
            return $this->request_wrapper->retrieve($this->action_token->getName(), $this->refinery->to()->string());
        }
        return $this->ctrl->getCmd();
    }

    public function view(): void
    {
        if ($this->iass_access->mayEditMembers()) {
            $search_params = ['crs', 'grp'];
            $container_id = $this->object->getParentContainerIdByType($this->object->getRefId(), $search_params);
            if ($container_id !== 0) {
                ilRepositorySearchGUI::fillAutoCompleteToolbar(
                    $this,
                    $this->toolbar,
                    array(
                        'auto_complete_name' => $this->lng->txt('user'),
                        'submit_name' => $this->lng->txt('add'),
                        'add_search' => true,
                        'add_from_container' => $container_id
                    )
                );
            } else {
                ilRepositorySearchGUI::fillAutoCompleteToolbar(
                    $this,
                    $this->toolbar,
                    array(
                        'auto_complete_name' => $this->lng->txt('user'),
                        'submit_name' => $this->lng->txt('add'),
                        'add_search' => true
                    )
                );
            }
        }
        $table = $this->getTable();
        $this->tpl->setContent(
            $this->ui_renderer->render($table)
        );
    }

    protected function viewDetails(int $id): FormInput
    {
        $this->ctrl->setParameterByClass('ilIndividualAssessmentMemberGUI', 'usr_id', $id);
        return $this->member_gui->getMember()->getGrading()->toFormInput(
            $this->ui_factory->input()->field(),
            $this->data_factory,
            $this->lng,
            $this->refinery,
            $this->member_gui,
            $this->user->getDateFormat(),
            $this->field_builder,
            $this->member_gui->getPossibleLPStates(),
            $this->member_gui->userMayPublish(),
            false,
            $this->object->getSettings()->isEventTimePlaceRequired(),
            $this->object->getSettings()->isFileRequired(),
            false,
            $this->member_gui->isManualGradingActive()
        )
        ->withDisabled(true);
    }

    public function getTable(): DataTable\Data
    {
        return $this->ui_factory->table()->data(
            $this->lng->txt('il_iass_member'),
            $this->getColumns(),
            $this
        )
            ->withActions($this->getActions())
            ->withRequest($this->request);
    }

    protected function getAdditionalViewControls(): array
    {
        return [];
    }

    protected function getActions(): array
    {
        return [
            'edit' => $this->ui_factory->table()->action()->single(
                $this->lng->txt(self::ACTION_EDIT),
                $this->url_builder->withParameter($this->action_token, self::ACTION_EDIT),
                $this->id_token
            ),
            'delete' => $this->ui_factory->table()->action()->single(
                $this->lng->txt(self::ACTION_DELETE),
                $this->url_builder->withParameter($this->action_token, self::ACTION_DELETE),
                $this->id_token
            ),
            'viewDetails' => $this->ui_factory->table()->action()->single(
                $this->lng->txt(self::ACTION_VIEW_DETAILS),
                $this->url_builder->withParameter($this->action_token, self::ACTION_VIEW_DETAILS),
                $this->id_token
            )->withAsync(),
            'multiDelete' => $this->ui_factory->table()->action()->multi(
                $this->lng->txt(self::ACTION_DELETE),
                $this->url_builder->withParameter($this->action_token, self::ACTION_MULTI_DELETE),
                $this->id_token
            )
        ];
    }

    public function getColumns(): array
    {
        $column = $this->ui_factory->table()->column();
        $columns = [
            self::STD_FIELD_FULL_NAME => $column->text($this->lng->txt('name'))->withIsSortable(true),
            self::STD_FIELD_LOGIN => $column->text($this->lng->txt('login'))->withIsSortable(true),
            self::STD_FIELD_LP => $column->text($this->lng->txt('learning_progress'))->withIsSortable(true),
            self::STD_FIELD_STATUS => $column->boolean(
                $this->lng->txt('status'),
                $this->lng->txt('finalized'),
                $this->lng->txt('not_finalized')
            )->withIsSortable(true),
            self::STD_GRADED_BY => $column->text($this->lng->txt('iass_graded_by'))->withIsSortable(true),
            self::STD_CHANGED_BY => $column->text($this->lng->txt('iass_changed_by'))->withIsSortable(true),
            self::STD_CHANGED_DT => $column->text($this->lng->txt('iass_changed'))->withIsSortable(true)
        ];

        if ($this->custom_form_fields_available) {
            $columns = array_merge($columns, [
                self::C_FIELD_SELECT => $column->text($this->lng->txt('iass_singleselect'))->withIsSortable(false),
                self::C_FIELD_DATETIME => $column->text($this->lng->txt('iass_datetime'))->withIsSortable(false)
            ]);
        } else {
            $columns = array_merge($columns, [
                self::STD_FIELD_DATETIME => $column->text($this->lng->txt('datetime'))->withIsSortable(true)
            ]);
        }

        return $columns;
    }

    public function getRows(
        DataTableInterface\DataRowBuilder $row_builder,
        array $visible_column_ids,
        Range $range,
        Order $order,
        ?array $filter_data,
        ?array $additional_parameters
    ): \Generator {
        $records = $this->object->getRecords($range, $order);
        foreach ($records as $record) {
            $row_id = (string) $record->id();
            $member_ids_with_records = $this->object->getIdsForMembersWithRecords($this->object);
            $roles = $this->object->getSettings()->getParticipantRoles();
            $member_ids_without_records = [];
            if ($roles != []) {
                foreach (unserialize($roles[0]) as $role_id) {
                    $member_ids_without_records = array_merge($member_ids_without_records, $this->rbac_review->assignedUsers((int) $role_id));
                }

                $member_ids_with_records = array_diff($member_ids_without_records, $member_ids_with_records);

            }

            // general form fields
            $id_in_array = in_array($record->id(), $member_ids_with_records);
            $fullname = $record->firstname() . ' ' . $record->lastname();
            $login = $record->login();
            $lp_status = $this->getEntryForStatus($record->LPStatus());
            $finalized = $record->finalized();
            $examiner_name = ilObjUser::_lookupFullname((int) $record->examinerId()) ?? null;
            $changer_name = ilObjUser::_lookupFullname((int) $record->changerId()) ?? null;
            $datetime = $record->changeTime();
            if ($datetime !== null) {
                $datetime = $this->date_formatter->format($this->user, $record->changeTime());
            }

            if ($id_in_array) {
                $lp_status = null;
                $finalized = false;
            }

            $rec = [
                self::STD_FIELD_FULL_NAME => $fullname,
                self::STD_FIELD_LOGIN => $login,
                self::STD_FIELD_LP => $lp_status,
                self::STD_FIELD_STATUS => $finalized,
                self::STD_GRADED_BY => $examiner_name,
                self::STD_CHANGED_BY => $changer_name,
                self::STD_CHANGED_DT => $datetime
            ];

            // fields from standard or custom form
            $custom_fields = $record->getGrading()->getCustomFields();
            if ($custom_fields === []) {
                $datetime = $record->eventTime()->format('Y-m-d H:i:s');
                $rec = array_merge($rec, [self::STD_FIELD_DATETIME => $datetime]);
            } else {
                foreach ($custom_fields as $custom_field) {
                    $type = $custom_field->getConfig()->getType();
                    if ($type === \ILIAS\IndividualAssessmentFormPool\FieldType::DATETIME) {
                        $datetime = $custom_field->getValue() ?? $custom_field->getConfig()->getDefaultValue();
                        if ($id_in_array) {
                            $datetime = null;
                        }
                        if ($datetime !== null) {
                            $datetime = $this->renderDateTime($datetime);
                        }
                        $rec = array_merge($rec, [
                            self::C_FIELD_DATETIME => $datetime
                        ]);
                    }
                    if ($type === \ILIAS\IndividualAssessmentFormPool\FieldType::SINGLESELECT) {
                        $singleselect = $custom_field->getValue() ?? $custom_field->getConfig()->getDefaultValue();
                        if ($id_in_array) {
                            $singleselect = null;
                        }

                        $rec = array_merge($rec, [
                            self::C_FIELD_SELECT => $singleselect
                        ]);
                    }
                }
            }

            yield $row_builder->buildDataRow($row_id, $rec);
        }
    }

    protected function getEntryForStatus(int $a_status): string
    {
        switch ($a_status) {
            case ilLPStatus::LP_STATUS_NOT_ATTEMPTED_NUM:
                return $this->lng->txt(ilLPStatus::LP_STATUS_NOT_ATTEMPTED);
            case ilLPStatus::LP_STATUS_IN_PROGRESS_NUM:
                return $this->lng->txt(ilLPStatus::LP_STATUS_IN_PROGRESS);
            case ilLPStatus::LP_STATUS_COMPLETED_NUM:
                return $this->lng->txt(ilLPStatus::LP_STATUS_COMPLETED);
            case ilLPStatus::LP_STATUS_FAILED_NUM:
                return $this->lng->txt(ilLPStatus::LP_STATUS_FAILED);
            default:
                throw new ilIndividualAssessmentException("Invalid status: " . $a_status);
        }
    }

    public function renderDateTime(?string $datetime = null): string
    {
        if ($datetime === null) {
            return '-';
        }
        $datetime = \DateTimeImmutable::createFromFormat('U', $datetime);

        return $datetime->format('Y-m-d H:i:s');
    }

    public function getTotalRowCount(?array $filter_data, ?array $additional_parameters): ?int
    {
        return $this->object->getRecordsCountForObjId($this->object->getId(), $this->object);
    }

    protected function removeUserConfirmation(): void
    {
        if (!$this->iass_access->mayEditMembers()) {
            $this->tpl->setOnScreenMessage("success", $this->lng->txt("msg_no_perm_read"), true);
            $this->ctrl->redirect($this, self::CMD_VIEW);
        }
        $usr_id = $this->getIdFromQueryToken();
        $message = $this->lng->txt('iass_remove_user_qst');

        $this->ctrl->setParameterByClass(self::class, 'usr_id', $usr_id);
        $message = $this->lng->txt('iass_remove_user_qst');

        $this->ctrl->setParameterByClass(self::class, 'usr_id', $usr_id);
        $remove = $this->ctrl->getFormAction($this, 'removeUser');
        $cancel = $this->ctrl->getFormAction($this, 'view');
        $this->ctrl->clearParameterByClass(self::class, 'usr_id');

        $buttons = [
            $this->ui_factory->button()->standard($this->lng->txt('remove'), $remove),
            $this->ui_factory->button()->standard($this->lng->txt('cancel'), $cancel)
        ];
        $message_box = $this->ui_factory->messageBox()->confirmation($message)->withButtons($buttons);
        $this->tpl->setContent($this->ui_renderer->render($message_box));
    }

    protected function removeUsersConfirmation(): void
    {
        if (!$this->iass_access->mayEditMembers()) {
            $this->tpl->setOnScreenMessage("success", $this->lng->txt("msg_no_perm_read"), true);
            $this->ctrl->redirect($this, self::CMD_VIEW);
        }
        $usr_ids = $this->getIdsForBulkAction();
        if ($usr_ids === null) {
            $usr_ids = $this->getIdsFromQueryToken();
        }
        $message = $this->lng->txt('iass_remove_user_qst');

        $this->ctrl->setParameterByClass(self::class, 'usr_id', implode(',', $usr_ids));
        $message = $this->lng->txt('iass_remove_user_qst');

        $this->ctrl->setParameterByClass(self::class, 'usr_id', implode(',', $usr_ids));
        $remove = $this->ctrl->getFormAction($this, 'removeUsers');
        $cancel = $this->ctrl->getFormAction($this, 'view');
        $this->ctrl->clearParameterByClass(self::class, 'usr_id');

        $buttons = [
            $this->ui_factory->button()->standard($this->lng->txt('remove'), $remove),
            $this->ui_factory->button()->standard($this->lng->txt('cancel'), $cancel)
        ];
        $message_box = $this->ui_factory->messageBox()->confirmation($message)->withButtons($buttons);
        $this->tpl->setContent($this->ui_renderer->render($message_box));
    }

    public function removeUser(): void
    {
        if (!$this->iass_access->mayEditMembers()) {
            $this->tpl->setOnScreenMessage("success", $this->lng->txt("msg_no_perm_read"), true);
            $this->ctrl->redirect($this, self::CMD_VIEW);
        }

        $usr_id = $this->request_wrapper->retrieve("usr_id", $this->refinery->kindlyTo()->int());
        $usr_id = $this->getMembersWithRecords([$usr_id]);
        $iass = $this->object;
        $iass->loadMembers()
             ->withoutPresentUser(new ilObjUser($usr_id[0]))
             ->updateStorageAndRBAC($iass->membersStorage(), $iass->accessHandler());
        ilIndividualAssessmentLPInterface::updateLPStatusByIds($iass->getId(), $usr_id);
        $this->tpl->setOnScreenMessage("success", $this->lng->txt("iass_user_removed"), true);
        $this->ctrl->redirect($this, self::CMD_VIEW);
    }

    protected function removeUsers(): void
    {
        if (!$this->iass_access->mayEditMembers()) {
            $this->tpl->setOnScreenMessage("success", $this->lng->txt("msg_no_perm_read"), true);
            $this->ctrl->redirect($this, self::CMD_VIEW);
        }

        $usr_ids = $this->request_wrapper->retrieve(
            "usr_id",
            $this->refinery->custom()->transformation(
                static fn($ids): array => explode(',', $ids)
            )
        );
        $roles = $this->object->getSettings()->getParticipantRoles();
        $user_without_records = [];
        foreach (unserialize($roles[0]) as $role_id) {
            $user_without_records = array_merge($user_without_records, $this->rbac_review->assignedUsers((int) $role_id));
        }

        foreach ($usr_ids as $usr_id) {
            $iass = $this->object;
            $iass->loadMembers()
                ->withoutPresentUser(new ilObjUser((int) $usr_id))
                ->updateStorageAndRBAC($iass->membersStorage(), $iass->accessHandler());
            ilIndividualAssessmentLPInterface::updateLPStatusByIds($iass->getId(), [(int) $usr_id]);
        }
        $this->tpl->setOnScreenMessage("success", $this->lng->txt("iass_user_removed"), true);
        $this->ctrl->redirect($this, self::CMD_VIEW);
    }

    protected function edit(int $id): void
    {
        $this->ctrl->setParameterByClass(
            ilIndividualAssessmentMemberGUI::class,
            'usr_id',
            $id
        );

        $this->ctrl->redirectToURL(
            $this->ctrl->getLinkTargetByClass(
                ilIndividualAssessmentMemberGUI::class,
                ilIndividualAssessmentMemberGUI::CMD_EDIT
            )
        );
    }

    protected function getIdsFromQueryToken(): array
    {
        if ($this->request_wrapper->has($this->action_token->getName())) {
            $ids = $this->request_wrapper->retrieve(
                $this->id_token->getName(),
                $this->refinery->custom()->transformation(function ($ids) {
                    if (is_array($ids)) {
                        return $ids;
                    }
                    return explode(',', $ids);
                })
            );
            return $ids;
        }

        throw new \Exception('No ids found in query.');
    }

    protected function getIdFromQueryToken(): int
    {
        if ($this->request_wrapper->has($this->id_token->getName())) {
            $id = $this->request_wrapper->retrieve(
                $this->id_token->getName(),
                $this->refinery->byTrying([
                    $this->refinery->kindlyTo()->int(),
                    $this->refinery->custom()->transformation(static fn($v): int => (int) current($v)),
                ])
            );
            return $id;
        }
        throw new \Exception('No id found in query.');
    }

    protected function getIdsForBulkAction(): ?array
    {
        $ids = $this->request_wrapper->retrieve(
            $this->id_token->getName(),
            $this->refinery->custom()->transformation(function ($ids) {
                if (is_array($ids)) {
                    return $ids;
                }
                return explode(',', $ids);
            })
        );

        if ($ids[0] === 'ALL_OBJECTS') {
            return $this->getMembersWithRecords();
        }

        return null;
    }

    protected function getMembersWithRecords(?array $ids = null): array
    {
        $member_ids_with_records = $this->object->getIdsForMembersWithRecords($this->object);
        $member_ids_without_records = [];
        $roles = $this->object->getSettings()->getParticipantRoles();

        if ($ids != null) {
            $member_ids_with_records = $ids;
        }
        if ($roles === null || is_array($roles)) {
            return $member_ids_with_records;
        }

        foreach (unserialize($roles[0]) as $role_id) {
            $member_ids_without_records = array_merge($member_ids_without_records, $this->rbac_review->assignedUsers((int) $role_id));
        }

        return array_diff($member_ids_with_records, $member_ids_without_records);
    }

    /**
     * @param int[]
     */
    public function addUsersFromSearch(array $user_ids): void
    {
        if (!empty($user_ids)) {
            $this->addUsers($user_ids);
        }

        $this->tpl->setOnScreenMessage("info", $this->lng->txt("search_no_selection"), true);
        $this->ctrl->redirect($this, 'view');
    }

    /**
     * Add users to corresponding iass-object. To be used by repository search.
     *
     * @param	int|string[]	$user_ids
     */
    public function addUsers(array $user_ids): void
    {
        if (!$this->iass_access->mayEditMembers()) {
            $this->tpl->setOnScreenMessage("success", $this->lng->txt("msg_no_perm_read"), true);
            $this->ctrl->redirect($this, self::CMD_VIEW);
        }
        $iass = $this->object;
        $members = $iass->loadMembers();
        $failure = null;
        if (count($user_ids) === 0) {
            $failure = 1;
        }
        foreach ($user_ids as $user_id) {
            $user = new ilObjUser($user_id);
            if (!$members->userAllreadyMember($user)) {
                $members = $members->withAdditionalUser($user);
            } else {
                $failure = 1;
            }
        }
        $members->updateStorageAndRBAC($iass->membersStorage(), $iass->accessHandler());
        ilIndividualAssessmentLPInterface::updateLPStatusByIds($iass->getId(), $user_ids);
        $this->ctrl->setParameter($this, 'failure', $failure);
        $this->ctrl->redirect($this, 'addedUsers');
    }

    protected function addedUsers(): void
    {
        $r = $this->refinery;
        if ($this->request_wrapper->retrieve('failure', $r->byTrying([$r->kindlyTo()->bool(), $r->always(false)]))) {
            $this->tpl->setOnScreenMessage("failure", $this->lng->txt('iass_add_user_failure'));
        } else {
            $this->tpl->setOnScreenMessage("success", $this->lng->txt('iass_add_user_success'));
        }
        $this->view();
    }
}
