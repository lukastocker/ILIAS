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

/**
 * @ilCtrl_Calls IARPReportGUI: ilIndividualAssessmentMemberGUI
 */
class IARPReportGUI
{
    public const CMD_VIEW = 'view';
    protected const F_SORT = 'sort';
    protected const F_MODE = 'mode';

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
    ) {
        $this->lng->loadLanguageModule('trac');
        $this->lng->loadLanguageModule('iass');
    }

    public function executeCommand(): void
    {
        $next_class = $this->ctrl->getNextClass($this);
        $cmd = $this->ctrl->getCmd() ?? self::CMD_VIEW;

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

                        $this->ctrl->setParameter($this, self::F_SORT, $order->join('', fn($_, $a, $o) => implode(':', [$a, $o])));
                        $this->ctrl->setParameter($this, self::F_MODE, $mode);

                        $filter_data = $this->filter_service->getData($this->getFilters()) ?? [];

                        $this->tpl->setContent(
                            $this->report($order, $mode, $filter_data)
                        );
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

    protected function report(Order $order, int $mode, array $filter_data): string
    {
        $data = iterator_to_array($this->repo->getResults(
            $order,
            $mode,
            $filter_data
        ));
        return $this->ui_renderer->render([
            $this->getFilters(),
            $this->getTable($mode)->withData($data),
        ]);
    }

    protected function downloadCustomFile(string $resource_id): void
    {
        $resource_id = $this->irss->manage()->find($resource_id);
        $this->irss->consume()->download($resource_id)->run();
    }

    protected function getTable(int $mode): Presentation
    {
        $vcf = $this->ui_factory->viewControl();
        $target = $this->ctrl->getLinkTarget($this, self::CMD_VIEW);
        $mode_options = $this->getModeOptions($target);
        $view_controls = [
            $vcf->mode($mode_options, 'mode')->withActive(array_keys($mode_options)[$mode + 1]),
            $vcf->sortation($this->getSortOptions())
                ->withTargetURL($target, self::F_SORT),
        ];

        return $this->ui_factory->table()->presentation(
            $this->lng->txt('report'),
            $view_controls,
            function (
                PresentationRow $row,
                IARPResult $record,
                UIFactory $ui_factory,
                $environment
            ) {
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
                            $environment['iass.valuerenderer'],
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
                )
                /*->withAction(
                    $ui_factory->button()
                        ->standard($this->lng->txt('view_participant_record'), '')
                    //->withOnClick($modal->getShowSignal())
                )*/
                ;
            }
        )
        ->withEnvironment([
            'lng' => $this->lng,
            'iass.valuerenderer' => $this->value_renderer,
            'current_user' => $this->current_user,
        ]);
    }

    protected function getFilters(): Filter
    {
        $filters = [];
        $filters[] = $this->ui_factory->input()->field()->text($this->lng->txt('user'));
        $filters[] = $this->ui_factory->input()->field()->text($this->lng->txt('assessment'));

        $filter_action = $this->ctrl->getLinkTarget($this, self::CMD_VIEW);
        return $this->filter_service->standard(
            'some filter id',
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
}
