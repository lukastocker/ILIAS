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

/**
 * Class ilExPeerReviewGUI
 *
 * @author Jörg Lützenkirchen <luetzenkirchen@leifos.com>
 * @author Alexander Killing <killing@leifos.de>
 * @ilCtrl_Calls ilExPeerReviewGUI: ilFileSystemGUI, ilRatingGUI, ilExSubmissionTextGUI, ilInfoScreenGUI
 */
class ilExPeerReviewGUI
{
    protected \ILIAS\Exercise\InternalGUIService $gui;
    protected ilCtrl $ctrl;
    protected ilTabsGUI $tabs_gui;
    protected ilLanguage $lng;
    protected ilGlobalPageTemplate $tpl;
    protected ilObjUser $user;
    protected ilExAssignment $ass;
    protected ?ilExSubmission $submission;
    protected int $requested_review_giver_id = 0;
    protected int $requested_review_peer_id = 0;
    protected string $requested_review_crit_id = "";
    protected int $requested_peer_id = 0;
    protected string $requested_crit_id = "";

    public function __construct(
        ilExAssignment $a_ass,
        ilExSubmission $a_submission = null
    ) {
        /** @var \ILIAS\DI\Container $DIC */
        global $DIC;

        $this->user = $DIC->user();
        $ilCtrl = $DIC->ctrl();
        $ilTabs = $DIC->tabs();
        $lng = $DIC->language();
        $tpl = $DIC["tpl"];

        $this->ass = $a_ass;
        $this->submission = $a_submission;

        // :TODO:
        $this->ctrl = $ilCtrl;
        $this->tabs_gui = $ilTabs;
        $this->lng = $lng;
        $this->tpl = $tpl;

        $request = $DIC->exercise()->internal()->gui()->request();
        $this->requested_review_giver_id = $request->getReviewGiverId();
        $this->requested_review_peer_id = $request->getReviewPeerId();
        $this->requested_review_crit_id = $request->getReviewCritId();
        $this->requested_peer_id = $request->getPeerId();
        $this->requested_crit_id = $request->getCritId();
        $this->gui = $DIC->exercise()->internal()->gui();
    }

    /**
     * @throws ilCtrlException
     */
    public function executeCommand(): void
    {
        $ilCtrl = $this->ctrl;
        $lng = $this->lng;
        $ilTabs = $this->tabs_gui;

        if (!$this->ass->getPeerReview()) {
            $this->returnToParentObject();
        }

        $class = $ilCtrl->getNextClass($this);
        $cmd = $ilCtrl->getCmd("showpeerreviewoverview");

        switch ($class) {
            case "ilfilesystemgui":
                $ilCtrl->saveParameter($this, array("fu"));

                // see self::downloadPeerReview()
                $giver_id = $this->requested_review_giver_id;
                $peer_id = $this->requested_review_peer_id;

                if (!$this->canGive()) {
                    $this->returnToParentObject();
                }

                $valid = false;
                $peer_items = $this->submission->getPeerReview()->getPeerReviewsByPeerId($peer_id, true);
                if (is_array($peer_items)) {
                    foreach ($peer_items as $item) {
                        if ($item["giver_id"] == $giver_id) {
                            $valid = true;
                        }
                    }
                }
                if (!$valid) {
                    $ilCtrl->redirect($this, "editPeerReview");
                }

                $ilTabs->clearTargets();
                $ilTabs->setBackTarget(
                    $lng->txt("back"),
                    $ilCtrl->getLinkTarget($this, "editPeerReview")
                );

                $fstorage = new ilFSStorageExercise($this->ass->getExerciseId(), $this->ass->getId());
                $fstorage->create();

                $fs_gui = new ilFileSystemGUI($fstorage->getPeerReviewUploadPath($peer_id, $giver_id));
                $fs_gui->setTableId("excfbpeer");
                $fs_gui->setAllowDirectories(false);
                $fs_gui->setTitle($this->ass->getTitle() . ": " .
                    $lng->txt("exc_peer_review") . " - " .
                    $lng->txt("exc_peer_review_give"));
                $this->ctrl->forwardCommand($fs_gui);
                break;

            case "ilratinggui":
                $peer_review = new ilExPeerReview($this->ass);
                $peer_review->updatePeerReviewTimestamp($this->requested_peer_id);

                $rating_gui = new ilRatingGUI();
                $rating_gui->setObject(
                    $this->ass->getId(),
                    "ass",
                    $this->requested_peer_id,
                    "peer"
                );
                $this->ctrl->forwardCommand($rating_gui);
                $ilCtrl->redirect($this, "editPeerReview");
                break;

            case "ilexsubmissiontextgui":
                $ilTabs->clearTargets();
                if (!$this->submission->isTutor()) {
                    $ilTabs->setBackTarget(
                        $lng->txt("back"),
                        $ilCtrl->getLinkTarget($this, "editPeerReview")
                    );
                    $this->ctrl->setReturn($this, "editPeerReview");
                } else {
                    $ilTabs->setBackTarget(
                        $lng->txt("back"),
                        $ilCtrl->getLinkTarget($this, "showGivenPeerReview")
                    );
                    $this->ctrl->setReturn($this, "showGivenPeerReview");
                }
                $gui = new ilExSubmissionTextGUI(new ilObjExercise($this->ass->getExerciseId(), false), $this->submission);
                $ilCtrl->forwardCommand($gui);
                break;

            default:
                $this->{$cmd . "Object"}();
                break;
        }
    }

