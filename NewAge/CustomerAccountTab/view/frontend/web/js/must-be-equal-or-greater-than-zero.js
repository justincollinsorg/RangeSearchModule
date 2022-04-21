define([
    'jquery',
    'jquery/validate',
    'mage/translate'
], function($) {
	'use strict';

	return function() {
		$.validator.addMethod(
			'must-be-equal-or-greater-than-zero',
			function(value, element) {
			    var min = parseInt(value);
			    var max = parseInt($("#highRangeInput").val());
				return (min >= 0) || (max > 0);
			},
			$.mage.__('Must be greater than 0')
		);
	};
});

