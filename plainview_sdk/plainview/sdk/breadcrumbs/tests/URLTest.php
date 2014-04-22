<?php

namespace plainview\sdk\breadcrumbs\tests;

class URLTest extends TestCase
{
	public function test_url()
	{
		$url = 'http://plainview.se&testing=true';
		$string = 'href="' . $url . '"';
		$bcs = $this->bcs();
		$bc = $bcs->breadcrumb( 'test' )
			->url( $url );
		$this->assertStringContains( $string, $bc );
	}
}
