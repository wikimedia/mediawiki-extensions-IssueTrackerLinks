<?php

namespace MediaWiki\Extension\IssueTrackerLinks\Hook;

use MediaWiki\Extension\ContentDroplets\Droplet\CustomTagDroplet;
use MediaWiki\Extension\ContentDroplets\Hook\ContentDropletsGetDropletsHook;
use MediaWiki\Extension\IssueTrackerLinks\PatternConfig;

class RegisterDroplets implements ContentDropletsGetDropletsHook {

	/**
	 * @param PatternConfig $patternConfig
	 */
	public function __construct(
		private readonly PatternConfig $patternConfig
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function onContentDropletsGetDroplets( array &$droplets ): void {
		foreach ( $this->patternConfig->getFullConfig() as $key => $data ) {
			$droplets[$key] = new CustomTagDroplet(
				name: $data['label'],
				description: $data['description'] ?? 'issuetrackerlinks-generic-droplet-desc',
				icon: 'icon-' . strtolower( $data['label'] ),
				tagname: 'issue-tracker-link',
				attributes: [ 'type' => 'gitlab', 'dataProvider' => $data['data-provider'] ?? '' ],
				hasContent: false,
				veCommand: $key . 'Command',
				type: '',
				categories: [ 'content' ],
				rlModules: [ 'ext.issuetrackerlinks.commands' ],
			);
		}
	}
}
