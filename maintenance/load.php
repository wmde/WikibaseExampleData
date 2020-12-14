<?php

namespace MediaWiki\Extension\WikibaseExampleData\Maintenance;

use MediaWiki\Extension\WikibaseExampleData\DataLoader;
use Wikibase\Repo\WikibaseRepo;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}

require_once "$IP/maintenance/Maintenance.php";

class Load extends \Maintenance {

	public function execute() {
		$loader = new DataLoader(
			WikibaseRepo::getDefaultInstance()->getStore()->getEntityStore()
		);
		$loader->execute();
	}

}

$maintClass = "MediaWiki\Extension\WikibaseExampleData\Maintenance\Load";
require_once RUN_MAINTENANCE_IF_MAIN;
