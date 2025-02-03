<?php

declare(strict_types=1);

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

namespace ILIAS\IndividualAssessmentFormPool\Testing;

use ILIAS\IndividualAssessmentFormPool\FieldBuilder;
use ILIAS\UI\Implementation\Component\Input\Field\Factory as FieldFactory;
use ILIAS\Refinery\Factory as Refinery;
use ILIAS\UI\Implementation\Component\Input\UploadLimitResolver;
use ILIAS\UI\Component\Input\Field\UploadHandler;

trait FieldBuilderMockFactory
{
    protected function getFieldFactory(): FieldFactory
    {
        return new FieldFactory(
            $this->createMock(UploadLimitResolver::class),
            new \IncrementalSignalGenerator(),
            new \ILIAS\Data\Factory(),
            $this->getRefinery(),
            $this->getLanguage()
        );
    }

    protected function getRefinery(): Refinery
    {
        return new Refinery(
            new \ILIAS\Data\Factory(),
            $this->getLanguage()
        );
    }

    protected function getLanguage(): \ilLanguage
    {
        return new class () extends \ilLanguage {
            public function __construct()
            {
            }

            public function loadLanguageModule(string $a_module): void
            {
            }
        };
    }

    protected function getFieldBuilder(): FieldBuilder
    {
        return new FieldBuilder(
            $this->getFieldFactory(),
            $this->getRefinery(),
            $this->getLanguage(),
            $this->createMock(UploadHandler::class),
            $this->createMock(\ilUIMarkdownPreviewGUI::class),
        );
    }
}
