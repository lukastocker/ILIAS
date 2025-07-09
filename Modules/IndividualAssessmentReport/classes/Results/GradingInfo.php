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

class GradingInfo
{
    public function __construct(
        protected bool $finalized,
        protected int $lp_status,
        protected ?string $record_note,
        protected string $internal_note,
        protected string $location,
        protected ?\DateTimeImmutable $event_time,
        protected ?string $file_id,
        protected array $custom_fields,
        protected ?UserInfo $examiner,
        protected ?UserInfo $changer,
        protected ?\DateTimeImmutable $last_change,
    ) {
    }

    public function isFinalized(): bool
    {
        return $this->finalized;
    }

    public function getLPStatus(): int
    {
        return $this->lp_status;
    }

    public function getRecordNote(): ?string
    {
        return $this->record_note;
    }

    public function getInternalNote(): string
    {
        return $this->internal_note;
    }

    public function getLocation(): string
    {
        return $this->location;
    }

    public function getEventTime(): ?\DateTimeImmutable
    {
        return $this->event_time;
    }

    public function getFileId(): ?string
    {
        return $this->file_id;
    }

    public function getCustomFields(): array
    {
        return $this->custom_fields;
    }

    public function getExaminer(): ?UserInfo
    {
        return $this->examiner;
    }

    public function getChangedBy(): ?UserInfo
    {
        return $this->changer;
    }

    public function getLastChange(): ?\DateTimeImmutable
    {
        return $this->last_change;
    }

}
