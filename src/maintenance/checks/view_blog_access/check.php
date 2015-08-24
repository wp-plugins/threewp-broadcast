<?php

namespace threewp_broadcast\maintenance\checks\view_blog_access;

use \threewp_broadcast\actions\get_user_writable_blogs;

/**
	@brief		See which blogs the users can access.
	@since		20131224
**/
class check
extends \threewp_broadcast\maintenance\checks\check
{
	public function get_description()
	{
		return 'Check to see which users can write to which blogs.';
	}

	public function get_name()
	{
		return 'View blog access';
	}

	public function step_start()
	{
		$o = new \stdClass;
		$o->inputs = new \stdClass;
		$o->form = $this->broadcast()->form2();
		$o->r = '';

		$o->inputs->user = $o->form->select( 'user' )
			->description( 'The user or users for which to check blog access.' )
			->label( 'User(s) to check' )
			->multiple();

		$all_users = get_users();
		$users = [];
		foreach( $all_users as $user )
		{
			$o->inputs->user->option( $user->data->user_login, $user->data->ID );
			$users[ $user->data->ID ] = $user;
		}

		$o->inputs->user->autosize();

		$button = $o->form->primary_button( 'view_blog_access' )
			->value( 'Display blog access for the selected user(s)' );

		if ( $o->form->is_posting() )
		{
			$o->form->post()->use_post_value();

			if ( $button->pressed() )
			{
				$r = '';
				$selected_users = $o->inputs->user->get_post_value();
				if ( is_array( $selected_users ) )
					foreach( $selected_users as $selected_user )
					{
						$user = $users[ $selected_user ];
						$filter = new get_user_writable_blogs( $selected_user );
						$blogs = $filter->execute()->blogs;

						if ( count( $blogs ) < 1 )
							$r .= $this->broadcast()->p_( '%s does not have access to any blogs.', $user->data->user_login );
						else
						{
							$blogs_ul = [];
							foreach( $blogs as $blog )
							{
								$blogs_ul []= sprintf( '<a href="%s/wp-admin">%s</a>', $blog->siteurl, $blog->get_name() );
							}
							$r .= $this->broadcast()->p_( '%s has access to the following blogs: %s%s%s',
								$user->data->user_login,
								'<ul>',
								$this->broadcast()->implode_html( $blogs_ul ),
								'</ul>'
							);
						}
					}

				if ( $r != '' )
				{
					$this->broadcast()->message( $r );
				}
			}
		}

		$o->r .= $this->broadcast()->p( "Use this tool to help diagnose why the Broadcast meta box isn't apprearing for some users. To be able to broadcast to a blog the user must be added as an author or above." );

		$o->r .= $o->form->open_tag();
		$o->r .= $o->form->display_form_table();
		$o->r .= $o->form->close_tag();
		return $o->r;
	}
}
