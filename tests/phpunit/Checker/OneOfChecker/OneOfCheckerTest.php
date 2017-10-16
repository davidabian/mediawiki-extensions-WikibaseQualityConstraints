<?php

namespace WikibaseQuality\ConstraintReport\Test\OneOfChecker;

use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Snak\PropertySomeValueSnak;
use DataValues\StringValue;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\Repo\Tests\NewItem;
use Wikibase\Repo\Tests\NewStatement;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\OneOfChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\StatementContext;
use WikibaseQuality\ConstraintReport\Tests\ConstraintParameters;
use WikibaseQuality\ConstraintReport\Tests\Fake\FakeSnakContext;
use WikibaseQuality\ConstraintReport\Tests\ResultAssertions;

/**
 * @covers \WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\OneOfChecker
 *
 * @group WikibaseQualityConstraints
 *
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult
 * @uses   \WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser
 *
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class OneOfCheckerTest extends \MediaWikiTestCase {

	use ConstraintParameters, ResultAssertions;

	/**
	 * @var OneOfChecker
	 */
	private $oneOfChecker;

	protected function setUp() {
		parent::setUp();
		$this->oneOfChecker = new OneOfChecker(
			$this->getConstraintParameterParser(),
			$this->getConstraintParameterRenderer()
		);
	}

	public function testOneOfConstraintValid() {
		$statement = NewStatement::forProperty( 'P123' )
			->withValue( new ItemId( 'Q1' ) )
			->build();

		$result = $this->oneOfChecker->checkConstraint(
			new FakeSnakContext( $statement->getMainSnak() ),
			$this->getConstraintMock( $this->itemsParameter( [ 'Q1', 'Q2', 'Q3' ] ) )
		);

		$this->assertCompliance( $result );
	}

	public function testOneOfConstraintInvalid() {
		$statement = NewStatement::forProperty( 'P123' )
			->withValue( new ItemId( 'Q9' ) )
			->build();

		$result = $this->oneOfChecker->checkConstraint(
			new FakeSnakContext( $statement->getMainSnak() ),
			$this->getConstraintMock( $this->itemsParameter( [ 'Q1', 'Q2', 'Q3' ] ) )
		);

		$this->assertViolation( $result, 'wbqc-violation-message-one-of' );
	}

	public function testOneOfConstraintWrongType() {
		$statement = NewStatement::forProperty( 'P123' )
			->withValue( new StringValue( 'Q1' ) )
			->build();

		$result = $this->oneOfChecker->checkConstraint(
			new FakeSnakContext( $statement->getMainSnak() ),
			$this->getConstraintMock( $this->itemsParameter( [ 'Q1', 'Q2', 'Q3' ] ) )
		);

		$this->assertViolation( $result, 'wbqc-violation-message-one-of' );
	}

	public function testOneOfConstraintArraySomevalueNovalue() {
		$somevalueSnak = new PropertySomeValueSnak( new PropertyId( 'P123' ) );
		$novalueSnak = new PropertyNoValueSnak( new PropertyId( 'P123' ) );

		foreach ( [ $somevalueSnak, $novalueSnak ] as $allowed ) {
			foreach ( [ $somevalueSnak, $novalueSnak ] as $present ) {
				$result = $this->oneOfChecker->checkConstraint(
					new FakeSnakContext( $present ),
					$this->getConstraintMock( $this->itemsParameter( [ $allowed ] ) )
				);
				if ( $allowed === $present ) {
					$this->assertCompliance( $result );
				} else {
					$this->assertViolation( $result, 'wbqc-violation-message-one-of' );
				}
			}
		}
	}

	public function testOneOfConstraintDeprecatedStatement() {
		$statement = NewStatement::noValueFor( 'P1' )
			->withDeprecatedRank()
			->build();
		$constraint = $this->getConstraintMock( [] );
		$entity = NewItem::withId( 'Q1' )
			->build();

		$checkResult = $this->oneOfChecker->checkConstraint( new StatementContext( $entity, $statement ), $constraint );

		$this->assertDeprecation( $checkResult );
	}

	public function testCheckConstraintParameters() {
		$constraint = $this->getConstraintMock( [] );

		$result = $this->oneOfChecker->checkConstraintParameters( $constraint );

		$this->assertCount( 1, $result );
	}

	/**
	 * @param string[] $parameters
	 *
	 * @return Constraint
	 */
	private function getConstraintMock( array $parameters ) {
		$mock = $this
			->getMockBuilder( Constraint::class )
			->disableOriginalConstructor()
			->getMock();
		$mock->expects( $this->any() )
			 ->method( 'getConstraintParameters' )
			 ->will( $this->returnValue( $parameters ) );
		$mock->expects( $this->any() )
			 ->method( 'getConstraintTypeItemId' )
			 ->will( $this->returnValue( 'One of' ) );

		return $mock;
	}

	/**
	 * @return EntityDocument
	 */
	private function getEntity() {
		return new Item( new ItemId( 'Q1' ) );
	}

}
