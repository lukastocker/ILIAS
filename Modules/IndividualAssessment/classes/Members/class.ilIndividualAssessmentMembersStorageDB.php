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

use ILIAS\ResourceStorage\Services as IRSS;

/**
 * Store member infos to DB
 */
class ilIndividualAssessmentMembersStorageDB implements ilIndividualAssessmentMembersStorage
{
    public const MEMBERS_TABLE = "iass_members";
    public function __construct(
        protected ilDBInterface $db,
        protected IRSS $irss,
        protected ilIndividualAssessmentGradingStakeholder $stakeholder,
        protected SpecifiedFormStorage $specified_form_storage,
        protected ilRbacReview $rbac_review
    ) {
    }

    /**
     * @inheritdoc
     */
    public function loadMembers(ilObjIndividualAssessment $obj): ilIndividualAssessmentMembers
    {
        $members = new ilIndividualAssessmentMembers($obj);
        $obj_id = $obj->getId();
        $sql = $this->loadMembersQuery($obj_id);
        $res = $this->db->query($sql);
        while ($rec = $this->db->fetchAssoc($res)) {
            $members = $members->withAdditionalRecord($rec);
        }
        return $members;
    }

    /**
     * @inheritdoc
     */
    public function loadMembersAsSingleObjects(
        ilObjIndividualAssessment $obj,
        string $filter = null,
        string $sort = null
    ): array {
        $members = [];
        $sql = $this->loadMemberQuery();
        $sql .= "	WHERE obj_id = " . $this->db->quote($obj->getId(), 'integer');
        if (!is_null($filter)) {
            $sql .= $this->getWhereFromFilter($filter);
        }
        $sql .= ")";

        $roles = $obj->getSettings()->getParticipantRoles();
        if ($roles !== null && $roles != []) {
            $user_ids = $this->getIdsForDiffOfMembersWithAndWithoutRecords($obj, $roles);
            $sql .= $this->loadMemberQueryWithoutEntries($obj, $user_ids, $filter);
        }

        if (!is_null($sort)) {
            $sql .= $this->getOrderByFromSort($sort);
        }

        $res = $this->db->query($sql);
        while ($rec = $this->db->fetchAssoc($res)) {
            $usr = new ilObjUser((int) $rec["usr_id"]);
            $members[] = $this->createAssessmentMember($obj, $usr, $rec);
        }

        return $members;
    }

    protected function loadMembersWithExistingRecord(
        ilObjIndividualAssessment $obj,
        string $filter = null,
        string $sort = null
    ): array {
        $members = [];
        $sql = $this->loadMemberQuery();
        $sql .= "	WHERE obj_id = " . $this->db->quote($obj->getId(), 'integer');

        if (!is_null($filter)) {
            $sql .= $this->getWhereFromFilter($filter);
        }

        if (!is_null($sort)) {
            $sql .= $this->getOrderByFromSort($sort);
        }
        $res = $this->db->query($sql);
        while ($rec = $this->db->fetchAssoc($res)) {
            $usr = new ilObjUser((int) $rec["usr_id"]);
            $members[] = $this->createAssessmentMember($obj, $usr, $rec);
        }
        return $members;
    }

    public function loadMembersAddedByRoleAndWithoutRecord(
        ilObjIndividualAssessment $obj,
        array $roles,
        ?string $filter = null,
        ?string $sort = null
    ): array {
        $members = [];
        if ($roles === null && $roles === []) {
            return $members;
        }
        $user_ids = $this->getIdsForDiffOfMembersWithAndWithoutRecords($obj, $roles);

        foreach ($user_ids as $user_id) {
            $user = new ilObjUser($user_id);
            $members[] = new ilIndividualAssessmentMember(
                $obj,
                new ilObjUser($user_id),
                new ilIndividualAssessmentUserGrading($obj->getSettings()->getTitle()),
                0
            );
        }

        return $members;
    }

