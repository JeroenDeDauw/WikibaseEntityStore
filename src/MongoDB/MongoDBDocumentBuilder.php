<?php

namespace Wikibase\EntityStore\MongoDB;

use Deserializers\Deserializer;
use Deserializers\Exceptions\DeserializationException;
use MongoBinData;
use Serializers\Serializer;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Entity\EntityIdParsingException;
use Wikibase\EntityStore\EntityStore;
use Wikibase\EntityStore\EntityStoreOptions;

/**
 * Internal class
 *
 * @licence GPLv2+
 * @author Thomas Pellissier Tanon
 */
class MongoDBDocumentBuilder {

	/**
	 * @var Serializer
	 */
	private $entitySerializer;

	/**
	 * @var Deserializer
	 */
	private $entityDeserializer;

	/**
	 * @var EntityIdParser
	 */
	private $entityIdParser;

	/**
	 * @var EntityStoreOptions
	 */
	private $options;

	/**
	 * @param Serializer $entitySerializer
	 * @param Deserializer $entityDeserializer
	 * @param EntityIdParser $entityIdParser
	 * @param EntityStoreOptions $options
	 */
	public function __construct(
		Serializer $entitySerializer,
		Deserializer $entityDeserializer,
		EntityIdParser $entityIdParser,
		EntityStoreOptions $options
	) {
		$this->entitySerializer = $entitySerializer;
		$this->entityDeserializer = $entityDeserializer;
		$this->entityIdParser = $entityIdParser;
		$this->options = $options;
	}

	/**
	 * @param EntityDocument $entityDocument
	 * @return array
	 */
	public function buildDocumentForEntity( EntityDocument $entityDocument ) {
		return $this->addIndexedDataToSerialization(
			$this->filterLanguages( $this->entitySerializer->serialize( $entityDocument ) )
		);
	}

	private function addIndexedDataToSerialization( array $serialization ) {
		$serialization['_id'] = $serialization['id'];
		$serialization['sterms'] = $this->buildSearchTermsForEntity( $serialization );

		return $serialization;
	}

	private function filterLanguages( array $serialization ) {
		$languagesOption = $this->options->getOption( EntityStore::OPTION_LANGUAGES );

		if( $languagesOption === null ) {
			return $serialization;
		}

		$languages = array_flip( $languagesOption );
		if( array_key_exists( 'labels', $serialization ) ) {
			$serialization['labels'] = array_intersect_key( $serialization['labels'], $languages );
		}
		if( array_key_exists( 'descriptions', $serialization ) ) {
			$serialization['descriptions'] = array_intersect_key( $serialization['descriptions'], $languages );
		}
		if( array_key_exists( 'aliases', $serialization ) ) {
			$serialization['aliases'] = array_intersect_key( $serialization['aliases'], $languages );
		}

		return $serialization;
	}

	private function buildSearchTermsForEntity( array $serialization ) {
		$searchTerms = array();

		if( array_key_exists( 'labels', $serialization ) ) {
			foreach( $serialization['labels'] as $label ) {
				$searchTerms[$label['language']][] = $this->cleanTextForSearch( $label['value'] );
			}
		}

		if( array_key_exists( 'aliases', $serialization ) ) {
			foreach( $serialization['aliases'] as $aliasGroup ) {
				foreach( $aliasGroup as $alias ) {
					$searchTerms[$alias['language']][] = $this->cleanTextForSearch( $alias['value'] );
				}
			}
		}

		return $searchTerms;
	}

	/**
	 * @param string $text
	 * @return string
	 */
	public function cleanTextForSearch( $text ) {
		$text = mb_strtolower( $text, 'UTF-8' ); //TODO: said to be very slow
		$text = trim( $text );

		return new MongoBinData( $text, MongoBinData::GENERIC );
	}

	/**
	 * @param array $document
	 * @return EntityDocument|null
	 */
	public function buildEntityForDocument( array $document ) {
		try {
			return $this->entityDeserializer->deserialize( $document );
		} catch( DeserializationException $exception ) {
			return null;
		}
	}

	/**
	 * @param array $document
	 * @return EntityId
	 * @throws EntityIdParsingException
	 */
	public function buildEntityIdForDocument( array $document ) {
		if( !array_key_exists( '_id', $document ) ) {
			throw new EntityIdParsingException();
		}

		return $this->entityIdParser->parse( $document['_id'] );
	}
}
