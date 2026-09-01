<?php
declare(strict_types=1);

namespace CB\ContentMigrator\Migration;

defined( 'ABSPATH' ) || exit;

final class PostRunner {
	private const JOB_META = '_cb_content_migrator_job';
	private const SOURCE_META = '_cb_content_migrator_source_id';
	private const TERM_SOURCE_META = '_cb_content_migrator_source_term_id';

	/** @param array<string,mixed> $job @return array<string,mixed> */
	public static function run_batch( array $job ): array {
		$ids = array_values( array_map( 'intval', (array) ( $job['source_ids'] ?? [] ) ) );
		$cursor = max( 0, (int) ( $job['cursor'] ?? 0 ) );
		$end = min( count( $ids ), $cursor + max( 10, min( 200, (int) ( $job['batch_size'] ?? 50 ) ) ) );
		$job['status'] = 'copying';
		$job['target_map'] = is_array( $job['target_map'] ?? null ) ? $job['target_map'] : [];
		$job['created_taxonomy_terms'] = is_array( $job['created_taxonomy_terms'] ?? null ) ? $job['created_taxonomy_terms'] : [];
		$job['errors'] = is_array( $job['errors'] ?? null ) ? $job['errors'] : [];
		for ( $i = $cursor; $i < $end; $i++ ) {
			$source_id = $ids[ $i ];
			if ( isset( $job['target_map'][ (string) $source_id ] ) ) {
				continue;
			}
			try {
				$job['target_map'][ (string) $source_id ] = self::copy_post( $source_id, $job );
			} catch ( \Throwable $e ) {
				self::add_error( $job, $source_id, 'copy', $e->getMessage() );
			}
		}
		$job['cursor'] = $end;
		if ( $end >= count( $ids ) ) {
			self::repair_parents( $job );
			$job['status'] = 'copied';
		}
		return $job;
	}

	/** @param array<string,mixed> $job */
	private static function copy_post( int $source_id, array &$job ): int {
		$source = get_post( $source_id );
		$source_type = sanitize_key( (string) ( $job['source_type'] ?? '' ) );
		$target_type = sanitize_key( (string) ( $job['target_type'] ?? '' ) );
		if ( ! $source instanceof \WP_Post || $source_type !== $source->post_type ) {
			throw new \RuntimeException( 'Source post is unavailable or changed type.' );
		}
		$target_id = wp_insert_post( wp_slash( [
			'post_type' => $target_type, 'post_status' => $source->post_status,
			'post_title' => $source->post_title, 'post_content' => $source->post_content,
			'post_excerpt' => $source->post_excerpt, 'post_author' => (int) $source->post_author,
			'post_date' => $source->post_date, 'post_date_gmt' => $source->post_date_gmt,
			'post_name' => $source->post_name, 'post_password' => $source->post_password,
			'comment_status' => $source->comment_status, 'ping_status' => $source->ping_status,
			'menu_order' => (int) $source->menu_order, 'post_parent' => 0,
		] ), true, true );
		if ( is_wp_error( $target_id ) ) {
			throw new \RuntimeException( $target_id->get_error_message() );
		}
		$target_id = (int) $target_id;
		update_post_meta( $target_id, self::JOB_META, sanitize_key( (string) $job['id'] ) );
		update_post_meta( $target_id, self::SOURCE_META, $source_id );
		if ( ! empty( $job['copy_featured_image'] ) && post_type_supports( $target_type, 'thumbnail' ) ) {
			$thumbnail_id = get_post_thumbnail_id( $source_id );
			if ( $thumbnail_id > 0 ) {
				set_post_thumbnail( $target_id, $thumbnail_id );
			}
		}
		self::copy_meta( $source_id, $target_id, $target_type, (array) ( $job['meta_map'] ?? [] ) );
		self::copy_taxonomies( $source_id, $target_id, (array) ( $job['tax_map'] ?? [] ), $job );
		return $target_id;
	}

