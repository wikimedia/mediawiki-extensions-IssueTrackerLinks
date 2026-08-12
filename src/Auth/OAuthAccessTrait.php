<?php

namespace MediaWiki\Extension\IssueTrackerLinks\Auth;

use Exception;
use InvalidArgumentException;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use MediaWiki\Extension\IssueTrackerLinks\Auth\Exception\NoAuthException;
use MediaWiki\Extension\IssueTrackerLinks\DataProvider;
use MediaWiki\Permissions\Authority;
use MediaWiki\Request\WebRequest;

trait OAuthAccessTrait {

	/** @var \Config|null */
	private ?\Config $config = null;

	private ?AuthDataStore $authStore = null;

	/**
	 * @param \Config $config
	 * @return void
	 */
	public function setOAuthConfig( \Config $config ): void {
		$this->config = $config;
	}

	public function setOAuthCredentialStore( AuthDataStore $authDataStore ): void {
		$this->authStore = $authDataStore;
	}

	/**
	 * @param Authority $actor
	 * @param DataProvider $forProvider
	 * @return AccessToken|null
	 * @throws IdentityProviderException
	 * @throws NoAuthException|Exception
	 */
	public function getAccessToken( Authority $actor, DataProvider $forProvider ): ?AccessToken {
		if ( !$this->authStore ) {
			throw new InvalidArgumentException( 'AuthDataStore not set' );
		}
		$stored = $this->authStore->getAuthData( $forProvider, $actor );
		if ( !is_array( $stored ) || !isset( $stored['access_token'] ) ) {
			throw new NoAuthException();
		}

		$token = new AccessToken( $stored );
		if ( $token->getExpires() && $token->hasExpired() && $token->getRefreshToken() ) {
			$token = $this->getOAuthProvider()->getAccessToken( 'refresh_token', [
				'refresh_token' => $token->getRefreshToken(),
			] );
			$this->authStore->setAuthData( $forProvider, $actor, $token->jsonSerialize() );
		}
		return $token;
	}

	/**
	 * @param WebRequest $request
	 * @param array $requestParams
	 * @return void
	 * @throws Exception
	 */
	public function authorize( WebRequest $request, array $requestParams ) {
		if ( !$this->config ) {
			throw new InvalidArgumentException( 'OAuth config not set' );
		}
		$provider = $requestParams['provider'];

		$state = bin2hex( random_bytes( 16 ) );
		$codeVerifier = bin2hex( random_bytes( 32 ) );
		$codeChallenge = rtrim(
			strtr( base64_encode( hash( 'sha256', $codeVerifier, true ) ), '+/', '-_' ),
			'='
		);
		$nonce = rtrim(
			strtr(
				base64_encode( hash( 'sha256', $request->getSession()->getId(), true ) ),
				'+/',
				'-_'
			),
			'='
		);
		$providerKey = $provider->getId();
		$session = $request->getSession();
		$session->persist();
		$session->set( $this->makeSessionKey( 'issuetrackerlinks-auth-state-', $providerKey ), $state );
		$session->set(
			$this->makeSessionKey( 'issuetrackerlinks-auth-code-verifier-', $providerKey ),
			$codeVerifier
		);
		$session->set( $this->makeSessionKey( 'issuetrackerlinks-auth-nonce-', $providerKey ), $nonce );
		$session->save();

		$params = $requestParams + [
			'state' => $state,
			'code_challenge' => $codeChallenge,
			'code_challenge_method' => 'S256',
			'nonce' => $nonce,
		];
		$url = $this->getOAuthProvider( $requestParams )->getAuthorizationUrl( $params );
		header( "Location: $url" );
		exit();
	}

	/**
	 * @param WebRequest $request
	 * @param Authority $forUser
	 * @param array $requestParams
	 * @return void
	 * @throws IdentityProviderException
	 */
	public function handleAuthorizationCallback( WebRequest $request, Authority $forUser, array $requestParams ): void {
		$provider = $requestParams['provider'];
		$providerKey = $provider->getId();
		$session = $request->getSession();
		$storedState = (string)$session->get(
			$this->makeSessionKey( 'issuetrackerlinks-auth-state-', $providerKey ),
			''
		);
		$providedState = (string)$request->getVal( 'state', '' );
		$code = (string)$request->getVal( 'code', '' );
		if ( !$storedState || !$providedState || !hash_equals( $storedState, $providedState ) || !$code ) {
			throw new \UnexpectedValueException( 'Invalid OAuth callback state or code' );
		}

		$tokenParams = [ 'code' => $code ];
		$codeVerifier = (string)$session->get(
			$this->makeSessionKey( 'issuetrackerlinks-auth-code-verifier-', $providerKey ),
			''
		);
		if ( $codeVerifier !== '' ) {
			$tokenParams['code_verifier'] = $codeVerifier;
		}
		$redirectUri = (string)( $requestParams['redirect_uri'] ?? '' );
		if ( $redirectUri !== '' ) {
			$tokenParams['redirect_uri'] = $redirectUri;
		}

		$accessToken = $this->getOAuthProvider( $requestParams )->getAccessToken(
			'authorization_code',
			$tokenParams
		);
		$this->authStore->setAuthData( $provider, $forUser, $accessToken->jsonSerialize() );

		$session->remove( $this->makeSessionKey( 'issuetrackerlinks-auth-state-', $providerKey ) );
		$session->remove( $this->makeSessionKey( 'issuetrackerlinks-auth-code-verifier-', $providerKey ) );
		$session->remove( $this->makeSessionKey( 'issuetrackerlinks-auth-nonce-', $providerKey ) );
		$session->save();
	}

