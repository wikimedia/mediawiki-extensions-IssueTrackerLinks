<?php

namespace MediaWiki\Extension\IssueTrackerLinks;

use ManualLogEntry;
use MediaWiki\Content\TextContent;
use MediaWiki\Page\Hook\PageDeleteCompleteHook;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Permissions\Authority;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Storage\Hook\PageSaveCompleteHook;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * TODO: This will get corrupted if config is changes, if names are changed, provider names change...
 */
class LinkRegistry implements PageSaveCompleteHook, PageDeleteCompleteHook {

	private const ISSUE_TABLE = 'issuetrackerlinks_issues';

	/**
	 * @param ILoadBalancer $lb
	 * @param DataProviderStore $dataProviderStore
	 * @param PermissionManager $permissionManager
	 * @param TitleFactory $titleFactory
	 */
	public function __construct(
		private readonly ILoadBalancer $lb,
		private readonly DataProviderStore $dataProviderStore,
		private readonly PermissionManager $permissionManager,
		private readonly TitleFactory $titleFactory
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function onPageSaveComplete( $wikiPage, $user, $summary, $flags, $revisionRecord, $editResult ) {
		$data = $wikiPage->getParserOutput()->getExtensionData( 'IssueTrackerLinks.links' );

		$this->refreshFromData( $wikiPage->getTitle(), $data ?? [] );
	}

	/**
	 * @inheritDoc
	 */
	public function onPageDeleteComplete(
		ProperPageIdentity $page, Authority $deleter, string $reason, int $pageID, RevisionRecord $deletedRev,
		ManualLogEntry $logEntry, int $archivedRevisionCount
	) {
		$this->clearLinks( $page );
	}

	/**
	 * @param RevisionRecord $rev
	 * @param Authority $actor
	 * @param Parser $parser
	 * @return void
	 * @throws \Exception
	 */
	public function rebuildForPage( RevisionRecord $rev, Authority $actor, Parser $parser ): void {
		$content = $rev->getContent( SlotRecord::MAIN );
		if ( !$content instanceof TextContent ) {
			throw new \InvalidArgumentException( 'Content is not a TextContent' );
		}
		$parserOptions = ParserOptions::newFromUser( $actor->getUser() );
		$output = $parser->parse( $content->getText(), $rev->getPage(), $parserOptions );
		$data = $output->getExtensionData( 'IssueTrackerLinks.links' );
		$this->refreshFromData( $rev->getPage(), $data ?? [] );
	}

	/**
	 * @param string $type
	 * @param array $params
	 * @param Authority $requester
	 * @return array
	 */
	public function getPagesForIssueType( string $type, array $params, Authority $requester ): array {
		return $this->getData(
			conds: [
				'itli_type' => $type,
			],
			paramsToMatch: $params,
			requester: $requester
		);
	}

	/**
	 * @param string $dataProvider
	 * @param array $params
	 * @param Authority $requester
	 * @return array
	 * @throws \Exception
	 */
	public function getPagesForDataProvider( string $dataProvider, array $params, Authority $requester ): array {
		$providerId = $this->dataProviderStore->getProviderId( $dataProvider );
		if ( !$providerId ) {
			return [];
		}

		return $this->getData(
			conds: [
				'itli_data_provider' => $providerId,
			],
			paramsToMatch: $params,
			requester: $requester
		);
	}

	/**
	 * @param PageIdentity $title
	 * @param array $data
	 * @return void
	 * @throws \Exception
	 */
	private function refreshFromData( PageIdentity $title, array $data ): void {
		$this->clearLinks( $title );

		if ( !$data ) {
			return;
		}
		$rows = [];
		foreach ( $data as $item ) {
			if ( $item['provider'] ) {
				$item['provider'] = $this->dataProviderStore->getProviderId( $item['provider'] );
			}
			$rows[] = [
				'itli_page_title' => $title->getDBkey(),
				'itli_page_namespace' => $title->getNamespace(),
				'itli_wiki_id' => WikiMap::getCurrentWikiId(),
				'itli_type' => $item['type'],
				'itli_params' => json_encode( $item['params'] ),
				'itli_data_provider' => $item['provider'] ?? null,
			];
		}
		$this->lb->getConnection( DB_PRIMARY )->newInsertQueryBuilder()
			->table( self::ISSUE_TABLE )
			->rows( $rows )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * @param Title $title
	 * @return void
	 */
	private function clearLinks( PageIdentity $title ): void {
		$this->lb->getConnection( DB_PRIMARY )->newDeleteQueryBuilder()
			->delete( self::ISSUE_TABLE )
			->where( [
				'itli_page_title' => $title->getDBkey(),
				'itli_page_namespace' => $title->getNamespace(),
				'itli_wiki_id' => WikiMap::getCurrentWikiId(),
			] )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * TODO: Multi-wiki retrieval?
	 * @param array $conds
	 * @param array $paramsToMatch
	 * @param Authority $requester
	 * @return array
	 */
	private function getData( array $conds, array $paramsToMatch, Authority $requester ): array {
		$conds['itli_wiki_id'] = WikiMap::getCurrentWikiId();
		$rows = $this->lb->getConnection( DB_REPLICA )->newSelectQueryBuilder()
			->table( self::ISSUE_TABLE )
			->fields( [ 'itli_page_title', 'itli_page_namespace', 'itli_wiki_id', 'itli_params' ] )
			->where( $conds )
			->caller( __METHOD__ )
			->fetchResultSet();

		$data = [];
		foreach ( $rows as $row ) {
			$issueParams = json_decode( $row->itli_params, true );
			if ( !is_array( $issueParams ) ) {
				continue;
			}
			foreach ( $paramsToMatch as $key => $value ) {
				if ( $issueParams[$key] !== $value ) {
					continue 2;
				}
			}

			if ( $row->itli_wiki_id !== WikiMap::getCurrentWikiId() ) {
				continue;
			}

			$title = $this->titleFactory->newFromText( $row->itli_page_title, $row->itli_page_namespace );
			if ( !$title ) {
				continue;
			}

			if ( !$this->permissionManager->userCan( 'read', $requester, $title ) ) {
				continue;
			}

			$data[] = [
				'page_title' => $row->itli_page_title,
				'page_namespace' => (int)$row->itli_page_namespace,
				'wiki_id' => $row->itli_wiki_id,
				'url' => $title?->getFullURL(),
				'params' => $issueParams,
			];
		}
		return $data;
	}
}
