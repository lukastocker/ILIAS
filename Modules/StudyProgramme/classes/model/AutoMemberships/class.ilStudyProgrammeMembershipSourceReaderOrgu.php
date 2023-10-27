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

/**
 * Provides adapters to read member-ids from a specific source.
 */
class ilStudyProgrammeMembershipSourceReaderOrgu implements ilStudyProgrammeMembershipSourceReader
{
    protected ilObjOrgUnitTree $orgu_tree;
    protected int $src_id;
    protected ilOrgUnitUserAssignmentDBRepository $assignmentRepo;
    protected bool $search_recursive;
    protected int $exclude_id;

    public function __construct(
        ilObjOrgUnitTree $orgu_tree,
        int $src_id,
        bool $search_recursive,
        int $exclude_id
    ) {
        $this->orgu_tree = $orgu_tree;
        $this->src_id = $src_id;
        $this->search_recursive = $search_recursive;
        $this->exclude_id = $exclude_id;

        $dic = ilOrgUnitLocalDIC::dic();
        $this->assignmentRepo = $dic["repo.UserAssignments"];
    }

    /**
     * @inheritdoc
     */
    public function getMemberIds(): array
    {
        $children[] = $this->src_id;
        if ($this->search_recursive) {
            $children = array_unique(array_merge($children, $this->orgu_tree->getAllChildren($this->src_id)));
            if ($key = array_search($this->exclude_id, $children)) {
                unset($children[$key]);
            }
        }
        $assignees = $this->assignmentRepo::where(
            ['orgu_id' => $children]
        )->getArray('id', 'user_id');

        return array_map(
            'intval',
            array_values($assignees)
        );
        return $this->assignmentRepo->getUsersByOrgUnits([$this->src_id]);
    }
}
