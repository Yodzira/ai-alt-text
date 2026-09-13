<?php
/**
 * Split classes.
 *
 * @package AIAltText
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIAT_Runner {

	const BUDGET_OPTION = 'aiat_tokens_today';
	const BUDGET_DAY    = 'aiat_tokens_day';

	public static function boot() {
		add_action( 'aiat_generate_batch', array( __CLASS__, 'generate_batch' ) );
	}

	/**
	 * Pick attachments without alt, request drafts, store them.
	 *
	 * @return array {generated, errors, skipped_budget}
	 */
	public static function generate_batch() {
		$settings = AIAT_Settings::get();
		$out      = array( 'generated' => 0, 'errors' => 0, 'skipped_budget' => false );

		if ( '' === $settings['api_key'] ) {
			$out['errors'] = -1; // Not configured.

			return $out;
		}

		// Daily budget bookkeeping.
		$today = gmdate( 'Y-m-d' );
		if ( get_option( self::BUDGET_DAY, '' ) !== $today ) {
			update_option( self::BUDGET_DAY, $today, false );
			update_option( self::BUDGET_OPTION, 0, false );
		}
		if ( ! AIAT_Budget::allows( (int) get_option( self::BUDGET_OPTION, 0 ), $settings['daily_cap'] ) ) {
			$out['skipped_budget'] = true;

			return $out;
		}

		$client  = new AIAT_Client();
		$targets = AIAT_Store::missing_alt( $settings['batch'] );

		foreach ( (array) $targets as $target ) {
			$request = AIAT_Prompt::request(
				self::image_reference( $target ),
				array(
					'style'    => $settings['style'],
					'language' => $settings['language'],
					'model'    => $settings['model'],
				)
			);
			$result = $client->generate( $settings['api_key'], $request );

			if ( $result['ok'] ) {
				AIAT_Store::add_draft( (int) $target['id'], $target['url'], $result['alt'], $settings['model'], $result['tokens'] );
				update_option( self::BUDGET_OPTION, (int) get_option( self::BUDGET_OPTION, 0 ) + $result['tokens'], false );
				$out['generated']++;
			} else {
				AIAT_Store::add_error( (int) $target['id'], $target['url'], $result['error'] );
				$out['errors']++;
			}
		}

		return $out;
	}

	/**
	 * Image reference for the provider: the public URL when the site is
	 * reachable from outside, otherwise the file itself as a data URL
	 * (local/staging sites the provider cannot fetch).
	 *
	 * @param array $target {id, url}.
	 * @return string
	 */
	public static function image_reference( array $target ) {
		$url = (string) ( isset( $target['url'] ) ? $target['url'] : '' );
		$id  = (int) ( isset( $target['id'] ) ? $target['id'] : 0 );

		$file = ( $id && function_exists( 'get_attached_file' ) ) ? get_attached_file( $id ) : '';
		if ( ! $file || ! is_readable( $file ) || filesize( $file ) > 4 * 1024 * 1024 ) {
			return $url;
		}

		$host = wp_parse_url( $url, PHP_URL_HOST );
		$site = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$public = is_string( $host ) && is_string( $site ) && strtolower( (string) $host ) === strtolower( (string) $site )
			&& ! in_array( strtolower( (string) $host ), array( 'localhost', '127.0.0.1' ), true )
			&& ! preg_match( '/^(10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[01])\.)/', (string) $host );

		if ( $public ) {
			return $url;
		}

		$bytes = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents -- small local media file.
		if ( false === $bytes ) {
			return $url;
		}
		$mime = 'image/jpeg';
		$info = function_exists( 'finfo_open' ) ? finfo_open( FILEINFO_MIME_TYPE ) : false;
		if ( $info ) {
			$detected = finfo_buffer( $info, $bytes );
			if ( is_string( $detected ) && '' !== $detected ) {
				$mime = $detected;
			}
			finfo_close( $info );
		}

		return 'data:' . $mime . ';base64,' . base64_encode( $bytes );
	}
}
