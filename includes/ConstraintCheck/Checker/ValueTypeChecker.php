<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

use Config;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\StatementListProvider;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintStatementParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\TypeCheckerHelper;
use Wikibase\DataModel\Statement\Statement;

/**
 * @package WikibaseQuality\ConstraintReport\ConstraintCheck\Checker
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class ValueTypeChecker implements ConstraintChecker {

	/**
	 * @var ConstraintStatementParameterParser
	 */
	private $constraintParameterParser;

	/**
	 * @var EntityLookup
	 */
	private $entityLookup;

	/**
	 * @var TypeCheckerHelper
	 */
	private $typeCheckerHelper;

	/**
	 * @var Config
	 */
	private $config;

	/**
	 * @param EntityLookup $lookup
	 * @param ConstraintStatementParameterParser $constraintParameterParser
	 * @param TypeCheckerHelper $typeCheckerHelper
	 * @param Config $config
	 */
	public function __construct(
		EntityLookup $lookup,
		ConstraintStatementParameterParser $constraintParameterParser,
		TypeCheckerHelper $typeCheckerHelper,
		Config $config
	) {
		$this->entityLookup = $lookup;
		$this->constraintParameterParser = $constraintParameterParser;
		$this->typeCheckerHelper = $typeCheckerHelper;
		$this->config = $config;
	}

	/**
	 * Checks 'Value type' constraint.
	 *
	 * @param Statement $statement
	 * @param Constraint $constraint
	 * @param EntityDocument|StatementListProvider $entity
	 *
	 * @return CheckResult
	 */
	public function checkConstraint( Statement $statement, Constraint $constraint, EntityDocument $entity ) {
		$parameters = [];
		$constraintParameters = $constraint->getConstraintParameters();

		$classes = $this->constraintParameterParser->parseClassParameter( $constraintParameters, $constraint->getConstraintTypeName() );
		$parameters['class'] = array_map(
			function( $id ) {
				return new ItemId( $id );
			},
			$classes
		);

		$relation = $this->constraintParameterParser->parseRelationParameter( $constraintParameters, $constraint->getConstraintTypeName() );
		if ( $relation === 'instance' ) {
			$relationId = $this->config->get( 'WBQualityConstraintsInstanceOfId' );
		} elseif ( $relation === 'subclass' ) {
			$relationId = $this->config->get( 'WBQualityConstraintsSubclassOfId' );
		}
		$parameters['relation'] = [ $relation ];

		if ( array_key_exists( 'constraint_status', $constraintParameters ) ) {
			$parameters['constraint_status'] = strval( $constraintParameters['constraint_status'] );
		}

		$mainSnak = $statement->getMainSnak();

		/*
		 * error handling:
		 *   $mainSnak must be PropertyValueSnak, neither PropertySomeValueSnak nor PropertyNoValueSnak is allowed
		 */
		if ( !$mainSnak instanceof PropertyValueSnak ) {
			$message = wfMessage( "wbqc-violation-message-value-needed" )->params( $constraint->getConstraintTypeQid() )->escaped();
			return new CheckResult( $entity->getId(), $statement, $constraint->getConstraintTypeQid(), $constraint->getConstraintId(), $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		$dataValue = $mainSnak->getDataValue();

		/*
		 * error handling:
		 *   type of $dataValue for properties with 'Value type' constraint has to be 'wikibase-entityid'
		 */
		if ( $dataValue->getType() !== 'wikibase-entityid' ) {
			$message = wfMessage( "wbqc-violation-message-value-needed-of-type" )->params( $constraint->getConstraintTypeQid(), 'wikibase-entityid' )->escaped();
			return new CheckResult( $entity->getId(), $statement, $constraint->getConstraintTypeQid(), $constraint->getConstraintId(), $parameters, CheckResult::STATUS_VIOLATION, $message );
		}
		/** @var EntityIdValue $dataValue */

		$item = $this->entityLookup->getEntity( $dataValue->getEntityId() );

		if ( !( $item instanceof StatementListProvider ) ) {
			$message = wfMessage( "wbqc-violation-message-value-entity-must-exist" )->escaped();
			return new CheckResult( $entity->getId(), $statement, $constraint->getConstraintTypeQid(), $constraint->getConstraintId(),  $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		$statements = $item->getStatements();

		if ( $this->typeCheckerHelper->hasClassInRelation( $statements, $relationId, $classes ) ) {
			$message = '';
			$status = CheckResult::STATUS_COMPLIANCE;
		} else {
			$message = $this->typeCheckerHelper->getViolationMessage(
				$statement->getPropertyId(),
				$item->getId(),
				$classes,
				'valueType',
				$relation
			);
			$status = CheckResult::STATUS_VIOLATION;
		}

		return new CheckResult( $entity->getId(), $statement, $constraint->getConstraintTypeQid(), $constraint->getConstraintId(), $parameters, $status, $message );
	}

}
