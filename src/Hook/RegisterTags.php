<?php

namespace MediaWiki\Extension\IssueTrackerLinks\Hook;

use MediaWiki\Extension\IssueTrackerLinks\Tag\IssueTag;
use MediaWiki\Hook\BeforePageDisplayHook;
use MWStake\MediaWiki\Component\GenericTagHandler\Hook\MWStakeGenericTagHandlerInitTagsHook;

class RegisterTags implements MWStakeGenericTagHandlerInitTagsHook, BeforePageDisplayHook {

	/**
	 * @inheritDoc
	 */
	public function onMWStakeGenericTagHandlerInitTags( array &$tags ) {
		$tags[] = new IssueTag();
	}

	/**
	 * @inheritDoc
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		$out->addModules( [ 'ext.issuetrackerlinks.definition' ] );
	}
}
