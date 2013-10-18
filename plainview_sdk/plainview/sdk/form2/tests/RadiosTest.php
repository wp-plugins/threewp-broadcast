<?php

namespace plainview\sdk\form2\tests;

class RadiosTest extends TestCase
{
	public function radios()
	{
		return $this->form()->radios( 'radiostest' )
			->label( 'Radios' )
			->option( 'Radio 1', 'r1' )
			->option( 'Radio 2', 'r2' )
			->option( 'Radio 3', 'r3' )
			->value( 'r2' );
	}

	public function test_ids()
	{
		$radios = $this->radios();
		$this->assertStringContainsRegExp( '/id=".*radiostest_r1"/', $radios );
		$this->assertStringContainsRegExp( '/id=".*radiostest_r2"/', $radios );
		$this->assertStringContainsRegExp( '/id=".*radiostest_r3"/', $radios );
	}

	public function test_names()
	{
		$radios = $this->radios();
		$matches = preg_match_all( '/name="radiostest"/', $radios );
		$this->assertEquals( 3, $matches );
	}

	public function test_option_names_extract()
	{
		// Each individual radios option is actually an input, reachable by using input( RADIOSNAME_VALUE ).
		$radios = $this->radios();
		$this->assertNotEquals( false, $radios->input( 'radiostest_r1' ) );
		$this->assertNotEquals( false, $radios->input( 'radiostest_r2' ) );
		$this->assertNotEquals( false, $radios->input( 'radiostest_r3' ) );
		// r100 doesn't exist.
		$this->assertEquals( false, $radios->input( 'radiostest_r100' ) );
	}

	public function test_r2_is_checked()
	{
		$radios = $this->radios();
		$this->assertStringContainsRegexp( '/\.*\<input.*\<input.*checked=\"checked\".*\<input/s', $radios );
	}

	public function test_labels()
	{
		$radios = $this->radios();
		$this->assertStringContains( '<legend>Radios</legend>', $radios );
		$this->assertStringContainsRegexp( '/\<label.*for=".*radiostest_r1".*Radio 1<\/label>/', $radios );
	}

	public function test_prefix_is_inherited_to_each_radio()
	{
		$radios = $this->radios();
		$radios->prefix( 'testprefix' );
		$matches = preg_match_all( '/name=\"testprefix\[/', $radios );
		$this->assertEquals( 3, $matches );
	}

	public function test_prefixes_are_inherited_to_each_radio()
	{
		$radios = $this->radios();
		$radios->prefix( 'testprefix1', 'testprefix2' );
		$matches = preg_match_all( '/name="testprefix1\[testprefix2\]\[radiostest\]/', $radios );
		$this->assertEquals( 3, $matches );
	}

	public function test_use_post_value()
	{
		$test_value = 'r3';
		$radios = $this->radios();
		$form = $radios->form();
		$form->post( [
			'radiostest' => $test_value,
		] );
		$post_value = $radios->get_post_value();
		$this->assertEquals( $test_value, $radios->get_post_value() );
	}
}
