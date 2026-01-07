<?php

namespace MediaWiki\Extension\IssueTrackerLinks\Maintenance;

use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Extension\IssueTrackerLinks\PatternConfig;
use MediaWiki\Maintenance\LoggedUpdateMaintenance;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\User\User;

class CreateDefaultContent extends LoggedUpdateMaintenance {

	/**
	 * @return bool
	 */
	protected function doDBUpdates() {
		$this->output( "Creating default IssueTrackerLinks configuration page..." );
		$title = $this->getServiceContainer()->getTitleFactory()->newFromText(
			PatternConfig::CONFIG_PAGE
		);
		if ( $title->exists() ) {
			$this->output( "IssueTrackerLinks config page already exists, skipping creation.\n" );
			return true;
		}
		$wikiPage = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $title );
		$updater = $wikiPage->newPageUpdater( User::newSystemUser( 'MediaWiki default', [ 'steal' => true ] ) );

		/** @var PatternConfig $patternConfig */
		$patternConfig = $this->getServiceContainer()->getService( 'IssueTrackerLinks.PatternConfig' );
		$updater->setContent( SlotRecord::MAIN, $patternConfig->getDefaultContent() );
		$updater->saveRevision( CommentStoreComment::newUnsavedComment(
			'Creating default IssueTrackerLinks configuration' )
		);
		if ( !$updater->getStatus()->isOK() ) {
			$this->output( "Failed to create IssueTrackerLinks config page" );
			return false;
		}
		return true;
	}

	/**
	 * @return string
	 */
	protected function getUpdateKey() {
		return 'issuetrackerslinks-createdefaultcontent';
	}
}
