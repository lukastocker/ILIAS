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

use ILIAS\UI\Component\Button\Button;
use ILIAS\UI\Renderer;

/**
 * Class ilUiLinkToSplitButtonMenuItemAdapter
 * @author Michael Jansen <mjansen@databay.de>
 *
 * @deprecated 10
 */
class ilUiLinkToSplitButtonMenuItemAdapter implements ilSplitButtonMenuItem
{
    protected Button $link;
    protected Renderer $renderer;

    public function __construct(Button $link, Renderer $renderer)
    {
        $this->link = $link;
        $this->renderer = $renderer;
    }

    public function getContent(): string
    {
        return $this->renderer->render([$this->link]);
    }
}
