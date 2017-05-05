<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Helper;

use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementList;

/**
 * Class for helper functions for the connection checkers.
 *
 * @package WikibaseQuality\ConstraintReport\ConstraintCheck\Helper
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class ConnectionCheckerHelper {

	/**
	 * Checks if there is a statement with a claim using the given property.
	 *
	 * @param StatementList $statementList
	 * @param string $propertyIdSerialization
	 *
	 * @return boolean
	 */
	public function hasProperty( StatementList $statementList, $propertyIdSerialization ) {
		/** @var Statement $statement */
		foreach ( $statementList as $statement ) {
			if ( $statement->getPropertyId()->getSerialization() === $propertyIdSerialization ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Checks if there is a statement with a claim using the given property and having one of the given items as its value.
	 *
	 * @param StatementList $statementList
	 * @param string $propertyIdSerialization
	 * @param string|string[] $itemIdSerializationOrArray
	 *
	 * @return boolean
	 */
	public function hasClaim(
		StatementList $statementList,
		$propertyIdSerialization,
		$itemIdSerializationOrArray
	) {
		/** @var Statement $statement */
		foreach ( $statementList as $statement ) {
			if ( $statement->getPropertyId()->getSerialization() === $propertyIdSerialization ) {
				if ( is_string( $itemIdSerializationOrArray ) ) { // string
					$itemIdSerializationArray = [ $itemIdSerializationOrArray ];
				} else { // array
					$itemIdSerializationArray = $itemIdSerializationOrArray;
				}
				if ( $this->arrayHasClaim( $statement, $itemIdSerializationArray ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * @param Statement $statement
	 * @param string[] $itemIdSerializationArray
	 *
	 * @return bool
	 */
	private function arrayHasClaim( Statement $statement, array $itemIdSerializationArray ) {
		$mainSnak = $statement->getMainSnak();

		if ( $mainSnak instanceof PropertyValueSnak ) {
			$dataValue = $mainSnak->getDataValue();

			return $dataValue instanceof EntityIdValue
				&& in_array(
					$dataValue->getEntityId()->getSerialization(),
					$itemIdSerializationArray,
					true
				);
		}

		return false;
	}

}
