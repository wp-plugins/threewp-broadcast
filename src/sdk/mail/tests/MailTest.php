<?php

class MailTest extends \plainview\sdk_broadcast\tests\TestCase
{
	public function test_mail()
	{
		$mail = new \plainview\sdk_broadcast\mail\mail;
		$this->assertTrue( is_a( $mail, '\\PHPMailer' ) );
	}
}
