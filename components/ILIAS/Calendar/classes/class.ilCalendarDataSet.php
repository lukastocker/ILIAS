<?php

declare(strict_types=1);
/* Copyright (c) 1998-2012 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * Calendar data set class.
 * @author  Alex Killing <alex.killing@gmx.de>
 * @version $Id$
 * @ingroup ingroup ServicesCalendar
 */
class ilCalendarDataSet extends ilDataSet
{
    /**
     * @inheritDoc
     */
    public function getSupportedVersions(): array
    {
        return array("4.3.0");
    }

    /**
     * @inheritDoc
     */
    protected function getXmlNamespace(string $a_entity, string $a_schema_version): string
    {
        return "http://www.ilias.de/xml/Services/Calendar/" . $a_entity;
    }

    /**
     * @inheritDoc
     */
    protected function getTypes(string $a_entity, string $a_version): array
    {
        // calendar
        if ($a_entity == "calendar") {
            switch ($a_version) {
                case "4.3.0":
                    return array(
                        "CatId" => "integer",
                        "ObjId" => "text",
                        "Title" => "text",
                        "Color" => "text",
                        "Type" => "integer"
                    );
            }
        }

        // calendar entry
        if ($a_entity == "cal_entry") {
            switch ($a_version) {
                case "4.3.0":
                    return array(
                        "Id" => "integer",
                        "Title" => "text",
                        "Subtitle" => "text",
                        "Description" => "text",
                        "Location" => "text",
                        "Fullday" => "integer",
                        "Starta" => "text",
                        "Enda" => "text",
                        "Informations" => "text",
                        "AutoGenerated" => "integer",
                        "ContextId" => "integer",
                        "TranslationType" => "integer",
                        "Notification" => "integer"
                    );
            }
        }

        // calendar/entry assignment
        if ($a_entity == "cal_assignment") {
            switch ($a_version) {
                case "4.3.0":
                    return array(
                        "CatId" => "integer",
                        "EntryId" => "integer"
                    );
            }
        }

        // recurrence rule
        if ($a_entity == "recurrence_rule") {
            switch ($a_version) {
                case "4.3.0":
                    return array(
                        "RuleId" => "integer",
                        "EntryId" => "integer",
                        "CalRecurrence" => "integer",
                        "FreqType" => "text",
                        "FreqUntilDate" => "text",
                        "FreqUntilCount" => "integer",
                        "Intervall" => "integer",
                        "Byday" => "text",
                        "Byweekno" => "text",
                        "Bymonth" => "text",
                        "Bymonthday" => "text",
                        "Byyearday" => "text",
                        "Bysetpos" => "text",
                        "Weekstart" => "text"
                    );
            }
        }
        return [];
    }

    /**
     * @inheritDoc
     */
    public function readData(string $a_entity, string $a_version, array $a_ids): void
    {
        if (!is_array($a_ids)) {
            $a_ids = array($a_ids);
        }

        // calendar
        if ($a_entity == "calendar") {
            switch ($a_version) {
                case "4.3.0":
                    $this->getDirectDataFromQuery("SELECT cat_id, obj_id, title, color, type " .
                        " FROM cal_categories " .
                        " WHERE " .
                        $this->db->in("cat_id", $a_ids, false, "integer"));
                    break;
            }
        }

        // cal assignments
        if ($a_entity == "cal_assignment") {
            switch ($a_version) {
                case "4.3.0":
                    $this->getDirectDataFromQuery("SELECT cat_id, cal_id entry_id " .
                        " FROM cal_cat_assignments " .
                        " WHERE " .
                        $this->db->in("cat_id", $a_ids, false, "integer"));
                    break;
            }
        }

        // cal entries
        if ($a_entity == "cal_entry") {
            switch ($a_version) {
                case "4.3.0":
                    $this->getDirectDataFromQuery("SELECT cal_id id, title, subtitle, description, location, fullday, " .
                        " starta, enda, informations, auto_generated, context_id, translation_type, notification " .
                        " FROM cal_entries " .
                        " WHERE " .
                        $this->db->in("cal_id", $a_ids, false, "integer"));
                    break;
            }
        }

        // recurrence_rule
        if ($a_entity == "recurrence_rule") {
            switch ($a_version) {
                case "4.3.0":
                    $this->getDirectDataFromQuery("SELECT rule_id, cal_id entry_id, cal_recurrence, freq_type, freq_until_date, freq_until_count, " .
                        " intervall, byday, byweekno, bymonth, bymonthday, byyearday, bysetpos, weekstart " .
                        " FROM cal_recurrence_rules " .
                        " WHERE " .
                        $this->db->in("cal_id", $a_ids, false, "integer"));
                    break;
            }
        }
    }

    /**
     * @inheritDoc
     */
    protected function getDependencies(
        string $a_entity,
        string $a_version,
        ?array $a_rec = null,
        ?array $a_ids = null
    ): array {
        switch ($a_entity) {
            case "calendar":
                $assignmnts = ilCalendarCategoryAssignments::_getAssignedAppointments(array($a_rec["CatId"] ?? []));
                $entries = array();
                foreach ($assignmnts as $cal_id) {
                    $entries[$cal_id] = $cal_id;
                }
                return array(
                    "cal_entry" => array("ids" => $entries),
                    "cal_assignment" => array("ids" => $a_rec["CatId"] ?? null)
                );
            case "cal_entry":
                return array(
                    "recurrence_rule" => array("ids" => $a_rec["Id"] ?? null)
                );
        }

        return [];
    }

