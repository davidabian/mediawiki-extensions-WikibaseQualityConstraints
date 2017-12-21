<?php

namespace WikibaseQuality\ConstraintReport\Tests\Api;

use HashBagOStuff;
use TimeAdjustableWANObjectCache;
use WANObjectCache;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\ItemIdParser;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\Lib\Store\EntityRevisionLookup;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Api\CachingResultsBuilder;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Api\ResultsBuilder;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachedCheckConstraintsResponse;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachingMetadata;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\DependencyMetadata;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\Metadata;

include_once __DIR__ . '/../../../../../tests/phpunit/includes/libs/objectcache/WANObjectCacheTest.php';

/**
 * @covers \WikibaseQuality\ConstraintReport\ConstraintCheck\Api\CachingResultsBuilder
 *
 * @license GNU GPL v2+
 */
class CachingResultsBuilderTest extends \PHPUnit_Framework_TestCase {

	public function testGetAndStoreResults_SameResults() {
		$expectedResults = new CachedCheckConstraintsResponse(
			[ 'Q100' => 'garbage data, should not matter' ],
			Metadata::blank()
		);
		$q100 = new ItemId( 'Q100' );
		$resultsBuilder = $this->getMock( ResultsBuilder::class );
		$resultsBuilder->expects( $this->once() )
			->method( 'getResults' )
			->with( [ $q100 ], [], null )
			->willReturn( $expectedResults );
		$cachingResultsBuilder = new CachingResultsBuilder(
			$resultsBuilder,
			WANObjectCache::newEmpty(),
			$this->getMock( EntityRevisionLookup::class ),
			$this->getMock( EntityIdParser::class ),
			86400
		);

		$results = $cachingResultsBuilder->getAndStoreResults( [ $q100 ], [], null );

		$this->assertSame( $expectedResults, $results );
	}

	public function testGetAndStoreResults_DontCacheClaimIds() {
		$expectedResults = new CachedCheckConstraintsResponse(
			[ 'Q100' => 'garbage data, should not matter' ],
			Metadata::blank()
		);
		$resultsBuilder = $this->getMock( ResultsBuilder::class );
		$resultsBuilder->expects( $this->once() )
			->method( 'getResults' )
			->with( [], [ 'fake' ], null )
			->willReturn( $expectedResults );
		$cache = $this->getMockBuilder( WANObjectCache::class )
			->disableOriginalConstructor()
			->getMock();
		$cache->expects( $this->never() )->method( 'set' );
		$lookup = $this->getMock( EntityRevisionLookup::class );
		$lookup->expects( $this->never() )->method( 'getLatestRevisionId ' );
		$cachingResultsBuilder = new CachingResultsBuilder(
			$resultsBuilder,
			$cache,
			$lookup,
			$this->getMock( EntityIdParser::class ),
			86400
		);

		$cachingResultsBuilder->getAndStoreResults( [], [ 'fake' ], null );
	}

	public function testGetAndStoreResults_DontCacheWithConstraintIds() {
		$expectedResults = new CachedCheckConstraintsResponse(
			[ 'Q100' => 'garbage data, should not matter' ],
			Metadata::blank()
		);
		$q100 = new ItemId( 'Q100' );
		$resultsBuilder = $this->getMock( ResultsBuilder::class );
		$resultsBuilder->expects( $this->once() )
			->method( 'getResults' )
			->with( [ $q100 ], [], [ 'fake' ] )
			->willReturn( $expectedResults );
		$cache = $this->getMockBuilder( WANObjectCache::class )
			->disableOriginalConstructor()
			->getMock();
		$cache->expects( $this->never() )->method( 'set' );
		$lookup = $this->getMock( EntityRevisionLookup::class );
		$lookup->expects( $this->never() )->method( 'getLatestRevisionId ' );
		$cachingResultsBuilder = new CachingResultsBuilder(
			$resultsBuilder,
			$cache,
			$lookup,
			$this->getMock( EntityIdParser::class ),
			86400
		);

		$cachingResultsBuilder->getAndStoreResults( [ $q100 ], [], [ 'fake' ] );
	}

