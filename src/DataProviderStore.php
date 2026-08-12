<?php

namespace MediaWiki\Extension\IssueTrackerLinks;

use Exception;
use InvalidArgumentException;
use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\IssueTrackerLinks\Auth\AuthDataStore;
use MediaWiki\Extension\IssueTrackerLinks\DataProviderBackend\IDataProviderBackend;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Wikimedia\ObjectFactory\ObjectFactory;
use Wikimedia\Rdbms\ILoadBalancer;

final class DataProviderStore implements LoggerAwareInterface {

	private const TABLE_NAME = 'issuetrackerlinks_provider_config';

	/** @var array|null */
	private ?array $providers = null;

	/**
	 * @var LoggerInterface
	 */
	private LoggerInterface $logger;

	/**
	 * @param array $backendRegistry
	 * @param ObjectFactory $objectFactory
	 * @param ILoadBalancer $loadBalancer
	 * @param AuthDataStore $authDataStore
	 */
	public function __construct(
		private readonly array $backendRegistry,
		private readonly ObjectFactory $objectFactory,
		private readonly ILoadBalancer $loadBalancer,
		private readonly AuthDataStore $authDataStore
	) {
		$this->logger = new NullLogger();
	}

	/**
	 * List all providers. Key is the provider name, value is the provider type.
	 *
	 * @return array
	 */
	public function listProviders(): array {
		$res = $this->loadBalancer->getConnection( DB_REPLICA )->newSelectQueryBuilder()
			->select( [ 'itlpc_name', 'itlpc_type' ] )
			->from( self::TABLE_NAME )
			->caller( __METHOD__ )
			->fetchResultSet();

		$providers = [];
		foreach ( $res as $row ) {
			$providers[$row->itlpc_name] = $row->itlpc_type;
		}
		return $providers;
	}

	/**
	 * @return array
	 */
	public function getAllProviders(): array {
		if ( $this->providers === null ) {
			$res = $this->loadBalancer->getConnection( DB_REPLICA )->newSelectQueryBuilder()
				->select( [ 'itlpc_id' ] )
				->from( self::TABLE_NAME )
				->caller( __METHOD__ )
				->fetchResultSet();

			$this->providers = [];
			foreach ( $res as $row ) {
				try {
					$this->providers[$row->itlpc_id] = $this->getProvider( $row->itlpc_id );
				} catch ( Exception ) {
					continue;
				}
			}
		}
		return $this->providers;
	}

	/**
	 * @param string $key
	 * @return DataProvider|null
	 * @throws Exception
	 */
	public function getProvider( string $key ): ?DataProvider {
		$db = $this->loadBalancer->getConnection( DB_REPLICA );
		$row = $db->newSelectQueryBuilder()
			->select( [ 'itlpc_id', 'itlpc_name', 'itlpc_type', 'itlpc_data' ] )
			->from( self::TABLE_NAME )
			->where( $db->makeList( [ 'itlpc_name' => $key, 'itlpc_id' => $key ], LIST_OR ) )
			->caller( __METHOD__ )
			->fetchRow();

		if ( !$row ) {
			return null;
		}

		try {
			$backend = $this->makeProviderBackend( $row->itlpc_type );
		} catch ( Exception $e ) {
			$this->logger->error( 'Error creating provider backend for "{key}" of type "{type}": {error}', [
				'key' => $key,
				'type' => $row->itlpc_type,
				'error' => $e->getMessage(),
			] );
			throw $e;
		}

		$connectionData = json_decode( $row->itlpc_data, true );
		if ( $connectionData === false ) {
			$this->logger->error( 'Error decoding provider data for "{key}": {error}', [
				'key' => $key,
				'error' => json_last_error_msg(),
			] );
			throw new InvalidArgumentException( 'Error decoding provider data' );
		}
		$config = new HashConfig( $connectionData );
		$backend->setAuthStore( $this->authDataStore );

		return new DataProvider( $row->itlpc_id, $key, $backend, $config );
	}

