<?php

namespace MediaWiki\Extension\IssueTrackerLinks\Property;

use BlueSpice\SMWConnector\PropertyValueProvider;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\WikiPageFactory;
use SMWDataItem;

class IssueTrackerLinksValueProvider extends PropertyValueProvider {
// @phan-suppress-previous-line PhanUndeclaredExtendedClass

	/**
	 * @inheritDoc
	 */
	public static function factory() {
		return [ new static(
			MediaWikiServices::getInstance()->getWikiPageFactory()
		) ];
	}

	/**
	 * @param WikiPageFactory $wikiPageFactory
	 */
	public function __construct(
		private readonly WikiPageFactory $wikiPageFactory
	) {
	}

	/**
	 * @return string
	 */
	public function getAliasMessageKey() {
		return "issuetrackerlinks-sesp-alias";
	}

	/**
	 * @return string
	 */
	public function getDescriptionMessageKey() {
		return "issuetrackerlinks-sesp-desc";
	}

	/**
	 * @return int
	 */
	public function getType() {
		// @phan-suppress-next-line PhanUndeclaredClassConstant
		return SMWDataItem::TYPE_URI;
	}

	/**
	 * @return string
	 */
	public function getId() {
		return '_ISSUETRACKERLINKS';
	}

	/**
	 * @return string
	 */
	public function getLabel() {
		return "Issue tracker links";
	}

	/**
	 * @inheritDoc
	 */
	public function addAnnotation( $appFactory, $property, $semanticData ) {
		$title = $semanticData->getSubject()->getTitle();
		if ( $title === null ) {
			return;
		}
		$wikipage = $this->wikiPageFactory->newFromTitle( $title );
		$parserOutput = $wikipage->getParserOutput();
		if ( !$parserOutput ) {
			return;
		}
		$tags = $parserOutput->getExtensionData( 'IssueTrackerLinks.tags' ) ?? [];
		$tags = array_unique( array_keys( $tags ) );

		foreach ( $tags as $i => $tag ) {
			$semanticData->addPropertyObjectValue(
				$property,
				// @phan-suppress-next-line PhanUndeclaredClassMethod, PhanUndeclaredClassConstant
				\SMWDIUri::newFromSerialization( SMWDataItem::TYPE_URI, $tag )
			);
		}
	}
}
