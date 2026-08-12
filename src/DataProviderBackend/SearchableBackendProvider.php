<?php

namespace MediaWiki\Extension\IssueTrackerLinks\DataProviderBackend;

use MediaWiki\Extension\IssueTrackerLinks\DataProvider;
use MediaWiki\Permissions\Authority;

interface SearchableBackendProvider extends IDataProviderBackend {

	/**
	 * @param string $query
	 * @param Authority $actor
	 * @param DataProvider $forProvider
	 * @return array
	 */
	public function search( string $query, Authority $actor, DataProvider $forProvider ): array;
}
