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
 * feedback class for assErrorText questions
 *
 * @author		Björn Heyser <bheyser@databay.de>
 * @version		$Id$
 *
 * @package		components/ILIAS/TestQuestionPool
 */
class ilAssErrorTextFeedback extends ilAssMultiOptionQuestionFeedback
{
    /**
     * @return string[] $answerOptionsByAnswerIndex
     */
    public function getAnswerOptionsByAnswerIndex(): array
    {
        return $this->questionOBJ->getErrorData();
    }

    /**
     * @param assAnswerErrorText $answer
     */
    protected function buildAnswerOptionLabel(int $index, $answer): string
    {
        $caption = $ordinal = $index + 1;
        $caption .= '. <br />"' . $answer->getTextWrong() . '" =&gt; ';
        $caption .= '"' . $answer->getTextCorrect() . '"';
        $caption .= '</i>';

        return $caption;
    }
}
