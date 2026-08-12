<?php

namespace MediaWiki\Extension\IssueTrackerLinks\Hook;

use MediaWiki\Extension\IssueTrackerLinks\Maintenance\CreateDefaultContent;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

class RunDatabaseUpdates implements LoadExtensionSchemaUpdatesHook {

	/**
	 * @inheritDoc
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$baseDir = dirname( __DIR__, 2 );
		$dbType = $updater->getDB()->getType();

		$updater->addExtensionTable(
			'issuetrackerlinks_auth_tokens',
			"$baseDir/db/$dbType/issuetrackerlinks_auth_tokens.sql"
		);

		$updater->addExtensionTable(
			'issuetrackerlinks_issues',
			"$baseDir/db/$dbType/issuetrackerlinks_issues.sql"
		);

		$updater->addExtensionTable(
			'issuetrackerlinks_provider_config',
			"$baseDir/db/$dbType/issuetrackerlinks_provider_config.sql"
		);

		$updater->addPostDatabaseUpdateMaintenance(
			CreateDefaultContent::class
		);
	}
}
