<?php
/**
 * API client and daily budget (pure).
 *
 * @package AIAltText
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * API client with an injectable transport (filterable in WP).
 */
class AIAT_Client {

	const DEFAULT_HOST = 'https://api.openai.com/v1/chat/completions';

	/** @var callable|null fn(string $url, array $args): array{code:int, body:string} */
	private $transport;

	public function __construct( $transport = null ) {
		$this->transport = $transport;
	}

	/**
	 * Generate alt for one image.
	 *
	 * @param string $api_key API key.
	 * @param array  $request Prompt payload.
	 * @return array {ok, alt, tokens, error}
	 */
	public function generate( $api_key, array $request ) {
		if ( '' === trim( (string) $api_key ) ) {
			return array( 'ok' => false, 'alt' => '', 'tokens' => 0, 'error' => 'No API key configured.' );
		}

		$url = apply_filters( 'aiat_endpoint', self::DEFAULT_HOST );
		$args = array(
			'timeout' => 30,
			'headers' => array(
				'Authorization' => 'Bearer ' . (string) $api_key,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $request ),
		);

		if ( $this->transport ) {
			$result = call_user_func( $this->transport, $url, $args );
		} else {
			$custom = apply_filters( 'aiat_transport', null, $url, $args );
			if ( null !== $custom ) {
				$result = $custom;
			} else {
				$response = wp_remote_post( $url, $args );
				if ( is_wp_error( $response ) ) {
					return array( 'ok' => false, 'alt' => '', 'tokens' => 0, 'error' => $response->get_error_message() );
				}
				$result = array(
					'code' => (int) wp_remote_retrieve_response_code( $response ),
					'body' => (string) wp_remote_retrieve_body( $response ),
				);
			}
		}

		$code = isset( $result['code'] ) ? (int) $result['code'] : 0;
		if ( 429 === $code ) {
			return array( 'ok' => false, 'alt' => '', 'tokens' => 0, 'error' => 'Rate limited (429).' );
		}
		if ( $code < 200 || $code >= 300 ) {
			return array( 'ok' => false, 'alt' => '', 'tokens' => 0, 'error' => 'HTTP ' . $code );
		}

		$parsed = AIAT_Prompt::parse_response( isset( $result['body'] ) ? $result['body'] : '' );
		if ( '' === $parsed['alt'] ) {
			return array( 'ok' => false, 'alt' => '', 'tokens' => $parsed['tokens'], 'error' => 'Empty answer.' );
		}

		return array( 'ok' => true, 'alt' => $parsed['alt'], 'tokens' => $parsed['tokens'], 'error' => '' );
	}
}
