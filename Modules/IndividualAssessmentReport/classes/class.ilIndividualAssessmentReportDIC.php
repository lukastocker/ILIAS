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


use Pimple\Container;
use ILIAS\Data\Factory as DataFactory;
use ILIAS\UI\URLBuilder;
use ILIAS\ResourceStorage\Stakeholder\ResourceStakeholder;

trait ilIndividualAssessmentReportDIC
{
    public function getObjectDIC(
        ilObjIndividualAssessmentReport $object,
        ArrayAccess $DIC
    ): Container {
        $container = new Container();

        if (! $object->getRefId()) {
            throw new \LogicException('no ref');
        }

        $container['iass.member.custom_storage'] = static fn($c): SpecifiedFormStorage =>
        new SpecifiedFormStorageDB($DIC['ilDB']);

        $container['ilIndividualAssessmentPrimitiveInternalNotificator'] = function () {
            return new ilIndividualAssessmentPrimitiveInternalNotificator();
        };

        $container['irss.stakeholder'] = static fn($c): ResourceStakeholder =>
        new ilIndividualAssessmentGradingStakeholder(
            $object->getId(),
            $DIC['ilUser']->getId()
        );

        $container['ilIndividualAssessmentMemberGUI'] = function ($c) use ($object, $DIC) {
            return new ilIndividualAssessmentMemberGUI(
                $DIC['ilCtrl'],
                $DIC['lng'],
                $DIC['tpl'],
                $DIC['ilUser'],
                $DIC['ui.factory']->input(),
                $DIC['ui.factory']->messageBox(),
                $DIC['ui.factory']->button(),
                $DIC['ui.factory']->link(),
                $DIC['refinery'],
                $c['DataFactory'],
                $DIC['ui.renderer'],
                $DIC['http']->request(),
                $c['ilIndividualAssessmentPrimitiveInternalNotificator'],
                $DIC["ilToolbar"],
                new ilObjIndividualAssessment(),
                $DIC['ilErr'],
                $DIC->refinery(),
                $DIC->http()->wrapper()->query(),
                $c['helper.dateformat'],
                $DIC['resource_storage'],
                $stakeholder = $c['irss.stakeholder'],
                $c['iafp.fieldbuilder']
            );
        };

        $container['iafp.fieldbuilder'] = static fn(): ILIAS\IndividualAssessmentFormPool\FieldBuilder =>
        new ILIAS\IndividualAssessmentFormPool\FieldBuilder(
            $DIC['ui.factory']->input()->field(),
            $DIC['refinery'],
            $DIC['lng'],
            new \ilUIDemoFileUploadHandlerGUI(),
            new \ilUIMarkdownPreviewGUI()
        );

        $container['gui.datatable.report'] = static fn($c): IARPReportDataTableGUI =>
        new IARPReportDataTableGUI(
            $DIC['ilCtrl'],
            $c['repo.results'],
            $DIC['ui.factory'],
            $DIC['ui.renderer'],
            $DIC['http']->request(),
            $DIC['refinery'],
            $DIC['http']->wrapper()->query(),
            $c['DataFactory'],
            $DIC['tpl'],
            $c['access'],
            $c['iass.valuerenderer'],
            $DIC['ilTabs'],
            $DIC['ilUser'],
            $DIC['lng'],
            $c['helper.dateformat'],
            $c['ilIndividualAssessmentMemberGUI'],
            $object->getSettings()->isGlobal() ? -1 : $c['parent_ref_id'],
            $c['iass.member.custom_storage']->checkForAvailableFormFields($object->getId())
        );

        $container['gui.report'] = static fn($c): IARPReportGUI =>
            new IARPReportGUI(
                $c['access'],
                $DIC['tpl'],
                $DIC['ilCtrl'],
                $DIC['ui.factory'],
                $DIC['ui.renderer'],
                $c['DataFactory'],
                $DIC['refinery'],
                $DIC['http']->request(),
                $DIC['http']->wrapper()->query(),
                $DIC['lng'],
                $c['repo.results'],
                $DIC->uiService()->filter(),
                $DIC['resource_storage'],
                $c['iass.valuerenderer'],
                $DIC['ilUser'],
                $object->getSettings()->isGlobal() ? -1 : $c['parent_ref_id'],
                $DIC['ilTabs'],
                $c['gui.datatable.report']
            );

        $container['parent_ref_id'] = static fn($c): int =>
            (int) $DIC['tree']->getParentNodeData($object->getRefId())['child'];

        $container['repo.results'] = static fn($c): IARPResultsDB =>
            new IARPResultsDB(
                $DIC['ilDB'],
                new SpecifiedFormStorageDB($DIC['ilDB']),
            );

        $container['iass.valuerenderer'] = static fn($c): IASSCustomFieldValueRenderer =>
            new IASSCustomFieldValueRenderer(
                $DIC['ilUser'],
                $c['helper.dateformat'],
                $DIC['refinery'],
                $DIC->resourceStorage(),
                $DIC['ui.factory'],
                $DIC['ui.renderer'],
                $DIC['ilCtrl'],
            );

        $container['helper.dateformat'] = static fn($c): ilIndividualAssessmentDateFormatter =>
            new ilIndividualAssessmentDateFormatter(
                $c['DataFactory']
            );

        $container['DataFactory'] = static fn(): DataFactory => new DataFactory();

        $container['access'] = static fn(): IARPAccessHandler =>
            new IARPAccessHandler(
                $DIC['ilAccess'],
                $DIC['rbacreview'],
                ilOrgUnitGlobalSettings::getInstance(),
                $DIC['ilObjDataCache'],
                new ilOrgUnitPositionAccess($DIC['ilAccess']),
                new ilOrgUnitUserAssignmentDBRepository($DIC['ilDB']),
                $DIC['ilUser']->getId(),
                $object->getRefId()
            );

        $container['settings_repo'] = static fn(): IARPSettingsRepoDB => new IARPSettingsRepoDB($DIC['ilDB']);

        return $container;
    }
}
