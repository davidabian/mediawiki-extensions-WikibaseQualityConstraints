<?php

namespace WikibaseQuality\ConstraintReport\Tests\ConstraintChecker;

use DataValues\TimeValue;
use LogicException;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\NowValue;

/**
 * @covers WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\NowValue
 *
 * @group WikibaseQualityConstraints
 *
 * @author Lucas Werkmeister
 * @license GNU GPL v2+
 */
class NowValueTest extends \PHPUnit_Framework_TestCase {

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
