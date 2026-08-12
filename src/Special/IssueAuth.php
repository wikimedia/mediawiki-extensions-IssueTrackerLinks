<?php

namespace MediaWiki\Extension\IssueTrackerLinks\Special;

use Exception;
use MediaWiki\Extension\IssueTrackerLinks\Auth\RedirectUriProvider;
use MediaWiki\Extension\IssueTrackerLinks\DataProvider;
use MediaWiki\Extension\IssueTrackerLinks\DataProviderBackend\IDataProviderBackend;
use MediaWiki\Extension\IssueTrackerLinks\DataProviderBackend\OAuthProviderBackend;
use MediaWiki\Extension\IssueTrackerLinks\DataProviderStore;
use MediaWiki\SpecialPage\UnlistedSpecialPage;
use Psr\Log\LoggerInterface;

class IssueAuth extends UnlistedSpecialPage {

	/**
	 * @param DataProviderStore $providerStore
	 * @param LoggerInterface $logger
	 */
	public function __construct(
		private readonly DataProviderStore $providerStore,
		private readonly LoggerInterface $logger
	) {
		parent::__construct( 'IssueAuth' );
	}

	/**
	 * @param string $subPage
	 * @return void
	 * @throws Exception
	 */
	public function execute( $subPage ) {
		parent::execute( $subPage );
		if ( !$this->getUser()->isRegistered() ) {
			$this->invalidAction();
		}

		$this->getHookContainer()->run( 'IssueTrackerLinksAuth', [ $this ] );
		if ( $subPage === 'start' ) {
			$providerKey = $this->getRequest()->getText( 'provider' );
			$provider = $this->getProvider( $providerKey );
			$this->getRequest()->getSession()->set( 'issuetrackerlinks.auth.provider', $providerKey );

			return $provider->getBackend()->authorize( $this->getRequest(), [
				'provider' => $provider,
				'redirect_uri' => $this->getRedirectUri( $provider->getBackend() ),
			] );
		}

		if ( $subPage === 'callback' ) {
			$providerKey = $this->getRequest()->getSession()->get( 'issuetrackerlinks.auth.provider' );
			$this->getRequest()->getSession()->remove( 'issuetrackerlinks.auth.provider' );

			try {
				$provider = $this->getProvider( $providerKey );
				$provider->getBackend()->handleAuthorizationCallback( $this->getRequest(), $this->getUser(), [
					'provider' => $provider,
					'redirect_uri' => $this->getRedirectUri( $provider->getBackend() ),
				] );
			} catch ( \Throwable $e ) {
				$this->logger->error( 'Error during OAuth callback: {error}', [ 'error' => $e->getMessage() ] );
				$this->invalidAction();
				return;
			}

			$payload = json_encode( [
				'type' => 'issuetrackerlinks.oauth.done',
				'provider' => $providerKey
			] );

			// Add a script tag that would notify the opener that flow is complete. Apparently this is the way to go...
			$this->getOutput()->addHTML(
				'<p>' . $this->getContext()->msg( 'issuetrackerlinks-auth-success' ) . '</p>' .
				'<script>(function(){' .
				'var payload=' . $payload . ';' .
				'if(window.opener&&!window.opener.closed){' .
				'try{window.opener.postMessage(payload,window.location.origin);}catch(e){}}' .
				'window.close();' .
				'})();</script>'
			);
			return;
		}

		$this->invalidAction();
	}

	private function invalidAction() {
		$this->getOutput()->showErrorPage(
			$this->getContext()->msg( 'issuetrackerlinks-auth-invalid-action' ),
			$this->getContext()->msg( 'issuetrackerlinks-auth-invalid-action-text' )
		);
	}

	/**
	 * @param string $providerKey
	 * @return DataProvider
	 * @throws Exception
	 */
	private function getProvider( string $providerKey ): DataProvider {
		if ( !$providerKey ) {
			$this->invalidAction();
		}
		$provider = $this->providerStore->getProvider( $providerKey );
		if ( !$provider ) {
			$this->invalidAction();
		}
		if ( !$provider->getBackend() instanceof OAuthProviderBackend ) {
			$this->invalidAction();
		}
		return $provider;
	}

	/**
	 * @param IDataProviderBackend $providerBackend
	 * @return string
	 */
	private function getRedirectUri( IDataProviderBackend $providerBackend ): string {
		$provider = new RedirectUriProvider(
			$this->getConfig(),
			$this->getHookContainer()
		);
		return $provider->getRedirectUri( $providerBackend );
	}

}
