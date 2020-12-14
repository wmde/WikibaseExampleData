<?php

namespace MediaWiki\Extension\WikibaseExampleData\Tests;

use MediaWiki\Extension\WikibaseExampleData\DataLoader;
use Wikimedia\TestingAccessWrapper;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Snak\PropertySomeValueSnak;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\Lib\Store\EntityStore;
use Wikibase\Lib\Store\EntityRevision;

class DataLoaderTest extends \MediaWikiUnitTestCase {

	private const SOME_DATA_TYPE = 'someDataType';

	private function getDataLoader( $entityStore = null ) {
		return new DataLoader( $entityStore ?? $this->createMock( EntityStore::class ) );
	}

	public function testimportEntities() {
		$mockStore = $this->createMock( EntityStore::class );
		// Round 1 property
		$mockStore->expects( $this->at( 0 ) )
		->method( 'saveEntity' )
		->willReturnCallback( function ( $entity ) {
			$this->assertEquals( null, $entity->getId() );
			$this->assertCount( 0, $entity->getStatements()->toArray() );
			return new EntityRevision( new Property( new PropertyId( 'P1' ), null, self::SOME_DATA_TYPE ) );
		} );
		// Round 1 item
		$mockStore->expects( $this->at( 1 ) )
		->method( 'saveEntity' )
		->willReturnCallback( function ( $entity ) {
			$this->assertEquals( null, $entity->getId() );
			$this->assertCount( 0, $entity->getStatements()->toArray() );
			return new EntityRevision( new Item( new ItemId( 'Q1' ) ) );
		} );
		// Round 2 item
		$mockStore->expects( $this->at( 2) )
		->method( 'saveEntity' )
		->willReturnCallback( function ( $entity ) {
			$this->assertEquals( new ItemId( 'Q1' ), $entity->getId() );
			$this->assertCount( 1, $entity->getStatements()->toArray() );
			$this->assertEquals( new PropertyId( 'P1' ), $entity->getStatements()->toArray()[0]->getMainSnak()->getPropertyId() );
			return new EntityRevision( new Item( new ItemId( 'Q1' ) ) );
		} );

		$loader = $this->getDataLoader( $mockStore );

		$entities = [
			'P8888' => new Property( new PropertyId( 'P8888' ), null, self::SOME_DATA_TYPE ),
			'Q44556' => new Item(
				new ItemId( 'Q44556' ), null, null,
				new StatementList( [ new Statement( new PropertySomeValueSnak( new PropertyId( 'P8888' ) ) ) ] )
			)
		];

		$loader->importEntities( $entities, $this->createMock( \User::class ) );
	}

	public function testAdjustIdsInEntities() {
		$loader = $this->getDataLoader();
		/** @var DataLoader $wrappedLoader */
		$wrappedLoader = TestingAccessWrapper::newFromObject( $loader );

		$entities = [
			'Q44556' => new Item(
				null, null, null,
				new StatementList( [ new Statement( new PropertySomeValueSnak( new PropertyId( 'P8888' ) ) ) ] )
			)
		];
		$idMap = [
			'Q44556' => 'Q1',
			'P8888' => 'P1',
		];

		$alteredEntities = $wrappedLoader->adjustIdsInEntities( $entities, $idMap );

		$this->assertEquals(
			new ItemId( $idMap['Q44556'] ),
			$alteredEntities['Q44556']->getId()
		);
		$this->assertEquals(
			new PropertyId( $idMap['P8888'] ),
			$alteredEntities['Q44556']->getStatements()->toArray()[0]->getMainSnak()->getPropertyId()
		);
	}

	public function testGetAdjustedStatement() {
		$loader = $this->getDataLoader();
		/** @var DataLoader $wrappedLoader */
		$wrappedLoader = TestingAccessWrapper::newFromObject( $loader );

		$statement = new Statement( new PropertySomeValueSnak( new PropertyId( 'P8888' ) ) );
		$propertyId = new PropertyId( 'P1' );

		$newStatement = $wrappedLoader->getAdjustedStatement( $statement, $propertyId );

		$this->assertEquals( $propertyId, $newStatement->getMainSnak()->getPropertyId() );
	}

}
