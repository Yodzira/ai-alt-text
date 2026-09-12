<?php

use PHPUnit\Framework\TestCase;

/**
 * Prompt building, response parsing, client, budget, settings.
 */
class CoreTest extends TestCase {

	public function test_request_payload() {
		$req = AIAT_Prompt::request(
			'https://site.com/img.jpg',
			array( 'style' => 'seo', 'language' => 'Russian', 'model' => 'gpt-4o-mini' )
		);

		$this->assertSame( 'gpt-4o-mini', $req['model'] );
		$this->assertStringContainsString( 'Russian', $req['messages'][0]['content'][0]['text'] );
		$this->assertStringContainsString( 'keywords', $req['messages'][0]['content'][0]['text'] );
		$this->assertSame( 'https://site.com/img.jpg', $req['messages'][0]['content'][1]['image_url']['url'] );
	}

	public function test_parse_response_clean() {
		$body = '{"choices":[{"message":{"content":"A team of developers at a desk"}}],"usage":{"total_tokens":57}}';
		$out  = AIAT_Prompt::parse_response( $body );

		$this->assertSame( 'A team of developers at a desk', $out['alt'] );
		$this->assertSame( 57, $out['tokens'] );
	}

	public function test_parse_response_strips_chatter_and_quotes() {
		$out = AIAT_Prompt::parse_response( '{"choices":[{"message":{"content":"Here is the alt text: \"A red car\""}}]}' );
		$this->assertSame( 'A red car', $out['alt'] );
	}

	public function test_parse_response_garbage() {
		$this->assertSame( '', AIAT_Prompt::parse_response( 'not json' )['alt'] );
		$this->assertSame( '', AIAT_Prompt::parse_response( '{"choices":[]}' )['alt'] );
	}

	public function test_client_rejects_missing_key_and_http_errors() {
		$never = static function () { throw new Exception( 'must not be called' ); };
		$this->assertFalse( ( new AIAT_Client( $never ) )->generate( '', array() )['ok'] );

		$transport = static function () { return array( 'code' => 429, 'body' => '{}' ); };
		$result    = ( new AIAT_Client( $transport ) )->generate( 'sk-key', array() );
		$this->assertFalse( $result['ok'] );
		$this->assertStringContainsString( '429', $result['error'] );
	}

	public function test_budget_gate() {
		$this->assertTrue( AIAT_Budget::allows( 1000, 0 ) );     // 0 = unlimited.
		$this->assertTrue( AIAT_Budget::allows( 999, 1000 ) );
		$this->assertFalse( AIAT_Budget::allows( 1000, 1000 ) );
	}

	public function test_settings_sanitize() {
		$GLOBALS['__aiat_options'] = array();
		$clean = AIAT_Settings::save(
			array( 'api_key' => ' sk-abc ', 'model' => 'gpt-4o', 'style' => 'bogus', 'batch' => 999, 'daily_cap' => -5 )
		);
		$this->assertSame( 'sk-abc', $clean['api_key'] );
		$this->assertSame( 'descriptive', $clean['style'] ); // Unknown style -> default.
		$this->assertSame( 25, $clean['batch'] );            // Clamped.
		$this->assertSame( 0, $clean['daily_cap'] );         // -5 -> clamped to 0 (unlimited).
	}
}