    /**
     * @inheritdoc
     */
    public function loadMember(
        ilObjIndividualAssessment $obj,
        ilObjUser $usr
    ): ilIndividualAssessmentMember {
        $obj_id = $obj->getId();
        $usr_id = $usr->getId();
        $sql = $this->loadMemberQuery();
        $sql .= "	WHERE obj_id = " . $this->db->quote($obj_id, 'integer') . "\n"
            . "		AND iassme.usr_id = " . $this->db->quote($usr_id, 'integer') . ")";

        $rec = $this->db->fetchAssoc($this->db->query($sql));
        if ($rec) {
            return $this->createAssessmentMember($obj, $usr, $rec);
        } elseif ($obj->getSettings()->getParticipantRoles() === null) {
            throw new ilIndividualAssessmentException("invalid usr-obj combination");
        } else {
            $participants = $this->loadMembersAddedByRoleAndWithoutRecord(
                $obj,
                $obj->getSettings()->getParticipantRoles()
            );
            foreach ($participants as $participant) {
                if ($participant->id() === $usr_id) {
                    $rec = [
                        'obj_id' => $obj,
                        'usr_id' => $participant->id(),
                        'examiner_id' => null,
                        'record' => null,
                        'internal_note' => null,
                        'notification_ts' => - 1,
                        'learning_progress' => null,
                        'finalized' => 0,
                        'place' => null,
                        'event_time' => null,
                        'file_name' => null,
                        'changer_id' => null,
                        'change_time' => null,
                        'user_login' => $participant->login(),
                        'examiner_login' => null
                    ];
                    return $this->createAssessmentMember($obj, new ilObjUser($participant->id()), $rec);
                }
            }
        }
    }

    protected function createAssessmentMember(
        ilObjIndividualAssessment $obj,
        ilObjUser $usr,
        array $record
    ): ilIndividualAssessmentMember {
        $changer_id = $record[ilIndividualAssessmentMembers::FIELD_CHANGER_ID];
        if (!is_null($changer_id)) {
            $changer_id = (int) $changer_id;
        }
        $change_time = null;
        $change_time_db = $record[ilIndividualAssessmentMembers::FIELD_CHANGE_TIME];
        if (!is_null($change_time_db)) {
            $change_time = new DateTimeImmutable($change_time_db);
        }
        $examiner_id = $record[ilIndividualAssessmentMembers::FIELD_EXAMINER_ID];
        if (!is_null($examiner_id)) {
            $examiner_id = (int) $examiner_id;
        }
        $custom_fields = $this->specified_form_storage->getSpecifiedFormFields($obj->getId(), $usr->getId());

        return new ilIndividualAssessmentMember(
            $obj,
            $usr,
            $this->createGrading($record, $usr->getFullname())
                ->withCustomFields($custom_fields),
            (int) $record[ilIndividualAssessmentMembers::FIELD_NOTIFICATION_TS],
            $examiner_id,
            $changer_id,
            $change_time
        );
    }

    protected function createGrading(array $record, string $user_fullname): ilIndividualAssessmentUserGrading
    {
        $event_time = null;
        $event_time_db = $record[ilIndividualAssessmentMembers::FIELD_EVENTTIME];
        if (!is_null($event_time_db)) {
            $event_time = new DateTimeImmutable();
            $event_time = $event_time->setTimestamp((int) $event_time_db);
        }
        return new ilIndividualAssessmentUserGrading(
            $user_fullname,
            (string) $record[ilIndividualAssessmentMembers::FIELD_RECORD],
            (string) $record[ilIndividualAssessmentMembers::FIELD_INTERNAL_NOTE],
            (string) $record[ilIndividualAssessmentMembers::FIELD_FILE_NAME],
            (string) $record[ilIndividualAssessmentMembers::FIELD_PLACE],
            $event_time,
            (int) $record[ilIndividualAssessmentMembers::FIELD_LEARNING_PROGRESS],
            (bool) $record[ilIndividualAssessmentMembers::FIELD_FINALIZED]
        );
    }

    /**
     * @inheritdoc
     */
    public function updateMember(ilIndividualAssessmentMember $member): void
    {
        $event_time = $member->eventTime();
        if (!is_null($event_time)) {
            $event_time = $event_time->getTimestamp();
        }

        $changer_id = $member->changerId();
        if ($changer_id === 0) {
            $changer_id = null;
        }

        $query = 'REPLACE INTO ' . self::MEMBERS_TABLE . PHP_EOL
            . '(obj_id, usr_id, examiner_id, record, internal_note, notification_ts, learning_progress, ' . PHP_EOL
            . 'finalized, place, event_time, file_name, changer_id, change_time)' . PHP_EOL
            . 'VALUES (' . PHP_EOL
            . $this->db->quote($member->assessmentId(), "integer") . ', '
            . $this->db->quote($member->id(), "integer") . ', '
            . $this->db->quote($member->examinerId() ?? null, "integer") . ', '
            . $this->db->quote($member->record() ?? null, "text") . ', '
            . $this->db->quote($member->internalNote() ?? null, "text") . ', '
            . $this->db->quote($member->notificationTS() ?? null, "integer") . ', '
            . $this->db->quote($member->LPStatus(), "integer") . ', '
            . $this->db->quote($member->finalized(), "integer") . ', '
            . $this->db->quote($member->place() ?? null, "text") . ', '
            . $this->db->quote($event_time, "integer") . ', '
            . $this->db->quote($member->fileName() ?? null, "text") . ', '
            . $this->db->quote($changer_id, "integer") . ', '
            . $this->db->quote($this->getActualDateTime(), "string")
            . ')';


        $this->db->manipulate($query);
        $this->specified_form_storage->storeSpecifiedUserValues(...$member->getGrading()->getCustomFields());
    }

