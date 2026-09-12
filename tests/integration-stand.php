<?php
/**
 * AI Alt Text integration — inside QA container:
 *   docker exec infra-wordpress-1 wp eval-file /tmp/aiat-integration.php --allow-root
 *
 * The AI transport is stubbed via the aiat_transport filter — no real API.
 */

defined( 'ABSPATH' ) || exit;

$GLOBALS['pass'] = 0;
$GLOBALS['fail'] = 0;

function check( $label, $cond ) {
	if ( $cond ) {
		$GLOBALS['pass']++;
		echo "  ok   {$label}\n";
	} else {
		$GLOBALS['fail']++;
		echo "  FAIL {$label}\n";
	}
}

echo "== AI Alt Text integration ==\n";

check( 'plugin active', is_plugin_active( 'ai-alt-text/ai-alt-text.php' ) );
check( 'classes loaded', class_exists( 'AIAT_Runner' ) && class_exists( 'AIAT_Store' ) );

global $wpdb;
$table     = AIAT_Store::table_name();
$has_table = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
if ( $has_table !== $table ) {
	AIAT_Store::activate();
	$has_table = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
}
check( 'drafts table present', $has_table === $table );

// Wipe leftovers of previous runs (crashed runs leave attachments behind).
global $wpdb;
$stale = $wpdb->get_results( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_title LIKE %s", 'aiat-%' ), ARRAY_A );
foreach ( (array) $stale as $stale_row ) {
	wp_delete_attachment( (int) $stale_row['ID'], true );
}
AIAT_Store::erase_all();
delete_option( 'aiat_settings' );

// Settings (after the cleanup preamble — it wipes aiat_settings).
AIAT_Settings::save( array( 'api_key' => 'sk-test-key', 'style' => 'descriptive', 'language' => 'English', 'batch' => 5 ) );
check( 'settings saved', 'sk-test-key' === AIAT_Settings::get()['api_key'] );

// Two attachments without alt.
$att1 = wp_insert_attachment( array( 'post_title' => 'aiat-one.jpg', 'post_status' => 'inherit' ), '/tmp/aiat-one.jpg', 0 );
$att2 = wp_insert_attachment( array( 'post_title' => 'aiat-two.jpg', 'post_status' => 'inherit' ), '/tmp/aiat-two.jpg', 0 );
update_post_meta( (int) $att1, '_wp_attachment_image_alt', 'already has alt' );
check( 'attachments created', (bool) $att1 && (bool) $att2 );

// Stub the AI transport with a canned vision response.
$seen_urls = array();
add_filter(
	'aiat_transport',
	static function ( $carry, $url, $args ) use ( &$seen_urls ) {
		$seen_urls[] = $url;
		return array(
			'code' => 200,
			'body' => wp_json_encode(
				array(
					'choices' => array( array( 'message' => array( 'content' => 'Here is the alt text: "QA stand photo for testing"' ) ) ),
					'usage'   => array( 'total_tokens' => 42 ),
				)
			),
		);
	},
	10,
	3
);

AIAT_Store::erase_all();
$out = AIAT_Runner::generate_batch();
check( 'batch generated drafts', $out['generated'] >= 1 );
$rows = AIAT_Store::recent( 10 );
$att2_rows = array_values( array_filter( $rows, static function ( $r ) use ( $att2 ) { return (int) $r['attachment_id'] === (int) $att2; } ) );
check( 'our attachment got a draft', 1 === count( $att2_rows ) );
check( 'draft stored with chatter stripped', 'draft' === $att2_rows[0]['status'] && 'QA stand photo for testing' === trim( $att2_rows[0]['draft'], "\"'" ) );
check( 'transport was called with the image', count( $seen_urls ) >= 1 );
check( 'alt-ful attachment has no draft row', ! in_array( (int) $att1, array_column( $rows, 'attachment_id' ), true ) );

// Apply the draft -> alt lands in the native meta.
check( 'apply draft succeeds', AIAT_Store::apply_draft( (int) $att2_rows[0]['id'] ) );
check( 'alt written to meta', 'QA stand photo for testing' === get_post_meta( (int) $att2, '_wp_attachment_image_alt', true ) );
$applied_row = AIAT_Store::recent( 10 );
$applied_found = false;
foreach ( $applied_row as $row ) {
	if ( (int) $row['attachment_id'] === (int) $att2 ) {
		$applied_found = 'applied' === $row['status'];
	}
}
check( 'draft marked applied', $applied_found );

// Rejected drafts free the attachment for a new batch.
AIAT_Store::add_draft( (int) $att1, 'http://localhost/x.jpg', 'draft to reject', 'm', 1 );
$drafts = AIAT_Store::recent( 5 );
$reject_id = 0;
foreach ( $drafts as $row ) {
	if ( 'draft' === $row['status'] ) {
		$reject_id = (int) $row['id'];
	}
}
AIAT_Store::reject_draft( $reject_id );
$after_reject = AIAT_Store::recent( 5 );
$rejected_found = false;
foreach ( $after_reject as $row ) {
	if ( (int) $row['id'] === $reject_id ) {
		$rejected_found = 'rejected' === $row['status'];
	}
}
check( 'reject marks the draft', $rejected_found );

// Cleanup: attachments + data.
wp_delete_attachment( (int) $att1, true );
wp_delete_attachment( (int) $att2, true );
AIAT_Store::erase_all();
delete_option( 'aiat_settings' );
check( 'erase clears drafts', array() === AIAT_Store::recent( 5 ) );

printf( "\n== AI Alt Text integration: %d pass, %d fail ==\n", $GLOBALS['pass'], $GLOBALS['fail'] );
exit( $GLOBALS['fail'] > 0 ? 1 : 0 );
