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

use OrgUnit\PublicApi\OrgUnitUserService;

class ilIndividualAssessmentMailTemplateContext extends ilMailTemplateContext
{
    public const ID = 'iass_context_manual';
    private const LINK = 'IASS_LINK';

    private const PLACEHOLDER_TRANSLATIONS = [
        self::LINK => 'iass_link'
    ];

    protected ilLanguage $lng;

    public function __construct(
        OrgUnitUserService $orgUnitUserService = null,
        ilMailEnvironmentHelper $envHelper = null,
        ilMailUserHelper $usernameHelper = null,
        ilMailLanguageHelper $languageHelper = null
    ) {
        parent::__construct(
            $orgUnitUserService,
            $envHelper,
            $usernameHelper,
            $languageHelper
        );

        global $DIC;

        $this->lng = $DIC['lng'];
        $this->lng->loadLanguageModule('iass');
    }

    public function getId(): string
    {
        return self::ID;
    }

    public function getTitle(): string
    {
        return $this->lng->txt('iass_mail_context_title');
    }

    public function getDescription(): string
    {
        return $this->lng->txt('iass_mail_context_info');
    }

    public function getSpecificPlaceholders(): array
    {
        $placeholders = [];
        foreach (self::PLACEHOLDER_TRANSLATIONS as $id => $label) {
            $placeholders[$id] = [
                'placeholder' => $id,
                'label' => $this->lng->txt($label)
            ];
        }
        return $placeholders;
    }

    public function resolveSpecificPlaceholder(
        string $placeholder_id,
        array $context_parameters,
        ilObjUser $recipient = null
    ): string {
        $placeholder_id = strtoupper($placeholder_id);
        $string = '';
        if (array_key_exists($placeholder_id, self::PLACEHOLDER_TRANSLATIONS)) {
            switch ($placeholder_id) {
                case self::LINK:
                    $string = ilLink::_getLink((int) $context_parameters['ref_id'], 'iass') . ' ';
                    break;
                default:
                    throw new Exception("cannot resolve placeholder: " . $placeholder_id);
            }
        }

        return $string;
    }
}