    public function returnToParentObject(): void
    {
        $this->ctrl->returnToParent($this);
    }

    /**
     * @throws ilDateTimeException
     */
    public static function getOverviewContent(
        ilInfoScreenGUI $a_info,
        ilExSubmission $a_submission
    ): void {
        global $DIC;

        $lng = $DIC->language();
        $ilCtrl = $DIC->ctrl();
        $gui = $DIC->exercise()
            ->internal()
            ->gui();

        $state = ilExcAssMemberState::getInstanceByIds($a_submission->getAssignment()->getId(), $a_submission->getUserId());

        $ass = $a_submission->getAssignment();

        $view_pc = "";
        $edit_pc = "";


        //if($ass->afterDeadlineStrict() &&
        //	$ass->getPeerReview())
        if ($state->hasSubmissionEndedForAllUsers() &&
            $ass->getPeerReview()) {
            $ilCtrl->setParameterByClass("ilExPeerReviewGUI", "ass_id", $a_submission->getAssignment()->getId());

            $nr_missing_fb = $a_submission->getPeerReview()->getNumberOfMissingFeedbacksForReceived();

            // before deadline (if any)
            // if(!$ass->getPeerReviewDeadline() ||
            //  	$ass->getPeerReviewDeadline() > time())
            if ($state->isPeerReviewAllowed()) {
                $dl_info = "";
                if ($ass->getPeerReviewDeadline()) {
                    $dl_info = " (" . sprintf(
                        $lng->txt("exc_peer_review_deadline_info_button"),
                        $state->getPeerReviewDeadlinePresentation()
                    ) . ")";
                }

                $b = $gui->link(
                    $lng->txt("exc_peer_review_give"),
                    $ilCtrl->getLinkTargetByClass(array("ilExSubmissionGUI", "ilExPeerReviewGUI"), "editPeerReview")
                )->emphasised();
                if ($nr_missing_fb) {
                    $b = $b->primary();
                }
                $edit_pc = $b->render();
            } elseif ($ass->getPeerReviewDeadline()) {
                $edit_pc = $lng->txt("exc_peer_review_deadline_reached");
            }

            // after deadline (if any)
            if ((!$ass->getPeerReviewDeadline() ||
                $ass->getPeerReviewDeadline() < time())) {
                // given peer review should be accessible at all times (read-only when not editable - see above)
                if ($ass->getPeerReviewDeadline() &&
                    $a_submission->getPeerReview()->countGivenFeedback(false)) {
                    $b = $gui->link(
                        $lng->txt("exc_peer_review_given"),
                        $ilCtrl->getLinkTargetByClass(array("ilExSubmissionGUI", "ilExPeerReviewGUI"), "showGivenPeerReview")
                    )->emphasised();
                    $view_pc = $b->render() . " ";
                }

                // did give enough feedback
                if (!$nr_missing_fb) {
                    // received any?
                    $received = (bool) sizeof($a_submission->getPeerReview()->getPeerReviewsByPeerId($a_submission->getUserId(), true));
                    if ($received) {
                        $b = $gui->link(
                            $lng->txt("exc_peer_review_show"),
                            $ilCtrl->getLinkTargetByClass(array("ilExSubmissionGUI", "ilExPeerReviewGUI"), "showReceivedPeerReview")
                        )->emphasised();
                        $view_pc .= $b->render();
                    }
                    // received none
                    else {
                        $view_pc .= $lng->txt("exc_peer_review_show_received_none");
                    }
                }
                // did not give enough
                else {
                    $view_pc .= $lng->txt("exc_peer_review_show_missing");
                }
            }
            /* must give before showing received
            else
            {
                $view_pc = $lng->txt("exc_peer_review_show_not_rated_yet");
            }
            */

            $sep = ($edit_pc != "" && $view_pc != "")
                ? "<br><br>"
                : "";

            $a_info->addProperty($lng->txt("exc_peer_review"), $edit_pc . $sep . $view_pc);

            $ilCtrl->setParameterByClass("ilExPeerReviewGUI", "ass_id", "");
        }
    }

