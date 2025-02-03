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

namespace ILIAS\IARP;

class UserInfo
{
    public function __construct(
        protected int $usr_id,
        protected string $login,
        protected string $firstname,
        protected string $lastname,
    ) {
    }

    public function getUserId(): int
    {
        return $this->usr_id;
    }

    public function getLogin(): string
    {
        return $this->login;
    }

    public function getFirstname(): string
    {
        return $this->firstname;
    }

    public function getLastname(): string
    {
        return $this->lastname;
    }

    public function getRepresentation(): string
    {
        return sprintf(
            '%s, %s [%s]',
            $this->getLastname(),
            $this->getFirstname(),
            $this->getLogin()
        );
    }

}
