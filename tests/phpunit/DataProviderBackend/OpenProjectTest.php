<?php

namespace MediaWiki\Extension\IssueTrackerLinks\Tests\Unit\DataProviderBackend;

use MediaWiki\Extension\IssueTrackerLinks\DataProviderBackend\OpenProject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ReflectionClass;
use ReflectionMethod;

class OpenProjectTest extends TestCase {

	/**
	 * @covers \MediaWiki\Extension\IssueTrackerLinks\DataProviderBackend\OpenProject::validateEntityResponse
	 */
	public function testValidateEntityResponseAcceptsValidPayload(): void {
		$backend = $this->makeBackendWithoutConstructor();

		$this->invokeValidateEntityResponse( $backend, [
			'id' => 5,
			'subject' => 'Issue subject',
			'description' => [
				'html' => '<p>desc</p>',
			],
			'startDate' => '2026-08-14',
			'dueDate' => '2026-09-01',
			'_embedded' => [
				'status' => [
					'name' => 'In progress',
					'isClosed' => false,
					'color' => '#ff0',
				],
				'assignee' => [ 'name' => 'Alice' ],
				'responsible' => [ 'name' => 'Bob' ],
			],
		] );

		$this->addToAssertionCount( 1 );
	}

	/**
	 * @covers \MediaWiki\Extension\IssueTrackerLinks\DataProviderBackend\OpenProject::validateEntityResponse
	 */
	public function testValidateEntityResponseAcceptsMissingOptionalFields(): void {
		$backend = $this->makeBackendWithoutConstructor();

		$this->invokeValidateEntityResponse( $backend, [
			'id' => 5,
			'subject' => 'Issue subject',
			'_embedded' => [
				'status' => [
					'name' => 'Open',
					'isClosed' => false,
				],
			],
		] );

		$this->addToAssertionCount( 1 );
	}

	/**
	 * @covers \MediaWiki\Extension\IssueTrackerLinks\DataProviderBackend\OpenProject::validateEntityResponse
	 */
	public function testValidateEntityResponseRejectsMissingStatusIsClosed(): void {
		$backend = $this->makeBackendWithoutConstructor();

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'OpenProject API error: Invalid response' );

		$this->invokeValidateEntityResponse( $backend, [
			'id' => 5,
			'subject' => 'Issue subject',
			'_embedded' => [
				'status' => [
					'name' => 'Open',
				],
			],
		] );
	}

	/**
	 * @covers \MediaWiki\Extension\IssueTrackerLinks\DataProviderBackend\OpenProject::validateEntityResponse
	 */
	public function testValidateEntityResponseRejectsInvalidDescriptionType(): void {
		$backend = $this->makeBackendWithoutConstructor();

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'OpenProject API error: Invalid response' );

		$this->invokeValidateEntityResponse( $backend, [
			'id' => 5,
			'subject' => 'Issue subject',
			'description' => 'should be object',
			'_embedded' => [
				'status' => [
					'name' => 'Open',
					'isClosed' => false,
				],
			],
		] );
	}

	/**
	 * @covers \MediaWiki\Extension\IssueTrackerLinks\DataProviderBackend\OpenProject::getKey
	 */
	public function testGetKey(): void {
		$backend = $this->makeBackendWithoutConstructor();
		$this->assertSame( 'openproject', $backend->getKey() );
	}

	/**
	 * @covers \MediaWiki\Extension\IssueTrackerLinks\DataProviderBackend\OpenProject::isValidConfiguration
	 */
	public function testIsValidConfiguration(): void {
		$backend = $this->makeBackendWithoutConstructor();

		$this->assertTrue( $backend->isValidConfiguration( [
			'base_url' => 'https://openproject.local',
			'client_id' => 'abc',
			'client_secret' => 'def',
		] ) );

		$this->assertFalse( $backend->isValidConfiguration( [
			'base_url' => 'https://openproject.local',
			'client_id' => 'abc',
		] ) );
		$this->assertFalse( $backend->isValidConfiguration( [
			'base_url' => '',
			'client_id' => 'abc',
			'client_secret' => 'def',
		] ) );
	}

	private function makeBackendWithoutConstructor(): OpenProject {
		$reflection = new ReflectionClass( OpenProject::class );
		/** @var OpenProject $backend */
		$backend = $reflection->newInstanceWithoutConstructor();

		$loggerProperty = $reflection->getProperty( 'logger' );
		$loggerProperty->setValue( $backend, new NullLogger() );

		return $backend;
	}

	private function invokeValidateEntityResponse( OpenProject $backend, array $payload ): void {
		$method = new ReflectionMethod( OpenProject::class, 'validateEntityResponse' );
		$method->setAccessible( true );
		$method->invoke( $backend, $payload );
	}
}