    protected function getActualDateTime(): string
    {
        return date("Y-m-d H:i:s");
    }

    /**
     * @inheritdoc
     */
    public function deleteMembers(ilObjIndividualAssessment $obj): void
    {
        foreach ($this->loadMembers($obj) as $member) {
            if ($identifier = $member[ilIndividualAssessmentMembers::FIELD_FILE_NAME]) {
                $resource_id = $this->irss->manage()->find($identifier);
                if ($resource_id) {
                    $this->irss->manage()->remove($resource_id, $this->stakeholder);
                }
            }
        }
        $sql = "DELETE FROM " . self::MEMBERS_TABLE . " WHERE obj_id = " . $this->db->quote($obj->getId(), 'integer');
        $this->db->manipulate($sql);
    }

    public function deleteCustomFieldsForObj(ilObjIndividualAssessment $obj): void
    {
        $this->specified_form_storage->deleteAllUserValuesAndFields(
            $this->irss,
            $this->stakeholder,
            $obj->getId()
        );
    }

    protected function loadMemberQuery(): string
    {
        return "(SELECT "
            . "iassme.obj_id,"
            . "iassme.usr_id,"
            . "iassme.examiner_id,"
            . "iassme.record,"
            . "iassme.internal_note,"
            . "iassme.notification_ts,"
            . "iassme.learning_progress,"
            . "iassme.finalized,"
            . "iassme.place,"
            . "iassme.event_time,"
            . "iassme.file_name,"
            . "iassme.changer_id,"
            . "iassme.change_time,"
            . "usr.login AS user_login,"
            . "ex.login AS examiner_login,"
            . "ch.login AS changer_login,"
            . "usr.firstname AS user_firstname"
            . " FROM " . self::MEMBERS_TABLE . " iassme\n"
            . "	JOIN usr_data usr ON iassme.usr_id = usr.usr_id\n"
            . "	LEFT JOIN usr_data ex ON iassme.examiner_id = ex.usr_id\n"
            . " LEFT JOIN usr_data ch ON iassme.changer_id = ch.usr_id\n"
        ;
    }

    protected function loadMembersQuery(int $obj_id): string
    {
        return "SELECT ex.firstname as " . ilIndividualAssessmentMembers::FIELD_EXAMINER_FIRSTNAME
                . "     , ex.lastname as " . ilIndividualAssessmentMembers::FIELD_EXAMINER_LASTNAME
                . "     , ud.firstname as " . ilIndividualAssessmentMembers::FIELD_CHANGER_FIRSTNAME
                . "     , ud.lastname as " . ilIndividualAssessmentMembers::FIELD_CHANGER_LASTNAME
                . "     ,usr.firstname as " . ilIndividualAssessmentMembers::FIELD_FIRSTNAME
                . "     ,usr.lastname as " . ilIndividualAssessmentMembers::FIELD_LASTNAME
                . "     ,usr.login as " . ilIndividualAssessmentMembers::FIELD_LOGIN
                . "	   ,iassme." . ilIndividualAssessmentMembers::FIELD_FILE_NAME
                . "     ,iassme.obj_id, iassme.usr_id, iassme.examiner_id, iassme.record, iassme.internal_note"
                . "     ,iassme.learning_progress, iassme.finalized,iassme.place"
                . "     ,iassme.event_time, iassme.changer_id, iassme.change_time\n"
                . " FROM iass_members iassme"
                . " JOIN usr_data usr ON iassme.usr_id = usr.usr_id"
                . " LEFT JOIN usr_data ex ON iassme.examiner_id = ex.usr_id"
                . " LEFT JOIN usr_data ud ON iassme.changer_id = ud.usr_id"
                . " WHERE obj_id = " . $this->db->quote($obj_id, 'integer');
    }

