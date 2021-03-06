<?php

namespace Wikibase\EntityStore\Api;

use Mediawiki\Api\SimpleRequest;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\EntityStore\EntityStore;
use Wikibase\EntityStore\EntityStoreOptions;
use Wikibase\EntityStore\Internal\EntitySerializationFactory;

/**
 * @covers Wikibase\EntityStore\Api\ApiEntityLookup
 *
 * @licence GPLv2+
 * @author Thomas Pellissier Tanon
 */
class ApiEntityLookupTest extends \PHPUnit_Framework_TestCase {

	public function testGetEntityDocumentsForIds() {
		$mediawikiApiMock = $this->getMockBuilder( 'Mediawiki\Api\MediawikiApi' )
			->disableOriginalConstructor()
			->getMock();
		$mediawikiApiMock->expects( $this->once() )
			->method( 'getRequest' )
			->with( $this->equalTo(
				new SimpleRequest(
					'wbgetentities',
					array(
						'ids' => 'Q42|P42'
					)
				)
			) )
			->will( $this->returnValue( array(
				'entities' => array(
					array(
						'id' => 'Q42',
						'type' => 'item'
					),
					array(
						'id' => 'P42',
						'type' => 'property',
						'datatype' => 'string'
					)
				)
			) ) );

		$serializationFactory = new EntitySerializationFactory();
		$lookup = new ApiEntityLookup(
			$mediawikiApiMock,
			$serializationFactory->newEntityDeserializer(),
			new EntityStoreOptions( array(
				EntityStore::OPTION_LANGUAGES => null,
				EntityStore::OPTION_LANGUAGE_FALLBACK => false
			) )
		);

		$this->assertEquals(
			array(
				new Item( new ItemId( 'Q42' ) ),
				new Property( new PropertyId( 'P42' ), null, 'string' )
			),
			$lookup->getEntityDocumentsForIds( array( new ItemId( 'Q42' ), new PropertyId( 'P42' ) ) )
		);
	}

	public function testGetEntityDocumentsForIdsWithEmptyInput() {
		$mediawikiApiMock = $this->getMockBuilder( 'Mediawiki\Api\MediawikiApi' )
			->disableOriginalConstructor()
			->getMock();

		$serializationFactory = new EntitySerializationFactory();
		$lookup = new ApiEntityLookup(
			$mediawikiApiMock,
			$serializationFactory->newEntityDeserializer(),
			new EntityStoreOptions( array(
				EntityStore::OPTION_LANGUAGES => null,
				EntityStore::OPTION_LANGUAGE_FALLBACK => false
			) )
		);

		$this->assertEquals( array(), $lookup->getEntityDocumentsForIds( array() ) );
	}

	public function testGetEntityDocumentForId() {
		$mediawikiApiMock = $this->getMockBuilder( 'Mediawiki\Api\MediawikiApi' )
			->disableOriginalConstructor()
			->getMock();
		$mediawikiApiMock->expects( $this->once() )
			->method( 'getRequest' )
			->with( $this->equalTo(
				new SimpleRequest(
					'wbgetentities',
					array(
						'ids' => 'Q42',
						'languages' => 'en|fr',
						'languagefallback' => true
					)
				)
			) )
			->will( $this->returnValue( array(
				'entities' => array(
					array(
						'id' => 'Q42',
						'type' => 'item'
					)
				)
			) ) );

		$serializationFactory = new EntitySerializationFactory();
		$lookup = new ApiEntityLookup(
			$mediawikiApiMock,
			$serializationFactory->newEntityDeserializer(),
			new EntityStoreOptions( array(
				EntityStore::OPTION_LANGUAGES => array( 'en', 'fr' ),
				EntityStore::OPTION_LANGUAGE_FALLBACK => true
			) )
		);

		$this->assertEquals(
			new Item( new ItemId( 'Q42' ) ),
			$lookup->getEntityDocumentForId( new ItemId( 'Q42' ) )
		);
	}

	public function testGetEntityDocumentWithException() {
		$mediawikiApiMock = $this->getMockBuilder( 'Mediawiki\Api\MediawikiApi' )
			->disableOriginalConstructor()
			->getMock();
		$mediawikiApiMock->expects( $this->once() )
			->method( 'getRequest' )
			->with( $this->equalTo(
				new SimpleRequest(
					'wbgetentities',
					array(
						'ids' => 'Q42',
						'languages' => 'en|fr'
					)
				)
			) )
			->will( $this->returnValue( array( 'entities' => array() ) ) );

		$serializationFactory = new EntitySerializationFactory();
		$lookup = new ApiEntityLookup(
			$mediawikiApiMock,
			$serializationFactory->newEntityDeserializer(),
			new EntityStoreOptions( array(
				EntityStore::OPTION_LANGUAGES => array( 'en', 'fr' ),
				EntityStore::OPTION_LANGUAGE_FALLBACK => false
			) ) );

		$this->setExpectedException( 'Wikibase\EntityStore\EntityNotFoundException');
		$lookup->getEntityDocumentForId( new ItemId( 'Q42' ) );
	}
}
