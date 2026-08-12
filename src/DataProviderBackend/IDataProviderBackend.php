<?php

namespace MediaWiki\Extension\IssueTrackerLinks\DataProviderBackend;

use Config;
use MediaWiki\Extension\IssueTrackerLinks\Auth\AuthDataStore;
use MediaWiki\Extension\IssueTrackerLinks\DataProvider;
use MediaWiki\Extension\IssueTrackerLinks\IssueEntity;
use MediaWiki\Permissions\Authority;
use MWStake\MediaWiki\Component\FormEngine\StandaloneFormSpecification;
use OOUI\PanelLayout;

interface IDataProviderBackend {

	/**
	 * @param Config $config
	 * @return mixed
	 */
	public function setConfig( Config $config );

	/**
	 * @param array $configData
	 * @return bool
	 */
	public function isValidConfiguration( array $configData ): bool;

	/**
	 * @param array $params
	 * @param Authority $actor
	 * @param DataProvider $forProvider
	 * @return IssueEntity
	 */
	public function getEntity( array $params, Authority $actor, DataProvider $forProvider ): IssueEntity;

	/**
	 * Must match key from registration
	 *
	 * @return string
	 */
	public function getKey(): string;

	/**
	 * @param AuthDataStore $authDataStore
	 * @return void
	 */
	public function setAuthStore( AuthDataStore $authDataStore ): void;

	/**
	 * Information to be shown on existing providers - must not include any sensitive data
	 * @return PanelLayout
	 */
	public function getViewLayout(): PanelLayout;

	/**
	 * Form to provider connection data when creating a new provider
	 * @return StandaloneFormSpecification
	 */
	public function getEditForm(): StandaloneFormSpecification;
}
