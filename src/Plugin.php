<?php
declare(strict_types=1);

namespace CB\ContentMigrator;

use CB\ContentMigrator\Admin\Controller;
use CB\ContentMigrator\Admin\Page;
use CB\ContentMigrator\Governance\Events;
use CB\ContentMigrator\Integration\Suite;

defined( 'ABSPATH' ) || exit;

final class Plugin {
	private static bool $booted = false;

	public static function boot(): void {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;
		Page::init();
		Controller::init();
		Suite::init();
		Events::init();
	}
}
