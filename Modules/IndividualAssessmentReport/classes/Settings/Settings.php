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

class Settings
{
    public function __construct(
        private int $obj_id,
        private bool $is_global
    ) {
    }

    public function getObjId(): int
    {
        return $this->obj_id;
    }

    public function withObjId(int $obj_id): self
    {
        $clone = clone $this;
        $clone->obj_id = $obj_id;
        return $clone;
    }

    public function isGlobal(): bool
    {
        return $this->is_global;
    }

    public function withGlobal(bool $is_global): self
    {
        $clone = clone $this;
        $clone->is_global = $is_global;
        return $clone;
    }

}