	public function testGetAndStoreResults_StoreResults() {
		$expectedResults = new CachedCheckConstraintsResponse(
			[ 'Q100' => 'garbage data, should not matter' ],
			Metadata::blank()
		);
		$q100 = new ItemId( 'Q100' );
		$resultsBuilder = $this->getMock( ResultsBuilder::class );
		$resultsBuilder->expects( $this->once() )
			->method( 'getResults' )
			->with( [ $q100 ], [], null )
			->willReturn( $expectedResults );
		$cache = new WANObjectCache( [ 'cache' => new HashBagOStuff() ] );
		$lookup = $this->getMock( EntityRevisionLookup::class );
		$cachingResultsBuilder = new CachingResultsBuilder(
			$resultsBuilder,
			$cache,
			$lookup,
			$this->getMock( EntityIdParser::class ),
			86400
		);

		$cachingResultsBuilder->getAndStoreResults( [ $q100 ], [], null );
		$cachedResults = $cache->get( $cachingResultsBuilder->getKey( $q100 ) );

		$this->assertNotNull( $cachedResults );
		$this->assertArrayHasKey( 'results', $cachedResults );
		$this->assertSame( $expectedResults->getArray()['Q100'], $cachedResults['results'] );
	}

	public function testGetAndStoreResults_StoreLatestRevisionIds() {
		$q100 = new ItemId( 'Q100' );
		$q101 = new ItemId( 'Q101' );
		$p102 = new PropertyId( 'P102' );
		$expectedResults = new CachedCheckConstraintsResponse(
			[ 'Q100' => 'garbage data, should not matter' ],
			Metadata::ofDependencyMetadata( DependencyMetadata::merge( [
				DependencyMetadata::ofEntityId( $q100 ),
				DependencyMetadata::ofEntityId( $q101 ),
				DependencyMetadata::ofEntityId( $p102 ),
			] ) )
		);
		$revisionIds = [
			$q100->getSerialization() => 12345,
			$q101->getSerialization() => 1337,
			$p102->getSerialization() => 42,
		];
		$resultsBuilder = $this->getMock( ResultsBuilder::class );
		$resultsBuilder->expects( $this->once() )
			->method( 'getResults' )
			->with( [ $q100 ], [], null )
			->willReturn( $expectedResults );
		$cache = new WANObjectCache( [ 'cache' => new HashBagOStuff() ] );
		$lookup = $this->getMock( EntityRevisionLookup::class );
		$lookup->expects( $this->atLeast( 3 ) )
			->method( 'getLatestRevisionId' )
			->willReturnCallback( function( EntityId $entityId ) use ( $revisionIds ) {
				$serialization = $entityId->getSerialization();
				$this->assertArrayHasKey( $serialization, $revisionIds );
				return $revisionIds[$serialization];
			} );
		$cachingResultsBuilder = new CachingResultsBuilder(
			$resultsBuilder,
			$cache,
			$lookup,
			$this->getMock( EntityIdParser::class ),
			86400
		);

		$cachingResultsBuilder->getAndStoreResults( [ $q100 ], [], null );
		$cachedResults = $cache->get( $cachingResultsBuilder->getKey( $q100 ) );

		$this->assertNotNull( $cachedResults );
		$this->assertArrayHasKey( 'latestRevisionIds', $cachedResults );
		$this->assertSame( $revisionIds, $cachedResults['latestRevisionIds'] );
	}

	public function testGetStoredResults_CacheMiss() {
		$cachingResultsBuilder = new CachingResultsBuilder(
			$this->getMock( ResultsBuilder::class ),
			WANObjectCache::newEmpty(),
			$this->getMock( EntityRevisionLookup::class ),
			$this->getMock( EntityIdParser::class ),
			86400
		);

		$response = $cachingResultsBuilder->getStoredResults( new ItemId( 'Q1' ) );

		$this->assertNull( $response );
	}

	public function testGetStoredResults_Outdated() {
		$entityRevisionLookup = $this->getMock( EntityRevisionLookup::class );
		$entityRevisionLookup->method( 'getLatestRevisionId' )
			->willReturnCallback( function( EntityId $entityId ) {
				switch ( $entityId->getSerialization() ) {
					case 'Q5':
						return 100;
					case 'Q10':
						return 101;
				}
			} );
		$cache = new WANObjectCache( [ 'cache' => new HashBagOStuff() ] );
		$cachingResultsBuilder = new CachingResultsBuilder(
			$this->getMock( ResultsBuilder::class ),
			$cache,
			$entityRevisionLookup,
			new ItemIdParser(),
			86400
		);
		$q5 = new ItemId( 'Q5' );
		$key = $cachingResultsBuilder->getKey( $q5 );
		$value = [
			'results' => 'garbage data, should not matter',
			'latestRevisionIds' => [
				'Q5' => 100,
				'Q10' => 99,
			],
		];
		$cache->set( $key, $value );

		$response = $cachingResultsBuilder->getStoredResults( $q5 );

		$this->assertNull( $response );
	}

