<?php

namespace MediaWiki\Extension\IssueTrackerLinks\Property;

use BlueSpice\SMWConnector\PropertyValueProvider;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\WikiPageFactory;
use SMWDataItem;

class IssueTrackerLinksValueProvider extends PropertyValueProvider {

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
	 * @param \SESP\AppFactory $appFactory
	 * @param \SMW\DIProperty $property
	 * @param \SMW\SemanticData $semanticData
	 * @return void
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
				 \SMWDIUri::newFromSerialization( SMWDataItem::TYPE_URI, $tag )
			);
		}
	}
}
