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
use ILIAS\IndividualAssessmentFormPool\FormsStorageDB;
use ILIAS\IndividualAssessmentFormPool\Form;
use ILIAS\IndividualAssessmentFormPool\FormsDataRetrieval;
use ILIAS\IndividualAssessmentFormPool\FieldBuilder;

class IAFPFormsGUI
{
    public const CMD_LIST = 'list';
    public const CMD_CREATE = 'create';
    public const CMD_EDIT = 'edit';
    public const CMD_ADDFIELDS = 'add_fields';
    public const CMD_SAVE = 'save';
    public const CMD_DELETE = 'delete';
    public const CMD_DELETE_CONFIRMED = 'delete_confirmed';
    public const CMD_PREVIEW = 'preview';

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
        protected readonly FormsDataRetrieval $data_retrieval,
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
                        $list = $this->listForms();
                        if ($list !== '') {
                            $this->tpl->setContent($list);
                            break;
                        }
                        // no break
                    case self::CMD_CREATE:
                        $frm = $this->getEditForm($this->forms_repo->getNewForm($this->iafp_obj_id));
                        $this->tpl->setContent($this->ui_renderer->render($frm));
                        break;

                    case self::CMD_EDIT:
                        $form_id = array_shift($ids);
                        $ui_form = $this->getEditForm($this->forms_repo->getFormById($form_id));
                        $out = $this->getFieldSelection($form_id);
                        $out[] = $ui_form;

                        $this->tpl->setContent($this->ui_renderer->render($out));
                        break;

                    case self::CMD_SAVE:
                        $form_id = array_shift($ids);
                        $form = $form_id === -1 ?
                            $this->forms_repo->getNewForm($this->iafp_obj_id) : $this->forms_repo->getFormById($form_id);
                        $ui_form = $this->getEditForm($form);
                        $this->tpl->setContent($this->ui_renderer->render($this->save($ui_form)));
                        break;

                    case self::CMD_ADDFIELDS:
                        $form_id = array_shift($ids);
                        $this->addFields($form_id);
                        $url = $this->getUrlString(self::CMD_EDIT, $form_id);
                        $this->ctrl->redirectToURL($url);

                        // no break
                    case self::CMD_PREVIEW:
                        $form_id = array_shift($ids);
                        $form = $this->forms_repo->getFormById($form_id);

                        $fields = array_map(
                            fn($f) => $this->field_builder->build($f->getConfig()),
                            $form->getFields()
                        );

                        echo $this->ui_renderer->render(
                            $this->ui_factory->input()->field()->section(
                                $fields,
                                $this->lng->txt('preview')
                            )->withDisabled(true)
                        );
                        exit();

                    case self::CMD_DELETE:
                        echo $this->ui_renderer->renderAsync(
                            $this->getDeleteConfirmation($ids)
                        );
                        exit();
                    case self::CMD_DELETE_CONFIRMED:
                        $this->forms_repo->deleteFormsByIds($ids);
                        $this->tpl->setOnScreenMessage('success', $this->lng->txt('forms_deleted'), true);
                        $this->ctrl->redirect($this, self::CMD_LIST);

