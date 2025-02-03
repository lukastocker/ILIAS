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

/**
 * Gets Forms from all Pools and copy their specifications to IASS.
 */
interface IAFPCollector
{
    /**
     * get options for creation of IASS objects
     * $options['iass_' . $form->getFormId()] = [$form->getName(), $form->getDescription()];
     */
    public function getFormsSelection(): array;

    /**
     * copy fields and their config to the given IASS object
     */
    public function copyFieldsToIASS(int $iafp_form_id, int $iass_obj_id): void;

    /**
     * clone fields (on copy of object)
     */
    public function cloneFields(int $source_iass_obj_id, int $target_iass_obj_id): void;

}
