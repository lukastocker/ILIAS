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
use ILIAS\Refinery\Factory as Refinery;
use ILIAS\UI\URLBuilder;
use ILIAS\UI\URLBuilderToken;
use Psr\Http\Message\ServerRequestInterface;
use ILIAS\HTTP\Wrapper\ArrayBasedRequestWrapper;
use ILIAS\UI\Implementation\Component\Input\Container\Form\Standard as UIForm;
use ILIAS\UI\Implementation\Component\MessageBox\MessageBox;
use ILIAS\UI\Implementation\Component\Modal\RoundTrip;
use ILIAS\IndividualAssessmentFormPool\FormsStorageDB;
use ILIAS\IndividualAssessmentFormPool\FieldType;
use ILIAS\IndividualAssessmentFormPool\Field;
use ILIAS\IndividualAssessmentFormPool\FieldsDataRetrieval;
use ILIAS\IndividualAssessmentFormPool\FieldBuilder;

/**
 *
 */
class IAFPFieldsGUI
{
    public const CMD_LIST = 'list';
    public const CMD_CREATE = 'create';
    public const CMD_EDIT = 'edit';
    public const CMD_SAVE = 'save';
    public const CMD_DELETE = 'delete';
    public const CMD_DELETE_CONFIRMED = 'delete_confirmed';

    protected URLBuilderToken $action_token;
    protected URLBuilderToken $rowid_token;

    public function __construct(
        protected readonly IAFPAccessHandler $iafp_access,
        protected readonly ilGlobalPageTemplate $tpl,
        protected readonly ilCtrl $ctrl,
        protected readonly UIFactory $ui_factory,
        protected readonly UIRenderer $ui_renderer,
        protected readonly Refinery $refinery,
        protected readonly ServerRequestInterface $request,
        protected readonly ArrayBasedRequestWrapper $query,
        protected readonly ilLanguage $lng,
        protected readonly FormsStorageDB $forms_repo,
        protected readonly FieldsDataRetrieval $data_retrieval,
        protected URLBuilder $url_builder,
        protected FieldBuilder $field_builder,
        protected readonly int $iafp_obj_id,
    ) {
        $namespace = ['iafpf', (string) $iafp_obj_id];
        list(
            $this->url_builder,
            $this->action_token,
            $this->rowid_token
        ) = $url_builder->acquireParameters($namespace, 'act', 'fid');
    }

    public function executeCommand(): void
    {
        $next_class = $this->ctrl->getNextClass($this);
        $cmd = $this->ctrl->getCmd() ?? self::CMD_VIEW;

        //$this->addToNavigationHistory();
        //$this->prepareOutput();

        switch ($next_class) {
            default:

                if ($this->query->has($this->action_token->getName())) {
                    $cmd = $this->query->retrieve(
                        $this->action_token->getName(),
                        $this->refinery->to()->string()
                    );
                    $ids = $this->query->retrieve(
                        $this->rowid_token->getName(),
                        $this->refinery->byTrying([
                            $this->refinery->to()->listOf($this->refinery->kindlyTo()->int()),
                            $this->refinery->custom()->transformation($this->getAllIds()),
                            $this->refinery->always([])
                        ])
                    );
                }

                switch ($cmd) {
                    case self::CMD_LIST:
                        $list = $this->listFields();
                        //if ($list !== '') {
                        $this->tpl->setContent($list);
                        break;
                        //}
                        //$this->ctrl->redirect($this, self:CMD_EDIT);
                    case self::CMD_CREATE:
                        $type = $this->getFieldCreationModal()->withRequest($this->request)->getData();
                        $field_id = $this->forms_repo->createField($type, $this->iafp_obj_id)->getFieldId();
                        $url = $this->getUrlString(self::CMD_EDIT, $field_id);
                        $this->ctrl->redirectToURL($url);
                        break;

                    case self::CMD_EDIT:
                        $field_id = array_shift($ids);
                        $frm = $this->getEditForm($this->forms_repo->getFieldById($field_id));
                        $this->tpl->setContent($this->ui_renderer->render($frm));
                        break;

                    case self::CMD_SAVE:
                        $field_id = array_shift($ids);
                        $field = $this->forms_repo->getFieldById($field_id);
                        $ui_form = $this->getEditForm($field);
                        $this->tpl->setContent($this->ui_renderer->render($this->save($ui_form)));
                        break;

                    case self::CMD_DELETE:
                        echo $this->ui_renderer->renderAsync(
                            $this->getDeleteConfirmation($ids)
                        );
                        exit();
                    case self::CMD_DELETE_CONFIRMED:
                        $used_ids = $this->forms_repo->getMappedFieldIds();
                        $ids = array_filter($ids, static fn(int $id): bool => !in_array($id, $used_ids));
                        $this->forms_repo->deleteFieldsByIds($ids);
                        $this->ctrl->redirect($this, self::CMD_LIST);

                        // no break
                    default:
                        throw new \Exception('no such command: ' . $cmd);
                }
        }
        //$this->addHeaderAction();
    }