	/** @param array<string,string> $map */
	private static function copy_meta( int $source_id, int $target_id, string $target_type, array $map ): void {
		foreach ( $map as $source_key => $target_key ) {
			$target_key = self::meta_key( (string) $target_key );
			if ( '' === (string) $source_key || '' === $target_key ) {
				continue;
			}
			if ( str_starts_with( $target_key, '_cb_content_migrator_' ) || ! current_user_can( 'edit_post_meta', $target_id, $target_key ) ) {
				throw new \RuntimeException( 'A mapped target meta key is reserved or not writable.' );
			}
			$values = get_post_meta( $source_id, (string) $source_key, false );
			if ( empty( $values ) ) {
				continue;
			}
			delete_post_meta( $target_id, $target_key );
			foreach ( $values as $value ) {
				add_post_meta( $target_id, $target_key, sanitize_meta( $target_key, $value, 'post', $target_type ) );
			}
		}
	}

	/** @param array<string,string> $map @param array<string,mixed> $job */
	private static function copy_taxonomies( int $source_id, int $target_id, array $map, array &$job ): void {
		$cache = [];
		foreach ( $map as $source_tax => $target_tax ) {
			$source_tax = sanitize_key( (string) $source_tax );
			$target_tax = sanitize_key( (string) $target_tax );
			if ( ! taxonomy_exists( $source_tax ) || ! taxonomy_exists( $target_tax ) ) {
				continue;
			}
			$target_object = get_taxonomy( $target_tax );
			if ( $target_object instanceof \WP_Taxonomy && 'do_not_allow' === (string) $target_object->cap->assign_terms ) {
				continue;
			}
			$terms = wp_get_object_terms( $source_id, $source_tax );
			if ( is_wp_error( $terms ) ) {
				throw new \RuntimeException( $terms->get_error_message() );
			}
			$target_ids = [];
			foreach ( $terms as $term ) {
				if ( ! $term instanceof \WP_Term ) {
					continue;
				}
				$target_ids[] = $source_tax === $target_tax ? (int) $term->term_id : self::ensure_term( $term, $source_tax, $target_tax, $cache, $job );
			}
			$result = wp_set_object_terms( $target_id, $target_ids, $target_tax, false );
			if ( is_wp_error( $result ) ) {
				throw new \RuntimeException( $result->get_error_message() );
			}
		}
	}

	/** @param array<string,int> $cache @param array<string,mixed> $job */
	private static function ensure_term( \WP_Term $source, string $source_tax, string $target_tax, array &$cache, array &$job ): int {
		$key = $source_tax . ':' . $source->term_id . '>' . $target_tax;
		if ( isset( $cache[ $key ] ) ) {
			return $cache[ $key ];
		}
		$existing = get_term_by( 'slug', $source->slug, $target_tax );
		if ( $existing instanceof \WP_Term ) {
			return $cache[ $key ] = (int) $existing->term_id;
		}
		$parent = 0;
		if ( $source->parent > 0 ) {
			$parent_term = get_term( $source->parent, $source_tax );
			if ( $parent_term instanceof \WP_Term ) {
				$parent = self::ensure_term( $parent_term, $source_tax, $target_tax, $cache, $job );
			}
		}
		$target_object = get_taxonomy( $target_tax );
		if ( $target_object instanceof \WP_Taxonomy && ! current_user_can( $target_object->cap->manage_terms ) ) {
			throw new \RuntimeException( 'Missing target terms cannot be created with the current permissions.' );
		}
		$inserted = wp_insert_term( $source->name, $target_tax, [ 'slug' => $source->slug, 'description' => $source->description, 'parent' => $parent ] );
		if ( is_wp_error( $inserted ) ) {
			if ( 'term_exists' === $inserted->get_error_code() ) {
				return $cache[ $key ] = (int) $inserted->get_error_data();
			}
			throw new \RuntimeException( $inserted->get_error_message() );
		}
		$target_id = (int) $inserted['term_id'];
		update_term_meta( $target_id, self::JOB_META, sanitize_key( (string) $job['id'] ) );
		update_term_meta( $target_id, self::TERM_SOURCE_META, (int) $source->term_id );
		$job['created_taxonomy_terms'][ $target_tax ] = array_values( array_unique( array_merge(
			array_map( 'intval', (array) ( $job['created_taxonomy_terms'][ $target_tax ] ?? [] ) ), [ $target_id ]
		) ) );
		return $cache[ $key ] = $target_id;
	}

