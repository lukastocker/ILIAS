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
 * @author		Björn Heyser <bheyser@databay.de>
 * @version		$Id$
 *
 * @package		Modules/Test
 */
class ilTestRandomQuestionSetSourcePoolDefinition
{
    /**
     * global $ilDB object instance
     *
     * @var ilDBInterface
     */
    protected $db = null;

    /**
     * object instance of current test
     *
     * @var ilObjTest
     */
    protected $testOBJ = null;

    private $id = null;

    private $poolId = null;

    /** @var null|int */
    private $poolRefId = null;

    private $poolTitle = null;

    private $poolPath = null;

    private $poolQuestionCount = null;

    /**
     * @var array taxId => [nodeId, ...]
     */
    private $originalTaxonomyFilter = [];

    /**
     * @var array taxId => [nodeId, ...]
     */
    private $mappedTaxonomyFilter = [];

    /**
     * @var array
     */
    private $typeFilter = [];
    // fau.
    // fau.

    /**
     * @var array
     */
    private $lifecycleFilter = [];

    private $questionAmount = null;

    private $sequencePosition = null;

    public function __construct(ilDBInterface $db, ilObjTest $testOBJ)
    {
        $this->db = $db;
        $this->testOBJ = $testOBJ;
    }

    public function setId(int $id)
    {
        $this->id = $id;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setPoolId($poolId)
    {
        $this->poolId = $poolId;
    }

    public function getPoolId()
    {
        return $this->poolId;
    }

    public function getPoolRefId(): ?int
    {
        return $this->poolRefId;
    }

    public function setPoolRefId(?int $poolRefId): void
    {
        $this->poolRefId = $poolRefId;
    }

    public function setPoolTitle($poolTitle)
    {
        $this->poolTitle = $poolTitle;
    }

    public function getPoolTitle()
    {
        return $this->poolTitle;
    }

    public function setPoolPath($poolPath)
    {
        $this->poolPath = $poolPath;
    }

    public function getPoolPath()
    {
        return $this->poolPath;
    }

    public function setPoolQuestionCount($poolQuestionCount)
    {
        $this->poolQuestionCount = $poolQuestionCount;
    }

    public function getPoolQuestionCount()
    {
        return $this->poolQuestionCount;
    }

    // fau: taxFilter/typeFilter - new setters/getters
    /**
     * get the original taxonomy filter conditions
     * @return array	taxId => [nodeId, ...]
     */
    public function getOriginalTaxonomyFilter(): array
    {
        return $this->originalTaxonomyFilter;
    }

    /**
     * set the original taxonomy filter condition
     * @param  array taxId => [nodeId, ...]
     */
    public function setOriginalTaxonomyFilter($filter = [])
    {
        $this->originalTaxonomyFilter = $filter;
    }

    /**
     * get the original taxonomy filter for insert into the database
     * @return null|string		serialized taxonomy filter
     */
    private function getOriginalTaxonomyFilterForDbValue(): ?string
    {
        // TODO-RND2017: migrate to separate table for common selections by e.g. statistics
        return empty($this->originalTaxonomyFilter) ? null : serialize($this->originalTaxonomyFilter);
    }

    /**
     * get the original taxonomy filter from database value
     * @param null|string		serialized taxonomy filter
     */
    private function setOriginalTaxonomyFilterFromDbValue($value)
    {
        // TODO-RND2017: migrate to separate table for common selections by e.g. statistics
        $this->originalTaxonomyFilter = empty($value) ? [] : unserialize($value);
    }

    /**
     * get the mapped taxonomy filter conditions
     * @return 	array	taxId => [nodeId, ...]
     */
    public function getMappedTaxonomyFilter(): array
    {
        return $this->mappedTaxonomyFilter;
    }

    /**
     * set the original taxonomy filter condition
     * @param array 	taxId => [nodeId, ...]
     */
    public function setMappedTaxonomyFilter($filter = [])
    {
        $this->mappedTaxonomyFilter = $filter;
    }

    /**
     * get the original taxonomy filter for insert into the database
     * @return null|string		serialized taxonomy filter
     */
    private function getMappedTaxonomyFilterForDbValue(): ?string
    {
        return empty($this->mappedTaxonomyFilter) ? null : serialize($this->mappedTaxonomyFilter);
    }

    /**
     * get the original taxonomy filter from database value
     * @param null|string		serialized taxonomy filter
     */
    private function setMappedTaxonomyFilterFromDbValue($value)
    {
        $this->mappedTaxonomyFilter = empty($value) ? [] : unserialize($value);
    }


    /**
     * set the mapped taxonomy filter from original by applying a keys map
     * @param ilQuestionPoolDuplicatedTaxonomiesKeysMap $taxonomiesKeysMap
     */
    public function mapTaxonomyFilter(ilQuestionPoolDuplicatedTaxonomiesKeysMap $taxonomiesKeysMap)
    {
        $this->mappedTaxonomyFilter = [];
        foreach ($this->originalTaxonomyFilter as $taxId => $nodeIds) {
            $mappedNodeIds = [];
            foreach ($nodeIds as $nodeId) {
                $mappedNodeIds[] = $taxonomiesKeysMap->getMappedTaxNodeId($nodeId);
            }
            $this->mappedTaxonomyFilter[$taxonomiesKeysMap->getMappedTaxonomyId($taxId)] = $mappedNodeIds;
        }
    }

    public function setTypeFilter($typeFilter = [])
    {
        $this->typeFilter = $typeFilter;
    }

    public function getTypeFilter(): array
    {
        return $this->typeFilter;
    }

    /**
     * get the question type filter for insert into the database
     */
    private function getTypeFilterForDbValue(): ?string
    {
        return empty($this->typeFilter) ? null : serialize($this->typeFilter);
    }

    /**
     * get the question type filter from database value
     */
    private function setTypeFilterFromDbValue(?string $value)
    {
        $this->typeFilter = empty($value) ? [] : unserialize($value);
    }

    public function getLifecycleFilter(): array
    {
        return $this->lifecycleFilter;
    }

    public function setLifecycleFilter(array $lifecycle_filter): void
    {
        $this->lifecycleFilter = $lifecycle_filter;
    }

    public function getLifecycleFilterForDbValue(): ?string
    {
        return empty($this->lifecycleFilter) ? null : serialize($this->lifecycleFilter);
    }

    public function setLifecycleFilterFromDbValue(?string $db_value)
    {
        $this->lifecycleFilter = empty($db_value) ? [] : unserialize($db_value);
    }

    /**
     * Get the type filter as a list of type tags
     * @return string[]
     */
    public function getTypeFilterAsTypeTags(): array
    {
        $map = [];
        foreach (ilObjQuestionPool::_getQuestionTypes(true) as $row) {
            $map[$row['question_type_id']] = $row['type_tag'];
        }

        $tags = [];
        foreach ($this->typeFilter as $type_id) {
            if (isset($map[$type_id])) {
                $tags[] = $map[$type_id];
            }
        }

        return $tags;
    }

    /**
     * Set the type filter from a list of type tags
     * @param string[] $tags
     */
    public function setTypeFilterFromTypeTags(array $tags)
    {
        $map = [];
        foreach (ilObjQuestionPool::_getQuestionTypes(true) as $row) {
            $map[$row['type_tag']] = $row['question_type_id'];
        }

        $this->typeFilter = [];
        foreach ($tags as $type_tag) {
            if (isset($map[$type_tag])) {
                $this->typeFilter[] = $map[$type_tag];
            }
        }
    }

    public function setQuestionAmount($questionAmount)
    {
        $this->questionAmount = $questionAmount;
    }

    public function getQuestionAmount()
    {
        return $this->questionAmount;
    }

    public function setSequencePosition($sequencePosition)
    {
        $this->sequencePosition = $sequencePosition;
    }

    public function getSequencePosition()
    {
        return $this->sequencePosition;
    }

    // -----------------------------------------------------------------------------------------------------------------

    /**
     * @param array $dataArray
     */
    public function initFromArray($dataArray)
    {
        foreach ($dataArray as $field => $value) {
            switch ($field) {
                case 'def_id':
                    $this->setId($value);
                    break;
                case 'pool_fi':
                    $this->setPoolId($value);
                    break;
                case 'pool_ref_id':
                    $this->setPoolRefId($value ? (int) $value : null);
                    break;
                case 'pool_title':
                    $this->setPoolTitle($value);
                    break;
                case 'pool_path':
                    $this->setPoolPath($value);
                    break;
                case 'pool_quest_count':
                    $this->setPoolQuestionCount($value);
                    break;
                case 'origin_tax_filter':
                    $this->setOriginalTaxonomyFilterFromDbValue($value);
                    break;
                case 'mapped_tax_filter':
                    $this->setMappedTaxonomyFilterFromDbValue($value);
                    break;
                case 'type_filter':
                    $this->setTypeFilterFromDbValue($value);
                    break;
                case 'lifecycle_filter':
                    $this->setLifecycleFilterFromDbValue($value);
                    break;
                    // fau.
                case 'quest_amount':
                    $this->setQuestionAmount($value);
                    break;
                case 'sequence_pos':
                    $this->setSequencePosition($value);
                    break;
            }
        }
    }

    /**
     * @param integer $poolId
     * @return boolean
     */
    public function loadFromDb($id): bool
    {
        $res = $this->db->queryF(
            "SELECT * FROM tst_rnd_quest_set_qpls WHERE def_id = %s",
            array('integer'),
            array($id)
        );

        while ($row = $this->db->fetchAssoc($res)) {
            $this->initFromArray($row);

            return true;
        }

        return false;
    }

    public function saveToDb()
    {
        if ($this->getId()) {
            $this->updateDbRecord($this->testOBJ->getTestId());
        } else {
            $this->insertDbRecord($this->testOBJ->getTestId());
        }
    }

    public function cloneToDbForTestId($testId)
    {
        $this->insertDbRecord($testId);
    }

    public function deleteFromDb()
    {
        $this->db->manipulateF(
            "DELETE FROM tst_rnd_quest_set_qpls WHERE def_id = %s",
            array('integer'),
            array($this->getId())
        );
    }

    /**
     * @param $testId
     */
    private function updateDbRecord($testId)
    {
        $this->db->update(
            'tst_rnd_quest_set_qpls',
            [
                'test_fi' => array('integer', $testId),
                'pool_fi' => array('integer', $this->getPoolId()),
                'pool_ref_id' => array('integer', $this->getPoolRefId()),
                'pool_title' => array('text', $this->getPoolTitle()),
                'pool_path' => array('text', $this->getPoolPath()),
                'pool_quest_count' => array('integer', $this->getPoolQuestionCount()),
                'origin_tax_filter' => array('text', $this->getOriginalTaxonomyFilterForDbValue()),
                'mapped_tax_filter' => array('text', $this->getMappedTaxonomyFilterForDbValue()),
                'type_filter' => array('text', $this->getTypeFilterForDbValue()),
                'lifecycle_filter' => array('text', $this->getLifecycleFilterForDbValue()),
                'quest_amount' => array('integer', $this->getQuestionAmount()),
                'sequence_pos' => array('integer', $this->getSequencePosition())
            ],
            [
                'def_id' => array('integer', $this->getId())
            ]
        );
    }

    /**
     * @param $testId
     */
    private function insertDbRecord(int $test_id): void
    {
        $next_id = $this->db->nextId('tst_rnd_quest_set_qpls');

        $this->db->insert('tst_rnd_quest_set_qpls', [
                'def_id' => array('integer', $next_id),
                'test_fi' => array('integer', $next_id),
                'pool_fi' => array('integer', $this->getPoolId()),
                'pool_ref_id' => array('integer', $this->getPoolRefId()),
                'pool_title' => array('text', $this->getPoolTitle()),
                'pool_path' => array('text', $this->getPoolPath()),
                'pool_quest_count' => array('integer', $this->getPoolQuestionCount()),
                'origin_tax_filter' => array('text', $this->getOriginalTaxonomyFilterForDbValue()),
                'mapped_tax_filter' => array('text', $this->getMappedTaxonomyFilterForDbValue()),
                'type_filter' => array('text', $this->getTypeFilterForDbValue()),
                'lifecycle_filter' => array('text', $this->getLifecycleFilterForDbValue()),
                'quest_amount' => array('integer', $this->getQuestionAmount()),
                'sequence_pos' => array('integer', $this->getSequencePosition())
        ]);

        $this->setId($next_id);
    }

    // -----------------------------------------------------------------------------------------------------------------

    public function getPoolInfoLabel(ilLanguage $lng): string
    {
        $pool_path = $this->getPoolPath();
        if (is_int($this->getPoolRefId()) && ilObject::_lookupObjId($this->getPoolRefId())) {
            $path = new ilPathGUI();
            $path->enableTextOnly(true);
            $pool_path = $path->getPath(ROOT_FOLDER_ID, (int) $this->getPoolRefId());
        }

        $poolInfoLabel = sprintf(
            $lng->txt('tst_random_question_set_source_questionpool_summary_string'),
            $this->getPoolTitle(),
            $pool_path,
            $this->getPoolQuestionCount()
        );

        return $poolInfoLabel;
    }

    // -----------------------------------------------------------------------------------------------------------------
}
