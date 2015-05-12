<?php

namespace WikidataQuality\ConstraintReport\Test\ConnectionChecker;

use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Claim\Claim;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use DataValues\StringValue;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use WikidataQuality\ConstraintReport\ConstraintCheck\Checker\SymmetricChecker;
use WikidataQuality\ConstraintReport\ConstraintCheck\Helper\ConnectionCheckerHelper;
use WikidataQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper;
use WikidataQuality\Tests\Helper\JsonFileEntityLookup;


/**
 * @covers WikidataQuality\ConstraintReport\ConstraintCheck\Checker\SymmetricChecker
 *
 * @uses   WikidataQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 * @uses   WikidataQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class SymmetricCheckerTest extends \MediaWikiTestCase {

	private $lookup;
	private $helper;
	private $connectionCheckerHelper;
	private $checker;
	private $entity;

	protected function setUp() {
		parent::setUp();
		$this->lookup = new JsonFileEntityLookup( __DIR__ );
		$this->helper = new ConstraintReportHelper();
		$this->connectionCheckerHelper = new ConnectionCheckerHelper();
		$this->checker = new SymmetricChecker( $this->lookup, $this->helper, $this->connectionCheckerHelper );
		$this->entity = $this->lookup->getEntity( new ItemId( 'Q1' ) );
	}

	protected function tearDown() {
		unset( $this->lookup );
		unset( $this->helper );
		unset( $this->connectionCheckerHelper );
		unset( $this->checker );
		unset( $this->entity );
		parent::tearDown();
	}
	
	public function testSymmetricConstraintWithCorrectSpouse() {
		$value = new EntityIdValue( new ItemId( 'Q3' ) );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) ) );

		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock(), $this->entity );
		$this->assertEquals( 'compliance', $checkResult->getStatus(), 'check should comply' );
	}

	public function testSymmetricConstraintWithWrongSpouse() {
		$value = new EntityIdValue( new ItemId( 'Q2' ) );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) ) );

		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock(), $this->entity );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testSymmetricConstraintWithWrongDataValue() {
		$value = new StringValue( 'Q3' );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) ) );

		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock(), $this->entity );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testSymmetricConstraintWithNonExistentEntity() {
		$value = new EntityIdValue( new ItemId( 'Q100' ) );
		$statement = new Statement( new Claim( new PropertyValueSnak( new PropertyId( 'P188' ), $value ) ) );

		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock(), $this->entity );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	public function testSymmetricConstraintNoValueSnak() {
		$statement = new Statement( new Claim( new PropertyNoValueSnak( 1 ) ) );

		$checkResult = $this->checker->checkConstraint( $statement, $this->getConstraintMock(), $this->entity );
		$this->assertEquals( 'violation', $checkResult->getStatus(), 'check should not comply' );
	}

	private function getConstraintMock() {
		$mock = $this
			->getMockBuilder( 'WikidataQuality\ConstraintReport\Constraint' )
			->disableOriginalConstructor()
			->getMock();
		$mock->expects( $this->any() )
			 ->method( 'getConstraintParameter' )
			 ->willReturn( array() );
		$mock->expects( $this->any() )
			 ->method( 'getConstraintTypeQid' )
			 ->willReturn( 'Symmetric' );

		return $mock;
	}

}