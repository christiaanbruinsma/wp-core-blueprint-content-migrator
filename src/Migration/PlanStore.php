<?php
declare(strict_types=1);

namespace CB\ContentMigrator\Migration;

defined( 'ABSPATH' ) || exit;

final class PlanStore {
	private const META_KEY = '_cb_content_migrator_plan';

	/** @param array<string,mixed> $plan */
	public static function save( array $plan ): void {
		update_user_meta( get_current_user_id(), self::META_KEY, $plan );
	}

	/** @return array<string,mixed>|null */
	public static function load(): ?array {
		$value = get_user_meta( get_current_user_id(), self::META_KEY, true );
		return is_array( $value ) ? $value : null;
	}

	public static function clear(): void {
		delete_user_meta( get_current_user_id(), self::META_KEY );
	}
}
