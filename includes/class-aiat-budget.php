<?php
/**
 * Daily token budget (pure).
 *
 * @package AIAltText
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Daily token budget (pure).
 */

class AIAT_Budget {

	/**
	 * Whether another batch may run under the daily cap.
	 *
	 * @param int $used_today Tokens spent today.
	 * @param int $cap        Daily cap.
	 * @return bool
	 */
	public static function allows( $used_today, $cap ) {
		$cap = max( 0, (int) $cap );
		if ( 0 === $cap ) {
			return true; // 0 = unlimited.
		}

		return (int) $used_today < $cap;
	}
}
