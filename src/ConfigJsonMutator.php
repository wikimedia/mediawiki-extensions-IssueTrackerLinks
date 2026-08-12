<?php

namespace MediaWiki\Extension\IssueTrackerLinks;

use DomainException;
use InvalidArgumentException;
use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Content\JsonContent;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\User;
use OutOfBoundsException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use UnexpectedValueException;

class ConfigJsonMutator {

	/**
	 * @var User|null
	 */
	private ?User $actor = null;

	/**
	 * @param TitleFactory $titleFactory
	 * @param WikiPageFactory $wikiPageFactory
	 * @param LoggerInterface $logger
	 */
	public function __construct(
		private readonly TitleFactory $titleFactory,
		private readonly WikiPageFactory $wikiPageFactory,
		private readonly LoggerInterface $logger
	) {
	}

	/**
	 * @param string $key
	 * @param array $config
	 * @param string|null $summary
	 * @return void
	 */
	public function addLinkConfig( string $key, array $config, ?string $summary = null ): void {
		$this->assertValidKey( $key );
		$all = $this->loadConfig();
		if ( isset( $all[$key] ) ) {
			throw new DomainException( "IssueTrackerLinks config key already exists: {$key}" );
		}

		$all[$key] = $this->normalizeConfig( $config );
		$this->assertNoDuplicates( $all );
		$this->saveConfig( $all, $summary ?? "Added IssueTrackerLinks config: {$key}" );
	}

	/**
	 * @param User $actor
	 * @return void
	 */
	public function setActor( User $actor ): void {
		$this->actor = $actor;
	}

	/**
	 * @param string $key
	 * @param array $config
	 * @param string|null $newKey
	 * @param string|null $summary
	 * @return void
	 */
	public function updateLinkConfig(
		string $key,
		array $config,
		?string $newKey = null,
		?string $summary = null
	): void {
		$this->assertValidKey( $key );
		$all = $this->loadConfig();
		if ( !isset( $all[$key] ) ) {
			throw new OutOfBoundsException( "IssueTrackerLinks config key does not exist: {$key}" );
		}

		$targetKey = $newKey ?? $key;
		$this->assertValidKey( $targetKey );
		if ( $targetKey !== $key && isset( $all[$targetKey] ) ) {
			throw new DomainException( "IssueTrackerLinks config key already exists: {$targetKey}" );
		}

		$existing = $all[$key];
		unset( $all[$key] );
		$all[$targetKey] = $this->normalizeConfig( array_merge( $existing, $config ) );

		$this->assertNoDuplicates( $all );
		$this->saveConfig( $all, $summary ?? "Updated IssueTrackerLinks config: {$key}" );
	}

	/**
	 * @param string $key
	 * @param string|null $summary
	 * @return void
	 */
	public function removeLinkConfig( string $key, ?string $summary = null ): void {
		$this->assertValidKey( $key );
		$all = $this->loadConfig();
		if ( !isset( $all[$key] ) ) {
			throw new OutOfBoundsException( "IssueTrackerLinks config key does not exist: {$key}" );
		}

		unset( $all[$key] );
		$this->saveConfig( $all, $summary ?? "Removed IssueTrackerLinks config: {$key}" );
	}

	/**
	 * @return array
	 */
	private function loadConfig(): array {
		$title = $this->titleFactory->newFromText( PatternConfig::CONFIG_PAGE );
		if ( !$title ) {
			throw new RuntimeException( 'Failed to resolve config page title' );
		}

		if ( !$title->exists() ) {
			return [];
		}

		$wikiPage = $this->wikiPageFactory->newFromTitle( $title );
		$content = $wikiPage->getContent();
		if ( $content === null ) {
			return [];
		}
		if ( !( $content instanceof JsonContent ) ) {
			throw new UnexpectedValueException(
				'IssueTrackerLinks config page is not JSON content'
			);
		}

		$text = $content->getText();
		if ( $text === '' ) {
			return [];
		}

		$data = json_decode( $text, true );
		if ( !is_array( $data ) ) {
			throw new UnexpectedValueException( 'IssueTrackerLinks config contains invalid JSON object' );
		}

		foreach ( $data as $key => $row ) {
			$this->assertValidKey( $key );
			if ( !is_array( $row ) ) {
				throw new UnexpectedValueException( "Config entry '{$key}' must be an object" );
			}
			$this->normalizeConfig( $row );
		}

		return $data;
	}

