/**
	@brief		Offer a popup SDK, based on Magnific.
	@since		2014-11-02 10:25:38
**/
broadcast_popup = function( options )
{
	$ = jQuery;

	this.$popup = undefined;
	this.html = '';
	this.title = '';
	this.options = options;

	/**
		@brief		Close the popup.
		@since		2014-11-02 11:06:07
	**/
	this.close = function()
	{
		$.magnificPopup.instance.close();
		return this;
	}

	/**
		@brief		Create the div.
		@since		2014-11-02 11:03:43
	**/
	this.create_div = function()
	{
		$( '.broadcast_popup' ).remove();
		this.$popup = $( '<div>' )
			.addClass( 'mfp-hide broadcast_popup' )
			.appendTo( $( 'body' ) );
		return this;
	}

	/**
		@brief		Open the popup.
		@since		2014-11-02 11:03:33
	**/
	this.open = function()
	{
		this.update();

		options = $.extend( this.options,
		{
			'items' :
			{
				'overflowY' : 'scroll',
				'src' : this.$popup,
				'type' : 'inline'
			}
		}
		);

		$.magnificPopup.open( options );
		return this;
	}

	/**
		@brief		Convenience function to set the popup's HTML.
		@since		2014-11-02 11:10:15
	**/
	this.set_html = function( html )
	{
		this.html = html;
		this.update();
		return this;
	}

	/**
		@brief		Set a header 1 for the popup.
		@since		2014-11-02 14:52:37
	**/
	this.set_title = function( title )
	{
		this.title = title;
		this.update();
		return this;
	}

	/**
		@brief		Update the contents of the popup.
		@since		2014-11-02 15:35:09
	**/
	this.update = function()
	{
		this.$popup.html( '<h1>' + this.title + '</h1>' + this.html );
		return this;
	}

	this.create_div();
	return this;
}
;
/**
	@brief		Subclass for handling of post bulk actions.
	@since		2014-10-31 23:15:10
**/
;(function( $ )
{
    $.fn.extend(
    {
        broadcast_post_actions: function()
        {
            return this.each( function()
            {
                var $this = $( this );

				// Don't add bulk post options several times.
				if( $this.data( 'broadcast_post_actions' ) !== undefined )
					return;
				$this.data( 'broadcast_post_actions', true )

                $this.submitted = false;

                $this.unbind( 'click' );

                $this.click( function()
                {
                	// Get the post ID.
                	$tr = $this.parentsUntil( 'tbody#the-list' ).last();
                	var id = $tr.prop( 'id' ).replace( 'post-', '' );

                	$this.$popup = broadcast_popup({
                			'callbacks' : {
                				'close' : function()
                				{
                					if ( ! $this.submitted )
                						return;
                					// Reload the page by submitting the filter.
									$( '#post-query-submit' ).click();
                				}
                			},
                		})
						.set_title( broadcast_strings.post_actions )
						.open();

					$this.fetch_form( {
						'action' : 'broadcast_post_action_form',
						'nonce' : $this.data( 'nonce' ),
						'post_id' : id,
					} );
                } );

                $this.display_form = function( json )
                {
					$this.$popup.set_html( json.html );

					// Take over the submit button.
					var $form = $( '#broadcast_post_action_form' );
					$( 'input.submit', $form ).click( function()
 					{
 						$this.submitted = true;
						// Assemble the form.
						$this.fetch_form( $form.serialize() + '&submit=submit' );
						return false;
					} );
                }

                /**
                	@brief		Fetch the form via ajax.
                	@since		2014-11-02 22:24:07
                **/
                $this.fetch_form = function( data )
                {
					$this.$popup.set_html( 'Loading...' );

                	// Fetch the post link editor.
                	$.ajax( {
                		'data' : data,
                		"dataType" : "json",
                		'type' : 'post',
                		'url' : ajaxurl,
                	} )
                	.done( function( data )
                	{
                		$this.display_form( data );
                	} )
					.fail( function( jqXHR )
					{
						$this.$popup
							.set_html( jqXHR.responseText )
							.set_title( 'Ajax error' );
					} );
                }
            }); // return this.each( function()
        } // plugin: function()
    }); // $.fn.extend({
} )( jQuery );
;
/**
	@brief		Handles the postbox (meta box).
	@since		2014-11-02 09:54:16
**/
;(function( $ )
{
    $.fn.extend(
    {
        broadcast_postbox: function()
        {
            return this.each( function()
            {
                var $this = $(this);

				var $blogs_container;
				var $blog_inputs;
				var $invert_selection;
				var $select_all;
				var $selection_change_container;
				var $show_hide;

				/**
					Hides all the blogs ... except those that have been selected.
				**/
				$this.hide_blogs = function()
				{
					$this.$blogs_container.removeClass( 'opened' ).addClass( 'closed' );
					$this.$show_hide.html( broadcast_strings.show_all );

					// Hide all those blogs that aren't checked
					$this.$blog_inputs.each( function( index, item )
					{
						var $input = $( this );
						var checked = $input.prop( 'checked' );
						// Ignore inputs that are supposed to be hidden.
						if ( $input.prop( 'hidden' ) === true )
							return;
						if ( ! checked )
							$input.parent().hide();
					} );
				},

				/**
					Reshows all the hidden blogs.
				**/
				$this.show_blogs = function()
				{
					this.$blogs_container.removeClass( 'closed' ).addClass( 'opened' );
					this.$show_hide.html( broadcast_strings.hide_all );
					$.each( $this.$blog_inputs, function( index, item )
					{
						var $input = $( this );
						if ( $input.prop( 'hidden' ) === true )
							return;
						$input.parent().show();
					} );
				}

				// If the box doesn't contain any input information, do nothing.
				if ( $( 'input', $this ).length < 1 )
					return;

				$this.$blogs_container = $( '.blogs.html_section', $this );

				// If there is no blogs selector, then there is nothing to do here.
				if ( $this.$blogs_container.length < 1 )
					return;

				$this.$blog_inputs = $( 'input.checkbox', $this.$blogs_container );

				// Container for selection change.
				$this.$selection_change_container = $( '<div />' )
					.addClass( 'clear selection_change_container howto' )
					.appendTo( $this.$blogs_container );

				// Append "Select all / none" text.
				$this.$select_all = $( '<span />' )
					.addClass( 'selection_change select_deselect_all' )
					.click(function()
					{
						var checkedStatus = ! $this.$blog_inputs.first().prop( 'checked' );
						$this.$blog_inputs.each( function(index, item)
						{
							var $item = $( item );
							// Only change the status of the blogs that aren't disabled.
							if ( $item.prop( 'disabled' ) != true )
								$item.prop( 'checked', checkedStatus );
						} );
					})
					.html( broadcast_strings.select_deselect_all )
					.appendTo( $this.$selection_change_container );

				$this.$selection_change_container.append( '&emsp;' );

				$this.$invert_selection = $( '<span />' )
					.click( function()
					{
						$this.$blog_inputs.each( function(index, item)
						{
							var $item = $( item );
							var checked = $item.prop( 'checked' );
							$item.prop( 'checked', ! checked );
						} );
					})
					.addClass( 'selection_change invert_selection' )
					.text( broadcast_strings.invert_selection )
					.appendTo( $this.$selection_change_container );

				// Need to hide the blog list?
				try
				{
					if ( broadcast_blogs_to_hide )
						true;
				}
				catch( e )
				{
					broadcast_blogs_to_hide = 5;
				}

				if ( $this.$blog_inputs.length > broadcast_blogs_to_hide )
				{
					$this.$show_hide = $( '<div />' )
						.addClass( 'show_hide howto' )
						.appendTo( $this.$blogs_container )
						.click( function()
						{
							if ( $this.$blogs_container.hasClass( 'opened' ) )
								$this.hide_blogs();
							else
								$this.show_blogs();
						} );

					$this.hide_blogs();
				}

				// GROUP functionality: Allow blogs to be mass selected, unselected.
				var $parent = $this;
				$( ".blog_groups select", $this ).change(function()
				{
					var $groups = $( this );
					var blogs = $groups.val().split(' ');
					for ( var counter=0; counter < blogs.length; counter++)
					{
						var $blog = $( "#plainview_sdk_broadcast_form2_inputs_checkboxes_blogs_" + blogs[counter], $this.$blogs_container );
						// Switch selection.
						if ( $blog.prop( 'checked' ) )
							$blog.prop( 'checked', false );
						else
							$blog.prop( 'checked', true );
					}

					// If the blog list is closed, then expand and then close again to show the newly selected blogs.
					if ( $this.$blogs_container.hasClass( 'closed' ) )
						$this.$show_hide.click().click();
				} ).change();
            } ); // return this.each( function()
        } // plugin: function()
    } ); // $.fn.extend({
} )( jQuery );
;
/**
	@brief		Subclass for handling of post bulk actions.
	@since		2014-10-31 23:15:10
**/
;(function( $ )
{
    $.fn.extend(
    {
        broadcast_post_bulk_actions: function()
        {
            return this.each( function()
            {
                var $this = $( this );

                /**
					@brief		Mark the bulkactions section as busy.
					@since		2014-11-01 23:43:52
				**/
				$this.busy = function( busy )
				{
					if ( busy )
						$( '.bulkactions' ).fadeTo( 250, 0.5 );
					else
						$( '.bulkactions' ).fadeTo( 250, 1 );
				}

				/**
					@brief		Return a string with all of the selected post IDs.
					@since		2014-10-31 23:15:48
				**/
				$this.get_ids = function()
				{
					var post_ids = [];
					// Get all selected rows
					var $inputs = $( '#posts-filter tbody#the-list th.check-column input:checked' );
					$.each( $inputs, function( index, item )
					{
						var $item = $( item );
						var $row = $( item ).parentsUntil( 'tr' ).parent();
						// Add it
						var id = $row.prop( 'id' ).replace( 'post-', '' );
						post_ids.push( id );
					} );
					return post_ids.join( ',' );
				}

				if ( typeof broadcast_bulk_post_actions === "undefined" )
					return;

				// Don't add bulk post options several times.
				if( $this.data( 'broadcast_post_bulk_actions' ) !== undefined )
					return;
				$this.data( 'broadcast_post_bulk_actions', true )

				// Begin by adding the broadcast optgroup.
				var $select = $( '.bulkactions select' );
				var $optgroup = $( '<optgroup>' );

				$.each( broadcast_bulk_post_actions, function( index, item )
				{
					var $option = $( '<option>' );
					$option.html( item.name );
					$option.prop( 'value', index );
					$option.addClass( 'broadcast' );
					$option.appendTo( $optgroup );
				} );

				// We appendTo here because otherwise it is only put in one place.
				$optgroup.prop( 'label', broadcast_strings.broadcast );
				$optgroup.appendTo( $select );

				// Take over the apply buttons
				$( '.button.action' )
				.click( function()
				{
					// What is the current selection?
					var $container = $( this ).parent();
					var $select = $( 'select', $container );

					var $selected = $( 'option:selected', $select );

					// Not a broadcast bulk post action = allow the button to work normally.
					if ( ! $selected.hasClass( 'broadcast' ) )
						return true;

					// Has the user selected any posts?
					var post_ids = $this.get_ids();
					if ( post_ids == '' )
					{
						broadcast_popup()
							.set_title( 'No posts selected' )
							.set_html( 'Please select at least one post to use the Broadcast bulk actions.' )
							.open();
						return false;
					}

					// Retrieve the action.
					var value = $selected.prop( 'value' );
					var action = broadcast_bulk_post_actions[ value ];
					// Use the callback.
					$this.busy( true );
					action.callback( $this );
					return false;
				} );

            }); // return this.each( function()
        } // plugin: function()
    }); // $.fn.extend({
} )( jQuery );
;
jQuery(document).ready( function( $ )
{
	$( '#threewp_broadcast.postbox' ).broadcast_postbox();
	$( '#posts-filter' ).broadcast_post_bulk_actions();
	$( '#posts-filter td.3wp_broadcast a.broadcast.post' ).broadcast_post_actions();
} );
;
