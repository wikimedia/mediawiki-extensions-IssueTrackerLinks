<?php

namespace MediaWiki\Extension\IssueTrackerLinks\Hook;

use MediaWiki\Extension\IssueTrackerLinks\Maintenance\CreateDefaultContent;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

class RunDatabaseUpdates implements LoadExtensionSchemaUpdatesHook {

	/**
	 * @inheritDoc
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$updater->addPostDatabaseUpdateMaintenance(
			CreateDefaultContent::class
		);
	}
}
