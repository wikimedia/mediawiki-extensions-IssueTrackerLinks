<?php

namespace MediaWiki\Extension\IssueTrackerLinks\Special;

use MediaWiki\Html\Html;
use OOJSPlus\Special\OOJSGridSpecialPage;

class IssueTrackerLinksConfig extends OOJSGridSpecialPage {

	public function __construct() {
		parent::__construct( 'IssueTrackerLinksConfig', 'wikiadmin' );
	}

	/**
	 * @param string $subPage
	 * @return void
	 */
	public function execute( $subPage ) {
		parent::execute( $subPage );

		$this->getOutput()->addHTML(
			Html::element( 'div', [ 'id' => 'bs-issuetrackerlinks-config-grid' ] )
		);
		$this->getOutput()->addModules( [ 'ext.issuetrackerlinks.special' ] );
	}
}
