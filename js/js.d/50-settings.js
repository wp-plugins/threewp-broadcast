/**
	@brief		Ajaxify the settings page.
	@since		2014-11-02 09:47:46
**/
;(function( $ )
{
    $.fn.extend(
    {
        broadcast_settings: function()
        {
            return this.each( function()
            {
                var $this = $(this);

				// Ajaxify the whitelist / blacklist
				$this.$broadcast_internal_fields = $( '#plainview_sdk_broadcast_form2_inputs_checkbox_broadcast_internal_custom_fields', $this );
				$this.$blacklist = $( '#plainview_sdk_broadcast_form2_inputs_textarea_custom_field_blacklist', $this );
				$this.$protectlist = $( '#plainview_sdk_broadcast_form2_inputs_textarea_custom_field_protectlist', $this );
				$this.$whitelist = $( '#plainview_sdk_broadcast_form2_inputs_textarea_custom_field_whitelist', $this );

				// Fade in the respective settings when the internal fields box is clicked.
				$this.$broadcast_internal_fields.change( function()
				{
					var checked = $( this ).prop( 'checked' );

					if ( checked )
					{
						$this.$blacklist.prop( 'readonly', ! checked ).fadeTo( 200, 1.0 );
						$this.$protectlist.prop( 'readonly', ! checked ).fadeTo( 200, 1.0 );
						$this.$whitelist.prop( 'readonly', checked ).fadeTo( 200, 0.5 );
					}
					else
					{
						$this.$blacklist.prop( 'readonly', ! checked ).fadeTo( 200, 0.5 );
						$this.$protectlist.prop( 'readonly', ! checked ).fadeTo( 200, 0.5 );
						$this.$whitelist.prop( 'readonly', checked ).fadeTo( 200, 1.0 );
					}
				} ).change();
            } ); // return this.each( function()
        } // plugin: function()
    } ); // $.fn.extend({
} )( jQuery );