    protected function canGive(): bool
    {
        return ($this->submission->isOwner() &&
            $this->ass->afterDeadlineStrict() &&
            (!$this->ass->getPeerReviewDeadline() ||
                $this->ass->getPeerReviewDeadline() > time()));
    }

    protected function canView(): bool
    {
        return ($this->submission->isTutor() ||
            ($this->submission->isOwner() &&
            $this->ass->afterDeadlineStrict() &&
            (!$this->ass->getPeerReviewDeadline() ||
                $this->ass->getPeerReviewDeadline() < time())));
    }

    /**
     * @throws ilObjectNotFoundException
     * @throws ilCtrlException
     * @throws ilDatabaseException
     * @throws ilDateTimeException
     */
    public function showGivenPeerReviewObject(): void
    {
        $tpl = $this->tpl;
        $lng = $this->lng;

        if (!$this->canView()) {
            $this->returnToParentObject();
        }

        $peer_items = $this->submission->getPeerReview()->getPeerReviewsByGiver($this->submission->getUserId());
        if ($peer_items === []) {
            $this->tpl->setOnScreenMessage('failure', $this->lng->txt("exc_peer_review_no_peers"), true);
            $this->returnToParentObject();
        }

        $tpl->setTitle($this->ass->getTitle() . ": " . $lng->txt("exc_peer_review_given"));

        $info_widget = new ilInfoScreenGUI($this);

        $this->renderInfoWidget($info_widget, $peer_items);

        $tpl->setContent($info_widget->getHTML());
    }

    /**
     * @throws ilObjectNotFoundException
     * @throws ilCtrlException
     * @throws ilDatabaseException
     * @throws ilDateTimeException
     */
    public function showReceivedPeerReviewObject(): void
    {
        $ilCtrl = $this->ctrl;
        $tpl = $this->tpl;
        $lng = $this->lng;

        if (!$this->canView() ||
            (!$this->submission->isTutor() &&
            $this->submission->getPeerReview()->getNumberOfMissingFeedbacksForReceived())) {
            $this->returnToParentObject();
        }

        $this->tabs_gui->clearTargets();
        $this->tabs_gui->setBackTarget($this->lng->txt("back"), $this->ctrl->getLinkTarget($this, "returnToParent"));

        $peer_items = $this->submission->getPeerReview()->getPeerReviewsByPeerId($this->submission->getUserId(), !$this->submission->isTutor());
        if ($peer_items === []) {
            // #11373
            $this->tpl->setOnScreenMessage('failure', $this->lng->txt("exc_peer_review_no_peers_reviewed_yet"), true);
            $ilCtrl->redirect($this, "returnToParent");
        }

        $tpl->setTitle($this->ass->getTitle() . ": " . $lng->txt("exc_peer_review_show"));

        $info_widget = new ilInfoScreenGUI($this);

        $this->renderInfoWidget($info_widget, $peer_items, true);

        $tpl->setContent($info_widget->getHTML());
    }

