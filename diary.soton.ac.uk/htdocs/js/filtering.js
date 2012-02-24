var showCats = function()
{
	$('div.day').show();
	$('div.event').hide();
	$('div.event').each(function(index, value) {
		if($(this).hasClass($('#org').val()) && $(this).hasClass($('#type').val()) && $(this).hasClass($('#place').val()))
		{
			$(this).show();
		}
	});
	$('div.day').each(function(index, value) {
		if($(this).children().filter(':visible').size() == 1)
		{
			$(this).hide();
		}
	});
}
