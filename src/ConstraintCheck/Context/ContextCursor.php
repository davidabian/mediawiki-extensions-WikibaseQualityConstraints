<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Context;

/**
 * A context cursor encapsulates the location
 * where a check result serialization for a certain Context should be stored.
 *
 * @license GNU GPL v2+
 */
interface ContextCursor {

	/**
	 * Store the check result serialization $result
	 * at the appropriate location for this context in $container.
	 *
	 * Mainly used in the API, where $container is part of the API response.
	 *
	 * If $result is null, don’t actually store it,
	 * but still populate the appropriate location for the context in $container
	 * (by creating all intermediate path elements of the location where $result would be stored).
	 *
	 * @param array|null $result
	 * @param array[] &$container
	 */
	public function storeCheckResultInArray( array $result = null, array &$container );

}
