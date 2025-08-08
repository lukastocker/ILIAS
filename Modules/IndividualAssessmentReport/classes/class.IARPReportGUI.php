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

use ILIAS\UI\Factory as UIFactory;
use ILIAS\UI\Renderer as UIRenderer;
use ILIAS\Data\Factory as DataFactory;
use ILIAS\Data\Order;
use ILIAS\Refinery\Factory as Refinery;
use Psr\Http\Message\ServerRequestInterface;
use ILIAS\HTTP\Wrapper\ArrayBasedRequestWrapper;
use ILIAS\UI\Implementation\Component\Table\Presentation;
use ILIAS\UI\Implementation\Component\Table\PresentationRow;
use ILIAS\UI\Implementation\Component\Input\Container\Filter\Standard as Filter;
use ILIAS\ResourceStorage\Services as IRSS;
use ILIAS\Data\Range;
use ILIAS\UI\Implementation\Component\ViewControl\Pagination;

/**
 * @ilCtrl_Calls IARPReportGUI: ilIndividualAssessmentMemberGUI, IARPReportDataTableGUI
 */
class IARPReportGUI
{
    public const CMD_VIEW = 'view';
    public const CMD_VIEW_DT = 'view_datatable';
    protected const F_SORT = 'sort';
    protected const F_MODE = 'mode';
    protected const F_PAGE = 'page';

    public function __construct(
        protected readonly IARPAccessHandler $iafp_access,
        protected readonly ilGlobalPageTemplate $tpl,
        protected readonly ilCtrl $ctrl,
        protected readonly UIFactory $ui_factory,
        protected readonly UIRenderer $ui_renderer,
        protected readonly DataFactory $data_factory,
        protected readonly Refinery $refinery,
        protected readonly ServerRequestInterface $request,
        protected readonly ArrayBasedRequestWrapper $request_wrapper,
        protected readonly ilLanguage $lng,
        protected readonly IARPResultsDB $repo,
        protected readonly ilUIFilterService $filter_service,
        protected readonly IRSS $irss,
        protected readonly IASSCustomFieldValueRenderer $value_renderer,
        protected readonly ilObjUser $current_user,
        protected readonly int $contained_in_ref_id, // -1 for 'all/global'
        protected readonly ilTabsGUI $tab_gui,
        protected readonly IARPReportDataTableGUI $report_data_table_gui
    ) {
        $this->lng->loadLanguageModule('trac');
        $this->lng->loadLanguageModule('iass');
        $this->getSubTabs($this->tab_gui);
    }

