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
 * A settings storage handler to write iass settings to db.
 */
class ilIndividualAssessmentSettingsStorageDB implements ilIndividualAssessmentSettingsStorage
{
    public const IASS_SETTINGS_TABLE = "iass_settings";
    public const IASS_SETTINGS_INFO_TABLE = "iass_info_settings";
    // cat-tms-patch start iassfeatures
    public const DATE_TIME_FORMAT = 'Y-m-d H:i:s';
    // cat-tms-patch end iassfeatures

    protected ilDBInterface $db;

    public function __construct(ilDBInterface $db)
    {
        $this->db = $db;
    }

    /**
     * @inheritdoc
     */
    public function createSettings(ilIndividualAssessmentSettings $settings): void
    {
        // cat-tms-patch start iassfeatures
        $values = [
            "obj_id" => ["integer", $settings->getObjId()],
            "content" => ["text", $settings->getContent()],
            "record_template" => ["text", $settings->getRecordTemplate()],
            "event_time_place_required" => ["integer", $settings->isEventTimePlaceRequired()],
            "file_required" => ["integer", $settings->isFileRequired()],
            "file_visible" => ["integer", $settings->isFileVisible()],
            "result_visible" => ["integer", $settings->isResultVisible()]
        ];
        // cat-tms-patch end iassfeatures

        $this->db->insert(self::IASS_SETTINGS_TABLE, $values);

        $values = ["obj_id" => ["integer", $settings->getObjId()]];
        $this->db->insert(self::IASS_SETTINGS_INFO_TABLE, $values);
    }

    /**
     * @inheritdoc
     */
    public function loadSettings(ilObjIndividualAssessment $obj): ilIndividualAssessmentSettings
    {
        if (!ilObjIndividualAssessment::_exists($obj->getId(), false, 'iass')) {
            // cat-tms-patch start iassfeatures
            return new ilIndividualAssessmentSettings(
                $obj->getId(),
                '',
                '',
                '',
                '',
                false,
                false,
                false,
                false
            );
            // cat-tms-patch end iassfeatures
        }

        // cat-tms-patch start iassfeatures
        $sql =
             "SELECT content, record_template, event_time_place_required, file_required, file_visible, result_visible" . PHP_EOL
            . ',report, report_from, report_to' . PHP_EOL
            . "FROM " . self::IASS_SETTINGS_TABLE . PHP_EOL
            . "WHERE obj_id = " . $this->db->quote($obj->getId(), 'integer') . PHP_EOL
        ;
        // cat-tms-patch end iassfeatures

        $result = $this->db->query($sql);

        if ($this->db->numRows($result) == 0) {
            throw new ilIndividualAssessmentException($obj->getId() . " not in database");
        }

        $row = $this->db->fetchAssoc($result);

        // cat-tms-patch start iassfeatures
        return new ilIndividualAssessmentSettings(
            $obj->getId(),
            $obj->getTitle(),
            $obj->getDescription(),
            $row["content"],
            $row["record_template"],
            (bool) $row["event_time_place_required"],
            (bool) $row['file_required'],
            (bool) $row["file_visible"],
            (bool) $row['result_visible'],
            (bool) $row['report'],
            $row['report_from'] ? \DateTimeImmutable::createFromFormat(self::DATE_TIME_FORMAT, $row['report_from']) : null,
            $row['report_to'] ? \DateTimeImmutable::createFromFormat(self::DATE_TIME_FORMAT, $row['report_to']) : null
        );
        // cat-tms-patch end iassfeatures
    }

    /**
     * @inheritdoc
     */
    public function updateSettings(ilIndividualAssessmentSettings $settings): void
    {
        $where = ["obj_id" => ["integer", $settings->getObjId()]];
        // cat-tms-patch start iassfeatures
        list($report, $report_from, $report_to) = $settings->getReportSettings();
        $values = [
            "content" => ["text", $settings->getContent()],
            "record_template" => ["text", $settings->getRecordTemplate()],
            "event_time_place_required" => ["integer", $settings->isEventTimePlaceRequired()],
            "file_required" => ["integer", $settings->isFileRequired()],
            "file_visible" => ["integer", $settings->isFileVisible()],
            "result_visible" => ["integer", $settings->isResultVisible()],
            "report" => ["integer", $report],
            "report_from" => ["timestamp", $report_from ? $report_from->format(self::DATE_TIME_FORMAT) : null],
            "report_to" => ["timestamp", $report_to ? $report_to->format(self::DATE_TIME_FORMAT) : null]
        ];
        // cat-tms-patch end iassfeatures
        $this->db->update(self::IASS_SETTINGS_TABLE, $values, $where);
    }

    /**
     * Load info-screen settings corresponding to obj
     */
    public function loadInfoSettings(ilObjIndividualAssessment $obj): ilIndividualAssessmentInfoSettings
    {
        if (!ilObjIndividualAssessment::_exists($obj->getId(), false, 'iass')) {
            return new ilIndividualAssessmentInfoSettings($obj->getId());
        }

        $sql =
            "SELECT contact, responsibility, phone, mails, consultation_hours" . PHP_EOL
            . "FROM " . self::IASS_SETTINGS_INFO_TABLE . PHP_EOL
            . "WHERE obj_id = " . $this->db->quote($obj->getId(), 'integer') . PHP_EOL
        ;

        $result = $this->db->query($sql);

        if ($this->db->numRows($result) == 0) {
            throw new ilIndividualAssessmentException($obj->getId() . " not in database");
        }

        $row = $this->db->fetchAssoc($result);

        return new ilIndividualAssessmentInfoSettings(
            $obj->getId(),
            $row["contact"],
            $row["responsibility"],
            $row['phone'],
            $row['mails'],
            $row['consultation_hours']
        );
    }

    /**
     * Update info-screen settings entry.
     */
    public function updateInfoSettings(ilIndividualAssessmentInfoSettings $settings): void
    {
        $where = ["obj_id" => ["integer", $settings->getObjId()]];

        $values = [
            "contact" => ["text", $settings->getContact()],
            "responsibility" => ["text", $settings->getResponsibility()],
            "phone" => ["text", $settings->getPhone()],
            "mails" => ["text", $settings->getMails()],
            "consultation_hours" => ["text", $settings->getConsultationHours()]
        ];

        $this->db->update(self::IASS_SETTINGS_INFO_TABLE, $values, $where);
    }

    /**
     * @inheritdoc
     */
    public function deleteSettings(ilObjIndividualAssessment $obj): void
    {
        $sql = "DELETE FROM " . self::IASS_SETTINGS_TABLE . " WHERE obj_id = %s";
        $this->db->manipulateF($sql, array("integer"), array($obj->getId()));

        $sql = "DELETE FROM " . self::IASS_SETTINGS_INFO_TABLE . " WHERE obj_id = %s";
        $this->db->manipulateF($sql, array("integer"), array($obj->getId()));
    }
}
