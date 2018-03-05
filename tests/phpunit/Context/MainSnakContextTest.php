<?php

namespace WikibaseQuality\ConstraintReport\Tests\Context;

use Wikibase\DataModel\Statement\Statement;
use Wikibase\Repo\Tests\NewItem;
use Wikibase\Repo\Tests\NewStatement;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\Context;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\MainSnakContext;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\MainSnakContextCursor;

/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Context\AbstractContext
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Context\MainSnakContext
 *
 * @group WikibaseQualityConstraints
 *
 * @author Lucas Werkmeister
 * @license GPL-2.0-or-later
 */
class MainSnakContextTest extends \PHPUnit\Framework\TestCase {

	public function testGetSnak() {
		$entity = NewItem::withId( 'Q1' )->build();
		$statement = NewStatement::noValueFor( 'P1' )->build();
		$context = new MainSnakContext( $entity, $statement );

		$this->assertSame( $statement->getMainSnak(), $context->getSnak() );
	}

	public function testGetEntity() {
		$entity = NewItem::withId( 'Q1' )->build();
		$statement = NewStatement::noValueFor( 'P1' )->build();
		$context = new MainSnakContext( $entity, $statement );

		$this->assertSame( $entity, $context->getEntity() );
	}

	public function testGetType() {
		$entity = NewItem::withId( 'Q1' )->build();
		$statement = NewStatement::noValueFor( 'P1' )->build();
		$context = new MainSnakContext( $entity, $statement );

		$this->assertSame( Context::TYPE_STATEMENT, $context->getType() );
	}

	public function testGetSnakRank() {
		$entity = NewItem::withId( 'Q1' )->build();
		$rank = Statement::RANK_DEPRECATED;
		$statement = NewStatement::noValueFor( 'P1' )
			->withRank( $rank )
			->build();
		$context = new MainSnakContext( $entity, $statement );

		$this->assertSame( $rank, $context->getSnakRank() );
	}

	public function testGetSnakStatement() {
		$entity = NewItem::withId( 'Q1' )->build();
		$statement = NewStatement::noValueFor( 'P1' )->build();
		$context = new MainSnakContext( $entity, $statement );

		$this->assertSame( $statement, $context->getSnakStatement() );
	}

	public function testGetSnakGroup() {
		$statement1 = NewStatement::noValueFor( 'P1' )->build();
		$statement2 = NewStatement::noValueFor( 'P1' )->build();
		$statement3 = NewStatement::noValueFor( 'P2' )
			->withDeprecatedRank()
			->build();
		$entity = NewItem::withId( 'Q1' )
			->andStatement( $statement1 )
			->andStatement( $statement2 )
			->andStatement( $statement3 )
			->build();
		$context = new MainSnakContext( $entity, $statement1 );

		$snakGroup = $context->getSnakGroup();

		$this->assertSame( [ $statement1->getMainSnak(), $statement2->getMainSnak() ], $snakGroup );
	}

	public function testGetCursor() {
		$entity = NewItem::withId( 'Q1' )->build();
		$statement = NewStatement::noValueFor( 'P1' )->build();
		$context = new MainSnakContext( $entity, $statement );

		$cursor = $context->getCursor();

		$this->assertInstanceOf( MainSnakContextCursor::class, $cursor );
	}

}