    public function executeCommand(): void
    {
        $next_class = $this->ctrl->getNextClass($this);
        $cmd = $this->ctrl->getCmd();

        switch ($next_class) {
            default:
                switch ($cmd) {
                    case self::CMD_VIEW:
                        if (!$this->iafp_access->mayView()) {
                            $this->tpl->setOnScreenMessage('failure', $this->lng->txt('permission_denied'), true);
                            $this->ctrl->redirectByClass(ilObjIndividualAssessmentReportGUI::class, ilObjIndividualAssessmentReportGUI::CMD_VIEW);
                        }

                        $order = $this->data_factory->order(...explode(':', array_key_first($this->getSortOptions())));
                        if ($this->request_wrapper->has(self::F_SORT)) {
                            $sortval = $this->request_wrapper->retrieve(self::F_SORT, $this->refinery->kindlyTo()->string());
                            if (array_key_exists($sortval, $this->getSortOptions())) {
                                $order = $this->data_factory->order(...explode(':', $sortval));
                            }
                        }

                        $mode = -1;
                        if ($this->request_wrapper->has(self::F_MODE)) {
                            $mode = $this->request_wrapper->retrieve(self::F_MODE, $this->refinery->kindlyTo()->int());
                        }

                        $usr_ids = [$this->current_user->getId()];
                        if ($this->iafp_access->mayViewOthersByPosition()) {
                            $usr_ids = array_merge(
                                $usr_ids,
                                $this->iafp_access->getUserIdsWhereCurrentUserHasAuthority()
                            );
                        }
                        if ($this->iafp_access->mayViewOthersByRBAC()) {
                            $usr_ids = [];
                        }

                        $filter_data = $this->filter_service->getData($this->getFilters()) ?? [];

                        $page = 0;
                        $range = null;
                        $page_size = $this->getUsersHitsPerPage();
                        $total_entries = $this->repo->countResults(
                            array_unique($usr_ids),
                            $mode,
                            $filter_data,
                            $this->contained_in_ref_id,
                            $order
                        );

                        if ($total_entries > $page_size) {
                            $target = $this->ctrl->getLinkTarget($this, self::CMD_VIEW);
                            if ($this->request_wrapper->has(self::F_PAGE)) {
                                $page = $this->request_wrapper->retrieve(self::F_PAGE, $this->refinery->kindlyTo()->int());
                            }
                            $pagination = $this->getPagination($total_entries, $page_size, $page, $target);
                            $range = $this->data_factory->range($pagination->getRange()->getStart(), $pagination->getRange()->getLength());
                        }

                        $this->ctrl->setParameter($this, self::F_SORT, $order->join('', fn($_, $a, $o) => implode(':', [$a, $o])));
                        $this->ctrl->setParameter($this, self::F_MODE, $mode);
                        $this->ctrl->setParameter($this, self::F_PAGE, $page);

                        $this->tpl->setContent(
                            $this->report($mode, $filter_data, $usr_ids, $total_entries, $order, $range)
                        );
                        break;

                    case 'view_dt':
                        if (!$this->iafp_access->mayView()) {
                            $this->tpl->setOnScreenMessage('failure', $this->lng->txt('permission_denied'), true);
                            $this->ctrl->redirectByClass(ilObjIndividualAssessmentReportGUI::class, ilObjIndividualAssessmentReportGUI::CMD_VIEW);
                        }
                        $this->tab_gui->activateSubTab(self::CMD_VIEW_DT);
                        $this->ctrl->forwardCommand($this->report_data_table_gui);
                        break;

                    case ilIndividualAssessmentMemberGUI::CMD_DOWNLOAD_CUST_FILE:
                        $resource_id = $this->request_wrapper->retrieve(
                            ilIndividualAssessmentMemberGUI::F_CUST_FILE_RID,
                            $this->refinery->kindlyTo()->string()
                        );
                        $this->downloadCustomFile($resource_id);
                        break;

                    default:
                        throw new \Exception('no such command: ' . $cmd);
                }
        }
    }

    protected function report(
        int $mode,
        array $filter_data,
        array $usr_ids,
        int $total_entries,
        ?Order $order = null,
        ?Range $range = null
    ): string {
        $data = iterator_to_array(
            $this->repo->getResults(
                array_unique($usr_ids),
                $mode,
                $filter_data,
                $this->contained_in_ref_id,
                $order,
                $range
            )
        );

        return $this->ui_renderer->render([
            $this->getFilters(),
            $this->getTable($mode, $total_entries)->withData($data),
        ]);
    }

    protected function downloadCustomFile(string $resource_id): void
    {
        $resource_id = $this->irss->manage()->find($resource_id);
        $this->irss->consume()->download($resource_id)->run();
    }

    protected function getTable(int $mode, int $total_entries): Presentation
    {
        $vcf = $this->ui_factory->viewControl();
        $target = $this->ctrl->getLinkTarget($this, self::CMD_VIEW);
        $mode_options = $this->getModeOptions($target);
        $view_controls = [
            $vcf->mode($mode_options, 'mode')->withActive(array_keys($mode_options)[$mode + 1]),
            $vcf->sortation($this->getSortOptions())
                ->withTargetURL($target, self::F_SORT),
        ];

        $page_size = $this->getUsersHitsPerPage();
        if ($total_entries > $page_size) {
            $view_controls[] = $this->getPagination($total_entries, $page_size, 0, $target);
        }

        return $this->ui_factory->table()->presentation(
            $this->lng->txt('report'),
            $view_controls,
            function (
                PresentationRow $row,
                IARPResult $record,
                UIFactory $ui_factory,
                $environment
            ) {

                $record = $record->withPermissionFilter(
                    $environment['current_user']->getId(),
                    $environment['perm.view_lp'],
                    $environment['perm.view_full'],
                    $environment['perm.view_specific']
                );

                return $row
                ->withHeadline($record->getHeadline())
                ->withSubheadline($record->getSubHeadline())
                ->withImportantFields(
                    $record->getImportantInfos(
                        $environment['lng'],
                        $environment['current_user']->getDateFormat()
                    )
                )
                ->withContent(
                    $ui_factory->listing()->descriptive(
                        $record->getContent(
                            $environment['lng'],
                            $environment['iass.valuerenderer']
                        )
                    )
                )
                ->withFurtherFieldsHeadline(
                    $environment['lng']->txt('iass_further_field_headline')
                )
                ->withFurtherFields(
                    $record->getFurtherFields(
                        $environment['lng'],
                        $environment['current_user']->getDateFormat()
                    )
                );
            }
        )
        ->withEnvironment([
            'lng' => $this->lng,
            'iass.valuerenderer' => $this->value_renderer,
            'current_user' => $this->current_user,
            'perm.view_lp' => $this->iafp_access->mayViewOthersLP(),
            'perm.view_full' => $this->iafp_access->mayViewOthersFull(),
            'perm.view_specific' => $this->iafp_access->mayViewSpecificRecords()
        ]);
    }