    protected function getTableActions(): array
    {
        $f = $this->ui_factory->table()->action();
        return [
            'edit' => $f->single(
                $this->txt('edit'),
                $this->url_builder->withParameter($this->action_token, self::CMD_EDIT),
                $this->rowid_token
            ),
            'delete' => $f->standard(
                $this->txt('delete'),
                $this->url_builder->withParameter($this->action_token, 'delete'),
                $this->rowid_token
            )->withAsync(),
        ];
    }

    protected function getUrlString(string $cmd, int|array $row_ids): string
    {
        $row_ids = is_array($row_ids) ? $row_ids : [$row_ids];
        return $this->url_builder
            ->withParameter($this->action_token, $cmd)
            ->withParameter($this->rowid_token, $row_ids)
            ->buildURI()
            ->__toString();
    }


    protected function getFieldCreationModal(): RoundTrip
    {
        return $this->ui_factory->modal()->roundtrip(
            $this->txt('new_field'),
            null,
            [
                $this->ui_factory->input()->field()->select(
                    $this->lng->txt('field_type'),
                    FieldType::toArray(),
                    $this->lng->txt('field_type_byline')
                )
            ],
            $this->ctrl->getLinkTarget($this, self::CMD_CREATE)
        )->withAdditionalTransformation(
            $this->refinery->custom()->transformation(
                fn($v) => FieldType::from((int) array_shift($v))
            )
        );
    }

    protected function listFields(): string
    {
        $modal = $this->getFieldCreationModal();
        $new_entry = $this->ui_factory->button()->primary(
            $this->txt('new_field'),
            //$this->ctrl->getLinkTarget($this, self::CMD_CREATE)
            $modal->getShowSignal()
        );

        $table = $this->ui_factory->table()
            ->data(
                $this->txt('iafp_fields'),
                $this->data_retrieval->getColumns(),
                $this->data_retrieval
            )
            ->withId('iafp_fields_table_' . $this->iafp_obj_id)
            ->withActions($this->getTableActions())
            ->withRequest($this->request);

        return $this->ui_renderer->render([
            $modal,
            $new_entry,
            $table
        ]);
    }

    protected function getEditForm(Field $field): UIForm
    {
        return $this->ui_factory->input()->container()->form()->standard(
            $this->getUrlString(self::CMD_SAVE, $field->getFieldId()),
            [
                $field->toFormInput(
                    $this->ui_factory->input()->field(),
                    $this->lng,
                    $this->refinery,
                    $this->iafp_obj_id,
                    $this->field_builder
                )
            ]
        )
        ->withAdditionalTransformation(
            $this->refinery->custom()->transformation(
                fn($v) => array_shift($v)
            )
        );
    }

    protected function save(UIForm $ui_form): UIForm
    {
        $ui_form = $ui_form->withRequest($this->request);
        $field = $ui_form->getData();
        if ($field === null) {
            return $ui_form;
        }

        $field_id = $this->forms_repo->storeField($field)->getFieldId();
        $this->tpl->setOnScreenMessage('success', $this->lng->txt('object_saved'), true);

        $url = $this->getUrlString(self::CMD_EDIT, $field_id);
        $this->ctrl->redirectToURL($url);
    }


    protected function getDeleteConfirmation(array $ids): MessageBox
    {
        $msg = '';

        $used_ids = $this->forms_repo->getMappedFieldIds();
        $delete_ids = array_filter(
            $ids,
            static fn(int $id): bool => !in_array($id, $used_ids)
        );
        $nodelete_ids = array_diff($ids, $delete_ids);
        if ($nodelete_ids !== []) {
            $msg .= 'Cannot delete these (used):'
                . $this->ui_renderer->render(
                    $this->ui_factory->listing()->unordered(
                        array_map('strval', $nodelete_ids)
                    )
                );
        }

        $msg .= '<br>Delete?'
        . $this->ui_renderer->render(
            $this->ui_factory->listing()->unordered(
                array_map('strval', $delete_ids)
            )
        );

        $buttons = [
            $this->ui_factory->button()->standard(
                $this->lng->txt('confirm_delete'),
                $this->getUrlString(self::CMD_DELETE_CONFIRMED, $ids)
            ),
            $this->ui_factory->button()->standard(
                $this->lng->txt('confirm_cancel'),
                $this->ctrl->getLinkTarget($this, self::CMD_LIST)
            ),
        ];
        return $this->ui_factory->messageBox()->confirmation($msg)
            ->withButtons($buttons);
    }

    protected function getAllIds(): Closure
    {
        $repo = $this->forms_repo;
        $obj_id = $this->iafp_obj_id;
        return function ($v) use ($repo, $obj_id): array {
            if (array_shift($v) === 'ALL_OBJECTS') {
                return $repo->getAllFieldIdsForObjId($obj_id);
            }
            throw new \InvalidArgumentException('no ids');
        };
    }

    protected function txt(string $code): string
    {
        return $this->lng->txt($code);
    }
}