	public function testGetStoredResults_Fresh() {
		$entityRevisionLookup = $this->getMock( EntityRevisionLookup::class );
		$entityRevisionLookup->method( 'getLatestRevisionId' )
			->willReturnCallback( function( EntityId $entityId ) {
				switch ( $entityId->getSerialization() ) {
					case 'Q5':
						return 100;
					case 'Q10':
						return 99;
				}
			} );
		$cache = new TimeAdjustableWANObjectCache( [ 'cache' => new HashBagOStuff() ] );
		$now = microtime( true );
		if ( ( $now - 1337 ) + 1337 !== $now ) {
			$this->markTestSkipped( 'Unix time outside float accuracy range?!' );
		}
		$cache->setTime( $now - 1337 );
		$cachingResultsBuilder = new CachingResultsBuilder(
			$this->getMock( ResultsBuilder::class ),
			$cache,
			$entityRevisionLookup,
			new ItemIdParser(),
			86400
		);
		$cachingResultsBuilder->setMicrotimeFunction( function ( $get_as_float ) use ( $now ) {
			$this->assertTrue( $get_as_float );
			return $now;
		} );
		$q5 = new ItemId( 'Q5' );
		$key = $cachingResultsBuilder->getKey( $q5 );
		$expectedResults = 'garbage data, should not matter';
		$value = [
			'results' => $expectedResults,
			'latestRevisionIds' => [
				'Q5' => 100,
				'Q10' => 99,
			],
		];
		$cache->set( $key, $value );

		$response = $cachingResultsBuilder->getStoredResults( $q5 );

		$this->assertNotNull( $response );
		$actualResults = $response->getArray();
		$this->assertSame( [ 'Q5' => $expectedResults ], $actualResults );
		$cachingMetadata = $response->getMetadata()->getCachingMetadata();
		$this->assertTrue( $cachingMetadata->isCached() );
		$maxAgeInSeconds = $cachingMetadata->getMaximumAgeInSeconds();
		$this->assertSame( 1337, $maxAgeInSeconds );
	}

	public function testGetResults_EmptyCache() {
		$cachingResultsBuilder = $this->getMockBuilder( CachingResultsBuilder::class )
			->disableOriginalConstructor()
			->setMethods( [ 'getStoredResults', 'getAndStoreResults' ] )
			->getMock();
		$cachingResultsBuilder->method( 'getStoredResults' )->willReturn( null );
		$cachingResultsBuilder->method( 'getAndStoreResults' )
			->willReturnCallback( function( $entityIds, $claimIds, $constraintIds ) {
				$this->assertSame( [], $claimIds );
				$this->assertNull( $constraintIds );
				$results = [];
				foreach ( $entityIds as $entityId ) {
					$serialization = $entityId->getSerialization();
					$results[$serialization] = 'garbage of ' . $serialization;
				}
				return new CachedCheckConstraintsResponse( $results, Metadata::blank() );
			} );
		/** @var CachingResultsBuilder $cachingResultsBuilder */

		$results = $cachingResultsBuilder->getResults(
			[ new ItemId( 'Q5' ), new ItemId( 'Q10' ) ],
			[],
			null
		);

		$expected = [ 'Q5' => 'garbage of Q5', 'Q10' => 'garbage of Q10' ];
		$actual = $results->getArray();
		asort( $expected );
		asort( $actual );
		$this->assertSame( $expected, $actual );
		$this->assertFalse( $results->getMetadata()->getCachingMetadata()->isCached() );
	}

	public function testGetResults_ConstraintIds() {
		$entityIds = [ new ItemId( 'Q5' ), new ItemId( 'Q10' ) ];
		$statementIds = [];
		$constraintIds = [ 'P12$11a14ea5-10dc-425b-b94d-6e65997be983' ];
		$expected = new CachedCheckConstraintsResponse(
			[ 'Q5' => 'garbage of Q5', 'Q10' => 'some garbage of Q10' ],
			Metadata::ofCachingMetadata( CachingMetadata::ofMaximumAgeInSeconds( 5 * 60 ) )
		);
		$cachingResultsBuilder = $this->getMockBuilder( CachingResultsBuilder::class )
			->disableOriginalConstructor()
			->setMethods( [ 'getStoredResults', 'getAndStoreResults' ] )
			->getMock();
		$cachingResultsBuilder->expects( $this->never() )->method( 'getStoredResults' );
		$cachingResultsBuilder->expects( $this->once() )->method( 'getAndStoreResults' )
			->with( $entityIds, $statementIds, $constraintIds )
			->willReturn( $expected );
		/** @var CachingResultsBuilder $cachingResultsBuilder */

		$results = $cachingResultsBuilder->getResults( $entityIds, $statementIds, $constraintIds );

		$this->assertSame( $expected->getArray(), $results->getArray() );
		$this->assertEquals( $expected->getMetadata(), $results->getMetadata() );
	}

