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

use ILIAS\UI\Component\Input\Container\Form\FormInput;
use ILIAS\UI\Component\Table as DataTableInterface;
use ILIAS\UI\URLBuilderToken;
use ILIAS\UI\URLBuilder;
use ILIAS\UI\Implementation\Component\Table as DataTable;
use Psr\Http\Message\ServerRequestInterface;
use ILIAS\UI\Factory as UIFactory;
use ILIAS\UI\Renderer;
use ILIAS\Data\Factory as DataFactory;
use ILIAS\Data\Range;
use ILIAS\Data\Order;

class IARPReportDataTableGUI implements DataTableInterface\DataRetrieval
{
    public const CMD_VIEW = 'view_dt';
    protected const ACTION_VIEW_DETAILS = 'viewDetails';

    private const STD_FIELD_FULL_NAME = "firstname";
    private const STD_FIELD_LOGIN = "login";
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
        protected ilCtrl $ctrl,
        protected IARPResultsDB $results_db,
        protected UIFactory $ui_factory,
        protected Renderer $ui_renderer,
        protected ServerRequestInterface $request,
        protected ILIAS\Refinery\Factory $refinery,
        protected ILIAS\HTTP\Wrapper\RequestWrapper $request_wrapper,
        protected DataFactory $data_factory,
        public ilGlobalTemplateInterface $tpl,
        protected IARPAccessHandler $iarp_access,
        protected IASSCustomFieldValueRenderer $value_renderer,
        protected ilTabsGUI $tabs_gui,
        protected ilObjUser $user,
        protected ilLanguage $lng,
        protected ilIndividualAssessmentDateFormatter $date_formatter,
        protected ilIndividualAssessmentMemberGUI $member_gui,
        protected readonly int $contained_in_ref_id, // -1 for 'all/global',
        protected bool $custom_form_fields_available
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

        $this->tabs_gui = $tabs_gui;

        $this->lng->loadLanguageModule('trac');
        $this->lng->loadLanguageModule('iass');
    }

    public function executeCommand(): void
    {
        $cmd = $this->ctrl->getCmd();
        switch ($cmd) {
            case self::CMD_VIEW:
                $this->view();
                break;
            case self::ACTION_VIEW_DETAILS:
                $section = $this->getSectionWithDetailInfos($this->getIdFromQueryToken());
                echo $this->ui_renderer->render($section);
                exit();
            default:
                $cmd = self::CMD_VIEW;
                break;
        }
    }

    protected function view(): void
    {
        $table = $this->getTable();
        $this->tpl->setContent(
            $this->ui_renderer->render($table)
        );
    }

    protected function getSectionWithDetailInfos(int $id): FormInput
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
        )->withDisabled(true);
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

    protected function getTable(): DataTable\Data
    {
        return $this->ui_factory->table()->data(
            $this->lng->txt('il_iass_member'),
            $this->getColumns(),
            $this
        )
            ->withActions($this->getActions())
            ->withRequest($this->request);
    }

    public function getRows(
        \ILIAS\UI\Component\Table\DataRowBuilder $row_builder,
        array $visible_column_ids,
        \ILIAS\Data\Range $range,
        \ILIAS\Data\Order $order,
        ?array $filter_data,
        ?array $additional_parameters
    ): Generator {
        $mode = -1;
        $usr_ids = [$this->user->getId()];
        if ($this->iarp_access->mayViewOthersByPosition()) {
            $usr_ids = array_merge(
                $usr_ids,
                $this->iarp_access->getUserIdsWhereCurrentUserHasAuthority()
            );
        }
        if ($this->iarp_access->mayViewOthersByRBAC()) {
            $usr_ids = [];
        }
        $total_entries = $this->results_db->countResults(
            array_unique($usr_ids),
            $mode,
            $filter_data,
            $this->contained_in_ref_id
        );
        $iass_id = "";
        $records = iterator_to_array($this->results_db->getResults($usr_ids, $mode, $filter_data, $this->contained_in_ref_id, $order, $range));
        foreach ($records as $record) {
            foreach ($record->getIds() as $ids) {
                list($iass_id, $field_id, $usr_id) = $ids;
            }
            $grading = $record->getIASSInfos();
            $user_info = $record->getUsrInfo();

            // general form fields
            $fullname = $user_info->getFirstname() . ' ' . $user_info->getLastname();
            $login = $user_info->getLogin();
            $lp_status = $this->getEntryForStatus($grading->getLPStatus());
            $examiner_name = '';
            $changer_name = '';
            $finalized = $grading->isFinalized();
            if ($grading->getExaminer() != null) {
                $examiner_name = $grading->getExaminer()->getLogin() ?? null;
            }
            if ($grading->getChangedBy() != null) {
                $changer_name = $grading->getChangedBy()->getLogin() ?? null;
            }
            $datetime = $grading->getLastChange();
            if ($datetime !== null) {
                $datetime = $this->date_formatter->format($this->user, $grading->getLastChange());
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
            $custom_fields = $record->getIASSInfos()->getCustomFields();
            if ($custom_fields === []) {
                $datetime = $grading->getEventTime()->format('Y-m-d H:i:s');
                $rec = array_merge($rec, [self::STD_FIELD_DATETIME => $datetime]);
            } else {
                foreach ($custom_fields as $custom_field) {
                    $type = $custom_field->getConfig()->getType();
                    if ($type === \ILIAS\IndividualAssessmentFormPool\FieldType::DATETIME) {
                        $datetime = $custom_field->getValue() ?? $custom_field->getConfig()->getDefaultValue();
                        if ($datetime !== null) {
                            $datetime = $this->renderDateTime($datetime);
                        }
                        $rec = array_merge($rec, [
                            self::C_FIELD_DATETIME => $datetime
                        ]);
                    }
                    if ($type === \ILIAS\IndividualAssessmentFormPool\FieldType::SINGLESELECT) {
                        $singleselect = $custom_field->getValue() ?? $custom_field->getConfig()->getDefaultValue();

                        $rec = array_merge($rec, [
                            self::C_FIELD_SELECT => $singleselect
                        ]);
                    }
                }
            }

            yield $row_builder->buildDataRow((string) $iass_id, $rec);
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
        return $this->results_db->countResults([], -1, $filter_data, $this->contained_in_ref_id);
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
            self::STD_GRADED_BY => $column->text($this->lng->txt('iass_graded_by'))->withIsSortable(false),
            self::STD_CHANGED_BY => $column->text($this->lng->txt('iass_changed_by'))->withIsSortable(false),
            self::STD_CHANGED_DT => $column->text($this->lng->txt('iass_changed'))->withIsSortable(true)
        ];
        if ($this->custom_form_fields_available) {
            $columns = array_merge($columns, [
                self::C_FIELD_SELECT => $column->text($this->lng->txt('iass_singleselect'))->withIsSortable(false),
                self::C_FIELD_DATETIME => $column->text($this->lng->txt('iass_datetime'))->withIsSortable(false)
            ]);
        } else {
            $columns = array_merge($columns, [
                self::STD_FIELD_DATETIME => $column->text($this->lng->txt('datetime'))->withIsSortable(false)
            ]);
        }

        return $columns;
    }

    protected function getActions(): array
    {
        return [
           'viewDetails' => $this->ui_factory->table()->action()->single(
               $this->lng->txt(self::ACTION_VIEW_DETAILS),
               $this->url_builder->withParameter($this->action_token, self::ACTION_VIEW_DETAILS),
               $this->id_token
           )->withAsync()
        ];
    }

    protected function getAdditionalViewControls(): array
    {
        return [];
    }
}
