<?php

namespace MediaWiki\Extension\IssueTrackerLinks\DataProviderBackend;

use Config;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
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

class OpenProject implements OAuthProviderBackend, SearchableBackendProvider, LoggerAwareInterface {
	use OAuthAccessTrait;
	use HtmlFormatterTrait;

	/** @var Config|null */
	private ?Config $connectionConfig = null;

	/** @var LoggerInterface */
	private LoggerInterface $logger;

	/** @var array */
	private array $projectCache = [];

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
		return 'openproject';
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

		$entityUrl = $this->connectionConfig->get( 'base_url' ) . '/api/v3/work_packages/' . $params['id'];
		$request = $this->httpRequestFactory->create(
			$entityUrl,
			$this->getHttpRequestOptions(),
			__METHOD__
		);
		$request->setHeader( 'Authorization', 'Bearer ' . $accessToken->getToken() );
		$request->setHeader( 'Accept', 'application/hal+json' );

		$response = $request->execute();

		if ( !$response->isOK() ) {
			$code = $response->getValue();
			if ( $code === 404 ) {
				throw new EntityNotFoundException();
			}
			if ( $code === 401 ) {
				throw new EntityForbiddenException();
			}
			$this->logger->error( 'OpenProject API error: {response}', [ 'response' => $request->getContent() ] );
			throw new \Exception( 'OpenProject API error: ' . $code );
		}
		$responseJson = $request->getContent();
		$responseData = json_decode( $responseJson, true );
		if ( !is_array( $responseData ) ) {
			$this->logger->error( 'OpenProject API error: non-JSON or non-object response: {response}', [
				'response' => $responseJson
			] );
			throw new \Exception( 'OpenProject API error: Invalid response' );
		}
		$this->validateEntityResponse( $responseData );

