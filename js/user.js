jQuery(document).ready(function($) {
	window.broadcast =
	{
		$arrow : null,
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
				.addClass( 'selection_change_container' )
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
			if ( this.$broadcast_blogs_htmls.length > 5 )
			{
				this.$arrow = $( '<div />' )
					.addClass( 'arrow howto' )
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
					$( "#plainview_form2_inputs_checkboxes_blogs_" + blogs[counter], window.broadcast.$broadcast ).prop( 'checked', true );
				// Select the "no value" option.
				$this.val( '' );
			});

		},

		/**
			Hides all the blogs ... except those that have been selected.
		**/
		hide_blogs : function()
		{
			window.broadcast.$blogs_html.removeClass( 'opened' ).addClass( 'closed' );
			this.$arrow.html( broadcast_strings.show_all );


			// Hide all those blogs that aren't checked
			this.$broadcast_blogs_htmls.each( function( index, item )
			{
				var $this = $( this );
				var checked = $this.prop( 'checked' );
				if ( ! checked )
					$this.parent().hide();
			});
		},

		/**
			Reshows all the hidden blogs.
		**/
		show_blogs : function()
		{
			window.broadcast.$blogs_html.removeClass( 'closed' ).addClass( 'opened' );
			this.$arrow.html( broadcast_strings.hide_all );
			this.$broadcast_blogs_htmls.parent().show();
		}
	};

	broadcast.init();
});