	/** @param array<string,mixed> $job */
	private static function repair_parents( array &$job ): void {
		$target = get_post_type_object( sanitize_key( (string) ( $job['target_type'] ?? '' ) ) );
		if ( ! $target instanceof \WP_Post_Type || ! $target->hierarchical ) {
			return;
		}
		$map = is_array( $job['target_map'] ?? null ) ? $job['target_map'] : [];
		foreach ( $map as $source_id => $target_id ) {
			$source = get_post( (int) $source_id );
			$parent_target = $source instanceof \WP_Post && $source->post_parent > 0 ? (int) ( $map[ (string) $source->post_parent ] ?? 0 ) : 0;
			if ( $parent_target <= 0 ) {
				continue;
			}
			$result = wp_update_post( [ 'ID' => (int) $target_id, 'post_parent' => $parent_target ], true );
			if ( is_wp_error( $result ) ) {
				self::add_error( $job, (int) $source_id, 'parent', $result->get_error_message() );
			}
		}
	}

	/** @param array<string,mixed> $job @return array{passed:bool,checked:int,issues:array<int,string>} */
	public static function verify( array $job ): array {
		$issues = [];
		$checked = 0;
		foreach ( (array) ( $job['target_map'] ?? [] ) as $source_id => $target_id ) {
			$source = get_post( (int) $source_id );
			$target = get_post( (int) $target_id );
			$checked++;
			if ( ! $source instanceof \WP_Post || ! $target instanceof \WP_Post ) {
				$issues[] = sprintf( 'Source %d or target %d is missing.', (int) $source_id, (int) $target_id );
				continue;
			}
			foreach ( [ 'post_title', 'post_content', 'post_excerpt', 'post_status', 'menu_order' ] as $field ) {
				if ( $source->{$field} !== $target->{$field} ) {
					$issues[] = sprintf( 'Target %d differs in %s.', $target->ID, $field );
				}
			}
			if ( (string) $job['target_type'] !== $target->post_type || sanitize_key( (string) get_post_meta( $target->ID, self::JOB_META, true ) ) !== sanitize_key( (string) $job['id'] ) ) {
				$issues[] = sprintf( 'Target %d has an invalid type or rollback marker.', $target->ID );
			}
			self::verify_meta( $source, $target, (array) ( $job['meta_map'] ?? [] ), $issues );
			self::verify_taxonomies( $source, $target, (array) ( $job['tax_map'] ?? [] ), $issues );
			if ( count( $issues ) >= 100 ) {
				break;
			}
		}
		return [ 'passed' => empty( $issues ) && $checked === (int) ( $job['total'] ?? 0 ), 'checked' => $checked, 'issues' => array_slice( $issues, 0, 100 ) ];
	}

	/** @param array<string,string> $map @param string[] $issues */
	private static function verify_meta( \WP_Post $source, \WP_Post $target, array $map, array &$issues ): void {
		foreach ( $map as $source_key => $target_key ) {
			$target_key = self::meta_key( (string) $target_key );
			if ( '' === $target_key ) {
				continue;
			}
			$expected = array_map( static fn( mixed $v ): mixed => sanitize_meta( $target_key, $v, 'post', $target->post_type ), get_post_meta( $source->ID, (string) $source_key, false ) );
			if ( $expected !== get_post_meta( $target->ID, $target_key, false ) ) {
				$issues[] = sprintf( 'Target %d differs for meta %s.', $target->ID, $target_key );
			}
		}
	}

	/** @param array<string,string> $map @param string[] $issues */
	private static function verify_taxonomies( \WP_Post $source, \WP_Post $target, array $map, array &$issues ): void {
		foreach ( $map as $source_tax => $target_tax ) {
			$a = wp_get_object_terms( $source->ID, (string) $source_tax, [ 'fields' => 'slugs' ] );
			$b = wp_get_object_terms( $target->ID, (string) $target_tax, [ 'fields' => 'slugs' ] );
			if ( is_wp_error( $a ) || is_wp_error( $b ) ) {
				$issues[] = sprintf( 'Target %d taxonomy verification failed.', $target->ID );
				continue;
			}
			sort( $a ); sort( $b );
			if ( $a !== $b ) {
				$issues[] = sprintf( 'Target %d differs for %s → %s.', $target->ID, $source_tax, $target_tax );
			}
		}
	}

