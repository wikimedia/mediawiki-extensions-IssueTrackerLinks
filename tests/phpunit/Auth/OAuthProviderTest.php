<?php

namespace MediaWiki\Extension\IssueTrackerLinks\Tests\Unit\Auth;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use MediaWiki\Extension\IssueTrackerLinks\Auth\OAuthProvider;
use PHPUnit\Framework\TestCase;

class OAuthProviderTest extends TestCase {

	/**
	 * @covers \MediaWiki\Extension\IssueTrackerLinks\Auth\OAuthProvider::getBaseAuthorizationUrl
	 * @covers \MediaWiki\Extension\IssueTrackerLinks\Auth\OAuthProvider::getBaseAccessTokenUrl
	 */
	public function testBuildsUrlsFromBaseAndRelativeEndpoints(): void {
		$provider = $this->newTestableProvider( [
			'clientId' => 'id',
			'clientSecret' => 'secret',
			'base_url' => 'https://github.com/',
			'authorize_endpoint' => '/login/oauth/authorize',
			'access_token_endpoint' => '/login/oauth/access_token',
		] );

		$this->assertSame(
			'https://github.com/login/oauth/authorize',
			$provider->getBaseAuthorizationUrl()
		);
		$this->assertSame(
			'https://github.com/login/oauth/access_token',
			$provider->getBaseAccessTokenUrl( [] )
		);
	}

	/**
	 * @covers \MediaWiki\Extension\IssueTrackerLinks\Auth\OAuthProvider::getBaseAccessTokenUrl
	 */
	public function testKeepsAbsoluteEndpointUntouched(): void {
		$provider = $this->newTestableProvider( [
			'clientId' => 'id',
			'clientSecret' => 'secret',
			'base_url' => 'https://github.com',
			'access_token_endpoint' => 'https://example.org/token',
		] );

		$this->assertSame(
			'https://example.org/token',
			$provider->getBaseAccessTokenUrl( [] )
		);
	}

	/**
	 * @covers \MediaWiki\Extension\IssueTrackerLinks\Auth\OAuthProvider::checkResponse
	 */
	public function testCheckResponseThrowsOnHttpError(): void {
		$provider = $this->newTestableProvider( [
			'clientId' => 'id',
			'clientSecret' => 'secret',
			'base_url' => 'https://github.com',
		] );

		$this->expectException( IdentityProviderException::class );
		$this->expectExceptionMessage( 'Bad code' );

		$this->invokeCheckResponse( $provider, new Response( 400 ), [ 'error_description' => 'Bad code' ] );
	}

	/**
	 * @covers \MediaWiki\Extension\IssueTrackerLinks\Auth\OAuthProvider::checkResponse
	 */
	public function testCheckResponseThrowsWhenErrorFieldExists(): void {
		$provider = $this->newTestableProvider( [
			'clientId' => 'id',
			'clientSecret' => 'secret',
			'base_url' => 'https://github.com',
		] );

		$this->expectException( IdentityProviderException::class );
		$this->expectExceptionMessage( 'invalid_grant' );

		$this->invokeCheckResponse( $provider, new Response( 200 ), [ 'error' => 'invalid_grant' ] );
	}

	/**
	 * @covers \MediaWiki\Extension\IssueTrackerLinks\Auth\OAuthProvider::checkResponse
	 */
	public function testCheckResponseAllowsSuccessfulPayload(): void {
		$provider = $this->newTestableProvider( [
			'clientId' => 'id',
			'clientSecret' => 'secret',
			'base_url' => 'https://github.com',
		] );

		$this->invokeCheckResponse(
			$provider,
			new Response( 200 ),
			[ 'access_token' => 'abc', 'token_type' => 'bearer' ]
		);

		$this->addToAssertionCount( 1 );
	}

	/**
	 * @covers \MediaWiki\Extension\IssueTrackerLinks\Auth\OAuthProvider::__construct
	 */
	public function testCanDisableTlsVerificationForOauthHttpClient(): void {
		$provider = $this->newTestableProvider( [
			'clientId' => 'id',
			'clientSecret' => 'secret',
			'base_url' => 'https://github.com',
			'insecure_tls' => true,
		] );

		$httpClient = $provider->getHttpClient();
		$this->assertInstanceOf( GuzzleClient::class, $httpClient );
		$this->assertFalse( $httpClient->getConfig( 'verify' ) );
	}

	private function newTestableProvider( array $options ): OAuthProvider {
		return new OAuthProvider( $options );
	}

	private function invokeCheckResponse( OAuthProvider $provider, Response $response, array $data ): void {
		$method = new \ReflectionMethod( OAuthProvider::class, 'checkResponse' );
		$method->setAccessible( true );
		$method->invoke( $provider, $response, $data );
	}
}
