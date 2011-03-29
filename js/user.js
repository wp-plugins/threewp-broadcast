jQuery(document).ready(function($) {
	var blog_count = $("#threewp_broadcast .blogs ul.broadcast_blogs li").length;
	
	// Need to hide the blog list?
	if ( blog_count > 5 )
	{
		$("#threewp_broadcast .broadcast_to").addClass('broadcast_to_opened').append('<div class="arrow_container"><div class="arrow"></div></div>');
		
		close_broadcasted_blogs( $("#threewp_broadcast .broadcast_to") );
		
		// Make it switchable!
		$("#threewp_broadcast .broadcast_to .arrow_container").click(function(){
			if ( $(this).parent().hasClass('broadcast_to_opened') )
				close_broadcasted_blogs( $(this).parent() );
			else
				open_broadcasted_blogs( $(this).parent() );
		});
	}
	
	/**
		Hides all the blogs ... except those that have been selected.
	**/
	function close_broadcasted_blogs( item )
	{
		// Close it up!
		$(item).removeClass('broadcast_to_opened');
		$(item).addClass('broadcast_to_closed');

		// Copy all selected blogs to the activated list
		$.each( $("#threewp_broadcast .blogs ul.broadcast_blogs li"), function (index,item){
			var checked = $('input', item).attr('checked');
			if ( ! checked )
				$(item).hide();
		}); 
	}
	
	/**
		Reshows all the hidden blogs.
	**/
	function open_broadcasted_blogs( item )
	{
		// Open it up!
		$(item).removeClass('broadcast_to_closed');
		$(item).addClass('broadcast_to_opened');
		
		$.each( $("#threewp_broadcast .blogs ul.broadcast_blogs li"), function(index, item){
			$(item).show();
		});
	}
});
