<?php

namespace MediaWiki\Extension\IssueTrackerLinks\Tests\Unit\DataProviderBackend;

use MediaWiki\Extension\IssueTrackerLinks\DataProviderBackend\GitHub;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ReflectionClass;
use ReflectionMethod;

class GitHubTest extends TestCase {

	/**
	 * @covers \MediaWiki\Extension\IssueTrackerLinks\DataProviderBackend\GitHub::validateEntityResponse
	 */
	public function testValidateEntityResponseAcceptsValidPayload(): void {
		$backend = $this->makeBackendWithoutConstructor();

		$this->invokeValidateEntityResponse( $backend, [
			'id' => 42,
			'number' => 123,
			'title' => 'Issue title',
			'state' => 'open',
			'created_at' => '2026-08-14T10:00:00Z',
			'updated_at' => '2026-08-14T11:00:00Z',
			'closed_at' => null,
			'body' => 'description',
			'user' => [
				'login' => 'octocat',
				'avatar_url' => 'https://avatars.githubusercontent.com/u/1',
				'html_url' => 'https://github.com/octocat',
			],
			'assignees' => [
				[
					'login' => 'alice',
					'avatar_url' => 'https://avatars.githubusercontent.com/u/2',
					'html_url' => 'https://github.com/alice',
				],
			],
		] );

		$this->addToAssertionCount( 1 );
	}

	/**
	 * @covers \MediaWiki\Extension\IssueTrackerLinks\DataProviderBackend\GitHub::validateEntityResponse
	 */
	public function testValidateEntityResponseRejectsMissingNumber(): void {
		$backend = $this->makeBackendWithoutConstructor();

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Github API error: Invalid response' );

		$this->invokeValidateEntityResponse( $backend, [
			'id' => 42,
			'title' => 'Issue title',
			'state' => 'open',
			'created_at' => '2026-08-14T10:00:00Z',
			'updated_at' => '2026-08-14T11:00:00Z',
			'closed_at' => null,
			'user' => [
				'login' => 'octocat',
				'avatar_url' => 'https://avatars.githubusercontent.com/u/1',
				'html_url' => 'https://github.com/octocat',
			],
			'assignees' => [],
		] );
	}

	/**
	 * @covers \MediaWiki\Extension\IssueTrackerLinks\DataProviderBackend\GitHub::validateEntityResponse
	 */
	public function testValidateEntityResponseRejectsInvalidAssigneeShape(): void {
		$backend = $this->makeBackendWithoutConstructor();

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Github API error: Invalid response' );

		$this->invokeValidateEntityResponse( $backend, [
			'id' => 42,
			'number' => 123,
			'title' => 'Issue title',
			'state' => 'open',
			'created_at' => '2026-08-14T10:00:00Z',
			'updated_at' => '2026-08-14T11:00:00Z',
			'closed_at' => null,
			'user' => [
				'login' => 'octocat',
				'avatar_url' => 'https://avatars.githubusercontent.com/u/1',
				'html_url' => 'https://github.com/octocat',
			],
			'assignees' => [
				[
					'login' => 'alice',
					'avatar_url' => 'https://avatars.githubusercontent.com/u/2',
				],
			],
		] );
	}

	/**
	 * @covers \MediaWiki\Extension\IssueTrackerLinks\DataProviderBackend\GitHub::getKey
	 */
	public function testGetKey(): void {
		$backend = $this->makeBackendWithoutConstructor();
		$this->assertSame( 'github', $backend->getKey() );
	}

	/**
	 * @covers \MediaWiki\Extension\IssueTrackerLinks\DataProviderBackend\GitHub::isValidConfiguration
	 */
	public function testIsValidConfiguration(): void {
		$backend = $this->makeBackendWithoutConstructor();

		$this->assertTrue( $backend->isValidConfiguration( [
			'base_url' => 'https://github.com',
			'client_id' => 'abc',
			'client_secret' => 'def',
		] ) );

		$this->assertFalse( $backend->isValidConfiguration( [
			'base_url' => 'https://github.com',
			'client_id' => 'abc',
		] ) );
		$this->assertFalse( $backend->isValidConfiguration( [
			'base_url' => 'https://github.com',
			'client_id' => 'abc',
			'client_secret' => '',
		] ) );
	}

	private function makeBackendWithoutConstructor(): GitHub {
		$reflection = new ReflectionClass( GitHub::class );
		/** @var GitHub $backend */
		$backend = $reflection->newInstanceWithoutConstructor();

		$loggerProperty = $reflection->getProperty( 'logger' );
		$loggerProperty->setValue( $backend, new NullLogger() );

		return $backend;
	}

	private function invokeValidateEntityResponse( GitHub $backend, array $payload ): void {
		$method = new ReflectionMethod( GitHub::class, 'validateEntityResponse' );
		$method->setAccessible( true );
		$method->invoke( $backend, $payload );
	}
}
