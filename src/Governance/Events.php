<?php
declare(strict_types=1);

namespace CB\ContentMigrator\Governance;

defined( 'ABSPATH' ) || exit;

final class Events {
	public const CREATED    = 'contentmigrator.job.created';
	public const BATCH      = 'contentmigrator.job.batchcopied';
	public const VERIFIED   = 'contentmigrator.job.verified';
	public const ROLLEDBACK = 'contentmigrator.job.rolledback';
	public const FINALIZED  = 'contentmigrator.job.finalized';
	public const TRASHED    = 'contentmigrator.source.trashed';

	public static function init(): void {
		if ( ! class_exists( '\\CB\\Core\\Governance\\EventRegistry' ) || ! class_exists( '\\CB\\Core\\Governance\\Audit' ) ) {
			return;
		}
		add_action( 'init', [ __CLASS__, 'register' ], 10 );
	}

	public static function register(): void {
		$registry = '\\CB\\Core\\Governance\\EventRegistry';
		$labels = [
			self::CREATED    => __( 'Content migration created', 'core-blueprint-content-migrator' ),
			self::BATCH      => __( 'Content migration batch copied', 'core-blueprint-content-migrator' ),
			self::VERIFIED   => __( 'Content migration verified', 'core-blueprint-content-migrator' ),
			self::ROLLEDBACK => __( 'Content migration rolled back', 'core-blueprint-content-migrator' ),
			self::FINALIZED  => __( 'Content migration finalized', 'core-blueprint-content-migrator' ),
			self::TRASHED    => __( 'Content migration source moved to Trash', 'core-blueprint-content-migrator' ),
		];
		foreach ( $labels as $id => $label ) {
			try {
				$registry::register( [ 'id' => $id, 'label' => $label, 'retention_category' => 'maintenance' ] );
			} catch ( \Throwable ) {
				// Governance is best-effort for this standalone utility.
			}
		}
	}

	/** @param array<string,mixed> $context */
	public static function record( string $event, string $severity, array $context ): bool {
		if ( ! class_exists( '\\CB\\Core\\Governance\\Audit' ) ) {
			return false;
		}
		try {
			return (bool) \CB\Core\Governance\Audit::record( $event, $severity, $context );
		} catch ( \Throwable ) {
			return false;
		}
	}
}
