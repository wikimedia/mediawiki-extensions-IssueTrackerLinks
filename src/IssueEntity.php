<?php

namespace MediaWiki\Extension\IssueTrackerLinks;

class IssueEntity implements \JsonSerializable {

	public function __construct(
		public readonly string $id,
		public readonly string $title,
		public readonly bool $isClosed,
		public readonly string $html
	) {
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'title' => $this->title,
			'isClosed' => $this->isClosed,
			'html' => $this->html
		];
	}
}
