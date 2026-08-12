<?php

namespace MediaWiki\Extension\IssueTrackerLinks\Auth\Exception;

class EntityNotFoundException extends \Exception {
	public function __construct() {
		parent::__construct( 'not_found', 404 );
	}
}
