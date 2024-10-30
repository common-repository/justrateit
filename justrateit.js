var JustRateIt = new function() {
	this.init = function() {

		if ('function' == typeof jQuery) {
			jQuery = jQuery;
			jQuery(function() {
				jQuery("body").on("click", ".justrateit a", function(){
					var id = jQuery(this).parent().attr("id").match(/justrateit-id-(.*)/);
					var value = jQuery(this).attr("class").match(/value-([12345])/);
					JustRateIt.vote(id[1], value[1], this);

					return false;
				});
				jQuery("[rel=justrateit-button]").each(function(a,b) {
					var id = jQuery(b).attr("id").match("justrateit-id-(.*)");

					if (!id) return;

					JustRateIt.getButton(id[1], function(data){
						jQuery(b).html(data)
					});
				});
			});
		}
		else 
		{
			// No updates on click if jQuery is missing. :(
		}

	}

	this.getButton = function(id, callback) {
		jQuery.post(ajaxurl, {action:"justrateit_ajax",identifier:id,justrateit:"get-button"}, callback);
	}

	this.vote = function(id, val, button) {
		jQuery = jQuery;

		jQuery.ajax({
			url: ajaxurl, 
			data: {action:"justrateit_ajax",value:val,identifier:id,justrateit:'vote'},
			dataType: 'json',
			type: 'post',
			success: function(data) {

				if ('undefined' == typeof data.error) {
					var count = jQuery(button).siblings(".count").html().match(/\(([0-9]+)\)/);

					if (count.length >= 2) {
						count = parseInt(count[1]);
						jQuery(button).siblings(".count").html("("+(count+1)+")")
					}
				}
				else {
					if (jQuery(button).parent().next().hasClass("error red")) {
						jQuery(button).parent().next().html(data.error);
					}
					else {
						jQuery(button).parent().after(' <span class="error red">'+data.error+'</span>');
					}
				}
			}
		});
	}
}

// Init the js right away
JustRateIt.init();