<?php

namespace MediaWiki\Extension\IssueTrackerLinks\Auth;

use MediaWiki\Extension\IssueTrackerLinks\DataProvider;
use MediaWiki\Permissions\Authority;
use RuntimeException;
use Wikimedia\Rdbms\ILoadBalancer;

final class AuthDataStore {

	private const TABLE_NAME = 'issuetrackerlinks_auth_tokens';

	/**
	 * @param ILoadBalancer $lb
	 */
	public function __construct(
		private readonly ILoadBalancer $lb
	) {
	}

	public function getAuthData( DataProvider $dataProvider, Authority $forUser ): ?array {
		$row = $this->lb->getConnection( DB_REPLICA )->newSelectQueryBuilder()
			->select( 'itlat_auth_data' )
			->from( self::TABLE_NAME )
			->where( [
				'itlat_user' => $forUser->getUser()->getId(),
				'itlat_provider' => $dataProvider->getId(),
			] )
			->caller( __METHOD__ )
			->fetchRow();
		if ( !$row ) {
			return null;
		}
		$decoded = json_decode( $row->itlat_auth_data, true );

		return is_array( $decoded ) ? $decoded : null;
	}

	/**
	 * @param DataProvider $dataProvider
	 * @param Authority $forUser
	 * @param array $data
	 * @return void
	 * @throws RuntimeException
	 */
	public function setAuthData( DataProvider $dataProvider, Authority $forUser, array $data ): void {
		if ( !$forUser->getUser()->isRegistered() ) {
			throw new RuntimeException( 'Storing auth data for unregistered users is not allowed' );
		}

		$this->invalidateAuthData( $dataProvider, $forUser );
		$row = [
			'itlat_user' => $forUser->getUser()->getId(),
			'itlat_provider' => $dataProvider->getId(),
			'itlat_auth_data' => json_encode( $data ),
		];

		$this->lb->getConnection( DB_PRIMARY )->newInsertQueryBuilder()
			->table( self::TABLE_NAME )
			->row( $row )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * @param DataProvider $dataProvider
	 * @param Authority $forUser
	 * @return void
	 */
	public function invalidateAuthData( DataProvider $dataProvider, Authority $forUser ): void {
		if ( !$forUser->getUser()->isRegistered() ) {
			return;
		}
		$this->lb->getConnection( DB_PRIMARY )->newDeleteQueryBuilder()
			->delete( self::TABLE_NAME )
			->where( [
				'itlat_user' => $forUser->getUser()->getId(),
				'itlat_provider' => $dataProvider->getId(),
			] )
			->caller( __METHOD__ )
			->execute();
	}
}
