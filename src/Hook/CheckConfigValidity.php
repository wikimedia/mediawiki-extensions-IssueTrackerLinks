<?php

namespace MediaWiki\Extension\IssueTrackerLinks\Hook;

use MediaWiki\Extension\IssueTrackerLinks\PatternConfig;
use MediaWiki\Hook\BeforePageDisplayHook;
use OOUI\HtmlSnippet;
use OOUI\MessageWidget;

class CheckConfigValidity implements BeforePageDisplayHook {

	public function __construct(
		private readonly PatternConfig $patternConfig
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		if ( $out->getTitle()?->getPrefixedDBkey() === PatternConfig::CONFIG_PAGE ) {
			if ( !$this->patternConfig->isValid() ) {
				$out->enableOOUI();
				$out->prependHTML(
					new MessageWidget( [
						'type' => 'error',
						'label' => new HtmlSnippet(
							$out->getContext()->msg( 'issuetrackerlinks-invalid-config-warning' )->parse()
						)
					] )
				);
			}
		}
	}
}
