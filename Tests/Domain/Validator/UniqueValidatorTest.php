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
 * Class UniqueValidatorTest
 */
class UniqueValidatorTest extends \TYPO3\TestingFramework\Core\Unit\UnitTestCase
{
    /**
     * @var \Evoweb\SfRegister\Validation\Validator\UniqueValidator
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
        $this->testingFramework->createTemplate($pageUid, array('include_static_file' => 'EXT:sf_register/Configuration/TypoScript/'));
        $this->testingFramework->createFakeFrontEnd($pageUid);

        /** @var \Evoweb\SfRegister\Validation\Validator\UniqueValidator $fixture */
        $this->fixture = $this->getAccessibleMock(
            'Evoweb\\SfRegister\\Validation\\Validator\\UniqueValidator',
            array('dummy'),
            array('global' => FALSE)
        );
    }

    /**
     * @return void
     */
    public function tearDown()
    {
        $this->testingFramework->cleanUp();

        unset($this->testingFramework);
    }

    /**
     * @test
     * @return void
     */
    public function isValidReturnsTrueIfCountOfValueInFieldReturnsZeroForLocalSearch()
    {
        $fieldname = 'username';
        $expected = 'myValue';

        $repositoryMock = $this->getMock('Evoweb\\SfRegister\\Domain\\Repository\\FrontendUserRepository', array(), array(), '', FALSE);
        $repositoryMock->expects($this->once())
            ->method('countByField')
            ->with($fieldname, $expected)
            ->will($this->returnValue(0));
        $this->fixture->injectUserRepository($repositoryMock);
        $this->fixture->setPropertyName($fieldname);

        $this->assertTrue($this->fixture->isValid($expected));
    }

    /**
     * @test
     * @return void
     */
    public function isValidReturnsFalseIfCountOfValueInFieldReturnsHigherThenZeroForLocalSearch()
    {
        $fieldname = 'username';
        $expected = 'myValue';

        $repositoryMock = $this->getMock('Evoweb\\SfRegister\\Domain\\Repository\\FrontendUserRepository', array(), array(), '', FALSE);
        $repositoryMock->expects($this->once())
            ->method('countByField')
            ->with($fieldname, $expected)
            ->will($this->returnValue(1));
        $this->fixture->injectUserRepository($repositoryMock);
        $this->fixture->setPropertyName($fieldname);

        $this->assertFalse($this->fixture->isValid($expected));
    }

    /**
     * @test
     * @return void
     */
    public function isValidReturnsTrueIfCountOfValueInFieldReturnsZeroForLocalAndGlobalSearch()
    {
        $fieldname = 'username';
        $expected = 'myValue';

        $repositoryMock = $this->getMock('Evoweb\\SfRegister\\Domain\\Repository\\FrontendUserRepository', array(), array(), '', FALSE);
        $repositoryMock->expects($this->once())
            ->method('countByField')
            ->with($fieldname, $expected)
            ->will($this->returnValue(0));
        $repositoryMock->expects($this->any())
            ->method('countByFieldGlobal')
            ->with($fieldname, $expected)
            ->will($this->returnValue(0));
        $this->fixture->injectUserRepository($repositoryMock);
        $this->fixture->setPropertyName($fieldname);

        $this->assertTrue($this->fixture->isValid($expected));
    }

    /**
     * @test
     * @return void
     */
    public function isValidReturnsFalseIfCountOfValueInFieldReturnsZeroForLocalAndHigherThenZeroForGlobalSearch()
    {
        $fieldname = 'username';
        $expected = 'myValue';

        $repositoryMock = $this->createMock(
            \Evoweb\SfRegister\Domain\Repository\FrontendUserRepository::class
        );
        $repositoryMock->expects($this->once())
            ->method('countByField')
            ->with($fieldname, $expected)
            ->will($this->returnValue(0));
        $repositoryMock->expects($this->any())
            ->method('countByFieldGlobal')
            ->with($fieldname, $expected)
            ->will($this->returnValue(1));
        $this->fixture->injectUserRepository($repositoryMock);
        $this->fixture->setPropertyName($fieldname);

        $this->assertFalse($this->fixture->isValid($expected));
    }
}
