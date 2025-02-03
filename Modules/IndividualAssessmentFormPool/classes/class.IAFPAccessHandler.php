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

use ILIAS\UI\Factory as UIFactory;
use ILIAS\UI\Renderer as UIRenderer;
use ILIAS\Data\Factory as DataFactory;
use ILIAS\Refinery\Factory as Refinery;
use ILIAS\UI\URLBuilder;
use ILIAS\UI\URLBuilderToken;
use Psr\Http\Message\ServerRequestInterface;
use ILIAS\HTTP\Wrapper\ArrayBasedRequestWrapper;
use ILIAS\UI\Implementation\Component\Input\Container\Form\Standard as UIForm;
use ILIAS\UI\Implementation\Component\MessageBox\MessageBox;
use ILIAS\IndividualAssessmentFormPool\FormsStorageDB;
use ILIAS\IndividualAssessmentFormPool\Form;
use ILIAS\IndividualAssessmentFormPool\FormsDataRetrieval;

//use ILIAS\HTTP\Wrapper\RequestWrapper;
//use ILIAS\UI\Component\Input\Container\Form\Standard as Form;

/**
 *
 */
class IAFPAccessHandler
{
    public function __construct(
        private ilAccessHandler $access,
        private ilRbacReview $review,
        private int $current_usr_id,
        private int $iafp_ref_id
    ) {
    }

    public function mayView(): bool
    {
        return $this->isSystemAdmin() ||
            $this->access->checkAccess('visible', '', $this->iafp_ref_id);
    }

    public function mayRead(): bool
    {
        return $this->isSystemAdmin() ||
            $this->access->checkAccess('read', '', $this->iafp_ref_id);

    }
    public function mayEdit(): bool
    {
        return $this->isSystemAdmin() ||
            $this->access->checkAccess('write', '', $this->iafp_ref_id);
    }

    protected function isSystemAdmin(): bool
    {
        return $this->review->isAssigned($this->current_usr_id, SYSTEM_ROLE_ID);
    }

}
