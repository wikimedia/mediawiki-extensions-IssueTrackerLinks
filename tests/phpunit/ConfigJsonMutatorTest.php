<?php

namespace MediaWiki\Extension\IssueTrackerLinks\Tests\Unit;

use InvalidArgumentException;
use MediaWiki\Extension\IssueTrackerLinks\ConfigJsonMutator;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class ConfigJsonMutatorTest extends TestCase {

	/**
	 * @covers \MediaWiki\Extension\IssueTrackerLinks\ConfigJsonMutator::normalizeConfig
	 */
	public function testNormalizeConfigAcceptsValidConfig(): void {
		$mutator = $this->newMutatorWithoutConstructor();

		$result = $this->invokePrivate( $mutator, 'normalizeConfig', [ [
			'url' => 'https://example.org/{id}',
			'name' => 'Example',
			'label' => 'Example label',
		] ] );

		$this->assertSame( [
			'url' => 'https://example.org/{id}',
			'name' => 'Example',
			'label' => 'Example label',
		], $result );
	}

	/**
	 * @covers \MediaWiki\Extension\IssueTrackerLinks\ConfigJsonMutator::normalizeConfig
	 */
	public function testNormalizeConfigRejectsMissingUrl(): void {
		$mutator = $this->newMutatorWithoutConstructor();

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Config entry requires a string "url" value' );

		$this->invokePrivate( $mutator, 'normalizeConfig', [ [ 'name' => 'Example' ] ] );
	}

	/**
	 * @covers \MediaWiki\Extension\IssueTrackerLinks\ConfigJsonMutator::normalizeConfig
	 */
	public function testNormalizeConfigRejectsEmptyUrl(): void {
		$mutator = $this->newMutatorWithoutConstructor();

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Config entry "url" cannot be empty' );

		$this->invokePrivate( $mutator, 'normalizeConfig', [ [ 'url' => '   ' ] ] );
	}

	/**
	 * @covers \MediaWiki\Extension\IssueTrackerLinks\ConfigJsonMutator::normalizeConfig
	 */
	public function testNormalizeConfigRejectsInvalidNameAndLabelTypes(): void {
		$mutator = $this->newMutatorWithoutConstructor();

		try {
			$this->invokePrivate( $mutator, 'normalizeConfig', [ [
				'url' => 'https://example.org/{id}',
				'name' => 123,
			] ] );
			$this->fail( 'Expected InvalidArgumentException for non-string name' );
		} catch ( InvalidArgumentException $e ) {
			$this->assertSame( 'Config entry "name" must be a string', $e->getMessage() );
		}

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Config entry "label" must be a string' );

		$this->invokePrivate( $mutator, 'normalizeConfig', [ [
			'url' => 'https://example.org/{id}',
			'label' => 123,
		] ] );
	}

	/**
	 * @covers \MediaWiki\Extension\IssueTrackerLinks\ConfigJsonMutator::assertNoDuplicates
	 */
	public function testAssertNoDuplicatesRejectsDuplicateUrl(): void {
		$mutator = $this->newMutatorWithoutConstructor();

		$this->expectException( \DomainException::class );
		$this->expectExceptionMessage(
			"IssueTrackerLinks config URL already exists for key 'github': https://example.org/{id}"
		);

		$this->invokePrivate( $mutator, 'assertNoDuplicates', [ [
			'github' => [ 'url' => 'https://example.org/{id}', 'name' => 'GitHub' ],
			'gitlab' => [ 'url' => 'https://example.org/{id}', 'name' => 'GitLab' ],
		] ] );
	}

	/**
	 * @covers \MediaWiki\Extension\IssueTrackerLinks\ConfigJsonMutator::assertNoDuplicates
	 */
	public function testAssertNoDuplicatesRejectsDuplicateNameCaseInsensitive(): void {
		$mutator = $this->newMutatorWithoutConstructor();

		$this->expectException( \DomainException::class );
		$this->expectExceptionMessage(
			"IssueTrackerLinks config name already exists for key 'github': github"
		);

		$this->invokePrivate( $mutator, 'assertNoDuplicates', [ [
			'github' => [ 'url' => 'https://example.org/github/{id}', 'name' => 'GitHub' ],
			'mirror' => [ 'url' => 'https://example.org/mirror/{id}', 'name' => 'github' ],
		] ] );
	}

	/**
	 * @covers \MediaWiki\Extension\IssueTrackerLinks\ConfigJsonMutator::getEntryName
	 */
	public function testGetEntryNameUsesTrimmedNameThenLabelFallback(): void {
		$mutator = $this->newMutatorWithoutConstructor();

		$this->assertSame(
			'Canonical Name',
			$this->invokePrivate( $mutator, 'getEntryName', [ [
				'name' => '  Canonical Name  ',
				'label' => 'Fallback Label',
			] ] )
		);
		$this->assertSame(
			'Fallback Label',
			$this->invokePrivate( $mutator, 'getEntryName', [ [
				'name' => '   ',
				'label' => '  Fallback Label  ',
			] ] )
		);
		$this->assertNull(
			$this->invokePrivate( $mutator, 'getEntryName', [ [
				'name' => '   ',
				'label' => '   ',
			] ] )
		);
	}

	/**
	 * @covers \MediaWiki\Extension\IssueTrackerLinks\ConfigJsonMutator::assertValidKey
	 */
	public function testAssertValidKeyRejectsEmptyKey(): void {
		$mutator = $this->newMutatorWithoutConstructor();

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Config key cannot be empty' );

		$this->invokePrivate( $mutator, 'assertValidKey', [ '  ' ] );
	}

	private function newMutatorWithoutConstructor(): ConfigJsonMutator {
		$reflection = new ReflectionClass( ConfigJsonMutator::class );
		/** @var ConfigJsonMutator $mutator */
		$mutator = $reflection->newInstanceWithoutConstructor();
		return $mutator;
	}

	/**
	 * @param ConfigJsonMutator $mutator
	 * @param string $method
	 * @param array $args
	 * @return mixed
	 */
	private function invokePrivate( ConfigJsonMutator $mutator, string $method, array $args ) {
		$reflectionMethod = new ReflectionMethod( ConfigJsonMutator::class, $method );
		$reflectionMethod->setAccessible( true );
		return $reflectionMethod->invokeArgs( $mutator, $args );
	}
}
