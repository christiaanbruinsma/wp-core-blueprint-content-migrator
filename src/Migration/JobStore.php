<?php
declare(strict_types=1);

namespace CB\ContentMigrator\Migration;

defined( 'ABSPATH' ) || exit;

final class JobStore {
	private const ACTIVE_OPTION = 'cb_content_migrator_active_job';
	private const PREFIX = 'cb_content_migrator_job_';

	/** @param array<string,mixed> $job */
	public static function create( array $job ): array {
		if ( null !== self::load_active() ) {
			throw new \RuntimeException( 'Another migration is already active.' );
		}
		$id = strtolower( str_replace( '-', '', wp_generate_uuid4() ) );
		$id = substr( $id, 0, 16 );
		$job['id'] = $id;
		$job['owner_user_id'] = get_current_user_id();
		$job['created_at'] = gmdate( 'c' );
		add_option( self::PREFIX . $id, $job, '', false );
		update_option( self::ACTIVE_OPTION, $id, false );
		return $job;
	}

	/** @return array<string,mixed>|null */
	public static function load_active(): ?array {
		$id = sanitize_key( (string) get_option( self::ACTIVE_OPTION, '' ) );
		return '' === $id ? null : self::load( $id );
	}

	/** @return array<string,mixed>|null */
	public static function load( string $id ): ?array {
		$value = get_option( self::PREFIX . sanitize_key( $id ), null );
		return is_array( $value ) ? $value : null;
	}

	/** @param array<string,mixed> $job */
	public static function save( array $job ): void {
		$id = sanitize_key( (string) ( $job['id'] ?? '' ) );
		if ( '' === $id ) {
			throw new \InvalidArgumentException( 'Migration job ID is missing.' );
		}
		update_option( self::PREFIX . $id, $job, false );
	}

	public static function delete( string $id ): void {
		$id = sanitize_key( $id );
		delete_option( self::PREFIX . $id );
		if ( $id === sanitize_key( (string) get_option( self::ACTIVE_OPTION, '' ) ) ) {
			delete_option( self::ACTIVE_OPTION );
		}
	}
}
