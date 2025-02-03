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

use ILIAS\IndividualAssessmentFormPool\FormsStorageDB;
use ILIAS\IndividualAssessmentFormPool\Field;
use ILIAS\IARP\Settings;

class ilObjIndividualAssessmentReport extends ilObject
{
    use ilIndividualAssessmentReportDIC;

    public const OBJ_TYPE = 'iarp';

    protected string $type;
    protected ?Pimple\Container $dic = null;
    protected IARPSettingsRepoDB $settings_repo;
    protected Settings $settings;

    public function __construct(int $id = 0, bool $call_by_reference = true)
    {
        $this->type = self::OBJ_TYPE;
        parent::__construct($id, $call_by_reference);
        global $DIC;
        $this->settings_repo = new IARPSettingsRepoDB($DIC['ilDB']);
    }

    /**
     * @inheritdoc
     */
    public function create(): int
    {
        $id = parent::create();
        $this->settings = $this->settings_repo->create($this->getId());
        return $id;
    }

    /**
     * @inheritdoc
     */
    public function read(): void
    {
        $this->settings = $this->settings_repo->get($this->getId());
        parent::read();
    }

    /**
     * @inheritdoc
     */
    public function delete(): bool
    {
        $this->settings_repo->delete($this->getId());
        return parent::delete();
    }

    /**
     * @inheritdoc
     */
    public function update(): bool
    {
        parent::update();
        $this->settings_repo->update($this->settings);
        return true;
    }

    /**
     * @inheritdoc
     */
    public function initDefaultRoles(): void
    {
    }

    /**
     * @inheritdoc
     */
    public function cloneObject(int $target_id, int $copy_id = 0, bool $omit_tree = false): ?ilObject
    {
        $new_obj = parent::cloneObject($target_id, $copy_id, $omit_tree);
        $new_obj = $new_obj->withSettings($this->getSettings()->withObjId($new_obj->getId()));
        $new_obj->update();
        return $new_obj;
    }

    public function getSettings(): Settings
    {
        return $this->settings;
    }

    public function withSettings(Settings $settings): self
    {
        $clone = clone $this;
        $clone->settings = $settings;
        return $clone;
    }

    public function getDic(): Pimple\Container
    {
        if ($this->dic === null) {
            global $DIC;
            $this->dic = $this->getObjectDIC($this, $DIC);
        }
        return $this->dic;
    }

}