	/**
	 * @param Authority $forUser
	 * @param DataProvider $forProvider
	 * @return void
	 */
	protected function tryRefresh( Authority $forUser, DataProvider $forProvider ): void {
		// If no AT set, bail out
		try {
			$this->getAccessToken( $forUser, $forProvider );
		} catch ( Exception ) {
			return;
		}
		// If AT is set, try refresh, even if not expired
		try {
			$token = $this->getOAuthProvider()->getAccessToken( 'refresh_token', [
				'refresh_token' => $this->getAccessToken( $forUser, $forProvider )->getRefreshToken(),
			] );
			$this->authStore->setAuthData( $forProvider, $forUser, $token->jsonSerialize() );
		} catch ( Exception ) {
			// If refresh fails, clear the stored token, make user re-authenticate
			$this->authStore->invalidateAuthData( $forProvider, $forUser );
		}
	}

	/**
	 * @param array $requestParams
	 * @return AbstractProvider
	 * @throws Exception
	 */
	protected function getOAuthProvider( array $requestParams = [] ): AbstractProvider {
		if ( !$this->config ) {
			throw new InvalidArgumentException( 'OAuth config not set' );
		}
		$clientId = $this->config->get( 'client_id' );
		$clientSecret = $this->config->get( 'client_secret' );
		$baseUrl = $this->config->get( 'base_url' );
		$authorizeEndpoint = $this->config->has( 'authorize_endpoint' ) ?
			$this->config->get( 'authorize_endpoint' ) : '/oauth/authorize';
		$accessTokenEndpoint = $this->config->has( 'access_token_endpoint' ) ?
			$this->config->get( 'access_token_endpoint' ) : '/oauth/token';
		$redirectUri = (
			$requestParams['redirect_uri'] ??
			( $this->config->has( 'redirect_uri' ) ? $this->config->get( 'redirect_uri' ) : '' )
		);
		$scopeSeparator = $this->config->has( 'scope_separator' ) ? $this->config->get( 'scope_separator' ) : ' ';

		$options = [
			'clientId' => $clientId,
			'clientSecret' => $clientSecret,
			'base_url' => $baseUrl,
			'authorize_endpoint' => $authorizeEndpoint,
			'access_token_endpoint' => $accessTokenEndpoint,
			'scopeSeparator' => $scopeSeparator,
		];
		if ( $redirectUri !== '' ) {
			$options['redirectUri'] = $redirectUri;
		}
		$scopes = $this->config->has( 'scopes' ) ? $this->config->get( 'scopes' ) : [];
		if ( is_array( $scopes ) || is_string( $scopes ) ) {
			$options['scopes'] = $scopes;
		}
		if ( $this->config->has( 'insecure_tls' ) ) {
			$insecureTls = filter_var(
				$this->config->get( 'insecure_tls' ),
				FILTER_VALIDATE_BOOLEAN,
				FILTER_NULL_ON_FAILURE
			);
			if ( $insecureTls === true ) {
				$options['insecure_tls'] = true;
			}
		}
		return new OAuthProvider( $options );
	}

	/**
	 * @param string $prefix
	 * @param string $providerKey
	 * @return string
	 */
	private function makeSessionKey( string $prefix, string $providerKey ): string {
		return $prefix . $providerKey;
	}

	/**
	 * @return string
	 * @throws Exception
	 */
	private function getDefaultProviderKey(): string {
		if ( !$this->config ) {
			throw new InvalidArgumentException( 'OAuth config not set' );
		}
		$hashSeed = implode( '|', [
			(string)$this->config->get( 'base_url' ),
			(string)$this->config->get( 'authorize_endpoint' ),
			(string)$this->config->get( 'access_token_endpoint' ),
			(string)$this->config->get( 'client_id' ),
		] );
		return sha1( $hashSeed );
	}
}