    protected function renderInfoWidget(
        ilInfoScreenGUI $a_info_widget,
        array $a_peer_items,
        bool $a_by_peer = false
    ): void {
        $lng = $this->lng;

        if ($this->submission->isTutor()) {
            $user_title = $a_by_peer
                ? $lng->txt("exc_peer_review_recipient")
                : $lng->txt("exc_peer_review_giver");
            $a_info_widget->addSection($user_title);
            $a_info_widget->addProperty(
                $lng->txt("name"),
                ilUserUtil::getNamePresentation($this->submission->getUserId(), false, false, "", true)
            );
        }

        if ($a_by_peer) {
            // submission

            $a_info_widget->addSection($lng->txt("exc_submission"));

            $submission = new ilExSubmission($this->ass, $this->submission->getUserId());
            $file_info = $submission->getDownloadedFilesInfoForTableGUIS();

            $a_info_widget->addProperty(
                $file_info["last_submission"]["txt"],
                $file_info["last_submission"]["value"] .
                $this->getLateSubmissionInfo($submission)
            );

            $sub_data = $this->getSubmissionContent($submission);
            if ($sub_data === '' || $sub_data === '0') {
                $sub_data = '<a href="' . $file_info["files"]["download_url"] . '">' . $lng->txt("download") . '</a>';
            }
            $a_info_widget->addProperty($lng->txt("exc_submission"), $sub_data);
        }

        foreach ($a_peer_items as $peer) {
            if (!$a_by_peer) {
                $giver_id = $this->submission->getUserId();
                $peer_id = $peer["peer_id"];
                $id_title = $lng->txt("exc_peer_review_recipient");
                $user_id = $peer_id;
            } else {
                $giver_id = $peer["giver_id"];
                $peer_id = $this->submission->getUserId();
                $id_title = $lng->txt("exc_peer_review_giver");
                $user_id = $giver_id;
            }

            // peer info
            if ($this->submission->isTutor()) {
                $id_value = ilUserUtil::getNamePresentation($user_id, "", "", false, true);
            } elseif (!$this->ass->hasPeerReviewPersonalized()) {
                $id_value = $peer["seq"];
            } else {
                $id_value = ilUserUtil::getNamePresentation($user_id);
            }
            $a_info_widget->addSection($id_title . ": " . $id_value);


            // submission info

            if (!$a_by_peer) {
                $submission = new ilExSubmission($this->ass, $peer_id);
                $file_info = $submission->getDownloadedFilesInfoForTableGUIS();

                $a_info_widget->addProperty(
                    $file_info["last_submission"]["txt"],
                    $file_info["last_submission"]["value"] .
                    $this->getLateSubmissionInfo($submission)
                );

                $sub_data = $this->getSubmissionContent($submission);
                if ($sub_data === '' || $sub_data === '0') {
                    if (isset($file_info["files"]["download_url"])) {
                        $sub_data = '<a href="' . $file_info["files"]["download_url"] . '">' . $lng->txt("download") . '</a>';
                    }
                }
                $a_info_widget->addProperty($lng->txt("exc_submission"), $sub_data);
            }


            // peer review items

            $values = $this->submission->getPeerReview()->getPeerReviewValues($giver_id, $peer_id);

            foreach ($this->ass->getPeerReviewCriteriaCatalogueItems() as $item) {
                $crit_id = $item->getId()
                    ? $item->getId()
                    : $item->getType();

                $item->setPeerReviewContext(
                    $this->ass,
                    $giver_id,
                    $peer_id
                );

                $title = $item->getTitle();
                $html = $item->getHTML($values[$crit_id] ?? null);
                $a_info_widget->addProperty($title ?: "&nbsp;", $html ?: "&nbsp;");
            }
        }
    }

    protected function getLateSubmissionInfo(
        ilExSubmission $a_submission
    ): string {
        $lng = $this->lng;

        // #18966 - late files info
        foreach ($a_submission->getFiles() as $file) {
            if ($file["late"]) {
                return '<div class="warning">' . $lng->txt("exc_late_submission") . '</div>';
            }
        }
        return "";
    }

