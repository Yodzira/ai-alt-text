<?php
/**
 * Drafts storage.
 *
 * @package AIAltText
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIAT_Store {

	public static function table_name() {
		global $wpdb;

		return $wpdb->prefix . 'aiat_drafts';
	}

	public static function activate() {
		global $wpdb;

		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();
		$sql     = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			attachment_id bigint(20) unsigned NOT NULL DEFAULT 0,
			image_url varchar(500) NOT NULL DEFAULT '',
			draft text NULL,
			status varchar(10) NOT NULL DEFAULT 'draft',
			model varchar(50) NOT NULL DEFAULT '',
			tokens int(11) NOT NULL DEFAULT 0,
			error varchar(255) NOT NULL DEFAULT '',
			created_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
			PRIMARY KEY  (id),
			KEY attachment_id (attachment_id),
			KEY status (status)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Attachments without alt text, excluding ones with a pending draft.
	 *
	 * @param int $limit Max rows.
	 * @return array[] id + guid.
	 */
	public static function missing_alt( $limit = 5 ) {
		global $wpdb;
		$table = self::table_name();

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID AS id, p.guid AS url
				 FROM {$wpdb->posts} p
				 WHERE p.post_type = 'attachment'
				   AND p.guid <> ''
				   AND NOT EXISTS (SELECT meta_id FROM {$wpdb->postmeta} m WHERE m.post_id = p.ID AND m.meta_key = '_wp_attachment_image_alt' AND m.meta_value <> '')
				   AND NOT EXISTS (SELECT id FROM {$table} d WHERE d.attachment_id = p.ID AND d.status = 'draft')
				 ORDER BY p.ID ASC LIMIT %d",
				(int) $limit
			),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL -- table names from wpdb/prefix.
	}

	public static function add_draft( $attachment_id, $url, $draft, $model, $tokens ) {
		global $wpdb;

		$wpdb->insert(
			self::table_name(),
			array(
				'attachment_id' => (int) $attachment_id,
				'image_url'     => substr( (string) $url, 0, 500 ),
				'draft'         => (string) $draft,
				'status'        => 'draft',
				'model'         => substr( (string) $model, 0, 50 ),
				'tokens'        => (int) $tokens,
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	public static function add_error( $attachment_id, $url, $error ) {
		global $wpdb;

		$wpdb->insert(
			self::table_name(),
			array(
				'attachment_id' => (int) $attachment_id,
				'image_url'     => substr( (string) $url, 0, 500 ),
				'draft'         => '',
				'status'        => 'error',
				'error'         => substr( (string) $error, 0, 255 ),
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%s' )
		);
	}

	public static function recent( $limit = 100 ) {
		global $wpdb;
		$table = self::table_name();

		return $wpdb->get_results( $wpdb->prepare( "SELECT id, attachment_id, image_url, draft, status, tokens, error FROM {$table} ORDER BY id DESC LIMIT %d", (int) $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL -- table from prefix.
	}

	/**
	 * Approve a draft: write alt to the attachment meta, mark applied.
	 *
	 * @param int $draft_id Draft row.
	 * @return bool
	 */
	public static function apply_draft( $draft_id ) {
		global $wpdb;
		$table = self::table_name();

		$draft = $wpdb->get_row( $wpdb->prepare( "SELECT id, attachment_id, draft FROM {$table} WHERE id = %d AND status = 'draft'", (int) $draft_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL -- table from prefix.
		if ( ! $draft || '' === trim( (string) $draft['draft'] ) ) {
			return false;
		}

		update_post_meta( (int) $draft['attachment_id'], '_wp_attachment_image_alt', trim( (string) $draft['draft'] ) );
		$wpdb->update( $table, array( 'status' => 'applied' ), array( 'id' => (int) $draft_id ), array( '%s' ), array( '%d' ) );

		return true;
	}

	public static function reject_draft( $draft_id ) {
		global $wpdb;
		$table = self::table_name();
		$wpdb->update( $table, array( 'status' => 'rejected' ), array( 'id' => (int) $draft_id, 'status' => 'draft' ), array( '%s' ), array( '%d', '%s' ) );

		return true;
	}

	public static function erase_all() {
		global $wpdb;
		$table = self::table_name();
		$wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.
	}

	public static function drop_table() {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL -- identifier derived from $wpdb->prefix.
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}
}
