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
 * Class ilScriptTaskElement
 *
 * @author Maximilian Becker <mbecker@databay.de>
 * @ingroup Services/WorkflowEngine
 */
class ilScriptTaskElement extends ilBaseElement
{
    public string $element_varname;

    public function getPHP(array $element, ilWorkflowScaffold $class_object): string
    {
        $code = "";
        $element_id = ilBPMN2ParserUtils::xsIDToPHPVarname($element['attributes']['id']);
        $this->element_varname = '$_v_' . $element_id;

        $event_definition = null;

        $class_object->registerRequire('./components/ILIAS/WorkflowEngine/classes/nodes/class.ilBasicNode.php');
        $code .= '
			' . $this->element_varname . ' = new ilBasicNode($this);
			$this->addNode(' . $this->element_varname . ');
			' . $this->element_varname . '->setName(\'' . $this->element_varname . '\');
		';
        $script_definition = ilBPMN2ParserUtils::extractScriptDefinitionFromElement($element);

        $class_object->addAuxilliaryMethod(
            "public function _v_" . $element_id . "_script(\$context)
			 {
			 " . $script_definition . "
			 }"
        );

        $class_object->registerRequire('./components/ILIAS/WorkflowEngine/classes/activities/class.ilScriptActivity.php');

        $code .= "
			" . $this->element_varname . "_scriptActivity = new ilScriptActivity(" . $this->element_varname . ");
			" . $this->element_varname . "_scriptActivity->setName('" . $this->element_varname . "');
			" . $this->element_varname . "_scriptActivity->setMethod('" . '_v_' . $element_id . "_script');
			" . $this->element_varname . "->addActivity(" . $this->element_varname . "_scriptActivity);
			";

        $code .= $this->handleDataAssociations($element, $class_object, $this->element_varname);

        return $code;
    }
}