	/** @param array<string,mixed> $job @return array{deleted:int,terms_deleted:int,issues:array<int,string>} */
	public static function rollback( array $job ): array {
		$deleted = 0; $terms_deleted = 0; $issues = [];
		foreach ( (array) ( $job['target_map'] ?? [] ) as $target_id ) {
			$target_id = (int) $target_id;
			if ( ! get_post( $target_id ) ) {
				continue;
			}
			if ( sanitize_key( (string) get_post_meta( $target_id, self::JOB_META, true ) ) !== sanitize_key( (string) $job['id'] ) ) {
				$issues[] = sprintf( 'Target %d rollback marker changed.', $target_id ); continue;
			}
			if ( false === wp_delete_post( $target_id, true ) ) {
				$issues[] = sprintf( 'Target %d could not be deleted.', $target_id ); continue;
			}
			$deleted++;
		}
		foreach ( (array) ( $job['created_taxonomy_terms'] ?? [] ) as $taxonomy => $term_ids ) {
			$taxonomy = sanitize_key( (string) $taxonomy );
			$term_ids = array_values( array_unique( array_map( 'intval', (array) $term_ids ) ) );
			usort( $term_ids, static fn( int $a, int $b ): int => self::term_depth( $b, $taxonomy ) <=> self::term_depth( $a, $taxonomy ) );
			foreach ( $term_ids as $term_id ) {
				if ( ! get_term( $term_id, $taxonomy ) ) { continue; }
				if ( sanitize_key( (string) get_term_meta( $term_id, self::JOB_META, true ) ) !== sanitize_key( (string) $job['id'] ) ) {
					$issues[] = sprintf( 'Target term %d rollback marker changed.', $term_id ); continue;
				}
				$result = wp_delete_term( $term_id, $taxonomy );
				if ( is_wp_error( $result ) || false === $result || null === $result ) {
					$issues[] = sprintf( 'Target term %d could not be deleted.', $term_id ); continue;
				}
				$terms_deleted++;
			}
		}
		return [ 'deleted' => $deleted, 'terms_deleted' => $terms_deleted, 'issues' => $issues ];
	}

	/** @param array<string,mixed> $job @return array{trashed:int,issues:array<int,string>} */
	public static function finalize( array $job, bool $trash_source ): array {
		$issues = []; $trashed = 0;
		if ( $trash_source ) {
			foreach ( (array) ( $job['source_ids'] ?? [] ) as $source_id ) {
				$source = get_post( (int) $source_id );
				if ( ! $source instanceof \WP_Post || (string) $job['source_type'] !== $source->post_type ) { continue; }
				if ( false === wp_trash_post( $source->ID ) ) { $issues[] = sprintf( 'Source %d could not be trashed.', $source->ID ); } else { $trashed++; }
			}
		}
		foreach ( (array) ( $job['target_map'] ?? [] ) as $target_id ) {
			delete_post_meta( (int) $target_id, self::JOB_META ); delete_post_meta( (int) $target_id, self::SOURCE_META );
		}
		foreach ( (array) ( $job['created_taxonomy_terms'] ?? [] ) as $term_ids ) {
			foreach ( array_map( 'intval', (array) $term_ids ) as $term_id ) {
				delete_term_meta( $term_id, self::JOB_META ); delete_term_meta( $term_id, self::TERM_SOURCE_META );
			}
		}
		return [ 'trashed' => $trashed, 'issues' => $issues ];
	}

	private static function term_depth( int $term_id, string $taxonomy ): int {
		$depth = 0; $seen = [];
		while ( $term_id > 0 && ! isset( $seen[ $term_id ] ) ) {
			$seen[ $term_id ] = true; $term = get_term( $term_id, $taxonomy );
			if ( ! $term instanceof \WP_Term || $term->parent <= 0 ) { break; }
			$depth++; $term_id = (int) $term->parent;
		}
		return $depth;
	}

	private static function meta_key( string $key ): string {
		$key = trim( $key );
		return preg_match( '/^[A-Za-z0-9_-]{1,191}$/', $key ) ? $key : '';
	}

	/** @param array<string,mixed> $job */
	private static function add_error( array &$job, int $source_id, string $stage, string $message ): void {
		if ( count( (array) $job['errors'] ) < 100 ) {
			$job['errors'][] = [ 'source_id' => $source_id, 'stage' => sanitize_key( $stage ), 'message' => sanitize_text_field( $message ) ];
		}
	}
}
