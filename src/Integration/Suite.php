<?php
declare(strict_types=1);

namespace CB\ContentMigrator\Integration;

use CB\ContentMigrator\Admin\Page;
use CB\ContentMigrator\Migration\JobStore;

defined( 'ABSPATH' ) || exit;

final class Suite {
	public const ID = 'core-blueprint-content-migrator';

	public static function init(): void {
		if ( ! function_exists( 'cb_content_migrator_base_ready' ) || ! cb_content_migrator_base_ready() ) {
			return;
		}
		add_action( 'cb_core_register_extensions', [ __CLASS__, 'register_extension' ] );
		add_filter( 'cb_core_module_status_definitions', [ __CLASS__, 'register_status_definition' ] );
	}

	public static function register_extension(): void {
		try {
			\CB\Core\ExtensionRegistry::register( [
				'id'           => self::ID,
				'plugin_file'  => CB_CONTENT_MIGRATOR_BASENAME,
				'requires_api' => CB_CONTENT_MIGRATOR_REQUIRED_API,
				'menu_url'     => admin_url( 'tools.php?page=' . Page::SLUG ),
				'status_id'    => self::ID,
			] );
		} catch ( \Throwable ) {
			// Base integration is an enhancement. Standalone migration must remain usable.
		}
	}

	/** @param array<string,array<string,mixed>> $definitions @return array<string,array<string,mixed>> */
	public static function register_status_definition( array $definitions ): array {
		$label = 'Content Migrator';
		if ( self::i18n_ready() ) {
			$label = __( 'Content Migrator', 'core-blueprint-content-migrator' );
		}
		$definitions[ self::ID ] = [
			'provider' => [ __CLASS__, 'status' ],
			'label'    => $label,
			'url'      => admin_url( 'tools.php?page=' . Page::SLUG ),
		];
		return $definitions;
	}

	/** @return array{state:string,detail:string,url:string} */
	public static function status(): array {
		$job = JobStore::load_active();
		$detail = 'No active migration';
		if ( is_array( $job ) ) {
			$detail = sprintf(
				'%1$s migration active · %2$d/%3$d processed',
				ucfirst( sanitize_key( (string) ( $job['mode'] ?? 'content' ) ) ),
				(int) ( $job['cursor'] ?? 0 ),
				(int) ( $job['total'] ?? 0 )
			);
		} elseif ( self::i18n_ready() ) {
			$detail = __( 'No active migration', 'core-blueprint-content-migrator' );
		}
		if ( is_array( $job ) && self::i18n_ready() ) {
			$detail = sprintf(
				/* translators: 1: migration mode, 2: processed items, 3: total items. */
				__( '%1$s migration active · %2$d/%3$d processed', 'core-blueprint-content-migrator' ),
				ucfirst( sanitize_key( (string) ( $job['mode'] ?? 'content' ) ) ),
				(int) ( $job['cursor'] ?? 0 ),
				(int) ( $job['total'] ?? 0 )
			);
		}
		return [ 'state' => 'ok', 'detail' => $detail, 'url' => admin_url( 'tools.php?page=' . Page::SLUG ) ];
	}

	private static function i18n_ready(): bool {
		return did_action( 'init' ) > 0 || doing_action( 'init' );
	}
}
