<?php
/**
 * Split classes.
 *
 * @package AIAltText
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIAT_Settings {

	const OPTION = 'aiat_settings';

	public static function defaults() {
		return array(
			'api_key' => '',
			'model'   => 'gpt-4o-mini',
			'style'   => 'descriptive',
			'language' => 'English',
			'daily_cap' => 100000,
			'batch'   => 5,
		);
	}

	public static function get() {
		$stored = get_option( self::OPTION, array() );
		$merged = array_merge( self::defaults(), is_array( $stored ) ? array_intersect_key( $stored, self::defaults() ) : array() );
		$merged['style']   = 'seo' === $merged['style'] ? 'seo' : 'descriptive';
		$merged['batch']   = max( 1, min( 25, (int) $merged['batch'] ) );
		$merged['daily_cap'] = max( 0, (int) $merged['daily_cap'] );

		return $merged;
	}

	public static function save( $in ) {
		$in   = is_array( $in ) ? $in : array();
		$defaults = self::defaults();
		$clean = array(
			'api_key' => substr( trim( (string) ( isset( $in['api_key'] ) ? $in['api_key'] : '' ) ), 0, 200 ),
			'model'   => substr( trim( (string) ( isset( $in['model'] ) ? $in['model'] : $defaults['model'] ) ), 0, 50 ),
			'style'   => 'seo' === ( isset( $in['style'] ) ? $in['style'] : '' ) ? 'seo' : 'descriptive',
			'language' => substr( trim( (string) ( isset( $in['language'] ) ? $in['language'] : $defaults['language'] ) ), 0, 30 ),
			'daily_cap' => max( 0, (int) ( isset( $in['daily_cap'] ) ? $in['daily_cap'] : $defaults['daily_cap'] ) ),
			'batch'   => max( 1, min( 25, (int) ( isset( $in['batch'] ) ? $in['batch'] : $defaults['batch'] ) ) ),
		);
		update_option( self::OPTION, $clean );

		return $clean;
	}
}
