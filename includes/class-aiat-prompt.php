<?php
/**
 * Prompt builder, response parser, request client (pure).
 *
 * @package AIAltText
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIAT_Prompt {

	/**
	 * Build the vision request payload for one image.
	 *
	 * @param string $image_url Public image URL.
	 * @param array  $opts {style: descriptive|seo, language, max_words, model, host}.
	 * @return array
	 */
	public static function request( $image_url, array $opts = array() ) {
		$style    = isset( $opts['style'] ) && 'seo' === $opts['style'] ? 'seo' : 'descriptive';
		$language = isset( $opts['language'] ) && '' !== trim( (string) $opts['language'] ) ? trim( (string) $opts['language'] ) : 'English';
		$words    = max( 3, min( 40, isset( $opts['max_words'] ) ? (int) $opts['max_words'] : 12 ) );
		$model    = isset( $opts['model'] ) && '' !== trim( (string) $opts['model'] ) ? trim( (string) $opts['model'] ) : 'gpt-4o-mini';

		$instruction = $style === 'seo'
			? "Write an alt text for this image for a website. Style: descriptive with relevant keywords. Language: {$language}. Maximum {$words} words. Answer with the alt text only."
			: "Write an alt text for this image for a website. Style: plain objective description. Language: {$language}. Maximum {$words} words. Answer with the alt text only.";

		return array(
			'model'      => $model,
			'max_tokens' => 80,
			'messages'   => array(
				array(
					'role'    => 'user',
					'content' => array(
						array( 'type' => 'text', 'text' => $instruction ),
						array( 'type' => 'image_url', 'image_url' => array( 'url' => (string) $image_url ) ),
					),
				),
			),
		);
	}

	/**
	 * Extract alt text from an API response body (tolerant).
	 *
	 * @param string $body Raw JSON body.
	 * @return array {alt: string, tokens: int} alt='' when unusable.
	 */
	public static function parse_response( $body ) {
		$payload = json_decode( (string) $body, true );
		if ( ! is_array( $payload ) ) {
			return array( 'alt' => '', 'tokens' => 0 );
		}

		$alt = '';
		if ( isset( $payload['choices'][0]['message']['content'] ) ) {
			$alt = (string) $payload['choices'][0]['message']['content'];
		}

		// Strip chatty prefixes ("Here is the alt text: ...").
		$alt = trim( (string) preg_replace( '/^(here (is|\'s)( the)?[^:]*:)\s*/i', '', $alt ) );
		$alt = trim( (string) preg_replace( '/^["\']|["\']$/', '', $alt ) );

		$tokens = isset( $payload['usage']['total_tokens'] ) ? (int) $payload['usage']['total_tokens'] : 0;

		return array( 'alt' => trim( $alt ), 'tokens' => $tokens );
	}
}
