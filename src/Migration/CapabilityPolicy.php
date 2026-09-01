<?php
declare(strict_types=1);

namespace CB\ContentMigrator\Migration;

defined( 'ABSPATH' ) || exit;

final class CapabilityPolicy {
	public static function assert_post_plan( string $source_type, string $target_type, array $source_ids ): void {
		$source = get_post_type_object( $source_type );
		$target = get_post_type_object( $target_type );
		if ( ! $source instanceof \WP_Post_Type || ! $target instanceof \WP_Post_Type ) {
			throw new \RuntimeException( __( 'The selected source or target post type is no longer registered.', 'core-blueprint-content-migrator' ) );
		}
		if ( ! current_user_can( $target->cap->create_posts ) ) {
			throw new \RuntimeException( __( 'You cannot create posts in the selected target post type.', 'core-blueprint-content-migrator' ) );
		}

		foreach ( array_map( 'intval', $source_ids ) as $source_id ) {
			self::assert_source_post_readable( $source_id, $source_type );
			$post = get_post( $source_id );
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			if ( (int) $post->post_author !== get_current_user_id() && ! current_user_can( $target->cap->edit_others_posts ) ) {
				throw new \RuntimeException( sprintf( __( 'Source post %d belongs to another author, but the target post type does not allow you to edit other authors’ posts.', 'core-blueprint-content-migrator' ), $source_id ) );
			}
			$status = get_post_status_object( $post->post_status );
			$requires_publish = $status instanceof \stdClass && ( ! empty( $status->public ) || ! empty( $status->private ) || ! empty( $status->protected ) );
			if ( $requires_publish && ! current_user_can( $target->cap->publish_posts ) ) {
				throw new \RuntimeException( sprintf( __( 'Source post %d has a status that you cannot reproduce in the target post type.', 'core-blueprint-content-migrator' ), $source_id ) );
			}
			if ( $status instanceof \stdClass && ! empty( $status->private ) && ! current_user_can( $target->cap->read_private_posts ) ) {
				throw new \RuntimeException( sprintf( __( 'Source post %d is private, but you cannot read private posts in the target post type.', 'core-blueprint-content-migrator' ), $source_id ) );
			}
		}
	}

	public static function assert_source_post_readable( int $post_id, string $expected_type ): void {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || $post->post_type !== $expected_type ) {
			throw new \RuntimeException( sprintf( __( 'Source post %d is unavailable or changed type.', 'core-blueprint-content-migrator' ), $post_id ) );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			throw new \RuntimeException( sprintf( __( 'You cannot migrate source post %d.', 'core-blueprint-content-migrator' ), $post_id ) );
		}
	}

	public static function assert_target_post_writable( int $post_id, string $expected_type ): void {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || $post->post_type !== $expected_type ) {
			throw new \RuntimeException( sprintf( __( 'Target post %d is unavailable or changed type.', 'core-blueprint-content-migrator' ), $post_id ) );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			throw new \RuntimeException( sprintf( __( 'You cannot update target post %d.', 'core-blueprint-content-migrator' ), $post_id ) );
		}
	}

	public static function assert_post_deletable( int $post_id ): void {
		if ( ! current_user_can( 'delete_post', $post_id ) ) {
			throw new \RuntimeException( sprintf( __( 'You cannot delete post %d.', 'core-blueprint-content-migrator' ), $post_id ) );
		}
	}

	public static function assert_taxonomy_plan( string $source_taxonomy, string $target_taxonomy, array $relationship_ids = [] ): void {
		$source = get_taxonomy( $source_taxonomy );
		$target = get_taxonomy( $target_taxonomy );
		if ( ! $source instanceof \WP_Taxonomy || ! $target instanceof \WP_Taxonomy ) {
			throw new \RuntimeException( __( 'The selected source or target taxonomy is no longer registered.', 'core-blueprint-content-migrator' ) );
		}
		if ( ! current_user_can( $source->cap->manage_terms ) ) {
			throw new \RuntimeException( __( 'You cannot manage the selected source taxonomy.', 'core-blueprint-content-migrator' ) );
		}
		if ( 'do_not_allow' === (string) $target->cap->manage_terms || ! current_user_can( $target->cap->manage_terms ) ) {
			throw new \RuntimeException( __( 'You cannot create or manage terms in the selected target taxonomy.', 'core-blueprint-content-migrator' ) );
		}
		if ( ! empty( $relationship_ids ) ) {
			self::assert_relationship_writes( $target_taxonomy, $relationship_ids );
		}
	}

	/** @param array<string,string> $map */
	public static function assert_post_taxonomy_map( array $map ): void {
		foreach ( $map as $source_taxonomy => $target_taxonomy ) {
			$source = get_taxonomy( sanitize_key( (string) $source_taxonomy ) );
			$target = get_taxonomy( sanitize_key( (string) $target_taxonomy ) );
			if ( ! $source instanceof \WP_Taxonomy || ! $target instanceof \WP_Taxonomy ) {
				throw new \RuntimeException( __( 'A mapped taxonomy is no longer registered.', 'core-blueprint-content-migrator' ) );
			}
			if ( ! current_user_can( $source->cap->manage_terms ) ) {
				throw new \RuntimeException( sprintf( __( 'You cannot read all terms from source taxonomy %s.', 'core-blueprint-content-migrator' ), $source->name ) );
			}
			if ( 'do_not_allow' === (string) $target->cap->assign_terms || ! current_user_can( $target->cap->assign_terms ) ) {
				throw new \RuntimeException( sprintf( __( 'You cannot assign terms in target taxonomy %s.', 'core-blueprint-content-migrator' ), $target->name ) );
			}
			if ( $source->name !== $target->name && ( 'do_not_allow' === (string) $target->cap->manage_terms || ! current_user_can( $target->cap->manage_terms ) ) ) {
				throw new \RuntimeException( sprintf( __( 'You cannot create missing terms in target taxonomy %s.', 'core-blueprint-content-migrator' ), $target->name ) );
			}
		}
	}

	public static function assert_relationship_writes( string $target_taxonomy, array $object_ids ): void {
		$target = get_taxonomy( $target_taxonomy );
		if ( ! $target instanceof \WP_Taxonomy || 'do_not_allow' === (string) $target->cap->assign_terms || ! current_user_can( $target->cap->assign_terms ) ) {
			throw new \RuntimeException( __( 'You cannot assign terms in the selected target taxonomy.', 'core-blueprint-content-migrator' ) );
		}
		foreach ( array_map( 'intval', $object_ids ) as $object_id ) {
			if ( ! current_user_can( 'edit_post', $object_id ) ) {
				throw new \RuntimeException( sprintf( __( 'You cannot change taxonomy relationships for post %d.', 'core-blueprint-content-migrator' ), $object_id ) );
			}
		}
	}

	public static function assert_term_deletable( string $taxonomy ): void {
		$object = get_taxonomy( $taxonomy );
		if ( ! $object instanceof \WP_Taxonomy || 'do_not_allow' === (string) $object->cap->delete_terms || ! current_user_can( $object->cap->delete_terms ) ) {
			throw new \RuntimeException( sprintf( __( 'You cannot delete terms in taxonomy %s.', 'core-blueprint-content-migrator' ), $taxonomy ) );
		}
	}
}
