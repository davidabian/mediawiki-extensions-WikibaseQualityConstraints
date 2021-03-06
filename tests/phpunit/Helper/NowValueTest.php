<?php

namespace WikibaseQuality\ConstraintReport\Tests\Helper;

use DataValues\TimeValue;
use LogicException;
use PHPUnit4And6Compat;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\NowValue;

/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\NowValue
 *
 * @group WikibaseQualityConstraints
 *
 * @author Lucas Werkmeister
 * @license GPL-2.0-or-later
 */
class NowValueTest extends \PHPUnit\Framework\TestCase {
	use PHPUnit4And6Compat;

	public function testGetTime() {
		$now = new NowValue();

		// note: this assertion may randomly fail on very rare occasions;
		// if it’s not reproducible, you can almost certainly ignore it
		$this->assertSame( gmdate( '+Y-m-d\TH:i:s\Z' ), $now->getTime() );
	}

	public function testGetTimezone() {
		$now = new NowValue();

		$this->assertSame( 0, $now->getTimezone() );
	}

	public function testGetCalendarModel() {
		$now = new NowValue();

		$this->assertSame( TimeValue::CALENDAR_GREGORIAN, $now->getCalendarModel() );
	}

	public function testSerialize() {
		$now = new NowValue();

		$this->setExpectedException( LogicException::class );
		$now->serialize();
	}

	public function testEquals() {
		$this->assertTrue( ( new NowValue() )->equals( new NowValue() ) );
	}

}
