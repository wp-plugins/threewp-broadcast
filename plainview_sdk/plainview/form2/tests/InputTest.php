<?php

namespace plainview\form2\tests;

class InputTest extends TestCase
{
	public function test_plaintext_description()
	{
		$text = $this->form()->text( 'text_test' )
			->description( 'A nice description' );
		$this->assertStringContainsRegexp( '/.*\<div.*class="description".*A nice description.*/', $text );
	}

	public function test_description_with_html()
	{
		$text = $this->form()->text( 'text_test' )
			->description( 'A <h1>bad</h1> description' );
		$this->assertStringContainsRegexp( '/.*\<div.*class="description".*A &lt;h1&gt;bad&lt;\/h1&gt; description.*/', $text );
	}

	public function test_input_described_by()
	{
		$text = $this->form()->text( 'text_test' )
			->description( 'A <h1>bad</h1> description' );
		$this->assertStringContainsRegexp( '/\<input.*aria-describedby=\".*\<div.*class=\"description\"/s', $text );
	}
}
