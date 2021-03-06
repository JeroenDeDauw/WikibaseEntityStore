<?php

namespace Wikibase\EntityStore\DataModel;

use Wikibase\DataModel\Entity\ItemId;

/**
 * @covers Wikibase\EntityStore\DataModel\SerializedEntity
 *
 * @licence GNU GPL v2+
 * @author Thomas Pellissier Tanon
 */
class SerializedEntityTest extends \PHPUnit_Framework_TestCase {

	public function testConstructorWithNoType() {
		$this->setExpectedException( 'InvalidArgumentException' );
		new SerializedEntity( null, array() );
	}

	public function testGetId() {
		$entity = new SerializedEntity( new ItemId( 'Q1' ), array( 'type' => 'item' ) );
		$this->assertEquals( new ItemId( 'Q1' ), $entity->getId() );
	}

	public function testGetNullId() {
		$entity = new SerializedEntity( null, array( 'type' => 'item' ) );
		$this->assertEquals( null, $entity->getId() );
	}

	public function testGetType() {
		$entity = new SerializedEntity( null, array( 'type' => 'foo' ) );
		$this->assertEquals( 'foo', $entity->getType() );
	}

	public function testGetSerialization() {
		$entity = new SerializedEntity( null, array( 'type' => 'foo' ) );
		$this->assertEquals( array( 'type' => 'foo' ), $entity->getSerialization() );
	}
}
