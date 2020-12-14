<?php

namespace MediaWiki\Extension\WikibaseExampleData;

use Wikibase\Repo\WikibaseRepo;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Entity\Item;
use User;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Statement\StatementListProvider;
use Wikibase\DataModel\Statement\StatementListHolder;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Snak\PropertyNoValueSnak;
use Wikibase\DataModel\Snak\PropertySomeValueSnak;
use Wikibase\DataModel\Snak\Snak;
use Wikibase\Lib\Store\EntityStore;

class DataLoader {

	private $dataDir = __DIR__ . '/../data/';

	private $entityStore;
	private $deserailizer;

	public function __construct( EntityStore $entityStore ) {
		$this->entityStore = $entityStore;
		$repo = WikibaseRepo::getDefaultInstance();
		$this->deserailizer = $repo->getBaseDataModelDeserializerFactory()->newEntityDeserializer();
	}

	public function execute() {
		// Mapping in all arrays is the original ID
		$files = $this->getAllFiles();

		$rawEntitiesToImport = [];
		foreach( $files as $file ) {
			$rawJson = file_get_contents( $file );
			$entity = $this->deserailizer->deserialize( json_decode( $rawJson, true ) );
			$rawEntitiesToImport[$entity->getId()->getSerialization()] = $entity;
		}

		$this->importEntities( $rawEntitiesToImport, User::newSystemUser( 'WikibaseExampleDataImporter' ) );
	}

	public function importEntities( array $entitiesToImport, User $user ) {
		// Old to New ids
		$idMap = [];

		// Get a first round of entities, ONLY with fingerprints, and with IDs removed...
		$roundOneEntitiesToLoad = $this->getFingerprintOnlyEntities( $entitiesToImport );

		// Write the entities
		foreach( $roundOneEntitiesToLoad as $sourceEntityId => $entity ) {
			$savedEntityRevision = $this->entityStore->saveEntity(
				$entity,
				'Import base entity',
				$user,
				EDIT_NEW
			);
			$newIdString = $savedEntityRevision->getEntity()->getId()->getSerialization();
			$idMap[ $sourceEntityId ] = $newIdString;
		}
		unset( $roundOneEntitiesToLoad );

		// Get a new set of entity objects with adjusted IDs, including statements
		$roundTwoEntitiesToLoad = $this->adjustIdsInEntities( $entitiesToImport, $idMap );

		// Write the entities again (this time with statements)
		foreach( $roundTwoEntitiesToLoad as $sourceEntityId => $entity ) {
			if( count( $entity->getStatements()->toArray() ) === 0 ) {
				// Skip entities that would have no statements added
				continue;
			}
			$savedEntityRevision = $this->entityStore->saveEntity(
				$entity,
				'Import statements',
				$user
			);
			$newIdString = $savedEntityRevision->getEntity()->getId()->getSerialization();
			$idMap[ $sourceEntityId ] = $newIdString;
		}

	}

	private function getFingerprintOnlyEntities( array $entities ) : array {
		$smallerEntities = [];

		foreach( $entities as $entity ){
			if( $entity->getType() === 'item' ) {
				$smallerEntities[ $entity->getId()->getSerialization() ] = new Item(
					null,
					$entity->getFingerprint()
				);
			} elseif( $entity->getType() === 'property' ) {
				$smallerEntities[ $entity->getId()->getSerialization() ] = new Property(
					null,
					$entity->getFingerprint(),
					$entity->getDataTypeId()
				);
			} else {
				die('ohnoes');
			}

		}

		return $smallerEntities;
	}

	private function adjustIdsInEntities( array $entities, array $idMap ) : array {
		/** @var StatementListProvider $entity */
		foreach( $entities as $sourceEntityId => $entity ) {
			// Adjust main IDs
			if( $entity->getType() === 'item' ) {
				$entity->setId( new ItemId( $idMap[$sourceEntityId] ) );
			} elseif( $entity->getType() === 'property' ) {
				$entity->setId( new PropertyId( $idMap[$sourceEntityId] ) );
			} else {
				die('ohnoes2');
			}
			// Adjust Statement MainSnakValues
			if( $entity instanceof StatementListProvider && $entity instanceof StatementListHolder ){
				$newStatements = new StatementList();
				foreach( $entity->getStatements()->toArray() as $statement ) {
					$newStatements->addStatement( $this->getAdjustedStatement(
						$statement,
						new PropertyId( $idMap[$statement->getMainSnak()->getPropertyId()->getSerialization()] )
						) );
				}
				$entity->setStatements( $newStatements );
			}
		}
		return $entities;
	}

	private function getAdjustedStatement( Statement $statement, PropertyId $newPropertyId ) : Statement {
		return new Statement(
			$this->getAdjustedMainsnak(
				$statement->getMainSnak(),
				$newPropertyId
			)
		);
	}

	private function getAdjustedMainsnak( Snak $mainSnak, PropertyId $newPropertyId ) : Snak {
		// It would be nice is this sort of thing was in data model?
		if( $mainSnak instanceof PropertyValueSnak ) {
			return new PropertyValueSnak(
				$newPropertyId,
				$mainSnak->getDataValue()
			);
		} elseif( $mainSnak instanceof PropertySomeValueSnak ) {
			return new PropertySomeValueSnak( $newPropertyId );

		} elseif ( $mainSnak instanceof PropertyNoValueSnak ){
			return new PropertyNoValueSnak( $newPropertyId );
		}
		die('ohnoes3');
	}

	private function getAllFiles() {
		return glob( $this->dataDir . '*' );
	}

}
