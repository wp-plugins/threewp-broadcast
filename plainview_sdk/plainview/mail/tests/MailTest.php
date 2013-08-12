<?php

class MailTest extends \plainview\tests\TestCase
{
	public function test_mail()
	{
		$mail = new \plainview\mail\mail;
		$this->assertTrue( is_a( $mail, '\\PHPMailer' ) );
	}
}
