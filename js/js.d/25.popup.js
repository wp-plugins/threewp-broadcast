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