		return new IssueEntity(
			id: $responseData['id'],
			title: "#{$responseData['id']} - {$responseData['subject']}",
			isClosed: $responseData['_embedded']['status']['isClosed'],
			html: $this->generateWorkPackageHtml( $responseData )
		);
	}

	/**
	 * @return array
	 */
	private function getHttpRequestOptions(): array {
		if ( !$this->connectionConfig || !$this->connectionConfig->has( 'insecure_tls' ) ) {
			return [];
		}
		$insecureTls = filter_var(
			$this->connectionConfig->get( 'insecure_tls' ),
			FILTER_VALIDATE_BOOLEAN,
			FILTER_NULL_ON_FAILURE
		);
		if ( $insecureTls !== true ) {
			return [];
		}
		return [
			'sslVerifyHost' => false,
			'sslVerifyCert' => false,
		];
	}

	/**
	 * @param string $query
	 * @param Authority $actor
	 * @param DataProvider $forProvider
	 * @return array
	 * @throws IdentityProviderException
	 * @throws NoAuthException
	 */
	public function search( string $query, Authority $actor, DataProvider $forProvider ): array {
		$accessToken = $this->getAccessToken( $actor, $forProvider );

		$searchUrl = $this->connectionConfig->get( 'base_url' ) .
			'/api/v3/work_packages?subject=' . urlencode( $query ) . '&pageSize=20';

		$request = $this->httpRequestFactory->create( $searchUrl, $this->getHttpRequestOptions(), __METHOD__ );
		$request->setHeader( 'Authorization', 'Bearer ' . $accessToken->getToken() );
		$request->setHeader( 'Accept', 'application/hal+json' );

		$response = $request->execute();
		if ( !$response->isOK() ) {
			$code = $response->getValue();
			$this->logger->error( 'OpenProject API error: {response}', [ 'response' => $request->getContent() ] );
			throw new \Exception( 'OpenProject API error: ' . $code );
		}
		$responseJson = $request->getContent();
		$responseData = json_decode( $responseJson, true );
		if ( !is_array( $responseData ) ) {
			$this->logger->error( 'OpenProject API error: non-JSON or non-object response: {response}', [
				'response' => $responseJson
			] );
		}

		$elements = $responseData['_embedded']['elements'] ?? [];
		$response = [];
		foreach ( $elements as $element ) {
			$project = $this->assertProject( $element['_links']['project']['href'], $accessToken );
			$response[] = [
				'label' => "#{$element['id']} - {$element['subject']}",
				'data' => [
					'project' => $project,
					'id' => $element['id'],
				]
			];
		}

		return $response;
	}

	/**
	 * @param string $url
	 * @param AccessToken $accessToken
	 * @return string
	 */
	private function assertProject( string $url, AccessToken $accessToken ): string {
		if ( !isset( $this->projectCache[$url] ) ) {
			$fullUrl = $this->connectionConfig->get( 'base_url' ) . $url;
			$request = $this->httpRequestFactory->create( $fullUrl, $this->getHttpRequestOptions(), __METHOD__ );
			$request->setHeader( 'Authorization', 'Bearer ' . $accessToken->getToken() );
			$request->setHeader( 'Accept', 'application/hal+json' );
			$response = $request->execute();
			if ( !$response->isOK() ) {
				$this->logger->error( 'OpenProject API error: {response}', [ 'response' => $request->getContent() ] );
				return '';
			}
			$responseJson = $request->getContent();
			$responseData = json_decode( $responseJson, true );
			if ( $responseData['identifier'] ?? null ) {
				$this->projectCache[$url] = $responseData['identifier'];
			} else {
				$this->projectCache[$url] = '';
			}
		}
		return $this->projectCache[$url];
	}

	/**
	 * @param array $data
	 * @return string
	 * @throws Exception
	 */
	private function generateWorkPackageHtml( array $data ): string {
		OutputPage::setupOOUI();

		$statusValue = $this->getColoredItem(
			value: $data['_embedded']['status']['name'],
			colorCode: $data['_embedded']['status']['color'] ?? ''
		);

		$typeValue = $this->getColoredItem(
			value: $data['_embedded']['type']['name'],
			colorCode: $data['_embedded']['type']['color'] ?? ''
		);

		$fields = $this->getKeyValueLayouts(
			data: [
				'status' => $statusValue,
				'assignee' => $data['_embedded']['assignee']['name'] ?? '',
				'responsible' => $data['_embedded']['responsible']['name'] ?? '',
				'start_date' => $data['startDate'] ?? '',
				'end_date' => $data['dueDate'] ?? '',
				'type' => $typeValue,
				'project' => $data['_embedded']['project']['name'] ?? '',
				'sprint' => $data['_embedded']['sprint']['name'] ?? '',
			],
			labels: [
				'status' => Message::newFromKey( 'issuetrackerlinks-detail-status' )->text(),
				'assignee' => Message::newFromKey( 'issuetrackerlinks-detail-assignee' )->text(),
				'responsible' => Message::newFromKey( 'issuetrackerlinks-detail-responsible' )->text(),
				'start_date' => Message::newFromKey( 'issuetrackerlinks-detail-start-date' )->text(),
				'end_date' => Message::newFromKey( 'issuetrackerlinks-detail-end-date' )->text(),
				'type' => Message::newFromKey( 'issuetrackerlinks-detail-type' )->text(),
				'project' => Message::newFromKey( 'issuetrackerlinks-detail-project' )->text(),
				'sprint' => Message::newFromKey( 'issuetrackerlinks-detail-sprint' )->text(),
			],
			icons: [
				'status' => 'tag',
				'assignee' => 'userAvatarOutline',
				'responsible' => 'userAvatarOutline',
				'start_date' => 'calendar',
				'end_date' => 'calendar',
				'type' => 'puzzle',
				'project' => 'tray',
			],
			showEmpty: false
		);

		if ( $data['description']['html'] ?? '' ) {
			$description = $this->getDescriptionWidget( $data['description']['html'] );
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
		if ( !isset( $responseData['id'] ) || !is_int( $responseData['id'] ) ) {
			$this->throwInvalidEntityResponse( 'Missing or invalid field: id' );
		}
		if ( !isset( $responseData['subject'] ) || !is_string( $responseData['subject'] ) ) {
			$this->throwInvalidEntityResponse( 'Missing or invalid field: subject' );
		}

		if ( !isset( $responseData['_embedded'] ) || !is_array( $responseData['_embedded'] ) ) {
			$this->throwInvalidEntityResponse( 'Missing or invalid field: _embedded' );
		}
		$embedded = $responseData['_embedded'];
		if ( !isset( $embedded['status'] ) || !is_array( $embedded['status'] ) ) {
			$this->throwInvalidEntityResponse( 'Missing or invalid field: _embedded.status' );
		}
		$status = $embedded['status'];
		if ( !isset( $status['name'] ) || !is_string( $status['name'] ) ) {
			$this->throwInvalidEntityResponse( 'Missing or invalid field: _embedded.status.name' );
		}
		if ( !isset( $status['isClosed'] ) || !is_bool( $status['isClosed'] ) ) {
			$this->throwInvalidEntityResponse( 'Missing or invalid field: _embedded.status.isClosed' );
		}
		if ( array_key_exists( 'color', $status ) && $status['color'] !== null && !is_string( $status['color'] ) ) {
			$this->throwInvalidEntityResponse( 'Invalid field: _embedded.status.color' );
		}

		if (
			isset( $responseData['description'] ) &&
			( !is_array( $responseData['description'] ) ||
			( isset( $responseData['description']['html'] ) && !is_string( $responseData['description']['html'] ) ) )
		) {
			$this->throwInvalidEntityResponse( 'Invalid field: description' );
		}
		foreach ( [ 'startDate', 'dueDate' ] as $field ) {
			if (
				array_key_exists( $field, $responseData ) &&
				$responseData[$field] !== null &&
				!is_string( $responseData[$field] )
			) {
				$this->throwInvalidEntityResponse( "Invalid field: $field" );
			}
		}
		foreach ( [ 'assignee', 'responsible' ] as $field ) {
			if (
				isset( $embedded[$field] ) &&
				( !is_array( $embedded[$field] ) ||
				( isset( $embedded[$field]['name'] ) && !is_string( $embedded[$field]['name'] ) ) )
			) {
				$this->throwInvalidEntityResponse( "Invalid field: _embedded.$field" );
			}
		}
	}

	/**
	 * @param string $reason
	 * @return void
	 * @throws \Exception
	 */
	private function throwInvalidEntityResponse( string $reason ): void {
		$this->logger->error( 'OpenProject API error: invalid response ({reason})', [ 'reason' => $reason ] );
		throw new \Exception( 'OpenProject API error: Invalid response' );
	}

	public function getViewLayout(): PanelLayout {
		if ( !$this->connectionConfig ) {
			return new PanelLayout();
		}
		$panel = new PanelLayout( [ 'expanded' => false ] );
		$panel->appendContent(
			[
				new FieldLayout( new LabelWidget( [
					'label' => $this->connectionConfig->get( 'client_id' ),
				] ), [ 'label' => Message::newFromKey( 'issuetrackerlinks-dataprovider-field-clientid' )->text() ] ),
				new FieldLayout( new LabelWidget( [
					'label' => $this->connectionConfig->get( 'base_url' ),
				] ), [ 'label' => Message::newFromKey( 'issuetrackerlinks-dataprovider-field-baseurl' )->text() ] ),

			]
		);
		return $panel;
	}

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
		] );
		return $spec;
	}
}
