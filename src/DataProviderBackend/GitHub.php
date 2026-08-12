<?php

namespace MediaWiki\Extension\IssueTrackerLinks\DataProviderBackend;

use Config;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use MediaWiki\Extension\IssueTrackerLinks\Auth\AuthDataStore;
use MediaWiki\Extension\IssueTrackerLinks\Auth\Exception\EntityForbiddenException;
use MediaWiki\Extension\IssueTrackerLinks\Auth\Exception\EntityNotFoundException;
use MediaWiki\Extension\IssueTrackerLinks\Auth\Exception\NoAuthException;
use MediaWiki\Extension\IssueTrackerLinks\Auth\OAuthAccessTrait;
use MediaWiki\Extension\IssueTrackerLinks\DataProvider;
use MediaWiki\Extension\IssueTrackerLinks\IssueEntity;
use MediaWiki\Extension\IssueTrackerLinks\Util\HtmlFormatterTrait;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Message\Message;
use MediaWiki\Output\OutputPage;
use MediaWiki\Permissions\Authority;
use MWStake\MediaWiki\Component\FormEngine\StandaloneFormSpecification;
use OOUI\Exception;
use OOUI\FieldLayout;
use OOUI\LabelWidget;
use OOUI\PanelLayout;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class GitHub implements OAuthProviderBackend, LoggerAwareInterface {
	use OAuthAccessTrait;
	use HtmlFormatterTrait;

	/** @var Config|null */
	private ?Config $connectionConfig = null;

	/** @var LoggerInterface */
	private LoggerInterface $logger;

	public function __construct(
		private readonly HttpRequestFactory $httpRequestFactory
	) {
		$this->logger = new NullLogger();
	}

	/**
	 * @param LoggerInterface $logger
	 * @return void
	 */
	public function setLogger( LoggerInterface $logger ): void {
		$this->logger = $logger;
	}

	/**
	 * @param Config $config
	 * @return void
	 */
	public function setConfig( Config $config ) {
		$this->connectionConfig = $config;
		$this->setOAuthConfig( $config );
	}

	/**
	 * @param AuthDataStore $authDataStore
	 * @return void
	 */
	public function setAuthStore( AuthDataStore $authDataStore ): void {
		$this->setOAuthCredentialStore( $authDataStore );
	}

	/**
	 * @return string
	 */
	public function getKey(): string {
		return 'github';
	}

	/**
	 * @param array $configData
	 * @return bool
	 */
	public function isValidConfiguration( array $configData ): bool {
		return !empty( $configData['base_url'] ) && is_string( $configData['base_url'] ) &&
			!empty( $configData['client_id'] ) && is_string( $configData['client_id'] ) &&
			!empty( $configData['client_secret'] ) && is_string( $configData['client_secret'] );
	}

	/**
	 * @param array $params
	 * @param Authority $actor
	 * @param DataProvider $forProvider
	 * @return IssueEntity
	 * @throws EntityForbiddenException
	 * @throws EntityNotFoundException
	 * @throws IdentityProviderException
	 * @throws NoAuthException
	 * @throws Exception
	 */
	public function getEntity( array $params, Authority $actor, DataProvider $forProvider ): IssueEntity {
		try {
			return $this->doGetEntity( $params, $actor, $forProvider );
		} catch ( EntityForbiddenException ) {
			$this->tryRefresh( $actor, $forProvider );
			return $this->doGetEntity( $params, $actor, $forProvider );
		}
	}

	/**
	 * @param array $params
	 * @param Authority $actor
	 * @param DataProvider $forProvider
	 * @return IssueEntity
	 * @throws EntityForbiddenException
	 * @throws EntityNotFoundException
	 * @throws Exception
	 * @throws IdentityProviderException
	 * @throws NoAuthException
	 */
	public function doGetEntity( array $params, Authority $actor, DataProvider $forProvider ): IssueEntity {
		$accessToken = $this->getAccessToken( $actor, $forProvider );

		$retrievalBase = $this->connectionConfig->has( 'api_url' ) ?
			$this->connectionConfig->get( 'api_url' ) :
			'https://api.github.com';
		$entityUrl = "$retrievalBase/repos/{$params['owner']}/{$params['repo']}/issues/{$params['id']}";
		$request = $this->httpRequestFactory->create( $entityUrl );
		$request->setHeader( 'Authorization', 'Bearer ' . $accessToken->getToken() );
		$request->setHeader( 'Accept', 'vnd.github.full+json' );

		$response = $request->execute();

		if ( !$response->isOK() ) {
			$code = $response->getValue();
			if ( $code === 404 ) {
				throw new EntityNotFoundException();
			}
			if ( $code === 401 ) {
				throw new EntityForbiddenException();
			}
			$this->logger->error( 'Github API error: {response}', [ 'response' => $request->getContent() ] );
			throw new \Exception( 'Github API error: ' . $code );
		}
		$responseJson = $request->getContent();
		$responseData = json_decode( $responseJson, true );
		if ( !is_array( $responseData ) ) {
			$this->logger->error( 'Github API error: non-JSON or non-object response: {response}', [
				'response' => $responseJson
			] );
			throw new \Exception( 'Github API error: Invalid response' );
		}
		$this->validateEntityResponse( $responseData );

		return new IssueEntity(
			id: $responseData['id'],
			title: "#{$responseData['number']} - {$responseData['title']}",
			isClosed: $responseData['closed_at'] !== null,
			html: $this->generateHtml( $responseData )
		);
	}

	/**
	 * @param array $data
	 * @return string
	 * @throws Exception
	 */
	private function generateHtml( array $data ): string {
		OutputPage::setupOOUI();

		$userHtml = $this->getUserHtmlSnippet(
			name: $data['user']['login'],
			avatar: $data['user']['avatar_url'],
			url: $data['user']['html_url']
		);

		$assigneesHtml = '';
		foreach ( $data['assignees'] as $assignee ) {
			$assigneesHtml = $this->getUserHtmlSnippet(
				name: $assignee['login'],
				avatar: $assignee['avatar_url'],
				url: $assignee['html_url']
			);
		}

		$fields = $this->getKeyValueLayouts(
			data: [
				'status' => $data['state'],
				'creator' => $userHtml,
				'assignee' => $assigneesHtml,
				'created' => $this->formatDate( $data['created_at'], 'Y-m-dTH:i:sO' ),
				'updated' => $this->formatDate( $data['updated_at'], 'Y-m-dTH:i:sO' ),
			],
			labels: [
				'status' => Message::newFromKey( 'issuetrackerlinks-detail-status' )->text(),
				'creator' => Message::newFromKey( 'issuetrackerlinks-detail-creator' )->text(),
				'assignee' => Message::newFromKey( 'issuetrackerlinks-detail-assignee' )->text(),
				'created' => Message::newFromKey( 'issuetrackerlinks-detail-created' )->text(),
				'updated' => Message::newFromKey( 'issuetrackerlinks-detail-updated' )->text(),
			],
			icons: [
				'status' => 'tag',
				'creator' => 'userAvatarOutline',
				'assignee' => 'userAvatarOutline',
				'created' => 'calendar',
				'updated' => 'calendar',
			],
			maxPerColumn: 3,
			showEmpty: false
		);

		if ( $data['body'] ?? '' ) {
			$description = $this->getDescriptionWidget( $data['body'], isHtml: false );
			$fields[] = $description;
		}

		return $this->getPanel( ...$fields )->toString();
	}

	/**
	 * @param array $responseData
	 * @return void
	 * @throws \Exception
	 */
	private function validateEntityResponse( array $responseData ): void {
		$requiredRootStringFields = [ 'title', 'state', 'created_at', 'updated_at' ];
		foreach ( $requiredRootStringFields as $field ) {
			if ( !isset( $responseData[$field] ) || !is_string( $responseData[$field] ) ) {
				$this->throwInvalidEntityResponse( "Missing or invalid field: $field" );
			}
		}

		if ( !isset( $responseData['id'] ) || !is_int( $responseData['id'] ) ) {
			$this->throwInvalidEntityResponse( 'Missing or invalid field: id' );
		}
		if ( !isset( $responseData['number'] ) || !is_int( $responseData['number'] ) ) {
			$this->throwInvalidEntityResponse( 'Missing or invalid field: number' );
		}
		if (
			!array_key_exists( 'closed_at', $responseData ) ||
			( $responseData['closed_at'] !== null && !is_string( $responseData['closed_at'] ) )
		) {
			$this->throwInvalidEntityResponse( 'Missing or invalid field: closed_at' );
		}
		if (
			array_key_exists( 'body', $responseData ) &&
			$responseData['body'] !== null &&
			!is_string( $responseData['body'] )
		) {
			$this->throwInvalidEntityResponse( 'Invalid field: body' );
		}

		if ( !isset( $responseData['user'] ) || !is_array( $responseData['user'] ) ) {
			$this->throwInvalidEntityResponse( 'Missing or invalid field: user' );
		}
		$user = $responseData['user'];
		foreach ( [ 'login', 'avatar_url', 'html_url' ] as $field ) {
			if ( !isset( $user[$field] ) || !is_string( $user[$field] ) ) {
				$this->throwInvalidEntityResponse( "Missing or invalid field: user.$field" );
			}
		}

		if ( !isset( $responseData['assignees'] ) || !is_array( $responseData['assignees'] ) ) {
			$this->throwInvalidEntityResponse( 'Missing or invalid field: assignees' );
		}
		foreach ( $responseData['assignees'] as $index => $assignee ) {
			if ( !is_array( $assignee ) ) {
				$this->throwInvalidEntityResponse( "Invalid field: assignees.$index" );
			}
			foreach ( [ 'login', 'avatar_url', 'html_url' ] as $field ) {
				if ( !isset( $assignee[$field] ) || !is_string( $assignee[$field] ) ) {
					$this->throwInvalidEntityResponse( "Missing or invalid field: assignees.$index.$field" );
				}
			}
		}
	}

	/**
	 * @param string $reason
	 * @return void
	 * @throws \Exception
	 */
	private function throwInvalidEntityResponse( string $reason ): void {
		$this->logger->error( 'Github API error: invalid response ({reason})', [ 'reason' => $reason ] );
		throw new \Exception( 'Github API error: Invalid response' );
	}

	/**
	 * @return PanelLayout
	 * @throws Exception
	 */
	public function getViewLayout(): PanelLayout {
		$panel = new PanelLayout( [ 'expanded' => false ] );
		if ( !$this->connectionConfig ) {
			return $panel;
		}
		$panel->appendContent(
			[
				new FieldLayout( new LabelWidget( [
					'label' => $this->connectionConfig->get( 'client_id' ),
				] ), [ 'label' => Message::newFromKey( 'issuetrackerlinks-dataprovider-field-clientid' )->text() ] ),
				new FieldLayout( new LabelWidget( [
					'label' => $this->connectionConfig->get( 'base_url' ),
				] ), [ 'label' => Message::newFromKey( 'issuetrackerlinks-dataprovider-field-baseurl' )->text() ] ),
				new FieldLayout(
					new LabelWidget( [
						'label' => $this->connectionConfig->get( 'authorize_endpoint' ),
					] ),
					[ 'label' => Message::newFromKey( 'issuetrackerlinks-dataprovider-field-authendpoint' )->text() ]
				),
				new FieldLayout(
					new LabelWidget( [
						'label' => $this->connectionConfig->get( 'access_token_endpoint' ),
					] ),
					[ 'label' => Message::newFromKey( 'issuetrackerlinks-dataprovider-field-atendpoint' )->text() ]
				),

			]
		);
		return $panel;
	}

	/**
	 * @return StandaloneFormSpecification
	 */
	public function getEditForm(): StandaloneFormSpecification {
		$spec = new StandaloneFormSpecification();
		$spec->setItems( [
			[
				'type' => 'text',
				'name' => 'client_id',
				'label' => Message::newFromKey( 'issuetrackerlinks-dataprovider-field-clientid' )->text(),
				'labelAlign' => 'top',
			],
			[
				'type' => 'text',
				'name' => 'client_secret',
				'label' => Message::newFromKey( 'issuetrackerlinks-dataprovider-field-clientsecret' )->text(),
				'labelAlign' => 'top',
			],
			[
				'type' => 'text',
				'name' => 'base_url',
				'label' => Message::newFromKey( 'issuetrackerlinks-dataprovider-field-baseurl' )->text(),
				'labelAlign' => 'top',
			],
			[
				'type' => 'text',
				'name' => 'authorize_endpoint',
				'label' => Message::newFromKey( 'issuetrackerlinks-dataprovider-field-authendpoint' )->text(),
				'labelAlign' => 'top',
				'value' => '/login/oauth/authorize'
			],
			[
				'type' => 'text',
				'name' => 'access_token_endpoint',
				'label' => Message::newFromKey( 'issuetrackerlinks-dataprovider-field-atendpoint' )->text(),
				'labelAlign' => 'top',
				'value' => '/login/oauth/access_token'

			],
		] );
		return $spec;
	}
}