    /**
     * @inheritdoc
     */
    public function insertMembersRecord(ilObjIndividualAssessment $iass, array $record): void
    {
        $values = [
            "obj_id" => [
                "integer",
                $iass->getId()
            ],
            "usr_id" => [
                "integer",
                $record[ilIndividualAssessmentMembers::FIELD_USR_ID]
            ],
            ilIndividualAssessmentMembers::FIELD_LEARNING_PROGRESS => [
                "text",
                $record[ilIndividualAssessmentMembers::FIELD_LEARNING_PROGRESS]
            ],
            ilIndividualAssessmentMembers::FIELD_FINALIZED => [
                "integer",
                0
            ],
            ilIndividualAssessmentMembers::FIELD_NOTIFICATION_TS => [
                "integer",
                -1
            ]
        ];

        if (isset($record[ilIndividualAssessmentMembers::FIELD_EXAMINER_ID])) {
            $values[ilIndividualAssessmentMembers::FIELD_EXAMINER_ID] =
                [
                    "integer",
                    $record[ilIndividualAssessmentMembers::FIELD_EXAMINER_ID]
                ];
        }
        if (isset($record[ilIndividualAssessmentMembers::FIELD_RECORD])) {
            $values[ilIndividualAssessmentMembers::FIELD_RECORD] =
                [
                    "text",
                    $record[ilIndividualAssessmentMembers::FIELD_RECORD]
                ];
        }
        if (isset($record[ilIndividualAssessmentMembers::FIELD_INTERNAL_NOTE])) {
            $values[ilIndividualAssessmentMembers::FIELD_INTERNAL_NOTE] =
                [
                    "text",
                    $record[ilIndividualAssessmentMembers::FIELD_INTERNAL_NOTE]
                ];
        }
        if (isset($record[ilIndividualAssessmentMembers::FIELD_PLACE])) {
            $values[ilIndividualAssessmentMembers::FIELD_PLACE] =
                [
                    "text",
                    $record[ilIndividualAssessmentMembers::FIELD_PLACE]
                ];
        }
        if (isset($record[ilIndividualAssessmentMembers::FIELD_EVENTTIME])) {
            $values[ilIndividualAssessmentMembers::FIELD_EVENTTIME] =
                [
                    "integer",
                    $record[ilIndividualAssessmentMembers::FIELD_EVENTTIME]
                ];
        }
        if (isset($record[ilIndividualAssessmentMembers::FIELD_FILE_NAME])) {
            $values[ilIndividualAssessmentMembers::FIELD_FILE_NAME] =
                [
                    "text",
                    $record[ilIndividualAssessmentMembers::FIELD_FILE_NAME]
                ];
        }
        if (isset($record[ilIndividualAssessmentMembers::FIELD_CHANGER_ID])) {
            $values[ilIndividualAssessmentMembers::FIELD_CHANGER_ID] =
                [
                    "integer",
                    $record[ilIndividualAssessmentMembers::FIELD_CHANGER_ID]
                ];
        }
        if (isset($record[ilIndividualAssessmentMembers::FIELD_CHANGE_TIME])) {
            $values[ilIndividualAssessmentMembers::FIELD_CHANGE_TIME] =
                [
                    "text",
                    $record[ilIndividualAssessmentMembers::FIELD_CHANGE_TIME]
                ];
        }

        $this->db->insert(self::MEMBERS_TABLE, $values);
    }

    /**
     * @inheritdoc
     */
    public function removeMembersRecord(ilObjIndividualAssessment $iass, array $record): void
    {
        if (array_key_exists(ilIndividualAssessmentMembers::FIELD_FILE_NAME, $record)
            && $identifier = $record[ilIndividualAssessmentMembers::FIELD_FILE_NAME]) {
            $resource_id = $this->irss->manage()->find($identifier);
            $this->irss->manage()->remove($resource_id, $this->stakeholder);
        }

        $sql =
             "DELETE FROM " . self::MEMBERS_TABLE . PHP_EOL
            . "WHERE obj_id = " . $this->db->quote($iass->getId(), 'integer') . PHP_EOL
            . "AND usr_id = " . $this->db->quote($record[ilIndividualAssessmentMembers::FIELD_USR_ID], 'integer') . PHP_EOL
        ;

        $this->db->manipulate($sql);
        $this->specified_form_storage->deleteSpecifiedUserValues(
            $this->irss,
            $this->stakeholder,
            $iass->getId(),
            $record[ilIndividualAssessmentMembers::FIELD_USR_ID]
        );
    }

    /**
     * @param string|int $filter
     */
    protected function getWhereFromFilter($filter): string
    {
        switch ($filter) {
            case ilLPStatus::LP_STATUS_NOT_ATTEMPTED_NUM:
                return "      AND finalized = 0 AND examiner_id IS NULL\n";
            case ilLPStatus::LP_STATUS_IN_PROGRESS_NUM:
                return "      AND finalized = 0 AND examiner_id IS NOT NULL\n";
            case ilLPStatus::LP_STATUS_COMPLETED_NUM:
                return "      AND finalized = 1 AND learning_progress = 2\n";
            case ilLPStatus::LP_STATUS_FAILED_NUM:
                return "      AND finalized = 1 AND learning_progress = 3\n";
            default:
                return "";
        }
    }