    /**
     * @inheritDoc
     */
    public function importRecord(
        string $a_entity,
        array $a_types,
        array $a_rec,
        ilImportMapping $a_mapping,
        string $a_schema_version
    ): void {
        switch ($a_entity) {
            case "calendar":
                // please note: we currently only support private user calendars to
                // be imported
                if (($a_rec["Type"] ?? 0) == 1) {
                    $usr_id = (int) $a_mapping->getMapping("components/ILIAS/User", "usr", $a_rec["ObjId"]);
                    if ($usr_id > 0 && ilObject::_lookupType($usr_id) == "usr") {
                        $category = new ilCalendarCategory(0);
                        $category->setTitle((string) $a_rec["Title"]);
                        $category->setColor((string) $a_rec["Color"]);
                        $category->setType((int) ilCalendarCategory::TYPE_USR);
                        $category->setObjId((int) $usr_id);
                        $category->add();
                        $a_mapping->addMapping(
                            "components/ILIAS/Calendar",
                            "calendar",
                            $a_rec["CatId"],
                            (string) $category->getCategoryID()
                        );
                    }
                }
                break;

            case "cal_entry":
                // please note: we currently only support private user calendars to
                // be imported
                if ((int) ($a_rec["ContextId"] ?? 0) == 0) {
                    $entry = new ilCalendarEntry(0);
                    $entry->setTitle((string) $a_rec["Title"]);
                    $entry->setSubtitle((string) $a_rec["Subtitle"]);
                    $entry->setDescription((string) $a_rec["Description"]);
                    $entry->setLocation((string) $a_rec["Location"]);
                    $entry->setFullday((bool) $a_rec["Fullday"]);
                    if ($a_rec["Starta"] != "") {
                        $entry->setStart(new ilDateTime($a_rec["Starta"], IL_CAL_DATETIME, 'UTC'));
                    }
                    if (($a_rec["Enda"] ?? '') != "") {
                        $entry->setEnd(new ilDateTime($a_rec["Enda"], IL_CAL_DATETIME, 'UTC'));
                    }
                    $entry->setFurtherInformations((string) ($a_rec["Informations"] ?? ''));
                    $entry->setAutoGenerated((bool) ($a_rec["AutoGenerated"] ?? false));
                    $entry->setContextId((int) ($a_rec["ContextId"] ?? 0));
                    $entry->setTranslationType((int) ($a_rec["TranslationType"] ?? 0));
                    $entry->enableNotification((bool) ($a_rec["Notification"] ?? false));
                    $entry->save();
                    $a_mapping->addMapping(
                        "components/ILIAS/Calendar",
                        "cal_entry",
                        $a_rec["Id"],
                        (string) $entry->getEntryId()
                    );
                }
                break;

            case "cal_assignment":
                $cat_id = (int) $a_mapping->getMapping("components/ILIAS/Calendar", "calendar", $a_rec["CatId"]);
                $entry_id = (int) $a_mapping->getMapping("components/ILIAS/Calendar", "cal_entry", $a_rec["EntryId"]);
                if ($cat_id > 0 && $entry_id > 0) {
                    $ass = new ilCalendarCategoryAssignments($entry_id);
                    $ass->addAssignment($cat_id);
                }
                break;

            case "recurrence_rule":
                $entry_id = $a_mapping->getMapping("components/ILIAS/Calendar", "cal_entry", $a_rec["EntryId"]);
                if ($entry_id > 0) {
                    $rec = new ilCalendarRecurrence();
                    $rec->setEntryId((int) $entry_id);
                    $rec->setRecurrence((int) $a_rec["CalRecurrence"]);
                    $rec->setFrequenceType((string) $a_rec["FreqType"]);
                    if ($a_rec["FreqUntilDate"] != "") {
                        $rec->setFrequenceUntilDate(new ilDateTime((string) $a_rec["FreqUntilDate"], IL_CAL_DATETIME));
                    }
                    $rec->setFrequenceUntilCount((int) $a_rec["FreqUntilCount"]);
                    $rec->setInterval((int) ($a_rec["Interval"] ?? 0));
                    $rec->setBYDAY((string) ($a_rec["Byday"] ?? ''));
                    $rec->setBYWEEKNO((string) ($a_rec["Byweekno"] ?? ''));
                    $rec->setBYMONTH((string) ($a_rec["Bymonth"] ?? ''));
                    $rec->setBYMONTHDAY((string) ($a_rec["Bymonthday"] ?? ''));
                    $rec->setBYYEARDAY((string) ($a_rec["Byyearday"] ?? ''));
                    $rec->setBYSETPOS((string) ($a_rec["Bysetpos"] ?? ''));
                    $rec->setWeekstart((string) ($a_rec["Weekstart"] ?? ''));
                    $rec->save();
                    $a_mapping->addMapping(
                        "components/ILIAS/Calendar",
                        "recurrence_rule",
                        $a_rec["RuleId"],
                        (string) $rec->getRecurrenceId()
                    );
                }
                break;
        }
    }
}
