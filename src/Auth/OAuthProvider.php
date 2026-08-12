<?php

namespace MediaWiki\Extension\IssueTrackerLinks\Auth;

use GuzzleHttp\Client;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GenericResourceOwner;
use League\OAuth2\Client\Token\AccessToken;
use Psr\Http\Message\ResponseInterface;

class OAuthProvider extends AbstractProvider {
	/** @var string */
	private string $baseUrl;
	/** @var string */
	private string $authorizeEndpoint = '/oauth/authorize';
	/** @var string */
	private string $accessTokenEndpoint = '/oauth/token';
	/** @var string */
	private string $resourceOwnerDetailsEndpoint;
	/** @var array */
	private array $defaultScopes = [];

	/**
	 * @param array $options
	 * @param array $collaborators
	 */
	public function __construct( array $options = [], array $collaborators = [] ) {
		if ( ( $options['insecure_tls'] ?? false ) === true && !isset( $collaborators['httpClient'] ) ) {
			$collaborators['httpClient'] = new Client( [ 'verify' => false ] );
		}
		parent::__construct( $options, $collaborators );

		$this->baseUrl = rtrim( (string)( $options['base_url'] ?? '' ), '/' );
		$this->authorizeEndpoint = (string)( $options['authorize_endpoint'] ?? $this->authorizeEndpoint );
		$this->accessTokenEndpoint = (string)( $options['access_token_endpoint'] ?? $this->accessTokenEndpoint );
		$this->resourceOwnerDetailsEndpoint = (string)( $options['resource_owner_details_endpoint'] ?? '' );

		$scopes = $options['scopes'] ?? [];
		if ( is_string( $scopes ) ) {
			$scopes = array_filter( array_map( 'trim', explode( ' ', $scopes ) ) );
		}
		if ( is_array( $scopes ) ) {
			$this->defaultScopes = array_values( $scopes );
		}
	}

	/**
	 * @param string $endpoint
	 * @return string
	 */
	private function buildUrl( string $endpoint ): string {
		if ( preg_match( '#^https?://#i', $endpoint ) ) {
			return $endpoint;
		}
		return $this->baseUrl . '/' . ltrim( $endpoint, '/' );
	}

	/**
	 * @return string
	 */
	public function getBaseAuthorizationUrl() {
		return $this->buildUrl( $this->authorizeEndpoint );
	}

	/**
	 * @param array $params
	 * @return string
	 */
	public function getBaseAccessTokenUrl( array $params ) {
		return $this->buildUrl( $this->accessTokenEndpoint );
	}

	/**
	 * @param AccessToken $token
	 * @return string
	 */
	public function getResourceOwnerDetailsUrl( AccessToken $token ) {
		return $this->buildUrl( $this->resourceOwnerDetailsEndpoint );
	}

	/**
	 * @return array
	 */
	protected function getDefaultScopes() {
		return $this->defaultScopes;
	}

	/**
	 * @param ResponseInterface $response
	 * @param array $data
	 * @return void
	 * @throws IdentityProviderException
	 */
	protected function checkResponse( ResponseInterface $response, $data ) {
		$statusCode = $response->getStatusCode();
		if ( $statusCode >= 400 ) {
			$message = is_array( $data ) ?
				(string)( $data['error_description'] ?? $data['error'] ?? 'OAuth provider error' ) :
				'OAuth provider error';
			throw new IdentityProviderException( $message, $statusCode, $data );
		}
		if ( is_array( $data ) && isset( $data['error'] ) ) {
			$message = (string)( $data['error_description'] ?? $data['error'] );
			throw new IdentityProviderException( $message, $statusCode, $data );
		}
	}

	/**
	 * @param array $response
	 * @param AccessToken $token
	 * @return GenericResourceOwner
	 */
	protected function createResourceOwner( array $response, AccessToken $token ) {
		return new GenericResourceOwner( $response, 'id' );
	}
}