	public function testGetResults_StatementIds() {
		$entityIds = [];
		$statementIds = [ 'Q5$9c009c6f-fdf5-41d1-86e9-e790427e3dc6' ];
		$constraintIds = [];
		$expected = new CachedCheckConstraintsResponse(
			[ 'Q5' => 'some garbage of Q5' ],
			Metadata::ofCachingMetadata( CachingMetadata::ofMaximumAgeInSeconds( 5 * 60 ) )
		);
		$cachingResultsBuilder = $this->getMockBuilder( CachingResultsBuilder::class )
			->disableOriginalConstructor()
			->setMethods( [ 'getStoredResults', 'getAndStoreResults' ] )
			->getMock();
		$cachingResultsBuilder->expects( $this->never() )->method( 'getStoredResults' );
		$cachingResultsBuilder->expects( $this->once() )->method( 'getAndStoreResults' )
			->with( $entityIds, $statementIds, $constraintIds )
			->willReturn( $expected );
		/** @var CachingResultsBuilder $cachingResultsBuilder */

		$results = $cachingResultsBuilder->getResults( $entityIds, $statementIds, $constraintIds );

		$this->assertSame( $expected->getArray(), $results->getArray() );
		$this->assertEquals( $expected->getMetadata(), $results->getMetadata() );
	}

	public function testGetResults_FullyCached() {
		$entityIds = [ new ItemId( 'Q5' ), new ItemId( 'Q10' ) ];
		$cachingResultsBuilder = $this->getMockBuilder( CachingResultsBuilder::class )
			->disableOriginalConstructor()
			->setMethods( [ 'getStoredResults', 'getAndStoreResults' ] )
			->getMock();
		$cachingResultsBuilder->expects( $this->exactly( 2 ) )->method( 'getStoredResults' )
			->willReturnCallback( function ( EntityId $entityId ) {
				$serialization = $entityId->getSerialization();
				return new CachedCheckConstraintsResponse(
					[ $serialization => 'garbage of ' . $serialization ],
					Metadata::ofCachingMetadata( CachingMetadata::ofMaximumAgeInSeconds( 64800 ) )
				);
			} );
		$cachingResultsBuilder->expects( $this->never() )->method( 'getAndStoreResults' );
		/** @var CachingResultsBuilder $cachingResultsBuilder */

		$results = $cachingResultsBuilder->getResults( $entityIds, [], null );

		$expected = [ 'Q5' => 'garbage of Q5', 'Q10' => 'garbage of Q10' ];
		$actual = $results->getArray();
		asort( $expected );
		asort( $actual );
		$this->assertSame( $expected, $actual );
		$this->assertSame( 64800, $results->getMetadata()->getCachingMetadata()->getMaximumAgeInSeconds() );
	}

	public function testGetResults_PartiallyCached() {
		$entityIds = [ new ItemId( 'Q5' ), new ItemId( 'Q10' ) ];
		$cachingResultsBuilder = $this->getMockBuilder( CachingResultsBuilder::class )
			->disableOriginalConstructor()
			->setMethods( [ 'getStoredResults', 'getAndStoreResults' ] )
			->getMock();
		$cachingResultsBuilder->expects( $this->exactly( 2 ) )->method( 'getStoredResults' )
			->willReturnCallback( function ( EntityId $entityId ) {
				if ( $entityId->getSerialization() === 'Q5' ) {
					return new CachedCheckConstraintsResponse(
						[ 'Q5' => 'garbage of Q5' ],
						Metadata::ofCachingMetadata( CachingMetadata::ofMaximumAgeInSeconds( 64800 ) )
					);
				} else {
					return null;
				}
			} );
		$cachingResultsBuilder->expects( $this->once() )->method( 'getAndStoreResults' )
			->with( [ $entityIds[1] ], [], null )
			->willReturn( new CachedCheckConstraintsResponse(
				[ 'Q10' => 'garbage of Q10' ],
				Metadata::ofDependencyMetadata( DependencyMetadata::ofEntityId( $entityIds[1] ) )
			) );
		/** @var CachingResultsBuilder $cachingResultsBuilder */

		$results = $cachingResultsBuilder->getResults( $entityIds, [], null );

		$expected = [ 'Q5' => 'garbage of Q5', 'Q10' => 'garbage of Q10' ];
		$actual = $results->getArray();
		asort( $expected );
		asort( $actual );
		$this->assertSame( $expected, $actual );
		$metadata = $results->getMetadata();
		$this->assertSame( 64800, $metadata->getCachingMetadata()->getMaximumAgeInSeconds() );
		$this->assertSame( [ $entityIds[1] ], $metadata->getDependencyMetadata()->getEntityIds() );
	}

}
