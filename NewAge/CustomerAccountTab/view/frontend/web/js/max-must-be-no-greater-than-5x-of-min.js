define([
    'jquery',
    'jquery/validate',
    'mage/translate'
], function($) {
	'use strict';
	
	return function() {
	    
		$.validator.addMethod(
			'max-must-be-no-greater-than-5x-of-min',
			function(value, element) {
				var max = parseInt(value);
		        var min = parseInt($("#lowRangeInput").val());
		        min = (min < 0) ? min = 0 : min;
		        var limit = 5*(min);
		        min = (min < 0) ? min = 1 : min;
		        limit = (!limit) ? limit = max : limit;
				return (max <= limit);
			},
			$.mage.__('Max must be no greater than 5x of Min.')
		);
	};
});