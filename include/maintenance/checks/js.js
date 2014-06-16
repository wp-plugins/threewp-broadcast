<script type="text/javascript">
	var $check;

	function ajaxify_check_html( $html )
	{
		jQuery( '.next_step_link', $html ).hide();
	}

	function broadcast_check_again()
	{
		$ = jQuery;

		// Load the next page.
		$.ajax({
			'type' : 'get',
			'success' : function( data )
			{
				var $data = $( data );

				// If there are results, replace the whole check contents with the results.
				var $results = $( '.threewp_broadcast_check.step_results', $data );
				if ( $results.length > 0 )
				{
					setTimeout( function()
					{
						ajaxify_check_html( $results );
						$check.html( $results.html() );
					}, 1000 );
					return;
				}

				var $new_check = $( '.threewp_broadcast_check', $data );

				if ( $new_check.length > 0 )
				{
					ajaxify_check_html( $new_check );
					$check.append( '<div>' + $new_check.html() + '</div>' );
				}
				else
				{
					$check.append( '<div>Error fetching the next page.</div>' );
				}

				if ( $( '.next_step_link', $new_check ).length < 1 )
					return;

				setTimeout( function(){
					broadcast_check_again();
				}, 500 );
			},
			'url' : window.location
		});
	}

	jQuery(document).ready(function( $ )
	{
		$check = $( '.threewp_broadcast_check' );
		while ( $check.length < 1 )
			return;

		ajaxify_check_html( $check );

		if ( $( '.next_step_link', $check ).length < 1 )
			return;

		setTimeout( function(){
				broadcast_check_again();
		}, 500 );

	})
</script>
