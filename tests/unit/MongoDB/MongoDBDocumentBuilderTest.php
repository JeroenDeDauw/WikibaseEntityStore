<?php

namespace Wikibase\EntityStore\MongoDB;

use Deserializers\Exceptions\DeserializationException;
use Wikibase\DataModel\Entity\BasicEntityIdParser;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Term\AliasGroup;
use Wikibase\DataModel\Term\AliasGroupList;
use Wikibase\DataModel\Term\Fingerprint;
use Wikibase\DataModel\Term\Term;
use Wikibase\DataModel\Term\TermList;
use Wikibase\EntityStore\EntityStore;
use Wikibase\EntityStore\EntityStoreOptions;

/**
 * @covers Wikibase\EntityStore\MongoDB\MongoDBDocumentBuilder
 *
 * @licence GPLv2+
 * @author Thomas Pellissier Tanon
 */
class MongoDBDocumentBuilderTest extends \PHPUnit_Framework_TestCase {

	public function testBuildDocumentForEntity() {
		$item = new Item(
			new ItemId( 'Q1' ),
			new Fingerprint(
				new TermList( array( new Term( 'en', 'foo' ) ) ),
				new TermList( array( new Term( 'en', 'bar' ) ) ),
				new AliasGroupList( array( new AliasGroup( 'fr', array( 'bÊz', 'bat' ) ) ) )
			)
		);

		$entitySerializerMock = $this->getMock( 'Serializers\Serializer' );
		$entitySerializerMock->expects( $this->once() )
			->method( 'serialize' )
			->with( $this->equalTo( $item ) )
			->willReturn( array( 'id' => 'Q1' ) );

		$entityDeserializerMock = $this->getMock( 'Deserializers\Deserializer' );

		$documentBuilder = new MongoDBDocumentBuilder(
			$entitySerializerMock,
			$entityDeserializerMock,
			new BasicEntityIdParser(),
			new EntityStoreOptions(array( EntityStore::OPTION_LANGUAGES => null ) )
		);

		$this->assertEquals(
			array(
				'_id' => 'Q1',
				'id' => 'Q1',
				'sterms' => array(
					'en' => array( 'foo' ),
					'fr' => array( 'bêz', 'bat' )
				)
			),
			$documentBuilder->buildDocumentForEntity( $item )
		);
	}

	public function testBuildDocumentForEntityWithLanguageOption() {
		$item = new Item(
			new ItemId( 'Q1' ),
			new Fingerprint(
				new TermList( array( new Term( 'en', 'foo' ), new Term( 'de', 'bar' ) ) ),
				new TermList( array( new Term( 'en', 'bar' ) ) ),
				new AliasGroupList( array(
					new AliasGroup( 'fr', array( 'baz', 'bat' ) ),
					new AliasGroup( 'it', array( 'foo' ) )
				) )
			)
		);

		$entitySerializerMock = $this->getMock( 'Serializers\Serializer' );
		$entitySerializerMock->expects( $this->once() )
			->method( 'serialize' )
			->with( $this->equalTo( $item ) )
			->willReturn( array( 'id' => 'Q1' ) );

		$entityDeserializerMock = $this->getMock( 'Deserializers\Deserializer' );

		$documentBuilder = new MongoDBDocumentBuilder(
			$entitySerializerMock,
			$entityDeserializerMock,
			new BasicEntityIdParser(),
			new EntityStoreOptions(array( EntityStore::OPTION_LANGUAGES => array( 'en', 'fr' ) ) )
		);

		$this->assertEquals(
			array(
				'_id' => 'Q1',
				'id' => 'Q1',
				'sterms' => array(
					'en' => array( 'foo' ),
					'fr' => array( 'baz', 'bat' )
				)
			),
			$documentBuilder->buildDocumentForEntity( $item )
		);
	}

