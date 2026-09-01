<?php
declare(strict_types=1);
define( 'ABSPATH', '/tmp/' );
define( 'EMPTY_TRASH_DAYS', 0 );
function __( string $text, ?string $domain = null ): string { return $text; }
require dirname( __DIR__ ) . '/src/Migration/PostRunner.php';
try {
	\CB\ContentMigrator\Migration\PostRunner::finalize( [ 'source_ids'=>[], 'target_map'=>[], 'created_taxonomy_terms'=>[] ], true );
	fwrite( STDERR, "Trash-disabled safety check: FAIL\n" );
	exit( 1 );
} catch ( Throwable $e ) {
	if ( ! str_contains( $e->getMessage(), 'Trash is disabled' ) ) {
		fwrite( STDERR, "Trash-disabled safety check: FAIL\n" );
		exit( 1 );
	}
}
echo "Trash-disabled safety check: PASS\n";
