<?php
/**
 * Plugin boot + admin.
 *
 * @package AIAltText
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIAT_Plugin {

	public static function boot() {
		if ( is_admin() ) {
			add_action( 'admin_menu', array( 'AIAT_Admin', 'menu' ) );
			add_action( 'admin_post_aiat_save', array( 'AIAT_Admin', 'handle_save' ) );
			add_action( 'admin_post_aiat_generate', array( 'AIAT_Admin', 'handle_generate' ) );
			add_action( 'admin_post_aiat_apply', array( 'AIAT_Admin', 'handle_apply' ) );
			add_action( 'admin_post_aiat_reject', array( 'AIAT_Admin', 'handle_reject' ) );
		}
		AIAT_Runner::boot();
	}
}

class AIAT_Admin {

	public static function menu() {
		add_menu_page( 'AI Alt Text', 'AI Alt Text', 'manage_options', 'ai-alt-text', array( __CLASS__, 'render' ), 'dashicons-format-image' );
	}

	public static function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'ai-alt-text' ) );
		}
		check_admin_referer( 'aiat_save' );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized in Settings::save().
		AIAT_Settings::save( isset( $_POST['aiat'] ) ? (array) $_POST['aiat'] : array() );
		wp_safe_redirect( admin_url( 'admin.php?page=ai-alt-text&saved=1' ) );
		exit;
	}

	public static function handle_generate() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'ai-alt-text' ) );
		}
		check_admin_referer( 'aiat_generate' );
		AIAT_Runner::generate_batch();
		wp_safe_redirect( admin_url( 'admin.php?page=ai-alt-text&generated=1' ) );
		exit;
	}

	public static function handle_apply() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'ai-alt-text' ) );
		}
		check_admin_referer( 'aiat_apply' );
		AIAT_Store::apply_draft( isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0 );
		wp_safe_redirect( admin_url( 'admin.php?page=ai-alt-text&applied=1' ) );
		exit;
	}

	public static function handle_reject() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'ai-alt-text' ) );
		}
		check_admin_referer( 'aiat_reject' );
		AIAT_Store::reject_draft( isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0 );
		wp_safe_redirect( admin_url( 'admin.php?page=ai-alt-text&rejected=1' ) );
		exit;
	}

	public static function render() {
		$settings = AIAT_Settings::get();
		$saved    = isset( $_GET['saved'] ) ? sanitize_key( wp_unslash( $_GET['saved'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display flag.
		$rows     = AIAT_Store::recent( 100 );
		?>
		<div class="wrap">
			<h1>AI Alt Text</h1>

			<?php if ( '1' === $saved ) : ?>
				<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="aiat_save">
				<?php wp_nonce_field( 'aiat_save' ); ?>
				<table class="form-table" role="presentation">
					<tr><th>API key</th><td><input type="password" name="aiat[api_key]" value="<?php echo esc_attr( $settings['api_key'] ); ?>" class="regular-text" autocomplete="new-password"><p class="description">OpenAI-compatible vision key. Stored in your DB, never sent anywhere else.</p></td></tr>
					<tr><th>Model</th><td><input type="text" name="aiat[model]" value="<?php echo esc_attr( $settings['model'] ); ?>" class="regular-text"></td></tr>
					<tr>
						<th>Style</th>
						<td>
							<label><input type="radio" name="aiat[style]" value="descriptive" <?php checked( $settings['style'], 'descriptive' ); ?>> Descriptive</label>
							<label><input type="radio" name="aiat[style]" value="seo" <?php checked( $settings['style'], 'seo' ); ?>> SEO-friendly</label>
						</td>
					</tr>
					<tr><th>Language</th><td><input type="text" name="aiat[language]" value="<?php echo esc_attr( $settings['language'] ); ?>" class="regular-text"></td></tr>
					<tr><th>Batch size</th><td><input type="number" name="aiat[batch]" min="1" max="25" value="<?php echo esc_attr( $settings['batch'] ); ?>" class="small-text"></td></tr>
					<tr><th>Daily token cap</th><td><input type="number" name="aiat[daily_cap]" min="0" step="1000" value="<?php echo esc_attr( $settings['daily_cap'] ); ?>" class="regular-text"><p class="description">0 = unlimited.</p></td></tr>
				</table>
				<p>
					<button type="submit" class="button button-primary">Save</button>
					<button type="submit" class="button" formaction="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=aiat_generate' ), 'aiat_generate' ) ); ?>">Generate next batch</button>
					<span class="description" style="margin-left:10px">Tokens today: <?php echo esc_html( (string) get_option( AIAT_Runner::BUDGET_OPTION, 0 ) ); ?></span>
				</p>
			</form>

			<h2>Drafts (last 100)</h2>
			<?php if ( ! $rows ) : ?>
				<p><em>No drafts yet — press "Generate next batch".</em></p>
			<?php else : ?>
				<table class="widefat striped" style="max-width:1150px">
					<thead><tr><th style="width:70px">ID</th><th style="width:70px">Att.</th><th>Draft alt text</th><th style="width:90px">Status</th><th style="width:80px">Tokens</th><th style="width:150px"></th></tr></thead>
					<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td><?php echo esc_html( $row['id'] ); ?></td>
							<td><?php echo esc_html( $row['attachment_id'] ); ?></td>
							<td><?php echo esc_html( 'error' === $row['status'] ? '❌ ' . $row['error'] : $row['draft'] ); ?></td>
							<td><?php echo esc_html( $row['status'] ); ?></td>
							<td><?php echo esc_html( $row['tokens'] ); ?></td>
							<td>
								<?php if ( 'draft' === $row['status'] ) : ?>
									<a class="button button-small button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=aiat_apply&id=' . (int) $row['id'] ), 'aiat_apply' ) ); ?>">Apply</a>
									<a class="button button-small" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=aiat_reject&id=' . (int) $row['id'] ), 'aiat_reject' ) ); ?>">Reject</a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}
}