    /**
     * @throws ilDateTimeException
     */
    public function editPeerReviewObject(): void
    {
        $tpl = $this->tpl;

        if (!$this->canGive()) {
            $this->returnToParentObject();
        }

        $peer_items = $this->submission->getPeerReview()->getPeerReviewsByGiver($this->submission->getUserId());
        if ($peer_items === []) {
            $this->tpl->setOnScreenMessage('failure', $this->lng->txt("exc_peer_review_no_peers"), true);
            $this->returnToParentObject();
        }

        $missing = $this->submission->getPeerReview()->getNumberOfMissingFeedbacksForReceived();
        if ($missing !== 0) {
            $dl = $this->ass->getPeerReviewDeadline();
            if (!$dl || $dl < time()) {
                $this->tpl->setOnScreenMessage('info', sprintf($this->lng->txt("exc_peer_review_missing_info"), $missing));
            } else {
                $this->tpl->setOnScreenMessage('info', sprintf(
                    $this->lng->txt("exc_peer_review_missing_info_deadline"),
                    $missing,
                    ilDatePresentation::formatDate(new ilDateTime($dl, IL_CAL_UNIX))
                ));
            }
        }

        $tbl = new ilExAssignmentPeerReviewTableGUI(
            $this,
            "editPeerReview",
            $this->ass,
            $this->submission->getUserId(),
            $peer_items
        );
        $tpl->setContent($tbl->getHTML());
    }

    public function editPeerReviewItemObject(
        ilPropertyFormGUI $a_form = null
    ): void {
        $tpl = $this->tpl;

        if (!$this->canGive() ||
            !$this->isValidPeer($this->requested_peer_id)) {
            $this->returnToParentObject();
        }

        if ($a_form === null) {
            $a_form = $this->initPeerReviewItemForm($this->requested_peer_id);
        }

        $tpl->setContent($a_form->getHTML());
    }

    protected function isValidPeer(int $a_peer_id): bool
    {
        $peer_items = $this->submission->getPeerReview()->getPeerReviewsByGiver($this->submission->getUserId());
        foreach ($peer_items as $item) {
            if ($item["peer_id"] == $a_peer_id) {
                return true;
            }
        }
        return false;
    }

    protected function getSubmissionContent(
        ilExSubmission $a_submission
    ): string {
        if ($this->ass->getType() != ilExAssignment::TYPE_TEXT) {
            return "";
        }

        $text = $a_submission->getFiles();
        if ($text !== []) {
            $text = array_shift($text);
            if (trim($text["atext"]) !== '' && trim($text["atext"]) !== '0') {
                // mob id to mob src
                return nl2br(ilRTE::_replaceMediaObjectImageSrc($text["atext"], 1));
            }
        }
        return "";
    }

    /**
     * @throws ilDateTimeException
     */
    protected function initPeerReviewItemForm(
        int $a_peer_id
    ): ilPropertyFormGUI {
        $ilCtrl = $this->ctrl;
        $lng = $this->lng;

        // get peer data
        $peer_items = $this->submission->getPeerReview()->getPeerReviewsByGiver($this->submission->getUserId());
        $peer = [];
        foreach ($peer_items as $item) {
            if ($item["peer_id"] == $a_peer_id) {
                $peer = $item;
                break;
            }
        }

        $ilCtrl->saveParameter($this, "peer_id");

        $form = new ilPropertyFormGUI();
        $form->setFormAction($ilCtrl->getFormAction($this, "updatePeerReview"));

        $form->setTitle($this->ass->getTitle() . ": " . $lng->txt("exc_peer_review_give"));

        // peer info
        if (!$this->ass->hasPeerReviewPersonalized()) {
            $id_title = $lng->txt("id");
            $id_value = $peer["seq"];
        } else {
            $id_title = $lng->txt("exc_peer_review_recipient");
            $id_value = ilUserUtil::getNamePresentation($peer["peer_id"]);
        }
        $id = new ilNonEditableValueGUI($id_title);
        $id->setValue($id_value);
        $form->addItem($id);

        // submission info

        $submission = new ilExSubmission($this->ass, $peer["peer_id"]);
        $file_info = $submission->getDownloadedFilesInfoForTableGUIS();

        $last_sub = new ilNonEditableValueGUI($file_info["last_submission"]["txt"], "", true);
        $last_sub->setValue($file_info["last_submission"]["value"] .
            $this->getLateSubmissionInfo($submission));
        $form->addItem($last_sub);

        $sub_data = $this->getSubmissionContent($submission);
        if (($sub_data === '' || $sub_data === '0') && isset($file_info["files"]["download_url"])) {
            $sub_data = '<a href="' . $file_info["files"]["download_url"] . '">' . $lng->txt("download") . '</a>';
        }

        $sub = new ilNonEditableValueGUI($lng->txt("exc_submission"), "", true);
        $sub->setValue($sub_data);
        $form->addItem($sub);

        // peer review items

        $input = new ilFormSectionHeaderGUI();
        $input->setTitle($lng->txt("exc_peer_review"));
        $form->addItem($input);

        $values = $this->submission->getPeerReview()->getPeerReviewValues($this->submission->getUserId(), $a_peer_id);

        foreach ($this->ass->getPeerReviewCriteriaCatalogueItems() as $item) {
            $crit_id = $item->getId()
                ? $item->getId()
                : $item->getType();

            $item->setPeerReviewContext(
                $this->ass,
                $this->submission->getUserId(),
                $peer["peer_id"],
                $form
            );
            $item->addToPeerReviewForm($values[$crit_id] ?? null);
        }

        $form->addCommandButton("updatePeerReview", $lng->txt("save"));
        $form->addCommandButton("editPeerReview", $lng->txt("cancel"));

        return $form;
    }