                        // no break
                    default:
                        throw new \Exception('no such command: ' . $cmd);
                }
        }

    }

    protected function getTableActions(): array
    {
        $f = $this->ui_factory->table()->action();
        $actions = [
            'edit' => $f->single(
                $this->txt('edit'),
                $this->url_builder->withParameter($this->action_token, self::CMD_EDIT),
                $this->rowid_token
            ),
            'preview' => $f->single(
                $this->txt('preview'),
                $this->url_builder->withParameter($this->action_token, self::CMD_PREVIEW),
                $this->rowid_token
            )->withAsync()
        ];
        if ($this->iafp_access->mayEdit()) {
            $actions['delete'] = $f->standard(
                $this->txt('delete'),
                $this->url_builder->withParameter($this->action_token, 'delete'),
                $this->rowid_token
            )->withAsync();
        }
        return $actions;
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

    protected function listForms(): string
    {
        $out = [];
        if ($this->iafp_access->mayEdit()) {
            $out[] = $this->ui_factory->button()->primary(
                $this->txt('new_form'),
                $this->ctrl->getLinkTarget($this, self::CMD_CREATE)
            );
        }

        $out[] = $this->ui_factory->table()
            ->data(
                $this->txt('iafp_forms'),
                $this->data_retrieval->getColumns(),
                $this->data_retrieval
            )
            ->withId('iafp_forms_table_' . $this->iafp_obj_id)
            ->withActions($this->getTableActions())
            ->withRequest($this->request);

        return $this->ui_renderer->render($out);
    }

    protected function getEditForm(Form $form): UIForm
    {
        return $this->ui_factory->input()->container()->form()->standard(
            $this->getUrlString(self::CMD_SAVE, $form->getFormId()),
            [
                $form->toFormInput(
                    $this->ui_factory->input()->field(),
                    $this->ui_renderer,
                    $this->lng,
                    $this->refinery,
                    $this->iafp_obj_id,
                    $this->forms_repo,
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

    protected function getFieldSelection(int $form_id): array
    {
        $options = [];
        $available_fields = $this->forms_repo->getFieldsForObjId($this->iafp_obj_id);
        foreach ($available_fields as $field) {
            $options[$field->getFieldId()] = $field->getName();
        }

        $modal = $this->ui_factory->modal()->roundtrip(
            $this->lng->txt('add_fields'),
            null,
            [
                $this->ui_factory->input()->field()->multiselect(
                    $this->lng->txt('pick_fields'),
                    $options
                )
            ],
            $this->getUrlString(self::CMD_ADDFIELDS, $form_id)
        )
        ->withAdditionalTransformation(
            $this->refinery->custom()->transformation(
                fn($v) => array_shift($v)
            )
        );

        $button = $this->ui_factory->button()->primary(
            $this->lng->txt('add_fields'),
            $modal->getShowSignal()
        );
        return [$button, $modal];
    }


    protected function addFields(int $form_id): void
    {
        list($button, $modal) = $this->getFieldSelection($form_id);
        $data = $modal->withRequest($this->request)->getData();
        if ($data !== null) {
            $form = $this->forms_repo->getFormById($form_id);
            $fields = $form->getFields();
            $field_ids = array_map(fn($f) => $f->getFieldId(), $fields);
            foreach ($data as $field_id) {
                if (! in_array((int) $field_id, $field_ids)) {
                    $fields[] = $this->forms_repo->getFieldById((int) $field_id);
                }
            }
            $this->forms_repo->storeForm($form->withFields(...$fields));
            $this->tpl->setOnScreenMessage('success', $this->lng->txt('fields_added'), true);
        }
    }

    protected function save(UIForm $ui_form): UIForm
    {
        $ui_form = $ui_form->withRequest($this->request);
        $form = $ui_form->getData();
        if ($form === null) {
            return $ui_form;
        }
        $form_id = $this->forms_repo->storeForm($form);
        $this->tpl->setOnScreenMessage('success', $this->lng->txt('object_saved'), true);

        $url = $this->getUrlString(self::CMD_EDIT, $form_id);
        $this->ctrl->redirectToURL($url);
    }

    protected function getDeleteConfirmation(array $ids): MessageBox
    {
        $forms = [];
        foreach ($this->forms_repo->getFormsForObjId($this->iafp_obj_id) as $frm) {
            $forms[$frm->getFormId()] = $frm;
        };

        $msg = $this->lng->txt('confirm_delete')
        . $this->ui_renderer->render(
            $this->ui_factory->listing()->unordered(
                array_map(
                    fn($form_id) => $forms[$form_id]->getName(),
                    $ids
                )
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
                return $repo->getAllFormIdsForObjId($obj_id);
            }
            throw new \InvalidArgumentException('no ids');
        };
    }

    protected function txt(string $code): string
    {
        return $this->lng->txt($code);
    }
}
