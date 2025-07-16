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

class ilIndividualAssessmentMembersDataTableGUI implements DataTableInterface\DataRetrieval
{
    private const TABLE_TYPE = "iass_type";
    private const FIELD_ID = "iass_id";

    public const CMD_VIEW = "view";

    private const STD_FIELD_FULL_NAME = "name";
    private const STD_FIELD_LP = "learning_progress";
    private const STD_FIELD_STATUS = "status";
    private const STD_FIELD_DATETIME = "datetime";
    private const STD_CHANGED_BY = "changed_by";
    private const STD_CHANGED_DT = "changed_dt";
    private const STD_GRADED_BY = "graded_by";

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
        DataFactory $data_factory,
        public ilGlobalTemplateInterface $tpl,
        protected ilIndividualAssessmentDateFormatter $date_formatter,
        protected bool $custom_form_fields_available
    ) {
        $here_uri = $data_factory->uri($this->request->getUri()->__toString());
        $url_builder = new URLBuilder($here_uri);
        $namespace = ['iassdtmembers'];
        list($url_builder, $action_token, $id_token) =
            $url_builder->acquireParameters(
                $namespace,
                "table_action",
                "member_td_ids"
            );
        $this->url_builder = $url_builder;
        $this->action_token = $action_token;
        $this->id_token = $id_token;
    }

    public function executeCommand(): void
    {
        $cmd = $this->ctrl->getCmd();

        switch ($cmd) {
            case self::CMD_VIEW:
                $this->view();
                break;
            default:
                $cmd = self::CMD_VIEW;
                break;
        }
    }

    public function view(): void
    {
        $table = $this->getTable();
        $actions = [
            'edit' => $this->ui_factory->table()->action()->single(
                $this->lng->txt('edit'),
                $this->url_builder->withParameter($this->action_token, 'edit'),
                $this->id_token
            ),
            'delete' => $this->ui_factory->table()->action()->single(
                $this->lng->txt('delete'),
                $this->url_builder->withParameter($this->action_token, 'delete'),
                $this->id_token
            )
        ];
        $table->withActions($actions);
        $this->tpl->setContent(
            $this->ui_renderer->render($table->withRequest($this->request))
        );
    }

    public function getTable(): DataTable\Data
    {
        return $this->ui_factory->table()->data(
            $this->lng->txt('participants'),
            $this->getColumns(),
            $this
        );
    }

    public function getColumns(): array
    {
        $column = $this->ui_factory->table()->column();
        $columns = [
            self::STD_FIELD_FULL_NAME => $column->text($this->lng->txt('name'))->withIsSortable(false),
            self::STD_FIELD_LP => $column->text($this->lng->txt('learning_progress'))->withIsSortable(true),
            self::STD_FIELD_STATUS => $column->boolean(
                $this->lng->txt('status'),
                $this->lng->txt('finalized'),
                $this->lng->txt('not_finalized')
            )->withIsSortable(true),
            self::STD_GRADED_BY => $column->text($this->lng->txt('graded_by_field'))->withIsSortable(true),
            self::STD_CHANGED_BY => $column->text($this->lng->txt('changed_by_field'))->withIsSortable(true),
            self::STD_CHANGED_DT => $column->text($this->lng->txt('changed_dt_field'))->withIsSortable(true)
        ];

        if ($this->custom_form_fields_available) {
            $columns = array_merge($columns, [
                self::C_FIELD_DATETIME => $column->text($this->lng->txt('datetime'))->withIsSortable(true),
                self::C_FIELD_SELECT => $column->text($this->lng->txt('select'))->withIsSortable(true)
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
            // general form fields
            $datetime = $record->changeTime();
            if ($datetime !== null) {
                $datetime = $this->date_formatter->format($this->user, $record->changeTime());
            }
            $rec = [
                self::STD_FIELD_FULL_NAME => $record->name(),
                self::STD_FIELD_LP => $record->LPStatus(),
                self::STD_FIELD_STATUS => $record->finalized(),
                self::STD_GRADED_BY => ilObjUser::_lookupFullname((int) $record->examinerId()) ?? null,
                self::STD_CHANGED_BY => ilObjUser::_lookupFullname((int) $record->changerId()) ?? null,
                self::STD_CHANGED_DT => $datetime
            ];

            // fields from standard or custom form
            $custom_fields = $record->getGrading()->getCustomFields();
            if ($custom_fields === []) {
                $datetime = $this->renderDateTime($record->eventTime());
                $rec = array_merge($rec, [self::STD_FIELD_DATETIME => $datetime]);
            } else {
                foreach ($custom_fields as $custom_field) {
                    $type = $custom_field->getConfig()->getType();
                    if ($type === \ILIAS\IndividualAssessmentFormPool\FieldType::DATETIME) {
                        $datetime = $custom_field->getValue() ?? $custom_field->getConfig()->getDefaultValue();
                        var_dump($datetime);
                        if ($datetime !== null) {
                            $datetime = $this->renderDateTime($datetime);
                        }
                        $rec = array_merge($rec, [
                            self::C_FIELD_DATETIME => $datetime
                        ]);
                    }
                    if ($type === \ILIAS\IndividualAssessmentFormPool\FieldType::SINGLESELECT) {
                        $rec = array_merge($rec, [
                            self::C_FIELD_SELECT => $custom_field->getValue() ?? $custom_field->getConfig()->getDefaultValue()
                        ]);
                    }
                }
            }

            yield $row_builder->buildDataRow($row_id, $rec)
                ->withDisabledAction('edit')
                ->withDisabledAction('delete');
        }
    }

    public function renderDateTime(?string $datetime = null): string
    {
        if ($datetime === null) {
            return '-';
        }
        $datetime = \DateTimeImmutable::createFromFormat('U', $datetime);

        return $this->date_formatter->format($this->user, $datetime);
    }

    public function getTotalRowCount(?array $filter_data, ?array $additional_parameters): ?int
    {
        return $this->object->getRecordsCountForObjId($this->object->getId());
    }
}