	/**
	 * @param string $name
	 * @param string $backendKey
	 * @param array $data
	 * @return void
	 * @throws Exception
	 */
	public function addProvider( string $name, string $backendKey, array $data ): void {
		if ( $this->hasProvider( $name ) ) {
			throw new InvalidArgumentException( "Provider $name already exists" );
		}
		$backend = $this->makeProviderBackend( $backendKey );
		if ( !$backend->isValidConfiguration( $data ) ) {
			throw new InvalidArgumentException( "Invalid configuration for provider $name" );
		}
		// Currently "name" is considered an id, but we add this field in case that turns out to be unreliable,
		// so we can switch to the ID instead
		$id = bin2hex( random_bytes( 16 ) );

		$this->loadBalancer->getConnection( DB_PRIMARY )->newInsertQueryBuilder()
			->table( self::TABLE_NAME )
			->row( [
				'itlpc_id' => $id,
				'itlpc_name' => $name,
				'itlpc_type' => $backend->getKey(),
				'itlpc_data' => json_encode( $data ),
			] )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * @param string $key
	 * @param array $data
	 * @return void
	 * @throws Exception
	 */
	public function updateProviderData( string $key, array $data ): void {
		$db = $this->loadBalancer->getConnection( DB_REPLICA );
		$backendKey = $db->newSelectQueryBuilder()
			->select( 'itlpc_type' )
			->from( self::TABLE_NAME )
			->where( $db->makeList( [ 'itlpc_name' => $key, 'itlpc_id' => $key ], LIST_OR ) )
			->caller( __METHOD__ )
			->fetchField();

		if ( !$backendKey ) {
			throw new InvalidArgumentException( "Provider $key not found" );
		}
		$backend = $this->makeProviderBackend( $backendKey );
		if ( !$backend->isValidConfiguration( $data ) ) {
			throw new InvalidArgumentException( "Invalid configuration for provider $key" );
		}

		$this->loadBalancer->getConnection( DB_PRIMARY )->newUpdateQueryBuilder()
			->table( self::TABLE_NAME )
			->where( [ 'itlpc_name' => $key ] )
			->set( [ 'itlpc_data' => json_encode( $data ) ] )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * @param string $key
	 * @return void
	 * @throws Exception
	 */
	public function deleteProvider( string $key ): void {
		if ( !$this->hasProvider( $key ) ) {
			throw new InvalidArgumentException( "Provider $key not found" );
		}
		$this->loadBalancer->getConnection( DB_PRIMARY )->newDeleteQueryBuilder()
			->table( self::TABLE_NAME )
			->where( [ 'itlpc_name' => $key ] )
			->execute();
	}

	/**
	 * @param string $key
	 * @return bool
	 */
	public function hasProvider( string $key ): bool {
		return $this->loadBalancer->getConnection( DB_REPLICA )->newSelectQueryBuilder()
			->select( 'itlpc_name' )
			->from( self::TABLE_NAME )
			->where( [ 'itlpc_name' => $key ] )
			->caller( __METHOD__ )
			->fetchField() !== false;
	}

	/**
	 * @return array
	 */
	public function listProviderBackends(): array {
		return array_keys( $this->backendRegistry );
	}

	/**
	 * Create a fresh instance to allow multiple providers of the same type
	 *
	 * @param string $key
	 * @return IDataProviderBackend|null
	 * @throws Exception
	 */
	public function makeProviderBackend( string $key ): ?IDataProviderBackend {
		if ( !isset( $this->backendRegistry[$key] ) ) {
			throw new InvalidArgumentException( "Provider type $key not found" );
		}
		$spec = $this->backendRegistry[$key];
		$backend = $this->objectFactory->createObject( $spec );
		if ( !( $backend instanceof IDataProviderBackend ) ) {
			throw new InvalidArgumentException( "Provider $key is not an instance of " . IDataProviderBackend::class );
		}
		if ( $backend->getKey() !== $key ) {
			throw new InvalidArgumentException( "Provider $key has mismatched key " . $backend->getKey() );
		}
		if ( $backend instanceof LoggerAwareInterface ) {
			$backend->setLogger( $this->logger );
		}

		return $backend;
	}

	/**
	 * @param LoggerInterface $logger
	 * @return void
	 */
	public function setLogger( LoggerInterface $logger ): void {
		$this->logger = $logger;
	}

	/**
	 * @param string $key
	 * @return string|null
	 * @throws Exception
	 */
	public function getProviderId( string $key ): ?string {
		return $this->getProvider( $key )?->getId();
	}

}
