<?php

use MediaWiki\Extension\IssueTrackerLinks\LinkRegistry;

require_once dirname( __DIR__, 3 ) . '/maintenance/Maintenance.php';

class RebuildLinkData extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->addOption( 'page', 'Page to rebuild', true, true, 'p' );
	}

	public function execute() {
		$page = $this->getServiceContainer()->getTitleFactory()->newFromText( $this->getOption( 'page' ) );
		if ( !$page ) {
			$this->fatalError( "Invalid page name: " . $this->getOption( 'page' ) );
		}

		/** @var LinkRegistry $linkRegistry */
		$linkRegistry = $this->getServiceContainer()->getService( 'IssueTrackerLinks.LinkRegistry' );

		$rev = $this->getServiceContainer()->getRevisionLookup()->getRevisionByTitle( $page );
		if ( !$rev ) {
			$this->fatalError( "Page not found: " . $page->getPrefixedText() );
		}

		try {
			$linkRegistry->rebuildForPage(
				rev: $rev,
				actor: \MediaWiki\User\User::newSystemUser( 'MediaWiki default', [ 'steal' => true ] ),
				parser: $this->getServiceContainer()->getParser()
			);
			$this->output( "Link data rebuilt for " . $page->getPrefixedText() . "\n" );
		} catch ( Throwable $ex ) {
			$this->fatalError( "Error rebuilding link data: " . $ex->getMessage() );
		}
	}
}

$maintClass = RebuildLinkData::class;
require_once RUN_MAINTENANCE_IF_MAIN;
