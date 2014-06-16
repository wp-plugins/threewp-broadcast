<?php

class MailTest extends \plainview\sdk\tests\TestCase
{
	public function test_mail()
	{
		$mail = new \plainview\sdk\mail\mail;
		$this->assertTrue( is_a( $mail, '\\PHPMailer' ) );
	}
}
