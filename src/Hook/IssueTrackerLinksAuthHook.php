<?php

namespace MediaWiki\Extension\IssueTrackerLinks\Hook;

use MediaWiki\SpecialPage\SpecialPage;

interface IssueTrackerLinksAuthHook {

	/**
	 * @param SpecialPage $authPage
	 * @return void
	 */
	public function onIssueTrackerLinksAuth( SpecialPage $authPage ): void;
}