	public function testBuildEntityForDocument() {
		$item = new Item( new ItemId( 'Q1' ) );

		$entitySerializerMock = $this->getMock( 'Serializers\Serializer' );

		$entityDeserializerMock = $this->getMock( 'Deserializers\Deserializer' );
		$entityDeserializerMock->expects( $this->once() )
			->method( 'deserialize' )
			->with( $this->equalTo( array( 'id' => 'Q1' ) ) )
			->willReturn( $item );

		$documentBuilder = new MongoDBDocumentBuilder(
			$entitySerializerMock,
			$entityDeserializerMock,
			new BasicEntityIdParser(),
			new EntityStoreOptions(array( EntityStore::OPTION_LANGUAGES => null ) )
		);

		$this->assertEquals(
			$item,
			$documentBuilder->buildEntityForDocument( array( 'id' => 'Q1' ) )
		);
	}

	public function testBuildEntityForDocumentWithException() {
		$entitySerializerMock = $this->getMock( 'Serializers\Serializer' );

		$entityDeserializerMock = $this->getMock( 'Deserializers\Deserializer' );
		$entityDeserializerMock->expects( $this->once() )
			->method( 'deserialize' )
			->with( $this->equalTo( array( 'i' => 'Q1' ) ) )
			->willThrowException( new DeserializationException() );

		$documentBuilder = new MongoDBDocumentBuilder(
			$entitySerializerMock,
			$entityDeserializerMock,
			new BasicEntityIdParser(),
			new EntityStoreOptions(array( EntityStore::OPTION_LANGUAGES => null ) )
		);

		$this->assertEquals(
			null,
			$documentBuilder->buildEntityForDocument( array( 'i' => 'Q1' ) )
		);
	}

	/**
	 * @dataProvider cleanTextForSearchProvider
	 */
	public function testBuildTermForSearch( $text, $cleaned ) {
		$entitySerializerMock = $this->getMock( 'Serializers\Serializer' );
		$entityDeserializerMock = $this->getMock( 'Deserializers\Deserializer' );
		$documentBuilder = new MongoDBDocumentBuilder(
			$entitySerializerMock,
			$entityDeserializerMock,
			new BasicEntityIdParser(),
			new EntityStoreOptions(array( EntityStore::OPTION_LANGUAGES => null ) )
		);

		$this->assertEquals(
			$cleaned,
			$documentBuilder->cleanTextForSearch( $text )
		);
	}

	public function cleanTextForSearchProvider() {
		return array(
			array(
				'test',
				'test'
			),
			array(
				'TODO',
				'todo'
			),
			array(
				'Être',
				'être'
			),
			array(
				'FOO-BAR\'BAZ',
				'foo bar baz'
			),
			array(
				'\'test-',
				'test'
			),
		);
	}

	public function testBuildEntityIdForDocument() {
		$entitySerializerMock = $this->getMock( 'Serializers\Serializer' );
		$entityDeserializerMock = $this->getMock( 'Deserializers\Deserializer' );
		$documentBuilder = new MongoDBDocumentBuilder(
			$entitySerializerMock,
			$entityDeserializerMock,
			new BasicEntityIdParser(),
			new EntityStoreOptions(array( EntityStore::OPTION_LANGUAGES => null ) )
		);

		$this->assertEquals(
			new ItemId( 'Q42' ),
			$documentBuilder->buildEntityIdForDocument( array( '_id' => 'Q42' ) )
		);
	}

	public function testBuildEntityIdForDocumentWithException() {
		$entitySerializerMock = $this->getMock( 'Serializers\Serializer' );
		$entityDeserializerMock = $this->getMock( 'Deserializers\Deserializer' );
		$documentBuilder = new MongoDBDocumentBuilder(
			$entitySerializerMock,
			$entityDeserializerMock,
			new BasicEntityIdParser(),
			new EntityStoreOptions(array( EntityStore::OPTION_LANGUAGES => null ) )
		);

		$this->setExpectedException( 'Wikibase\DataModel\Entity\EntityIdParsingException' );
		$documentBuilder->buildEntityIdForDocument( array() );
	}
}
