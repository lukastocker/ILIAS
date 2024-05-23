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

namespace ILIAS\UI\Component\Input\Container\Form;

/**
 * This describes a standard form.
 */
interface Standard extends FormWithPostURL
{
    /**
     * Sets the label of the submit button of the form
     */
    public function withSubmitLabel(string $label): Standard;

    /**
     * Gets the submit label of the form.
     */
    public function getSubmitLabel(): ?string;

    /**
     * Adds an additional submit button to the form
     * which change the form's action and MUST have a different
     * label than the standard submit button.
     * This method can be called multiple times to add more buttons.
     */
    public function withAdditionalSubmitButton(string $label, string $action): self;

    /**
     * Gets an array of all additional submit buttons
     * as label => action pairs
     */
    public function getAdditionalSubmitButtons(): array;
}
