<?php

namespace MediaWiki\Extension\IssueTrackerLinks;

use MediaWiki\Config\Config;
use MediaWiki\Content\JsonContent;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\ResourceLoader\Context;
use MediaWiki\Title\TitleFactory;
use Psr\Log\LoggerInterface;

class PatternConfig {

	/**
	 * @param Context $context
	 * @param Config $config
	 * @return string
	 */
	public static function getClientConfig( Context $context, Config $config ): array {
		$self = MediaWikiServices::getInstance()->getService( 'IssueTrackerLinks.PatternConfig' );
		return $self->getFullConfig();
	}

	/** @var string */
	public const CONFIG_PAGE = 'MediaWiki:IssueTrackerLinksConfig.json';

	/** @var array|null */
	private ?array $patterns = null;

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
	 * @return bool
	 */
	public function isValid(): bool {
		$data = $this->getRawData();
		return $data !== null && $this->validateData( $data );
	}

	/**
	 * @param string $type
	 * @return array|null
	 */
	public function getConfigForType( string $type ): ?array {
		$this->load();
		return $this->patterns[$type] ?? null;
	}

	/**
	 * @return array
	 */
	public function getFullConfig(): array {
		$this->load();
		return $this->patterns ?? [];
	}

	/**
	 * @return JsonContent
	 */
	public function getDefaultContent(): JsonContent {
		return new JsonContent( json_encode( [
			'github' => [
				'url' => 'https://github.com/{owner}/{repo}/issues/{id}',
				'label' => 'issuetrackerlinks-type-label-github',
				'display-mask' => '{owner}/{repo}#{id}',
			],
			'gitlab' => [
				'url' => 'https://gitlab.com/{owner}/{repo}/-/issues/{id}',
				'label' => 'issuetrackerlinks-type-label-gitlab',
				'display-mask' => '{owner}-{repo}:{id}',
			],
			'jira' => [
				'url' => 'https://{jira-domain}/browse/{id}',
				'label' => 'issuetrackerlinks-type-label-jira',
				'display-mask' => '{id}',
			],
		], JSON_PRETTY_PRINT ) );
	}

	/**
	 * @return void
	 */
	private function load() {
		if ( $this->patterns === null ) {
			$data = $this->getRawData();
			if ( !$this->validateData( $data ) ) {
				$this->logger->error(
					'IssueTrackerLinks: Config page contains invalid data structure: ' . static::CONFIG_PAGE
				);
				$this->patterns = [];
				return;
			}
			$this->patterns = $data;
		}
	}

	/**
	 * @param array $data
	 * @return bool
	 */
	private function validateData( array $data ): bool {
		// Data must be in format: [ { "pattern-name-1": "...", "pattern-name-2": "..." }, ... ]
		// where values must be an array containing at least the "url" key
		$keys = [];
		foreach ( $data as $key => $value ) {
			if ( in_array( $key, $keys, true ) ) {
				// Duplicate keys are not allowed
				return false;
			}
			$keys[] = $key;
			if ( !is_string( $key ) || !is_array( $value ) || !isset( $value['url'] ) || !is_string( $value['url'] ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @return array|null
	 */
	private function getRawData(): ?array {
		$page = $this->titleFactory->newFromText( static::CONFIG_PAGE );
		if ( !$page || !$page->exists() ) {
			$this->logger->warning( 'IssueTrackerLinks: Config page does not exist: ' . static::CONFIG_PAGE );
			return [];
		}
		$wp = $this->wikiPageFactory->newFromTitle( $page );
		$content = $wp->getContent();
		if ( !( $content instanceof JsonContent ) ) {
			$this->logger->error( 'IssueTrackerLinks: Config page is not JSON content: ' . static::CONFIG_PAGE );
			return null;
		}
		$text = $content->getText();
		if ( empty( $text ) ) {
			$this->logger->warning( 'IssueTrackerLinks: Config page is empty: ' . static::CONFIG_PAGE );
			return [];
		}
		$data = json_decode( $text, true );
		if ( !is_array( $data ) ) {
			$this->logger->error( 'IssueTrackerLinks: Config page contains invalid JSON: ' . static::CONFIG_PAGE );
			return null;
		}

		return $data;
	}
}
