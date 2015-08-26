<?php

class MailTest extends \plainview\sdk_broadcast\tests\TestCase
{
	/**
		@brief		Create a mail object.
		@since		2015-05-19 22:25:17
	**/
	public function mail()
	{
		return new \plainview\sdk_broadcast\mail\mail;
	}

	public function test_mail()
	{
		$mail = $this->mail();
		$this->assertTrue( is_a( $mail, '\\PHPMailer' ) );
	}

	/**
		@brief		Test the sprinting of the html text.
		@since		2015-05-19 22:24:57
	**/
	public function test_html_sprintf()
	{
		$this->sprintf_property( 'html', 'Body' );
	}

	/**
		@brief		Test that a method works with sprintf.
		@since		2015-05-19 22:47:35
	**/
	public function sprintf_property( $method, $property )
	{
		$mail = $this->mail();
		$mail->$method( 'Test text' );
		$this->assertEquals( $mail->$property, 'Test text' );

		$mail->$method( 'Test text %s', 'again' );
		$this->assertEquals( $mail->$property, 'Test text again' );

		$mail->$method( 'Test text %s' );
		$this->assertEquals( $mail->$property, 'Test text %s' );
	}

	/**
		@brief		Test the sprinting of the subject.
		@since		2015-05-19 22:24:57
	**/
	public function test_subject_sprintf()
	{
		$this->sprintf_property( 'subject', 'Subject' );
	}

	/**
		@brief		Test the sprinting of the text.
		@since		2015-05-19 22:24:57
	**/
	public function test_text_sprintf()
	{
		$this->sprintf_property( 'text', 'Body' );
	}
}
