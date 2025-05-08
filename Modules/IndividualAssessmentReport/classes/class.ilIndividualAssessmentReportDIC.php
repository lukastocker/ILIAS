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
use ILIAS\IndividualAssessmentReport\FormsStorageDB;
use ILIAS\IndividualAssessmentReport\FormsDataRetrieval;
use ILIAS\IndividualAssessmentReport\FieldsDataRetrieval;
use ILIAS\IndividualAssessmentReport\FieldBuilder;

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
                $object->getSettings()->isGlobal() ? -1 : $c['parent_ref_id']
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
                $DIC['ilUser']->getId(),
                $object->getRefId()
            );

        return $container;
    }
}
