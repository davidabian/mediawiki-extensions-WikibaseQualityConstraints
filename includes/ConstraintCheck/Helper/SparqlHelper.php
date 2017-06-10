<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Helper;

use Config;
use Http;
use MediaWiki\MediaWikiServices;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\Rdf\RdfVocabulary;

/**
 * Class for running a SPARQL query on some endpoint and getting the results.
 *
 * @package WikibaseQuality\ConstraintReport\ConstraintCheck\Helper
 * @author Lucas Werkmeister
 * @license GNU GPL v2+
 */
class SparqlHelper {

	const MAX_QUERY_SECONDS = 10;

	/**
	 * @var Config
	 */
	private $config;

	/**
	 * @var string
	 */
	private $entityPrefix;

	/**
	 * @var string
	 */
	private $prefixes;

	/**
	 * @var EntityIdParser
	 */
	private $entityIdParser;

	public function __construct(
		Config $config,
		RdfVocabulary $rdfVocabulary,
		EntityIdParser $entityIdParser
	) {
		$this->config = $config;
		$this->entityIdParser = $entityIdParser;

		$this->entityPrefix = $rdfVocabulary->getNamespaceUri( RdfVocabulary::NS_ENTITY );
		$this->prefixes = <<<EOT
PREFIX wd: <{$rdfVocabulary->getNamespaceUri( RdfVocabulary::NS_ENTITY )}>
PREFIX wds: <{$rdfVocabulary->getNamespaceUri( RdfVocabulary::NS_STATEMENT )}>
PREFIX wdt: <{$rdfVocabulary->getNamespaceUri( RdfVocabulary::NSP_DIRECT_CLAIM )}>
PREFIX p: <{$rdfVocabulary->getNamespaceUri( RdfVocabulary::NSP_CLAIM )}>
PREFIX ps: <{$rdfVocabulary->getNamespaceUri( RdfVocabulary::NSP_CLAIM_STATEMENT )}>
EOT;
	}

	/**
	 * @param string $id entity ID serialization of the entity to check
	 * @param string[] $classes entity ID serializations of the expected types
	 * @param boolean $withInstance true for “instance” relation, false for “subclass” relation
	 * @return boolean
	 */
	public function hasType( $id, array $classes, $withInstance ) {
		$instanceOfId = $this->config->get( 'WBQualityConstraintsInstanceOfId' );
		$subclassOfId = $this->config->get( 'WBQualityConstraintsSubclassOfId' );

		$path = ( $withInstance ? "wdt:$instanceOfId/" : "" ) . "wdt:$subclassOfId*";

		foreach ( array_chunk( $classes, 20 ) as $classesChunk ) {
			$classesValues = implode( ' ', array_map(
				function( $class ) {
					return 'wd:' . $class;
				},
				$classesChunk
			) );

			$query = <<<EOF
ASK {
  BIND(wd:$id AS ?item)
  VALUES ?class { $classesValues }
  ?item $path ?class.
}
EOF;

			$result = $this->runQuery( $query );
			if ( $result['boolean'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param Statement $statement
	 * @return (EntityId|null)[]
	 */
	public function findEntitiesWithSameStatement( Statement $statement ) {
		$pid = $statement->getPropertyId()->serialize();
		$guid = str_replace( '$', '-', $statement->getGuid() );

		$query = <<<EOF
SELECT ?otherEntity WHERE {
  BIND(wds:$guid AS ?statement)
  BIND(p:$pid AS ?p)
  BIND(ps:$pid AS ?ps)
  ?entity ?p ?statement.
  ?statement ?ps ?value.
  ?otherStatement ?ps ?value.
  ?otherEntity ?p ?otherStatement.
  FILTER(?otherEntity != ?entity)
}
LIMIT 10
EOF;

		$result = $this->runQuery( $query );

		return array_map(
			function( $resultBindings ) {
				$entityIRI = $resultBindings['otherEntity']['value'];
				$entityPrefixLength = strlen( $this->entityPrefix );
				if ( substr( $entityIRI, 0, $entityPrefixLength ) === $this->entityPrefix ) {
					try {
						return $this->entityIdParser->parse( substr( $entityIRI, $entityPrefixLength ) );
					} catch ( EntityIdParsingException $e ) {
						// fall through
					}
				}
				return null;
			},
			$result['results']['bindings']
		);
	}

	/**
	 * Runs a query against the configured endpoint and returns the results.
	 *
	 * @param string $query The query, unencoded (plain string).
	 *
	 * @return array The returned JSON data (you typically iterate over ["results"]["bindings"]).
	 */
	public function runQuery( $query ) {

		$endpoint = $this->config->get( 'WBQualityConstraintsSparqlEndpoint' );
		$url = $endpoint . '?' . http_build_query(
			[
				'query' => "#wbqc\n" . $this->prefixes . $query,
				'format' => 'json',
				'maxQueryTimeMillis' => self::MAX_QUERY_SECONDS * 1000,
			],
			null, ini_get( 'arg_separator.output' ),
			// encode spaces with %20, not +
			PHP_QUERY_RFC3986
		);

		$json = Http::get(
			$url,
			[
				'timeout' => self::MAX_QUERY_SECONDS + 1,
			]
		);

		if ( $json === false ) {
			throw new SparqlHelperException();
		}
		$arr = json_decode( $json, true );
		return $arr;
	}

}