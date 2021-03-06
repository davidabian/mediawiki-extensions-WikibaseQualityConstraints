<?php

namespace WikibaseQuality\ConstraintReport\Tests\Cache;

use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachedEntityIds;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\Metadata;

/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachedEntityIds
 *
 * @group WikibaseQualityConstraints
 *
 * @author Lucas Werkmeister
 * @license GPL-2.0-or-later
 */
class CachedEntityIdsTest extends \PHPUnit\Framework\TestCase {

	public function testGetArray() {
		$array = [ 'boolean' => true ];
		$cm = Metadata::blank();

		$cei = new CachedEntityIds( $array, $cm );

		$this->assertSame( $array, $cei->getArray() );
	}

}