    protected function getFilters(): Filter
    {
        $filters = [];
        $filters[] = $this->ui_factory->input()->field()->text($this->lng->txt('user'));
        $filters[] = $this->ui_factory->input()->field()->text($this->lng->txt('assessment'));

        $filter_action = $this->ctrl->getLinkTarget($this, self::CMD_VIEW);
        return $this->filter_service->standard(
            self::class . '_filter_' . $this->contained_in_ref_id,
            $filter_action,
            $filters,
            array_map(fn() => true, $filters),
            true,
            true
        );
    }

    protected function getSortOptions(): array
    {
        return [
            "change_time:" . Order::ASC => $this->lng->txt("iass_sort_changetime_asc"),
            "change_time:" . Order::DESC => $this->lng->txt("iass_sort_changetime_desc")
        ];
    }

    protected function getModeOptions(string $target): array
    {
        $target = $target . '&' . self::F_MODE . '=';
        return [
            $this->lng->txt("iass_filter_all") => $target . '-1',
            $this->lng->txt(ilLPStatus::LP_STATUS_NOT_ATTEMPTED) => $target . ilLPStatus::LP_STATUS_NOT_ATTEMPTED_NUM,
            $this->lng->txt(ilLPStatus::LP_STATUS_IN_PROGRESS) => $target . ilLPStatus::LP_STATUS_IN_PROGRESS_NUM,
            $this->lng->txt(ilLPStatus::LP_STATUS_COMPLETED) => $target . ilLPStatus::LP_STATUS_COMPLETED_NUM,
            $this->lng->txt(ilLPStatus::LP_STATUS_FAILED) => $target . ilLPStatus::LP_STATUS_FAILED_NUM,

        ];
    }

    public function getPagination(
        int $total_entries,
        int $page_size,
        int $current_page,
        string $target
    ): Pagination {
        if ($this->request_wrapper->has(self::F_PAGE)) {
            $current_page = $this->request_wrapper->retrieve(
                self::F_PAGE,
                $this->refinery->kindlyTo()->int()
            );
        }

        $pagination_url = $this->ctrl->getLinkTarget($this, self::CMD_VIEW);
        return $this->ui_factory->viewControl()
                                ->pagination()
                                ->withTargetURL($target, self::F_PAGE)
                                ->withTotalEntries($total_entries)
                                ->withPageSize($page_size)
                                ->withCurrentPage($current_page)
        ;
    }

    protected function getUsersHitsPerPage(): int
    {
        return (int) $this->current_user->getPref("hits_per_page");
    }

    protected function getSubTabs(ilTabsGUI $tabs_gui): void
    {
        $tabs_gui->addSubTab(
            self::CMD_VIEW,
            $this->lng->txt('presentation_table'),
            $this->ctrl->getLinkTarget($this, self::CMD_VIEW)
        );
        $tabs_gui->addSubTab(
            self::CMD_VIEW_DT,
            $this->lng->txt('data_table'),
            $this->ctrl->getLinkTargetByClass(
                [
                    self::class,
                    IARPReportDataTableGUI::class
                ],
                IARPReportDataTableGUI::CMD_VIEW
            ),
        );
    }
}
