jQuery(document).ready(function($) {
	window.broadcast =
	{
		$show_hide : null,
		$broadcast : null,
		$broadcast_blogs_htmls : null,
		$blogs_html : null,
		$select_all : null,
		$invert_selection : null,

		init : function()
		{
			this.$broadcast = $( '#threewp_broadcast.postbox' );

			// If the box doesn't exist, do nothing.
			if ( this.$broadcast.length < 1 )
				return;

			// If the box doesn't contain any input information, do nothing.
			if ( $( 'input', this.$broadcast ).length < 1 )
				return;

			this.$blogs_html = $( '.blogs.html_section', this.$broadcast );
			this.$broadcast_blogs_htmls = $( 'input.checkbox', this.$blogs_html );

			// Container for selection change.
			this.$selection_change_container = $( '<div />' )
				.addClass( 'clear' )
				.addClass( 'selection_change_container howto' )
				.appendTo( this.$blogs_html );

			// Append "Select all / none" text.
			this.$select_all = $( '<span />' )
				.addClass( 'selection_change select_deselect_all' )
				.click(function()
				{
					var checkedStatus = ! window.broadcast.$broadcast_blogs_htmls.first().prop( 'checked' );
					window.broadcast.$broadcast_blogs_htmls.each( function(index, item)
					{
						var $item = $( item );
						// Only change the status of the blogs that aren't disabled.
						if ( $item.prop( 'disabled' ) != true )
							$item.prop( 'checked', checkedStatus );
					});
				})
				.text( broadcast_strings.select_deselect_all )
				.appendTo( this.$selection_change_container );

			this.$selection_change_container.append( '&emsp;' );

			this.$invert_selection = $( '<span />' )
				.click( function()
				{
					window.broadcast.$broadcast_blogs_htmls.each( function(index, item)
					{
						var $item = $( item );
						var checked = $item.prop( 'checked' );
						$item.prop( 'checked', ! checked );
					});
				})
				.addClass( 'selection_change invert_selection' )
				.text( broadcast_strings.invert_selection )
				.appendTo( this.$selection_change_container );

			// Need to hide the blog list?
			if ( broadcast_blogs_to_hide === undefined )
				broadcast_blogs_to_hide = 5;
			if ( this.$broadcast_blogs_htmls.length > broadcast_blogs_to_hide )
			{
				this.$show_hide = $( '<div />' )
					.addClass( 'show_hide howto' )
					.appendTo( this.$blogs_html )
					.click( function()
					{
						var $this = $( this );
						if ( window.broadcast.$blogs_html.hasClass( 'opened' ) )
							window.broadcast.hide_blogs();
						else
							window.broadcast.show_blogs();
					});

				this.hide_blogs();
			}

			// GROUP functionality: Allow blogs to be mass selected, unselected.
			$( ".blog_groups select", this.$broadcast ).change(function()
			{
				var $this = $( this );
				var blogs = $this.val().split(' ');
				for ( var counter=0; counter < blogs.length; counter++)
					$( "#plainview_sdk_form2_inputs_checkboxes_blogs_" + blogs[counter], window.broadcast.$broadcast ).prop( 'checked', true );
				// Select the "no value" option.
				$this.val( '' );

				// If the blog list is closed, then expand and then close again to show the newly selected blogs.
				if ( window.broadcast.$blogs_html.hasClass( 'closed' ) )
					window.broadcast.$show_hide.click().click();
			});

		},

		/**
			Hides all the blogs ... except those that have been selected.
		**/
		hide_blogs : function()
		{
			window.broadcast.$blogs_html.removeClass( 'opened' ).addClass( 'closed' );
			this.$show_hide.html( broadcast_strings.show_all );

			// Hide all those blogs that aren't checked
			this.$broadcast_blogs_htmls.each( function( index, item )
			{
				var $this = $( this );
				var checked = $this.prop( 'checked' );
				// Ignore inputs that are supposed to be hidden.
				if ( $this.prop( 'hidden' ) === true )
					return;
				if ( ! checked )
					$this.parent().hide();
			});
		},

		// Ajaxify the settings page.
		init_settings_page : function()
		{
			this.$settings_form = $( 'form#broadcast_settings' );
			if ( this.$settings_form.length < 1 )
				return;

			// Ajaxify the whitelist / blacklist
			this.$settings_form.$broadcast_internal_fields = $( '#plainview_sdk_form2_inputs_checkbox_broadcast_internal_custom_fields', this.$settings_form );
			this.$settings_form.$blacklist = $( '#plainview_sdk_form2_inputs_textarea_custom_field_blacklist', this.$settings_form );
			this.$settings_form.$whitelist = $( '#plainview_sdk_form2_inputs_textarea_custom_field_whitelist', this.$settings_form );

			// Fade in the respective settings when the internal fields box is clicked.
			this.$settings_form.$broadcast_internal_fields.change( function()
			{
				var checked = $( this ).prop( 'checked' );

				if ( checked )
				{
					window.broadcast.$settings_form.$blacklist.prop( 'readonly', ! checked ).fadeTo( 200, 1.0 );
					window.broadcast.$settings_form.$whitelist.prop( 'readonly', checked ).fadeTo( 200, 0.5 );
				}
				else
				{
					window.broadcast.$settings_form.$blacklist.prop( 'readonly', ! checked ).fadeTo( 200, 0.5 );
					window.broadcast.$settings_form.$whitelist.prop( 'readonly', checked ).fadeTo( 200, 1.0 );
				}
			}).change();
		},

		/**
			Reshows all the hidden blogs.
		**/
		show_blogs : function()
		{
			window.broadcast.$blogs_html.removeClass( 'closed' ).addClass( 'opened' );
			this.$show_hide.html( broadcast_strings.hide_all );
			$.each( this.$broadcast_blogs_htmls, function( index, item )
			{
				var $this = $( this );
				if ( $this.prop( 'hidden' ) === true )
					return;
				$this.parent().show();
			});
		}
	};

	broadcast.init();
	broadcast.init_settings_page();
});