    protected function getOrderByFromSort(string $sort): string
    {
        $vals = explode(":", $sort);
        return " ORDER BY " . $vals[0] . " " . $vals[1];
    }

    public function getRecords(
        ilObjIndividualAssessment $object,
        ?\ILIAS\Data\Range $range = null,
        ?\ILIAS\Data\Order $order = null
    ): array {
        $records = [];
        $roles = $object->getSettings()->getParticipantRoles();

        $sql = $this->loadMemberQuery();
        $sql .= "	WHERE obj_id = " . $this->db->quote($object->getId(), 'integer') . ")";

        if ($roles !== null && $roles != []) {
            $user_ids = $this->getIdsForDiffOfMembersWithAndWithoutRecords($object, $roles);
            $sql .= $this->loadMemberQueryWithoutEntries($object, $user_ids);
        }

        if ($order !== null) {
            $sql .= $order->join(' ORDER BY', fn(...$o) => implode(' ', $o));
        }
        if ($range !== null) {
            $sql .= sprintf(' LIMIT %2$s OFFSET %1$s', ...$range->unpack());
        }

        $res = $this->db->query($sql);
        while ($row = $this->db->fetchAssoc($res)) {
            $user = new ilObjUser((int) $row["usr_id"]);
            $records[] = $this->createAssessmentMember($object, $user, $row);
        }
        return $records;
    }

    public function getRecordsCountForObjId(
        int $obj_id,
        ilObjIndividualAssessment $obj = null
    ): ?int {
        $query = "SELECT count(*) as cnt" . PHP_EOL
            . "FROM " . self::MEMBERS_TABLE . " iassme" . PHP_EOL
            . "WHERE obj_id = " . $this->db->quote($obj_id, 'integer');
        $res = $this->db->query($query);
        $record_counter = (int) $this->db->fetchAssoc($res)['cnt'];

        $roles = $obj->getSettings()->getParticipantRoles();
        if ($roles !== null && $roles != []) {
            $record_counter = $record_counter + count($this->getIdsForDiffOfMembersWithAndWithoutRecords($obj, $roles));
        }

        return (int) $record_counter;
    }
    protected function loadMemberQueryWithoutEntries(
        ilObjIndividualAssessment $object,
        array $user_ids = [],
        ?string $filter = null
    ): string {
        switch ($filter) {
            case ilLPStatus::LP_STATUS_NOT_ATTEMPTED_NUM:
                $query = '';
                break;
            case ilLPStatus::LP_STATUS_IN_PROGRESS_NUM:
            case ilLPStatus::LP_STATUS_COMPLETED_NUM:
            case ilLPStatus::LP_STATUS_FAILED_NUM:
            default:
                return '';
        }

        $query = " UNION ALL ( SELECT "
            . "null,"
            . "usr_id,"
            . "null,"
            . "null,"
            . "null,"
            . "null,"
            . "null,"
            . "null,"
            . "null,"
            . "null,"
            . "null,"
            . "null,"
            . "null,"
            . "null,"
            . "login AS user_login,"
            . "null,"
            . "firstname AS user_firstname"
            . " FROM usr_data " . PHP_EOL
            . " WHERE " . $this->db->in('usr_id', $user_ids, false, "integer") . ")" . PHP_EOL
        ;

        return $query;
    }

    public function getIdsForMembersWithRecords(ilObjIndividualAssessment $object): array
    {
        $id_query = "SELECT usr_id FROM iass_members WHERE obj_id = " . $this->db->quote($object->getId(), 'integer');
        $res = $this->db->query($id_query);
        $members_with_records = [];
        while ($row = $this->db->fetchAssoc($res)) {
            $members_with_records[] = $row["usr_id"];
        }

        return $members_with_records;
    }

    protected function getIdsForDiffOfMembersWithAndWithoutRecords(ilObjIndividualAssessment $obj, array $roles): array
    {
        $mem_with_record_ids = $this->getIdsForMembersWithRecords($obj);

        $user_ids = [];
        foreach (unserialize($roles[0]) as $role_id) {
            $user_ids = array_merge($user_ids, $this->rbac_review->assignedUsers((int) $role_id));
        }

        return array_diff($user_ids, $mem_with_record_ids);
    }
}
