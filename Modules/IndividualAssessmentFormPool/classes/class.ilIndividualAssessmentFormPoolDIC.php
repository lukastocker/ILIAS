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
use ILIAS\IndividualAssessmentFormPool\FormsStorageDB;
use ILIAS\IndividualAssessmentFormPool\FormsDataRetrieval;
use ILIAS\IndividualAssessmentFormPool\FieldsDataRetrieval;
use ILIAS\IndividualAssessmentFormPool\FieldBuilder;

trait ilIndividualAssessmentFormPoolDIC
{
    public static function getGeneralDIC(
        ArrayAccess $DIC
    ): Container {
        $container = new Container();

        $container['urlbuilder'] = static fn($c): URLBuilder =>
            new URLBuilder(
                (new DataFactory())->uri($DIC['http']->request()->getUri()->__toString())
            );

        $container['fieldbuilder'] = static fn(): FieldBuilder =>
            new FieldBuilder(
                $DIC['ui.factory']->input()->field(),
                $DIC['refinery'],
                $DIC['lng'],
                new \ilUIDemoFileUploadHandlerGUI(),
                new \ilUIMarkdownPreviewGUI()
            );

        $container['repo.forms'] = static fn(): FormsStorageDB =>
            new FormsStorageDB(
                $DIC['ilDB'],
                $DIC['ilAccess']
            );
        return $container;
    }

    public function getObjectDIC(
        ilObjIndividualAssessmentFormPool $object,
        ArrayAccess $DIC
    ): Container {
        $container = new Container();
        $general_dic = self::getGeneralDIC($DIC);

        $container['repo.forms'] = static fn(): FormsStorageDB => $general_dic['repo.forms'];
        $container['urlbuilder'] = static fn($c): URLBuilder => $general_dic['urlbuilder'];
        $container['fieldbuilder'] = static fn($c): FieldBuilder => $general_dic['fieldbuilder'];

        if (! $object->getRefId()) {
            throw new \LogicException('no ref');
        }
        $container['gui.forms'] = static fn($c): IAFPFormsGUI =>
            new IAFPFormsGUI(
                $c['access'],
                $DIC['tpl'],
                $DIC['ilCtrl'],
                $DIC['ui.factory'],
                $DIC['ui.renderer'],
                $DIC['refinery'],
                $DIC['http']->request(),
                $DIC['http']->wrapper()->query(),
                $DIC['lng'],
                $c['repo.forms'],
                $c['dataretrieval.forms'],
                $c['urlbuilder'],
                $c['fieldbuilder'],
                $object->getId(),
                $DIC['ilTabs']
            );

        $container['gui.fields'] = static fn($c): IAFPFieldsGUI =>
            new IAFPFieldsGUI(
                $c['access'],
                $DIC['tpl'],
                $DIC['ilCtrl'],
                $DIC['ui.factory'],
                $DIC['ui.renderer'],
                $DIC['refinery'],
                $DIC['http']->request(),
                $DIC['http']->wrapper()->query(),
                $DIC['lng'],
                $c['repo.forms'],
                $c['dataretrieval.fields'],
                $c['urlbuilder'],
                $c['fieldbuilder'],
                $object->getId(),
                $DIC['ilTabs']
            );

        $container['access'] = static fn(): IAFPAccessHandler =>
            new IAFPAccessHandler(
                $DIC['ilAccess'],
                $DIC['rbacreview'],
                $DIC['ilUser']->getId(),
                $object->getRefId()
            );

        $container['dataretrieval.forms'] = static fn($c): FormsDataRetrieval =>
            new FormsDataRetrieval(
                $c['access'],
                $c['repo.forms'],
                $DIC['ui.factory'],
                $DIC['lng'],
                $object->getId(),
            );
        $container['dataretrieval.fields'] = static fn($c): FieldsDataRetrieval =>
            new FieldsDataRetrieval(
                $c['repo.forms'],
                $DIC['ui.factory'],
                $DIC['lng'],
                $object->getId(),
            );

        return $container;
    }
}
