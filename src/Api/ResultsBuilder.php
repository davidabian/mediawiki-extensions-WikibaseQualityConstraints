<?php

namespace WikibaseQuality\ConstraintReport\Api;

use Wikibase\DataModel\Entity\EntityId;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\CachedCheckConstraintsResponse;

/**
 * @author Lucas Werkmeister
 * @license GPL-2.0-or-later
 */
interface ResultsBuilder {

	/**
	 * @param EntityId[] $entityIds
	 * @param string[] $claimIds
	 * @param string[]|null $constraintIds
	 * @param string[] $statuses
	 * @return CachedCheckConstraintsResponse
	 */
	public function getResults(
		array $entityIds,
		array $claimIds,
		array $constraintIds = null,
		array $statuses
	);

}
