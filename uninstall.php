<?php
/**
 * Core Blueprint Content Migrator intentionally preserves active migration state on uninstall.
 * Reinstalling the plugin must retain rollback information; no source or target content is deleted here.
 *
 * @package CB_Content_Migrator
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;
