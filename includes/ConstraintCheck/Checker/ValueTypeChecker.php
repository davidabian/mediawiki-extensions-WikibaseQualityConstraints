<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\Lib\Store\EntityLookup;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintReportHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\TypeCheckerHelper;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Entity\Entity;


/**
 * @package WikibaseQuality\ConstraintReport\ConstraintCheck\Checker
 * @author BP2014N1
 * @license GNU GPL v2+
 */
class ValueTypeChecker implements ConstraintChecker {

	/**
	 * @var ConstraintReportHelper
	 */
	private $helper;

	/**
	 * @var EntityLookup
	 */
	private $entityLookup;

	/**
	 * @var TypeCheckerHelper
	 */
	private $typeCheckerHelper;

	const instanceId = 'P31';
	const subclassId = 'P279';

	/**
	 * @param EntityLookup $lookup
	 * @param ConstraintReportHelper $helper
	 * @param TypeCheckerHelper $typeCheckerHelper
	 */
	public function __construct( EntityLookup $lookup, ConstraintReportHelper $helper, TypeCheckerHelper $typeCheckerHelper ) {
		$this->entityLookup = $lookup;
		$this->helper = $helper;
		$this->typeCheckerHelper = $typeCheckerHelper;
	}

	/**
	 * Checks 'Value type' constraint.
	 *
	 * @param Statement $statement
	 * @param Constraint $constraint
	 * @param Entity $entity
	 *
	 * @return CheckResult
	 */
	public function checkConstraint( Statement $statement, Constraint $constraint, Entity $entity = null ) {
		$constraintName = 'Value type';
		$parameters = array ();
		$constraintParameters = $constraint->getConstraintParameters();

		$classes = false;
		if ( array_key_exists( 'class', $constraintParameters ) ) {
			$classes = explode( ',', $constraintParameters['class'] );
			$parameters['class'] = $this->helper->parseParameterArray( $classes );
		}

		$relation = false;
		if ( array_key_exists( 'relation', $constraintParameters ) ) {
			$relation = $constraintParameters['relation'];
			$parameters['relation'] = $this->helper->parseSingleParameter( $relation, true );
		}

		if ( array_key_exists( 'constraint_status', $constraintParameters ) ) {
			$parameters['constraint_status'] = $this->helper->parseSingleParameter( $constraintParameters['constraint_status'], true );
		}

		$mainSnak = $statement->getClaim()->getMainSnak();

		/*
		 * error handling:
		 *   $mainSnak must be PropertyValueSnak, neither PropertySomeValueSnak nor PropertyNoValueSnak is allowed
		 */
		if ( !$mainSnak instanceof PropertyValueSnak ) {
			$message = wfMessage( "wbqc-violation-message-value-needed" )->params( $constraintName )->escaped();
			return new CheckResult( $statement, $constraint->getConstraintTypeQid(), $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		$dataValue = $mainSnak->getDataValue();

		/*
		 * error handling:
		 *   type of $dataValue for properties with 'Value type' constraint has to be 'wikibase-entityid'
		 *   parameter $constraintParameters['class']  must not be null
		 */
		if ( $dataValue->getType() !== 'wikibase-entityid' ) {
			$message = wfMessage( "wbqc-violation-message-value-needed-of-type" )->params( $constraintName, 'wikibase-entityid' )->escaped();
			return new CheckResult( $statement, $constraint->getConstraintTypeQid(), $parameters, CheckResult::STATUS_VIOLATION, $message );
		}
		if ( !$classes ) {
			$message = wfMessage( "wbqc-violation-message-parameter-needed" )->params( $constraintName, 'class' )->escaped();
			return new CheckResult( $statement, $constraint->getConstraintTypeQid(), $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		/*
		 * error handling:
		 *   parameter $constraintParameters['relation'] must be either 'instance' or 'subclass'
		 */
		if ( $relation === 'instance' ) {
			$relationId = self::instanceId;
		} elseif ( $relation === 'subclass' ) {
			$relationId = self::subclassId;
		} else {
			$message = wfMessage( "wbqc-violation-message-type-relation-instance-or-subclass" )->escaped();
			return new CheckResult( $statement, $constraint->getConstraintTypeQid(), $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		$item = $this->entityLookup->getEntity( $dataValue->getEntityId() );

		if ( !$item ) {
			$message = wfMessage( "wbqc-violation-message-value-entity-must-exist" )->escaped();
			return new CheckResult( $statement, $constraint->getConstraintTypeQid(), $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		$statements = $item->getStatements();

		if ( $this->typeCheckerHelper->hasClassInRelation( $statements, $relationId, $classes ) ) {
			$message = '';
			$status = CheckResult::STATUS_COMPLIANCE;
		} else {
			$message = wfMessage( "wbqc-violation-message-value-type" )->escaped();
			$status = CheckResult::STATUS_VIOLATION;
		}

		return new CheckResult( $statement, $constraint->getConstraintTypeQid(), $parameters, $status, $message );
	}

}