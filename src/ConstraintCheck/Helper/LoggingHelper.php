<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Helper;

use Config;
use IBufferingStatsdDataFactory;
use Psr\Log\LoggerInterface;
use Wikibase\DataModel\Entity\EntityId;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\Context;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;

/**
 * Helper class for tracking and logging messages.
 *
 * @author Lucas Werkmeister
 * @license GPL-2.0-or-later
 */
class LoggingHelper {

	/**
	 * @var IBufferingStatsdDataFactory
	 */
	private $dataFactory;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var float[]
	 */
	private $constraintCheckDurationLimits;

	/**
	 * @param IBufferingStatsdDataFactory $dataFactory,
	 * @param LoggerInterface $logger
	 * @param Config $config
	 */
	public function __construct(
		IBufferingStatsdDataFactory $dataFactory,
		LoggerInterface $logger,
		Config $config
	) {
		$this->dataFactory = $dataFactory;
		$this->logger = $logger;
		$this->constraintCheckDurationLimits = [
			'info' => $config->get( 'WBQualityConstraintsCheckDurationInfoSeconds' ),
			'warning' => $config->get( 'WBQualityConstraintsCheckDurationWarningSeconds' ),
		];
	}

	/**
	 * Log a constraint check.
	 * The constraint check is tracked on the statsd data factory,
	 * and also logged with the logger interface if it took longer than a certain time.
	 * Multiple limits corresponding to different log levels can be specified in the configuration;
	 * checks that exceed a higher limit are logged at a more severe level.
	 *
	 * @param Context $context
	 * @param Constraint $constraint
	 * @param CheckResult $result
	 * @param string $constraintCheckerClass
	 * @param float $durationSeconds
	 * @param string $method Use __METHOD__.
	 */
	public function logConstraintCheck(
		Context $context,
		Constraint $constraint,
		CheckResult $result,
		$constraintCheckerClass,
		$durationSeconds,
		$method
	) {
		$constraintCheckerClassShortName = substr( strrchr( $constraintCheckerClass, '\\' ), 1 );
		$constraintTypeItemId = $constraint->getConstraintTypeItemId();

		$this->dataFactory->timing(
			'wikibase.quality.constraints.check.timing.' .
				$constraintTypeItemId . '-' .
				$constraintCheckerClassShortName,
			$durationSeconds * 1000
		);

		// find the longest limit (and associated log level) that the duration exceeds
		foreach ( $this->constraintCheckDurationLimits as $level => $limit ) {
			if (
				// duration exceeds this limit
				isset( $limit ) && $durationSeconds > $limit &&
				// this limit is longer than previous longest limit
				( !isset( $limitSeconds ) || $limit > $limitSeconds )
			) {
				$limitSeconds = $limit;
				$logLevel = $level;
			}
		}

		if ( !isset( $limitSeconds ) ) {
			return;
		}
		if ( $context->getType() !== Context::TYPE_STATEMENT ) {
			// TODO log less details but still log something
			return;
		}

		$resultMessage = $result->getMessage();
		if ( $resultMessage !== null ) {
			$resultMessageKey = $resultMessage->getMessageKey();
		} else {
			$resultMessageKey = null;
		}

		$this->logger->log(
			$logLevel,
			'Constraint check with {constraintCheckerClassShortName} ' .
			'took longer than {limitSeconds} second(s) ' .
			'(duration: {durationSeconds} seconds).',
			[
				'method' => $method,
				'loggingMethod' => __METHOD__,
				'durationSeconds' => $durationSeconds,
				'limitSeconds' => $limitSeconds,
				'constraintId' => $constraint->getConstraintId(),
				'constraintPropertyId' => $constraint->getPropertyId()->getSerialization(),
				'constraintTypeItemId' => $constraintTypeItemId,
				'constraintParameters' => $constraint->getConstraintParameters(),
				'constraintCheckerClass' => $constraintCheckerClass,
				'constraintCheckerClassShortName' => $constraintCheckerClassShortName,
				'entityId' => $context->getEntity()->getId()->getSerialization(),
				'statementGuid' => $context->getSnakStatement()->getGuid(),
				'resultStatus' => $result->getStatus(),
				'resultParameters' => $result->getParameters(),
				'resultMessage' => $resultMessageKey,
			]
		);
	}

	/**
	 * Log a cache hit for a complete constraint check result for the given entity ID.
	 *
	 * @param EntityId $entityId
	 */
	public function logCheckConstraintsCacheHit( EntityId $entityId ) {
		$this->dataFactory->increment(
			'wikibase.quality.constraints.cache.entity.hit'
		);
	}

	/**
	 * Log cache misses for a complete constraint check result for the given entity IDs.
	 *
	 * @param EntityId[] $entityIds
	 */
	public function logCheckConstraintsCacheMisses( array $entityIds ) {
		$this->dataFactory->updateCount(
			'wikibase.quality.constraints.cache.entity.miss',
			count( $entityIds )
		);
	}

	/**
	 * Log that the dependency metadata for a check result had an empty set of entity IDs.
	 * This should never happen – at least the entity being checked should always be contained.
	 */
	public function logEmptyDependencyMetadata() {
		$this->logger->log(
			'warning',
			'Dependency metadata for constraint check result had empty set of entity IDs.',
			[
				'loggingMethod' => __METHOD__,
				// callers of this method don’t have much information to pass to us,
				// so for now we don’t log any other structured data
				// and hope that the request URL provides enough information
			]
		);
	}

	/**
	 * Log that the dependency metadata for a check result has a very large set of entity IDs.
	 *
	 * @param EntityId[] $entityIds
	 * @param int $maxRevisionIds
	 */
	public function logHugeDependencyMetadata( array $entityIds, $maxRevisionIds ) {
		$this->logger->log(
			'warning',
			'Dependency metadata for constraint check result has huge set of entity IDs ' .
			'(count ' . count( $entityIds ) . ', limit ' . $maxRevisionIds . '); ' .
			'caching disabled for this check result.',
			[
				'loggingMethod' => __METHOD__,
				'entityIds' => array_map(
					function ( EntityId $entityId ) {
						return $entityId->getSerialization();
					},
					$entityIds
				),
				'maxRevisionIds' => $maxRevisionIds,
			]
		);
	}

}
