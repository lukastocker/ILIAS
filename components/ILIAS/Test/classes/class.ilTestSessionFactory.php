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

/**
 * Factory for test session
 * @author         Björn Heyser <bheyser@databay.de>
 * @version        $Id$
 * @package        components/ILIAS/Test
 */
class ilTestSessionFactory
{
    /**
     * singleton instances of test sessions
     *
     * @var array<ilTestSession>
     */
    private $testSession = array();

    /**
     * object instance of current test
     * @var ilObjTest
     */
    private $testOBJ = null;

    /**
     * constructor
     * @param ilObjTest $testOBJ
     */
    public function __construct(ilObjTest $testOBJ)
    {
        $this->testOBJ = $testOBJ;
    }

    /**
     * temporarily bugfix for resetting the state of this singleton
     * smeyer
     * --> BH: not required anymore
     */
    public function reset()
    {
        $this->testSession = array();
    }

    /**
     * Creates and returns an instance of a test sequence
     * that corresponds to the current test mode
     *
     * @param integer $activeId
     * @return ilTestSession
     */
    public function getSession($activeId = null)
    {
        if ($activeId === null ||
            $this->testSession === array() ||
            !array_key_exists($activeId, $this->testSession) ||
            $this->testSession[$activeId] === null
        ) {
            $testSession = $this->getNewTestSessionObject();

            $testSession->setRefId($this->testOBJ->getRefId());
            $testSession->setTestId($this->testOBJ->getTestId());

            if ($activeId) {
                $testSession->loadFromDb($activeId);
                $this->testSession[$activeId] = $testSession;
            } else {
                global $DIC;
                $ilUser = $DIC['ilUser'];

                $testSession->loadTestSession(
                    $this->testOBJ->getTestId(),
                    $ilUser->getId(),
                    $testSession->getAccessCodeFromSession()
                );

                return $testSession;
            }
        }

        return $this->testSession[$activeId];
    }

    /**
     * @todo: Björn, we also need to handle the anonymous user here
     * @param integer $userId
     * @return ilTestSession
     */
    public function getSessionByUserId($userId)
    {
        if (!isset($this->testSession[$this->buildCacheKey($userId)])) {
            $testSession = $this->getNewTestSessionObject();

            $testSession->setRefId($this->testOBJ->getRefId());
            $testSession->setTestId($this->testOBJ->getTestId());

            $testSession->loadTestSession($this->testOBJ->getTestId(), $userId);

            $this->testSession[$this->buildCacheKey($userId)] = $testSession;
        }

        return $this->testSession[$this->buildCacheKey($userId)];
    }

    /**
     * @return ilTestSession
     */
    private function getNewTestSessionObject()
    {
        return new ilTestSession();
    }

    /**
     * @param $userId
     * @return string
     */
    private function buildCacheKey($userId): string
    {
        return "{$this->testOBJ->getTestId()}::{$userId}";
    }
}
