<?php

namespace MediaWiki\Extension\IssueTrackerLinks\ContentDroplets;

use MediaWiki\Extension\ContentDroplets\Droplet\TagDroplet;
use MediaWiki\Message\Message;

class GithubDroplet extends TagDroplet {

	/**
	 * @inheritDoc
	 */
	public function getName(): Message {
		return Message::newFromKey(
			'issuetrackerlinks-inspector-header-title',
			Message::newFromKey( 'issuetrackerlinks-type-label-github' )->text()
		);
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription(): Message {
		return Message::newFromKey( 'issuetrackerlinks-generic-droplet-desc' );
	}

	/**
	 * @inheritDoc
	 */
	public function getIcon(): string {
		return 'droplet-github';
	}

	/**
	 * @return array
	 */
	public function getCategories(): array {
		return [ 'content' ];
	}

	/**
	 * @return string
	 */
	protected function getTagName(): string {
		return 'issue-tracker-link';
	}

	/**
	 * @return array
	 */
	protected function getAttributes(): array {
		return [
			'type' => 'github'
		];
	}

	/**
	 * @return string|null
	 */
	public function getVeCommand(): ?string {
		// Warning: Virtual command, defined in ext.issuetrackerlinks.commands.js
		return 'githubCommand';
	}

	/**
	 * @return bool
	 */
	protected function hasContent(): bool {
		return false;
	}

	/**
	 * @return array
	 */
	public function getRLModules(): array {
		return [ 'ext.issuetrackerlinks.commands' ];
	}
}
