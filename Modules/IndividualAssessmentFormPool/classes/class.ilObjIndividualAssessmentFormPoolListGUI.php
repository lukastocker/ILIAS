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

class ilObjIndividualAssessmentFormPoolListGUI extends ilObjectListGUI
{
    public function init(): void
    {
        $this->static_link_enabled = false;
        $this->delete_enabled = true;
        $this->cut_enabled = true;
        $this->copy_enabled = true;
        $this->subscribe_enabled = false;
        $this->link_enabled = false;
        $this->info_screen_enabled = true;
        $this->progress_enabled = false;
        $this->notice_properties_enabled = false;
        $this->preconditions_enabled = false;
        $this->timings_enabled = false;
        $this->type = "iafp";
        $this->gui_class_name = "ilObjIndividualAssessmentFormPoolGUI";


        //        $this->substitutions = ilAdvancedMDSubstitution::_getInstanceByObjectType($this->type);
        //        $this->enableSubstitutions($this->substitutions->isActive());

        // general commands array
        $this->commands = ilObjIndividualAssessmentFormPoolAccess::_getCommands();
    }

    /**
    * Get command target frame
    *
    * @param	string		$cmd			command
    *
    * @return	string		command target frame
    */
    public function getCommandFrame(string $cmd): string
    {
        return ilFrameTargetInfo::_getFrame("MainContent");
    }

    public function getCommandLink(string $cmd): string
    {
        switch ($cmd) {
            case 'infoScreen':
                $return = $this->ctrl->getLinkTargetByClass($this->gui_class_name, "view");
                break;
            default:
                $return = parent::getCommandLink($cmd);
        }

        return $return;
    }
}
