<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Context;

use LogicException;

/**
 * A context cursor that is only associated with an entity,
 * not with any statement or something else within it.
 * It can only be used to partially populate a results container,
 * not to actually store a full check result.
 *
 * @author Lucas Werkmeister
 * @license GPL-2.0-or-later
 */
class EntityContextCursor extends ApiV2ContextCursor {

	/**
	 * @var string
	 */
	private $entityId;

	/**
	 * @param string $entityId
	 */
	public function __construct(
		$entityId
	) {
		$this->entityId = $entityId;
	}

	/**
	 * @codeCoverageIgnore This method is not supported.
	 */
	public function getType() {
		throw new LogicException( 'EntityContextCursor has no full associated context' );
	}

	public function getEntityId() {
		return $this->entityId;
	}

	/**
	 * @codeCoverageIgnore This method is not supported.
	 */
	public function getStatementPropertyId() {
		throw new LogicException( 'EntityContextCursor has no full associated context' );
	}

	/**
	 * @codeCoverageIgnore This method is not supported.
	 */
	public function getStatementGuid() {
		throw new LogicException( 'EntityContextCursor has no full associated context' );
	}

	/**
	 * @codeCoverageIgnore This method is not supported.
	 */
	public function getSnakPropertyId() {
		throw new LogicException( 'EntityContextCursor has no full associated context' );
	}

	/**
	 * @codeCoverageIgnore This method is not supported.
	 */
	public function getSnakHash() {
		throw new LogicException( 'EntityContextCursor has no full associated context' );
	}

	/**
	 * @codeCoverageIgnore This method is not supported.
	 */
	public function &getMainArray( array &$container ) {
		throw new LogicException( 'EntityContextCursor cannot store check results' );
	}

	/**
	 * Populate the result container up to the 'claims' level.
	 *
	 * @param array|null $result must be null
	 * @param array[] &$container
	 */
	public function storeCheckResultInArray( array $result = null, array &$container ) {
		if ( $result !== null ) {
			throw new LogicException( 'EntityContextCursor cannot store check results' );
		}

		$this->getClaimsArray( $container );
	}

}
