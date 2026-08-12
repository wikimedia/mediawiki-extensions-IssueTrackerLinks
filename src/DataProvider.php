<?php

namespace MediaWiki\Extension\IssueTrackerLinks;

use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\IssueTrackerLinks\DataProviderBackend\IDataProviderBackend;
use MediaWiki\Permissions\Authority;

final class DataProvider {

	/**
	 * @param string $id
	 * @param string $name
	 * @param IDataProviderBackend $providerBackend
	 * @param HashConfig $providerConfig
	 */
	public function __construct(
		private readonly string $id,
		private readonly string $name,
		private readonly IDataProviderBackend $providerBackend,
		private readonly HashConfig $providerConfig
	) {
		$this->providerBackend->setConfig( $this->providerConfig );
	}

	/**
	 * @return string
	 */
	public function getId(): string {
		return $this->id;
	}

	/**
	 * @return string
	 */
	public function getName(): string {
		return $this->name;
	}

	/**
	 * @return IDataProviderBackend
	 */
	public function getBackend(): IDataProviderBackend {
		return $this->providerBackend;
	}

	/**
	 * @param array $params
	 * @param Authority $actor
	 * @return IssueEntity
	 */
	public function getEntity( array $params, Authority $actor ): IssueEntity {
		return $this->getBackend()->getEntity( $params, $actor, $this );
	}

	/**
	 * @param string $query
	 * @param Authority $actor
	 * @return array
	 */
	public function search( string $query, Authority $actor ): array {
		return $this->getBackend()->search( $query, $actor, $this );
	}
}
