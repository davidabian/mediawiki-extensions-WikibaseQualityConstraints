<?php

namespace WikibaseQuality\ConstraintReport\Tests\ValueCountChecker;

use PHPUnit4And6Compat;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Reference;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Snak\PropertySomeValueSnak;
use Wikibase\Repo\Tests\NewItem;
use Wikibase\Repo\Tests\NewStatement;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\MultiValueChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\MainSnakContext;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\QualifierContext;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\ReferenceContext;
use WikibaseQuality\ConstraintReport\Tests\ConstraintParameters;
use WikibaseQuality\ConstraintReport\Tests\ResultAssertions;

/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\MultiValueChecker
 *
 * @group WikibaseQualityConstraints
 *
 * @author Lucas Werkmeister
 * @license GPL-2.0-or-later
 */
class MultiValueCheckerTest extends \PHPUnit\Framework\TestCase {

	use ConstraintParameters, PHPUnit4And6Compat, ResultAssertions;

	/**
	 * @var MultiValueChecker
	 */
	private $checker;

	protected function setUp() {
		parent::setUp();

		$this->checker = new MultiValueChecker( $this->getConstraintParameterParser() );
	}

	public function testMultiValueConstraint_One() {
		$statement = NewStatement::noValueFor( 'P1' )->build();
		$item = NewItem::withId( 'Q1' )
			->andStatement( $statement )
			->build();
		$context = new MainSnakContext( $item, $statement );
		$constraint = $this->getConstraintMock();

		$checkResult = $this->checker->checkConstraint( $context, $constraint );

		$this->assertViolation( $checkResult, 'wbqc-violation-message-multi-value' );
	}

	public function testMultiValueConstraint_Two() {
		$statement1 = NewStatement::noValueFor( 'P1' )->build();
		$statement2 = NewStatement::noValueFor( 'P1' )->build();
		$item = NewItem::withId( 'Q1' )
			->andStatement( $statement1 )
			->andStatement( $statement2 )
			->build();
		$context = new MainSnakContext( $item, $statement1 );
		$constraint = $this->getConstraintMock();

		$checkResult = $this->checker->checkConstraint( $context, $constraint );

		$this->assertCompliance( $checkResult );
	}

	public function testMultiValueConstraint_TwoButOneDeprecated() {
		$statement1 = NewStatement::noValueFor( 'P1' )->build();
		$statement2 = NewStatement::noValueFor( 'P1' )
			->withDeprecatedRank()
			->build();
		$item = NewItem::withId( 'Q1' )
			->andStatement( $statement1 )
			->andStatement( $statement2 )
			->build();
		$context = new MainSnakContext( $item, $statement1 );
		$constraint = $this->getConstraintMock();

		$checkResult = $this->checker->checkConstraint( $context, $constraint );

		$this->assertViolation( $checkResult, 'wbqc-violation-message-multi-value' );
	}

	public function testMultiValueConstraint_TwoAndWithSameSeparator() {
		$statement1 = NewStatement::noValueFor( 'P1' )
			->withQualifier( 'P2', 'foo' )
			->build();
		$statement2 = NewStatement::noValueFor( 'P1' )
			->withQualifier( 'P2', 'foo' )
			->build();
		$item = NewItem::withId( 'Q1' )
			->andStatement( $statement1 )
			->andStatement( $statement2 )
			->build();
		$context = new MainSnakContext( $item, $statement1 );
		$constraint = $this->getConstraintMock(
			$this->separatorsParameter( [ 'P2' ] )
		);

		$checkResult = $this->checker->checkConstraint( $context, $constraint );

		$this->assertCompliance( $checkResult );
	}

	public function testMultiValueConstraint_TwoButWithDifferentSeparator() {
		$statement1 = NewStatement::noValueFor( 'P1' )
			->withQualifier( 'P2', 'foo' )
			->build();
		$statement2 = NewStatement::noValueFor( 'P1' )
			->withQualifier( 'P2', 'bar' )
			->build();
		$item = NewItem::withId( 'Q1' )
			->andStatement( $statement1 )
			->andStatement( $statement2 )
			->build();
		$context = new MainSnakContext( $item, $statement1 );
		$constraint = $this->getConstraintMock(
			$this->separatorsParameter( [ 'P2' ] )
		);

		$checkResult = $this->checker->checkConstraint( $context, $constraint );

		$this->assertViolation( $checkResult, 'wbqc-violation-message-multi-value-separators' );
	}

	public function testMultiValueConstraint_One_Qualifier() {
		$qualifier1 = new PropertyNoValueSnak( new PropertyId( 'P1' ) );
		$qualifier2 = new PropertyNoValueSnak( new PropertyId( 'P2' ) );
		$statement = NewStatement::someValueFor( 'P10' )->build();
		$statement->getQualifiers()->addSnak( $qualifier1 );
		$statement->getQualifiers()->addSnak( $qualifier2 );
		$item = NewItem::withId( 'Q1' )
			->andStatement( $statement )
			->build();
		$context = new QualifierContext( $item, $statement, $qualifier1 );
		$constraint = $this->getConstraintMock();

		$checkResult = $this->checker->checkConstraint( $context, $constraint );

		$this->assertViolation( $checkResult, 'wbqc-violation-message-multi-value' );
	}

	public function testMultiValueConstraint_Two_Reference() {
		$referenceSnak1 = new PropertyNoValueSnak( new PropertyId( 'P1' ) );
		$referenceSnak2 = new PropertySomeValueSnak( new PropertyId( 'P1' ) );
		$reference = new Reference( [ $referenceSnak1, $referenceSnak2 ] );
		$statement = NewStatement::someValueFor( 'P10' )->build();
		$statement->getReferences()->addReference( $reference );
		$item = NewItem::withId( 'Q1' )
			->andStatement( $statement )
			->build();
		$context = new ReferenceContext( $item, $statement, $reference, $referenceSnak1 );
		$constraint = $this->getConstraintMock();

		$checkResult = $this->checker->checkConstraint( $context, $constraint );

		$this->assertCompliance( $checkResult );
	}

	public function testSingleValueConstraintDeprecatedStatement() {
		$statement = NewStatement::noValueFor( 'P1' )
			->withDeprecatedRank()
			->build();
		$entity = NewItem::withId( 'Q1' )
			->build();
		$context = new MainSnakContext( $entity, $statement );
		$constraint = $this->getConstraintMock();

		$checkResult = $this->checker->checkConstraint( $context, $constraint );

		$this->assertDeprecation( $checkResult );
	}

	public function testCheckConstraintParameters() {
		$constraint = $this->getConstraintMock();

		$result = $this->checker->checkConstraintParameters( $constraint );

		$this->assertEmpty( $result );
	}

	/**
	 * @param array $parameters
	 *
	 * @return Constraint
	 */
	private function getConstraintMock( array $parameters = [] ) {
		$mock = $this
			->getMockBuilder( Constraint::class )
			->disableOriginalConstructor()
			->getMock();
		$mock->expects( $this->any() )
			->method( 'getConstraintParameters' )
			->will( $this->returnValue( $parameters ) );
		$mock->expects( $this->any() )
			->method( 'getConstraintTypeItemId' )
			->will( $this->returnValue( 'Q21510857' ) );

		return $mock;
	}

}
