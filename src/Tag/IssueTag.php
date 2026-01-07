<?php

namespace MediaWiki\Extension\IssueTrackerLinks\Tag;

use MediaWiki\Language\RawMessage;
use MediaWiki\MediaWikiServices;
use MediaWiki\Message\Message;
use MWStake\MediaWiki\Component\FormEngine\FormLoaderSpecification;
use MWStake\MediaWiki\Component\GenericTagHandler\ClientTagSpecification;
use MWStake\MediaWiki\Component\GenericTagHandler\GenericTag;
use MWStake\MediaWiki\Component\GenericTagHandler\ITagHandler;
use MWStake\MediaWiki\Component\InputProcessor\Processor\StringValue;

class IssueTag extends GenericTag {

	/**
	 * @return string[]
	 */
	public function getTagNames(): array {
		return [
			'issue'
		];
	}

	/**
	 * @return string|null
	 */
	public function getContainerElementName(): ?string {
		return 'span';
	}

	/**
	 * @return bool
	 */
	public function hasContent(): bool {
		return false;
	}

	/**
	 * @return array|null
	 */
	public function getParamDefinition(): ?array {
		return [
			'params' => ( new StringValue() )->setRequired( true ),
			'type' => ( new StringValue() )->setRequired( true )
		];
	}

	/**
	 * @return string[]|null
	 */
	public function getResourceLoaderModules(): ?array {
		return [ 'ext.issuetrackerlinks.definition' ];
	}

	/**
	 * @param MediaWikiServices $services
	 * @return ITagHandler
	 */
	public function getHandler( MediaWikiServices $services ): ITagHandler {
		return new IssueTagHandler( $services->getService( 'IssueTrackerLinks.PatternConfig' ) );
	}

	/**
	 * @inheritDoc
	 */
	public function getClientTagSpecification(): ClientTagSpecification|null {
		return new ClientTagSpecification(
			'IssueTrackerLink',
			new RawMessage( '' ),
			new FormLoaderSpecification(
				'ext.issuetrackerlinks.droplet.Form', [ 'ext.issuetrackerlinks.droplet.form' ]
			),
			Message::newFromKey( "issuetrackerlinks-inspector-title" )
		);
	}
}
