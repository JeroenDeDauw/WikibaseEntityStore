<?php

namespace Wikibase\EntityStore\MongoDB;

use Deserializers\Exceptions\DeserializationException;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Term\AliasGroup;
use Wikibase\DataModel\Term\AliasGroupList;
use Wikibase\DataModel\Term\Fingerprint;
use Wikibase\DataModel\Term\Term;
use Wikibase\DataModel\Term\TermList;

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
				new TermList( array( new Term( 'en', 'foo') ) ),
				new TermList( array( new Term( 'en', 'bar') ) ),
				new AliasGroupList( array( new AliasGroup( 'fr', array( 'baz', 'bat' ) ) ) )
			)
		);

		$entitySerializerMock = $this->getMock( 'Serializers\Serializer' );
		$entitySerializerMock->expects( $this->once() )
			->method( 'serialize' )
			->with( $this->equalTo( $item ) )
			->willReturn( array( 'id' => 'Q1' ) );

		$entityDeserializerMock = $this->getMock( 'Deserializers\Deserializer' );

		$documentBuilder = new MongoDBDocumentBuilder( $entitySerializerMock, $entityDeserializerMock );

		$this->assertEquals(
			array(
				'id' => 'Q1',
				'searchterms' => array(
					array( 'language' => 'en', 'value' => 'foo' ),
					array( 'language' => 'fr', 'value' => 'baz' ),
					array( 'language' => 'fr', 'value' => 'bat' )
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

		$documentBuilder = new MongoDBDocumentBuilder( $entitySerializerMock, $entityDeserializerMock );

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

		$documentBuilder = new MongoDBDocumentBuilder( $entitySerializerMock, $entityDeserializerMock );

		$this->assertEquals(
			null,
			$documentBuilder->buildEntityForDocument( array( 'i' => 'Q1' ) )
		);
	}

	/**
	 * @dataProvider buildTermForSearchProvider
	 */
	public function testBuildTermForSearch( Term $term, $serialization ) {
		$entitySerializerMock = $this->getMock( 'Serializers\Serializer' );
		$entityDeserializerMock = $this->getMock( 'Deserializers\Deserializer' );
		$documentBuilder = new MongoDBDocumentBuilder( $entitySerializerMock, $entityDeserializerMock );

		$this->assertEquals(
			$serialization,
			$documentBuilder->buildTermForSearch( $term )
		);
	}

	public function buildTermForSearchProvider() {
		return array(
			array(
				new Term( 'en', 'test' ),
				array(
					'language' => 'en',
					'value' => 'test'
				)
			),
			array(
				new Term( 'fr', 'Être' ),
				array(
					'language' => 'fr',
					'value' => 'être'
				)
			),
			array(
				new Term( 'en', 'FOO-BAR\'BAZ' ),
				array(
					'language' => 'en',
					'value' => 'foo bar baz'
				)
			),
			array(
				new Term( 'en', '\'test-' ),
				array(
					'language' => 'en',
					'value' => 'test'
				)
			),
		);
	}
}
