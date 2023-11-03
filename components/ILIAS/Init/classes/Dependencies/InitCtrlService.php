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

use ILIAS\DI\Container;
use ILIAS\components\Help\ScreenId\HelpScreenIdObserver;

/**
 * Class InitCtrlService wraps the initialization of ilCtrl.
 * @author Thibeau Fuhrer <thf@studer-raimann.ch>
 * This class exists because due to dependency-injection the
 * initialization of ilCtrl got a little more complicated -
 * and is used on several occasions.
 */
final class InitCtrlService
{
    /**
     * Initializes the ilCtrl service.
     * This method EXPECTS that $GLOBALS['DIC'] is already initialized
     * with the http services and the refinery factory.
     * @param Container $dic
     * @throws ilCtrlException if the initialization fails.
     */
    public function init(Container $dic): void
    {
        $this->abortIfMissingDependencies($dic);
        $ilias_path = dirname(__FILE__, 6) . '/';

        try {
            $ctrl_structure = new ilCtrlStructure(
                require $ilias_path . ilCtrlStructureArtifactObjective::ARTIFACT_PATH,
                require $ilias_path . ilCtrlBaseClassArtifactObjective::ARTIFACT_PATH,
                require $ilias_path . ilCtrlSecurityArtifactObjective::ARTIFACT_PATH
            );
        } catch (Throwable $t) {
            throw new ilCtrlException(self::class . " could not require artifacts, try `composer du` first.");
        }

        $token_repository = new ilCtrlTokenRepository();
        $path_factory = new ilCtrlPathFactory($ctrl_structure);
        $context = new ilCtrlContext(
            $path_factory,
            $dic->http()->wrapper()->query(),
            $dic->refinery()
        );

        // create global instance of ilCtrl
        $GLOBALS['ilCtrl'] = new ilCtrl(
            $ctrl_structure,
            $token_repository,
            $path_factory,
            $context,
            $dic["http.response_sender_strategy"],
            $dic->http()->request(),
            $dic->http()->wrapper()->post(),
            $dic->http()->wrapper()->query(),
            $dic->refinery(),
            $dic["component.factory"],
            new ilCtrlSubject(),
        );

        // add helper function to DI container that
        // returns the global instance.
        $dic['ilCtrl'] = static function () {
            return $GLOBALS['ilCtrl'];
        };
    }

    /**
     * Aborts if another dependency required by the ctrl service
     * is not yet available.
     * @param Container $dic
     * @throws ilCtrlException if a necessary dependency is not yet
     *                         initialized.
     */
    private function abortIfMissingDependencies(Container $dic): void
    {
        if (!$dic->offsetExists('http')) {
            throw new ilCtrlException("Cannot initialize ilCtrl if HTTP Services are not yet available.");
        }

        if (!$dic->offsetExists('refinery')) {
            throw new ilCtrlException("Cannot initialize ilCtrl if Refinery Factory is not yet available.");
        }

        // if (!$dic->offsetExists('ilDB')) {
        //     throw new ilCtrlException("Cannot initialize ilCtrl if Database is not yet available.");
        // }
    }
}
