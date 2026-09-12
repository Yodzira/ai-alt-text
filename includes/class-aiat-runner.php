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
				$target['url'],
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
}
