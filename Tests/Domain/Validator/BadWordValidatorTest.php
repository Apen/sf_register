<?php
namespace Evoweb\SfRegister\Tests\Domain\Validator;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2011 Sebastian Fischer <typo3@evoweb.de>
 *  All rights reserved
 *
 *  This class is a backport of the corresponding class of FLOW3.
 *  All credits go to the v5 team.
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * Class BadWordValidatorTest
 */
class BadWordValidatorTest extends \TYPO3\TestingFramework\Core\Unit\UnitTestCase
{
    /**
     * @var \Evoweb\SfRegister\Validation\Validator\BadWordValidator
     */
    protected $fixture;

    /**
     * @var \Tx_Phpunit_Framework
     */
    private $testingFramework;

    /**
     * @return void
     */
    public function setUp()
    {
        $this->testingFramework = new \Tx_Phpunit_Framework('fe_users');
        $pageUid = $this->testingFramework->createFrontEndPage();
        $this->testingFramework->createTemplate(
            $pageUid, array('include_static_file' => 'EXT:sf_register/Configuration/TypoScript/maximal/')
        );
        $this->testingFramework->createFakeFrontEnd($pageUid);

        /** @var \Evoweb\SfRegister\Validation\Validator\BadWordValidator|\TYPO3\CMS\Core\Tests\AccessibleObjectInterface $fixture */
        $fixture = $this->getAccessibleMock('Evoweb\\SfRegister\\Validation\\Validator\\BadWordValidator', array('dummy'));
        $fixture->_set('settings', $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_sfregister.']['settings.']);
        $this->fixture = $fixture;
    }

    /**
     * @return void
     */
    public function tearDown()
    {
        $this->testingFramework->cleanUp();

        unset($this->fixture, $this->testingFramework);
    }

    /**
     * @test
     * @return void
     */
    public function settingsContainsValidTyposcriptSettings()
    {
        $this->assertArrayHasKey(
            'badWordList',
            $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_sfregister.']['settings.']
        );
    }

    /**
     * @test
     * @return void
     */
    public function isValidReturnsFalseForWordOnBadwordlist()
    {
        $words = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(
            ',',
            $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_sfregister.']['settings.']['badWordList']
        );

        $this->assertFalse(
            $this->fixture->isValid(current($words))
        );
    }

    /**
     * @test
     * @return void
     */
    public function isValidReturnsTrueForGoodPassword()
    {
        $this->assertTrue(
            $this->fixture->isValid('4dw$koL')
        );
    }
}
