define([
    'jquery',
    'jquery/validate',
    'mage/translate'
], function($) {
	'use strict';

	return function() {
		
		$.validator.addMethod(
			'max-must-be-greater-than-low',
			function(max, element) {
			    max = parseInt(max);
				var min = parseInt($("#lowRangeInput").val());
				return (max > min) || ((max > 1) && isNaN(min));
			},
			$.mage.__('Max must be greater than Min.')
		);
	};
});