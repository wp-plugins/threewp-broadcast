jQuery(document).ready(function($) {
	window.broadcast = {
		init: function(){
			
			// Allow blogs to be mass selected, unselected.							
			$("#__broadcast__broadcast_group").change(function(){
				var blogs = $(this).val().split(" ");
				for (var counter=0; counter < blogs.length; counter++)
				{
					$("#__broadcast__groups__666__" + blogs[counter] ).attr("checked", true);
				}
				$("#__broadcast_group").val("");
			});
			
			// React to changes to the tags click.
			if ( !$("#__broadcast__tags").attr("checked") )
				$("p.broadcast_input_tags_create").hide();
				
			$("#__broadcast__tags").change(function(){
				$("p.broadcast_input_tags_create").animate({
					height: "toggle"
				});
			});
			
			// React to changes to the taxonomies click.
			if ( !$("#__broadcast__taxonomies").attr("checked") )
				$("p.broadcast_input_taxonomies_create").hide();
				
			$("#__broadcast__taxonomies").change(function(){
				$("p.broadcast_input_taxonomies_create").animate({
					height: "toggle"
				});
			});
			
		}
	};
	
	broadcast.init();

	$broadcast = $("#threewp_broadcast");

	
	var blog_count = $( ".blogs ul.broadcast_blogs li", $broadcast ).length;
	
	
	if ( $( "input", $broadcast ).length < 1 )
		return;
	
	// Select all / none
	$broadcast.append(" \
		<p class=\"selection_change select_deselect_all\">" + broadcast_strings.select_deselect_all + "</p> \
		<p class=\"selection_change invert_selection\">" + broadcast_strings.invert_selection + "</p>");

	$(".select_deselect_all").click(function(){
		var checkedStatus = ! $("#threewp_broadcast .broadcast_blogs .checkbox").attr("checked");
		$("#threewp_broadcast .broadcast_blogs .checkbox").each(function(key, value){
			if ( $(value).attr("disabled") != true)
				$(value).attr("checked", checkedStatus);
		});
	})

	$("#threewp_broadcast .invert_selection").click( function(){
			$.each( $(".broadcast_blogs input"), function(index, item){
					$(item).attr("checked", ! $(item).attr("checked") ); 
			});
	});
	
	// Need to hide the blog list?
	if ( blog_count > 5 )
	{
		$("#threewp_broadcast .broadcast_to").addClass("broadcast_to_opened").append("<div class=\"arrow_container\"><div class=\"arrow\"></div></div>");
		
		close_broadcasted_blogs( $("#threewp_broadcast .broadcast_to") );
		
		// Make it switchable!
		$("#threewp_broadcast .broadcast_to .arrow_container").click(function(){
			if ( $(this).parent().hasClass("broadcast_to_opened") )
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
		$(item).removeClass("broadcast_to_opened");
		$(item).addClass("broadcast_to_closed");

		// Copy all selected blogs to the activated list
		$.each( $("#threewp_broadcast .blogs ul.broadcast_blogs li"), function (index,item){
			var checked = $("input", item).attr("checked");
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
		$(item).removeClass("broadcast_to_closed");
		$(item).addClass("broadcast_to_opened");
		
		$.each( $("#threewp_broadcast .blogs ul.broadcast_blogs li"), function(index, item){
			$(item).show();
		});
	}

});
