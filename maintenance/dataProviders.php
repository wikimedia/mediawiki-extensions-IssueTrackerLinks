<?php

use MediaWiki\Extension\IssueTrackerLinks\DataProviderStore;

require_once dirname( __DIR__, 3 ) . '/maintenance/Maintenance.php';

class DataProviders extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->addOption( 'ingest', 'JSON configuration file to ingest', false, true, 'i' );
	}

	public function execute() {
		/** @var DataProviderStore $dataProviderStore */
		$dataProviderStore = $this->getServiceContainer()->getService( 'IssueTrackerLinks.DataProviderStore' );

		if ( $this->hasOption( 'ingest' ) ) {
			$file = $this->getOption( 'ingest' );
			$absolutePath = realpath( $file );
			if ( !file_exists( $absolutePath ) ) {
				$this->fatalError( "File $file does not exist" );
			}
			$json = file_get_contents( $absolutePath );
			$data = json_decode( $json, true );
			if ( $data === false ) {
				$this->fatalError( "Error decoding JSON file $file" );
			}

			foreach ( $data as $provider ) {
				$this->output( "Processing provider \"" . $provider['name'] . "\"...\n\t-> " );
				if (
					!is_array( $provider ) |
					!isset( $provider['name'] ) ||
					!isset( $provider['type'] ) ||
					!isset( $provider['data'] ) ||
					!is_array( $provider['data'] )
				) {
					$this->fatalError( "Invalid provider configuration in file $file" );
				}

				if ( !$dataProviderStore->hasProvider( $provider['name'] ) ) {
					try {
						$dataProviderStore->addProvider( $provider['name'], $provider['type'], $provider['data'] );
						$this->output( "Added provider\n" );
					} catch ( Exception $e ) {
						$this->output( "Error adding provider: {$e->getMessage()}\n" );
					}
				} else {
					try {
						$dataProviderStore->updateProviderData( $provider['name'], $provider['data'] );
						$this->output( "Updated provider configuration\n" );
					} catch ( Exception $e ) {
						$this->output( "Error updating provider: {$e->getMessage()}\n" );
					}
				}
			}
			return;
		}

		// List
		$providers = $dataProviderStore->listProviders();
		if ( empty( $providers ) ) {
			$this->output( "No providers found\n" );
		} else {
			$this->output( "Providers:\n" );
			foreach ( $providers as $provider => $type ) {
				$this->output( "-> $provider ($type)\n" );
			}
		}
	}
}

$maintClass = DataProviders::class;
require_once RUN_MAINTENANCE_IF_MAIN;
