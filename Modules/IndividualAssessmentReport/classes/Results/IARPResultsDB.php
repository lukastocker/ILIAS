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

use ILIAS\IARP\UserInfo;
use ILIAS\IARP\IASSInfo;
use ILIAS\IARP\GradingInfo;
use ILIAS\Data\Order;

class IARPResultsDB
{
    private array $user_info_cache = [];
    private array $iass_info_cache = [];

    public function __construct(
        protected readonly ilDBInterface $db,
        protected readonly SpecifiedFormStorageDB $custom_forms_storage,
    ) {
    }

    public function getResults(
        Order $order,
        int $lp_mode,
        array $filter_data,
        int $contained_in_ref_id
    ): \Iterator {
        foreach ($this->getRecords($order, $lp_mode, $filter_data, $contained_in_ref_id) as $rec) {
            yield(
                new IARPResult(
                    $this->getUserInfo($rec),
                    $this->getIASSInfo($rec),
                    $this->getGradingInfo($rec)
                )
            );
        }
    }

    protected function getUserInfo(array $rec): UserInfo
    {
        $usr_id = $rec['usr_id'];
        if (!array_key_exists($usr_id, $this->user_info_cache)) {
            $this->user_info_cache[$usr_id] = new UserInfo(
                $rec['usr_id'],
                $rec['login'],
                $rec['firstname'],
                $rec['lastname'],
            );
        }
        return $this->user_info_cache[$usr_id];
    }

    protected function getUserInfoFor(int $usr_id): UserInfo
    {
        if (!array_key_exists($usr_id, $this->user_info_cache)) {
            $usr = new \ilObjUser($usr_id);
            $this->user_info_cache[$usr_id] = new UserInfo(
                $usr_id,
                $usr->getLogin(),
                $usr->getFirstname(),
                $usr->getLastname(),
            );
        }
        return $this->user_info_cache[$usr_id];
    }

    protected function getIASSInfo(array $rec): IASSInfo
    {
        $obj_id = $rec['obj_id'];
        if (!array_key_exists($obj_id, $this->iass_info_cache)) {
            $this->iass_info_cache[$obj_id] = new IASSInfo(
                $rec['obj_id'],
                $rec['title'],
            );
        }
        return $this->iass_info_cache[$obj_id];
    }

    protected function getGradingInfo(array $rec): GradingInfo
    {
        $custom_fields = $this->custom_forms_storage->getSpecifiedFormFields(
            $rec['obj_id'],
            $rec['usr_id']
        );

        return new GradingInfo(
            (bool) $rec['finalized'],
            $rec['learning_progress'],
            (string) $rec['record'],
            (string) $rec['internal_note'],
            (string) $rec['place'],
            $rec['event_time'] ? \DateTimeImmutable::createFromFormat('U', (string) $rec['event_time']) : null,
            $rec['file_name'],
            $custom_fields,
            $rec['examiner_id'] ? $this->getUserInfoFor($rec['examiner_id']) : null,
            $rec['changer_id'] ? $this->getUserInfoFor($rec['changer_id']) : null,
            $rec['change_time'] ? \DateTimeImmutable::createFromFormat(
                ilIndividualAssessmentSettingsStorageDB::DATE_TIME_FORMAT,
                $rec['change_time']
            ) : null,
        );
    }


    protected function getRecords(
        Order $order,
        int $lp_mode,
        array $filter_data,
        int $contained_in_ref_id
    ): array {
        $sqlpart_order = $order->join('ORDER BY', fn(...$o) => implode(' ', $o));
        $sqlpart_mode = $lp_mode === -1 ? '' : 'AND learning_progress = ' . $this->db->quote($lp_mode, 'integer');
        $sqlpart_filter = '';
        $sqlpart_tree = '';

        if ($contained_in_ref_id !== -1) {
            $sqlpart_tree = 'JOIN tree on tree.child = ref.ref_id '
                . 'AND tree.path LIKE "%.' . (string) $contained_in_ref_id . '.%"' . PHP_EOL;
        }

        if ($filter_data !== []) {
            list($f_usr, $f_ass) = $filter_data;
            if ($f_usr) {
                $sqlpart_filter .= 'AND ( '
                    . 'ud.login LIKE "%' . $f_usr . '%" OR '
                    . 'ud.firstname LIKE "%' . $f_usr . '%" OR '
                    . 'ud.lastname LIKE "%' . $f_usr . '%")' . PHP_EOL;
            }
            if ($f_ass) {
                $sqlpart_filter .= 'AND ( '
                    . 'od.title LIKE "%' . $f_ass . '%" OR '
                    . 'od.description LIKE "%' . $f_ass . '%")' . PHP_EOL;
            }
        }

        $query = 'SELECT *' . PHP_EOL
            . 'FROM iass_members ia' . PHP_EOL
            . 'JOIN usr_data ud ON ia.usr_id = ud.usr_id' . PHP_EOL
            . 'JOIN object_data od ON ia.obj_id = od.obj_id' . PHP_EOL
            . 'JOIN iass_settings ias ON ia.obj_id = ias.obj_id' . PHP_EOL
            . 'JOIN object_reference ref ON ia.obj_id = ref.obj_id' . PHP_EOL
            . $sqlpart_tree
            . 'WHERE ias.report = 1' . PHP_EOL
            . 'AND ref.deleted IS NULL' . PHP_EOL
            . 'AND ('
            . '(ias.report_from IS NULL AND ias.report_to IS NULL)'
            . ' OR '
            . '(ias.report_from < NOW() AND ias.report_to > NOW())'
            . ')' . PHP_EOL
            . $sqlpart_mode . PHP_EOL
            . $sqlpart_filter . PHP_EOL
            . $sqlpart_order
        ;

        $res = $this->db->query($query);
        return $this->db->fetchAll($res);
    }

}
