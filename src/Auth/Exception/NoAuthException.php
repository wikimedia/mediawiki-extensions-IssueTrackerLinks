<?php

namespace MediaWiki\Extension\IssueTrackerLinks\Auth\Exception;

class NoAuthException extends \Exception {
	public function __construct() {
		parent::__construct( 'no_auth', 401 );
	}
}
