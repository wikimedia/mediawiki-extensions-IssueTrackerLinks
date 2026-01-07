<?php

namespace MediaWiki\Extension\IssueTrackerLinks;

use OOUI\ButtonWidget;

class IssueWidget extends ButtonWidget {

	/**
	 * @param string $type
	 * @param string $label
	 * @param string $url
	 * @param array $params
	 */
	public function __construct( string $type, string $label, string $url, array $params = [] ) {
		parent::__construct( [
			'label' => $label,
			'href' => $url,
			'classes' => [ 'mw-issue-link', 'mw-issue-type-' . $type ],
			'data' => [
				'type' => $type,
				'params' => json_encode( $params ),
			]
		] );
		$this->content[0]->setAttributes( [ 'style' => 'padding:0 0 0 5px;background-color:none;min-height:30px' ] );
	}
}