    public function updateCritAjaxObject(): void
    {
        $ilCtrl = $this->ctrl;
        $ilUser = $this->user;
        $tpl = $this->tpl;

        if (!$this->canGive() ||
            !$this->requested_peer_id ||
            !$this->requested_crit_id ||
            !$ilCtrl->isAsynch()) {
            exit();
        }

        $peer_id = $this->requested_peer_id;
        $crit_id = $this->requested_crit_id;
        $giver_id = $ilUser->getId();

        if (!is_numeric($crit_id)) {
            $crit = ilExcCriteria::getInstanceByType($crit_id);
        } else {
            $crit = ilExcCriteria::getInstanceById($crit_id);
        }
        $crit->setPeerReviewContext($this->ass, $giver_id, $peer_id);
        $html = $crit->updateFromAjax();

        $this->handlePeerReviewChange();

        echo $html;
        echo $tpl->getOnLoadCodeForAsynch();
        exit();
    }

    public function updatePeerReviewObject(): void
    {
        $lng = $this->lng;
        $ilCtrl = $this->ctrl;

        if (!$this->canGive() ||
            !$this->isValidPeer($this->requested_peer_id)) {
            $this->returnToParentObject();
        }

        $peer_id = $this->requested_peer_id;

        $form = $this->initPeerReviewItemForm($peer_id);
        if ($form->checkInput()) {
            $valid = true;

            $values = array();
            foreach ($this->ass->getPeerReviewCriteriaCatalogueItems() as $item) {
                $item->setPeerReviewContext(
                    $this->ass,
                    $this->submission->getUserId(),
                    $peer_id,
                    $form
                );
                $value = $item->importFromPeerReviewForm();
                if ($value !== null) {
                    $crit_id = $item->getId()
                        ? $item->getId()
                        : $item->getType();
                    $values[$crit_id] = $value;
                }
                if (!$item->validate($value)) {
                    $valid = false;
                }
            }

            if ($valid) {
                $this->submission->getPeerReview()->updatePeerReview($peer_id, $values);

                $this->handlePeerReviewChange();

                $this->tpl->setOnScreenMessage('success', $this->lng->txt("exc_peer_review_updated"), true);
                $ilCtrl->redirect($this, "editPeerReview");
            } else {
                $this->tpl->setOnScreenMessage('failure', $lng->txt("form_input_not_valid"));
            }
        }

        $form->setValuesByPost();
        $this->editPeerReviewItemObject($form);
    }

    protected function handlePeerReviewChange(): void
    {
        // (in)valid peer reviews could change assignment status
        $exercise = new ilObjExercise($this->ass->getExerciseId(), false);
        $exercise->processExerciseStatus(
            $this->ass,
            $this->submission->getUserIds(),
            $this->submission->hasSubmitted(),
            $this->submission->validatePeerReviews()
        );
    }

