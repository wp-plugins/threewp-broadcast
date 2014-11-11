jQuery(document).ready( function( $ )
{
	$( 'form#broadcast_settings' ).broadcast_settings();
	$( '#threewp_broadcast.postbox' ).broadcast_postbox();
	$( '#posts-filter' ).broadcast_post_bulk_actions();
	$( '#posts-filter td.3wp_broadcast a.broadcast.post' ).broadcast_post_actions();
} );
