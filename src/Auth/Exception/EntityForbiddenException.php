<?php

namespace MediaWiki\Extension\IssueTrackerLinks\Auth\Exception;

class EntityForbiddenException extends \Exception {
	public function __construct() {
		parent::__construct( 'forbidden', 401 );
	}
}
