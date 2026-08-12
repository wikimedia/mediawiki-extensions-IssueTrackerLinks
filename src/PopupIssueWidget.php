<?php

namespace MediaWiki\Extension\IssueTrackerLinks;

use OOUI\ButtonWidget;

class PopupIssueWidget extends ButtonWidget {

	/**
	 * @param string $type
	 * @param string $label
	 * @param string $url
	 * @param array $params
	 * @param array $config
	 */
	public function __construct( string $type, string $label, string $url, array $params, array $config ) {
		parent::__construct( [
			'label' => $label,
			'href' => $url,
			'classes' => [ 'mw-issue-link', 'mw-issue-type-' . $type, 'mw-issue-link-popup' ],
			'data' => [
				'label' => $label,
				'url' => $url,
				'type' => $type,
				'params' => json_encode( $params ),
				'show-details' => true,
				'provider-type' => $config['data-provider']
			]
		] );
		$this->content[0]->setAttributes( [ 'style' => 'padding:0 0 0 5px;min-height:30px' ] );
	}

	/**
	 * @return array
	 */
	public function getGeneratedAttributes() {
		$parentAttrs = parent::getGeneratedAttributes();

		foreach ( $this->getData() as $key => $value ) {
			$parentAttrs['data-' . $key] = $value;
		}

		return $parentAttrs;
	}
}
