<?php

namespace WikibaseQuality\ConstraintReport\Api;

use FormlessAction;
use IContextSource;
use Page;
use Wikibase\Rdf\RdfVocabulary;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\Store\EntityIdLookup;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\NullResult;
use WikibaseQuality\ConstraintReport\ConstraintReportFactory;
use Wikimedia\Purtle\RdfWriterFactory;

/**
 * Produce constraint check results in RDF.
 * Only returns cached constraint check results for now.
 */
class CheckConstraintsRdf extends FormlessAction {

	/**
	 * @var EntityIdLookup
	 */
	private $entityIdLookup;
	/**
	 * @var ResultsSource
	 */
	private $resultsSource;
	/**
	 * @var RdfVocabulary
	 */
	private $rdfVocabulary;

	public function __construct(
		Page $page,
		IContextSource $context,
		ResultsSource $resultsSource,
		EntityIdLookup $entityIdLookup,
		RdfVocabulary $rdfVocabulary
	) {
		parent::__construct( $page, $context );
		$this->resultsSource = $resultsSource;
		$this->entityIdLookup = $entityIdLookup;
		$this->rdfVocabulary = $rdfVocabulary;
	}

	public static function newFromGlobalState( Page $page, IContextSource $context = null ) {
		$repo = WikibaseRepo::getDefaultInstance();
		$constraintReportFactory = ConstraintReportFactory::getDefaultInstance();

		return new static(
			$page,
			$context,
			$constraintReportFactory->getResultsSource(),
			$repo->getEntityIdLookup(),
			$repo->getRdfVocabulary()
		);
	}

	/**
	 * Return the name of the action this object responds to
	 * @since 1.17
	 *
	 * @return string Lowercase name
	 */
	public function getName() {
		return 'constraintsrdf';
	}

	/**
	 * Whether this action requires the wiki not to be locked
	 * @since 1.17
	 *
	 * @return bool
	 */
	public function requiresWrite() {
		return false;
	}

	/**
	 * @see Action::requiresUnblock
	 *
	 * @return bool Always false.
	 */
	public function requiresUnblock() {
		return false;
	}

	/**
	 * Cleanup GUID string so it's OK for RDF.
	 * Should match what we're doing on RDF generation.
	 * @param string $guid
	 * @return string
	 */
	private function cleanupGuid( $guid ) {
		return preg_replace( '/[^\w-]/', '-', $guid );
	}

	/**
	 * Show something on GET request.
	 * @return string|null Will be added to the HTMLForm if present, or just added to the
	 *     output if not.  Return null to not add anything
	 */
	public function onView() {
		$response = $this->getRequest()->response();
		$this->getOutput()->disable();

		if ( !$this->resultsSource instanceof CachingResultsSource ) {
			// TODO: make configurable whether only cached results are returned
			$response->statusHeader( 501 ); // Not Implemented
			return null;
		}

		$entityId = $this->entityIdLookup->getEntityIdForTitle( $this->getTitle() );
		if ( $entityId === null ) {
			$response->statusHeader( 404 ); // Not Found
			return null;
		}

		$results = $this->resultsSource->getStoredResults( $entityId );
		if ( $results === null ) {
			$response->statusHeader( 204 ); // No Content
			return null;
		}

		$format = 'ttl'; // TODO: make format an option

		$writerFactory = new RdfWriterFactory();
		$formatName = $writerFactory->getFormatName( $format );
		$contentType = $writerFactory->getMimeTypes( $formatName )[0];

		$response->header( "Content-Type: $contentType; charset=UTF-8" );

		$writer = $writerFactory->getWriter( $formatName );
		foreach ( [ RdfVocabulary::NS_STATEMENT, RdfVocabulary::NS_ONTOLOGY ] as $ns ) {
			$writer->prefix( $ns, $this->rdfVocabulary->getNamespaceURI( $ns ) );
		}
		$writer->start();

		foreach ( $results->getArray() as $checkResult ) {
			if ( $checkResult instanceof NullResult ) {
				continue;
			}
			if ( $checkResult->getStatus() === CheckResult::STATUS_BAD_PARAMETERS ) {
				continue;
			}

			$writer->about( RdfVocabulary::NS_STATEMENT,
				$this->cleanupGuid( $checkResult->getContextCursor()->getStatementGuid() ) )
				->say( RdfVocabulary::NS_ONTOLOGY, 'hasViolationForConstraint' )
				->is( RdfVocabulary::NS_STATEMENT,
					$this->cleanupGuid( $checkResult->getConstraint()->getConstraintId() ) );
		}
		$writer->finish();
		echo $writer->drain();
		return null;
	}

}
