<?php

namespace MediaWiki\Extension\IssueTrackerLinks;

use OOUI\ButtonWidget;

class SimpleIssueWidget extends ButtonWidget {

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
			'classes' => [ 'mw-issue-link', 'mw-issue-type-' . $type ]
		] );
		$this->content[0]->setAttributes( [ 'style' => 'padding:0 0 0 5px;min-height:30px' ] );
	}
}