    public function downloadPeerReviewObject(): void
    {
        $ilCtrl = $this->ctrl;

        if (!$this->canView() &&
            !$this->canGive()) {
            $this->returnToParentObject();
        }

        $giver_id = $this->requested_review_giver_id;
        $peer_id = $this->requested_review_peer_id;
        $crit_id = $this->requested_review_crit_id;

        if (!is_numeric($crit_id)) {
            $crit = ilExcCriteria::getInstanceByType($crit_id);
        } else {
            $crit = ilExcCriteria::getInstanceById($crit_id);
        }

        $crit->setPeerReviewContext($this->ass, $giver_id, $peer_id);
        $file = $crit->getFileByHash();
        if ($file) {
            ilFileDelivery::deliverFileLegacy($file, basename($file));
        }

        $ilCtrl->redirect($this, "returnToParent");
    }



    //
    // ADMIN
    //

    public function showPeerReviewOverviewObject(): void
    {
        $tpl = $this->tpl;

        if (!$this->ass ||
            !$this->ass->getPeerReview()) {
            $this->returnToParentObject();
        }

        $tbl = new ilExAssignmentPeerReviewOverviewTableGUI(
            $this,
            "showPeerReviewOverview",
            $this->ass
        );

        $panel = "";
        $panel_data = $tbl->getPanelInfo();
        if (is_array($panel_data) && count($panel_data) > 0) {
            $ptpl = new ilTemplate("tpl.exc_peer_review_overview_panel.html", true, true, "components/ILIAS/Exercise");
            foreach ($panel_data as $item) {
                $ptpl->setCurrentBlock("user_bl");
                foreach ($item["value"] as $user) {
                    $ptpl->setVariable("USER", $user);
                    $ptpl->parseCurrentBlock();
                }

                $ptpl->setCurrentBlock("item_bl");
                $ptpl->setVariable("TITLE", $item["title"]);
                $ptpl->parseCurrentBlock();
            }

            $f = $this->gui->ui()->factory();
            $r = $this->gui->ui()->renderer();
            $p = $f->panel()->standard(
                $this->lng->txt("exc_peer_review_overview_invalid_users"),
                $f->legacy($ptpl->get())
            );

            $panel = $r->render($p);
        }

        $tpl->setContent($tbl->getHTML() . $panel);
    }

    public function confirmResetPeerReviewObject(): void
    {
        $ilCtrl = $this->ctrl;
        $tpl = $this->tpl;
        $ilTabs = $this->tabs_gui;

        if (!$this->ass ||
            !$this->ass->getPeerReview()) {
            $this->returnToParentObject();
        }

        $ilTabs->clearTargets();

        $cgui = new ilConfirmationGUI();
        $cgui->setFormAction($ilCtrl->getFormAction($this));
        $cgui->setHeaderText(sprintf($this->lng->txt("exc_peer_review_reset_sure"), $this->ass->getTitle()));
        $cgui->setCancel($this->lng->txt("cancel"), "showPeerReviewOverview");
        $cgui->setConfirm($this->lng->txt("delete"), "resetPeerReview");

        $tpl->setContent($cgui->getHTML());
    }

    public function resetPeerReviewObject(): void
    {
        $ilCtrl = $this->ctrl;

        if (!$this->ass ||
            !$this->ass->getPeerReview()) {
            $this->returnToParentObject();
        }

        $peer_review = new ilExPeerReview($this->ass);
        $all_giver_ids = $peer_review->resetPeerReviews();

        if (is_array($all_giver_ids)) {
            // if peer review is valid for completion, we have to re-calculate all assignment members
            $exercise = new ilObjExercise($this->ass->getExerciseId(), false);
            if ($exercise->isCompletionBySubmissionEnabled() &&
                $this->ass->getPeerReviewValid() != ilExAssignment::PEER_REVIEW_VALID_NONE) {
                foreach ($all_giver_ids as $user_id) {
                    $submission = new ilExSubmission($this->ass, $user_id);
                    $pgui = new self($this->ass, $submission);
                    $pgui->handlePeerReviewChange();
                }
            }
        }

        $this->tpl->setOnScreenMessage('success', $this->lng->txt("exc_peer_review_reset_done"), true);
        $ilCtrl->redirect($this, "showPeerReviewOverview");
    }
}
