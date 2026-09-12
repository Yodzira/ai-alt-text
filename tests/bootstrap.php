<?php
/**
 * Standalone bootstrap: prompt, client, budget are pure (shims for wp_*).
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/wp/' );
}
if ( ! defined( 'AIAT_DIR' ) ) {
	define( 'AIAT_DIR', dirname( __DIR__ ) );
}

function wp_json_encode( $value ) {
	return json_encode( $value );
}

$GLOBALS['__aiat_options'] = array();

function get_option( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['__aiat_options'] ) ? $GLOBALS['__aiat_options'][ $key ] : $default;
}

function update_option( $key, $value, $autoload = null ) {
	$GLOBALS['__aiat_options'][ $key ] = $value;

	return true;
}

function apply_filters( $tag, $value ) {
	return $value; // No filters in unit tests.
}

spl_autoload_register(
	static function ( $class ) {
		if ( 0 !== strpos( $class, 'AIAT_' ) ) {
			return;
		}
		$snake = strtolower( preg_replace( '/([a-z0-9])([A-Z])/', '$1-$2', substr( $class, 5 ) ) );
		$snake = str_replace( '_', '-', $snake );
		$file  = AIAT_DIR . '/includes/class-aiat-' . $snake . '.php';
		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);
