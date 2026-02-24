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
// @phan-suppress-previous-line PhanUndeclaredExtendedClass

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
			// @phan-suppress-next-line PhanUndeclaredClassMethod
			'params' => ( new StringValue() )->setRequired( true ),
			// @phan-suppress-next-line PhanUndeclaredClassMethod
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
		// @phan-suppress-previous-line PhanUndeclaredTypeReturnType
		return new IssueTagHandler( $services->getService( 'IssueTrackerLinks.PatternConfig' ) );
	}

	/**
	 * @inheritDoc
	 */
	public function getClientTagSpecification(): ClientTagSpecification|null {
		// @phan-suppress-previous-line PhanUndeclaredTypeReturnType
		// @phan-suppress-next-line PhanUndeclaredClassMethod
		return new ClientTagSpecification(
			'IssueTrackerLink',
			new RawMessage( '' ),
			// @phan-suppress-next-line PhanUndeclaredClassMethod
			new FormLoaderSpecification(
				'ext.issuetrackerlinks.droplet.Form', [ 'ext.issuetrackerlinks.droplet.form' ]
			),
			Message::newFromKey( "issuetrackerlinks-inspector-title" )
		);
	}
}
