<?php

use \plainview\base;

class BaseTest
	extends \plainview\tests\TestCase
{
	public function test_is_private_ip()
	{
		$this->assertTrue( base::is_private_ip( '10.0.53.2' ) );
		$this->assertTrue( base::is_private_ip( '172.16.0.5' ) );
		$this->assertTrue( base::is_private_ip( '192.168.98.200' ) );
		$this->assertTrue( base::is_private_ip( '169.254.43.44' ) );
		$this->assertTrue( base::is_private_ip( '127.0.1.1' ) );
		$this->assertFalse( base::is_private_ip( '213.64.153.20' ) );
	}
}
