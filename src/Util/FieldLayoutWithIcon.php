<?php

namespace MediaWiki\Extension\IssueTrackerLinks\Util;

use OOUI\FieldLayout;
use OOUI\IconWidget;
use OOUI\Widget;

class FieldLayoutWithIcon extends FieldLayout {

	/**
	 * @param Widget $fieldWidget
	 * @param array $config
	 * @throws \OOUI\Exception
	 */
	public function __construct( $fieldWidget, array $config = [] ) {
		parent::__construct( $fieldWidget, $config );
		$this->header->prependContent(
			new IconWidget( [ 'icon' => $config['icon'] ] )
		);
	}
}