	/**
	 * @param array $all
	 * @param string $summary
	 * @return void
	 */
	private function saveConfig( array $all, string $summary ): void {
		if ( !$this->actor ) {
			throw new RuntimeException( 'No actor set' );
		}
		$title = $this->titleFactory->newFromText( PatternConfig::CONFIG_PAGE );
		if ( !$title ) {
			throw new RuntimeException( 'Failed to resolve config page title' );
		}

		$json = json_encode( $all, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		if ( !is_string( $json ) ) {
			throw new RuntimeException( 'Failed to encode IssueTrackerLinks config JSON' );
		}

		$content = new JsonContent( $json . "\n" );
		$wikiPage = $this->wikiPageFactory->newFromTitle( $title );
		$updater = $wikiPage->newPageUpdater( $this->actor );
		$updater->setContent( SlotRecord::MAIN, $content );
		$updater->saveRevision( CommentStoreComment::newUnsavedComment( $summary ) );

		$status = $updater->getStatus();
		if ( !$status->isOK() ) {
			$this->logger->error(
				'IssueTrackerLinks: failed to save config page {page}: {errors}',
				[
					'page' => PatternConfig::CONFIG_PAGE,
					'errors' => implode( '; ', $status->getErrorsArray() )
				]
			);
			throw new RuntimeException( 'Failed to persist IssueTrackerLinks config page' );
		}
	}

	/**
	 * @param array $config
	 * @return array
	 */
	private function normalizeConfig( array $config ): array {
		if ( !isset( $config['url'] ) || !is_string( $config['url'] ) ) {
			throw new InvalidArgumentException( 'Config entry requires a string "url" value' );
		}
		if ( trim( $config['url'] ) === '' ) {
			throw new InvalidArgumentException( 'Config entry "url" cannot be empty' );
		}

		if ( isset( $config['name'] ) && !is_string( $config['name'] ) ) {
			throw new InvalidArgumentException( 'Config entry "name" must be a string' );
		}
		if ( isset( $config['label'] ) && !is_string( $config['label'] ) ) {
			throw new InvalidArgumentException( 'Config entry "label" must be a string' );
		}

		return $config;
	}

	/**
	 * @param array $all
	 * @return void
	 */
	private function assertNoDuplicates( array $all ): void {
		$urlIndex = [];
		$nameIndex = [];

		foreach ( $all as $key => $config ) {
			$url = trim( (string)$config['url'] );
			if ( isset( $urlIndex[$url] ) ) {
				throw new DomainException(
					"IssueTrackerLinks config URL already exists for key '{$urlIndex[$url]}': {$url}"
				);
			}
			$urlIndex[$url] = $key;

			$name = $this->getEntryName( $config );
			if ( $name !== null ) {
				$nameKey = strtolower( $name );
				if ( isset( $nameIndex[$nameKey] ) ) {
					throw new DomainException(
						"IssueTrackerLinks config name already exists for key '{$nameIndex[$nameKey]}': {$name}"
					);
				}
				$nameIndex[$nameKey] = $key;
			}
		}
	}

	/**
	 * @param array $config
	 * @return string|null
	 */
	private function getEntryName( array $config ): ?string {
		if ( isset( $config['name'] ) && is_string( $config['name'] ) && trim( $config['name'] ) !== '' ) {
			return trim( $config['name'] );
		}
		if ( isset( $config['label'] ) && is_string( $config['label'] ) && trim( $config['label'] ) !== '' ) {
			return trim( $config['label'] );
		}

		return null;
	}

	/**
	 * @param string $key
	 * @return void
	 */
	private function assertValidKey( string $key ): void {
		if ( trim( $key ) === '' ) {
			throw new InvalidArgumentException( 'Config key cannot be empty' );
		}
	}
}
