<?php

use MediaWiki\Extension\IssueTrackerLinks\Auth\AuthDataStore;
use MediaWiki\Extension\IssueTrackerLinks\Auth\RedirectUriProvider;
use MediaWiki\Extension\IssueTrackerLinks\ConfigJsonMutator;
use MediaWiki\Extension\IssueTrackerLinks\DataProviderStore;
use MediaWiki\Extension\IssueTrackerLinks\LinkRegistry;
use MediaWiki\Extension\IssueTrackerLinks\PatternConfig;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Registration\ExtensionRegistry;

return [
	'IssueTrackerLinks.PatternConfig' => static function ( MediaWikiServices $services ) {
		return new PatternConfig(
			$services->getTitleFactory(),
			$services->getWikiPageFactory(),
			$services->getService( 'IssueTrackerLinks._Logger' )
		);
	},
	'IssueTrackerLinks._Logger' => static function () {
		return LoggerFactory::getInstance( 'IssueTrackerLinks' );
	},
	'IssueTrackerLinks.DataProviderStore' => static function ( MediaWikiServices $services ) {
		$store = new DataProviderStore(
			ExtensionRegistry::getInstance()->getAttribute( 'IssueTrackerLinksDataProviderBackend' ),
			$services->getObjectFactory(),
			$services->getDBLoadBalancer(),
			$services->getService( 'IssueTrackerLinks.AuthDataStore' ),
		);
		$store->setLogger( $services->getService( 'IssueTrackerLinks._Logger' ) );
		return $store;
	},
	'IssueTrackerLinks.AuthDataStore' => static function ( MediaWikiServices $services ) {
		return new AuthDataStore( $services->getDBLoadBalancer() );
	},
	'IssueTrackerLinks.ConfigJsonMutator' => static function ( MediaWikiServices $services ) {
		return new ConfigJsonMutator(
			$services->getTitleFactory(),
			$services->getWikiPageFactory(),
			$services->getService( 'IssueTrackerLinks._Logger' )
		);
	},
	'IssueTrackerLinks.LinkRegistry' => static function ( MediaWikiServices $services ) {
		return new LinkRegistry(
			$services->getDBLoadBalancer(),
			$services->getService( 'IssueTrackerLinks.DataProviderStore' ),
			$services->getPermissionManager(),
			$services->getTitleFactory()
		);
	},
	'IssueTrackerLinks.RedirectUriProvider' => static function ( MediaWikiServices $services ) {
		return new RedirectUriProvider(
			$services->getMainConfig(),
			$services->getHookContainer()
		);
	}
];
