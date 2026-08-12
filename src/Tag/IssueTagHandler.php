<?php

namespace MediaWiki\Extension\IssueTrackerLinks\Tag;

use MediaWiki\Extension\IssueTrackerLinks\DataProviderStore;
use MediaWiki\Extension\IssueTrackerLinks\PatternConfig;
use MediaWiki\Extension\IssueTrackerLinks\PopupIssueWidget;
use MediaWiki\Extension\IssueTrackerLinks\SimpleIssueWidget;
use MediaWiki\Message\Message;
use MediaWiki\Output\OutputPage;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\PPFrame;
use MWStake\MediaWiki\Component\GenericTagHandler\ITagHandler;
use OOUI\MessageWidget;

class IssueTagHandler implements ITagHandler {
// @phan-suppress-previous-line PhanUndeclaredInterface

	/**
	 * @param PatternConfig $patternConfig
	 * @param DataProviderStore $dataProviderStore
	 */
	public function __construct(
		private readonly PatternConfig $patternConfig,
		private readonly DataProviderStore $dataProviderStore
	) {
	}

	/**
	 * @param string $input
	 * @param array $params
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @return string
	 */
	public function getRenderedContent( string $input, array $params, Parser $parser, PPFrame $frame ): string {
		$type = $params['type'];
		$config = $this->patternConfig->getConfigForType( $type );
		$tagParams = $this->deserializeParams( $params['params'] );
		OutputPage::setupOOUI();
		if ( $config === null ) {
			return ( new MessageWidget( [
				'type' => 'error',
				'label' => Message::newFromKey( 'issuetrackerlinks-unknown-type', $type )->text()
			] ) )->toString();
		}
		$label = $this->makeLabel( $tagParams, $config );
		$url = $this->makeUrl( $tagParams, $config );
		$parser->getOutput()->appendExtensionData( 'IssueTrackerLinks.tags', $url );
		$this->updateExtensionData( $parser, $params['type'], $tagParams, $config );
		$parser->getOutput()->addModuleStyles( [ 'ext.issuetrackerlinks.styles' ] );

		if ( $this->isSimpleLink( $config ) ) {
			return ( new SimpleIssueWidget( $params['type'], $label, $url, $tagParams ) )->toString();
		}
		return ( new PopupIssueWidget( $params['type'], $label, $url, $tagParams, $config ) )->toString();
	}

	/**
	 * @param array $params
	 * @param array $config
	 * @return string
	 */
	private function makeLabel( array $params, array $config ): string {
		$mask = $config['display-mask'] ?? null;
		if ( !$mask ) {
			return implode( ';', array_values( $params ) );
		}
		// Match all {param}s from mask to available params, and replace. If not found, skip it
		return preg_replace_callback(
			'/\{([a-zA-Z0-9_-]+)\}/',
			static fn ( $matches ) => $params[$matches[1]] ?? '',
			$mask
		);
	}

	/**
	 * @param array $params
	 * @param array $config
	 * @return string
	 */
	private function makeUrl( array $params, array $config ): string {
		$urlMask = $config['url'];
		return preg_replace_callback(
			'/\{([a-zA-Z0-9_-]+)\}/',
			static fn ( $matches ) => $params[$matches[1]] ?? '',
			$urlMask
		);
	}

	/**
	 * @param string $raw
	 * @return array
	 */
	private function deserializeParams( string $raw ): array {
		// Raw string contains param_name:value;param_name2:value2
		$result = [];
		$pairs = explode( ';', $raw );
		foreach ( $pairs as $pair ) {
			$parts = explode( ':', $pair, 2 );
			if ( count( $parts ) === 2 ) {
				$result[trim( $parts[0] )] = urldecode( trim( $parts[1] ) );
			}
		}
		return $result;
	}

	/**
	 * @param array $config
	 * @return bool
	 */
	private function isSimpleLink( array $config ): bool {
		return !isset( $config['data-provider'] ) || !$this->dataProviderStore->hasProvider( $config['data-provider'] );
	}

	/**
	 * @param Parser $parser
	 * @param string $type
	 * @param array $params
	 * @param array $config
	 * @return void
	 */
	private function updateExtensionData( Parser $parser, string $type, array $params, array $config ) {
		$data = $parser->getOutput()->getExtensionData( 'IssueTrackerLinks.links' );
		if ( !is_array( $data ) ) {
			$data = [];
		}
		$data[] = [
			'type' => $type,
			'params' => $params,
			'provider' => $config['data-provider'] ?? null,
		];
		$parser->getOutput()->setExtensionData( 'IssueTrackerLinks.links', $data );
	}
}
