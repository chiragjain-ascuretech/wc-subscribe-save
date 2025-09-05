(function($){
	function newRow(index){
		return $(
			'<tr class="wcss-plan-row">\
				<td><input type="text" name="wcss_plans['+index+'][label]"/></td>\
				<td><input type="number" min="1" name="wcss_plans['+index+'][interval_count]" value="1"/></td>\
				<td><select name="wcss_plans['+index+'][interval_unit]"><option value="day">day</option><option value="week" selected>week</option><option value="month">month</option></select></td>\
				<td><select name="wcss_plans['+index+'][discount_type]"><option value="percent" selected>percent</option><option value="fixed">fixed</option></select></td>\
				<td><input type="number" step="0.01" min="0" name="wcss_plans['+index+'][discount_value]" value="10"/></td>\
				<td><button type="button" class="button wcss-remove-plan">Remove</button></td>\
			</tr>'
		);
	}

	$(document).on('click', '#wcss-add-plan', function(){
		var $body = $('#wcss-plans-body');
		var idx = $body.find('tr.wcss-plan-row').length;
		$body.append(newRow(idx));
	});

	$(document).on('click', '.wcss-remove-plan', function(){
		$(this).closest('tr').remove();
		// reindex names to keep sequential indices
		$('#wcss-plans-body').find('tr.wcss-plan-row').each(function(i, tr){
			$(tr).find('input, select').each(function(){
				var name = $(this).attr('name');
				if (!name) return;
				name = name.replace(/wcss_plans\[[0-9]+\]/, 'wcss_plans['+i+']');
				$(this).attr('name', name);
			});
		});
	});
})(jQuery);


