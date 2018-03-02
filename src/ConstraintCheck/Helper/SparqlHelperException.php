<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Helper;

use RuntimeException;

/**
 * @license GPL-2.0-or-later
 */
class SparqlHelperException extends RuntimeException {

	public function __construct() {
		parent::__construct( 'The SPARQL query endpoint returned an error.' );
	}

}
