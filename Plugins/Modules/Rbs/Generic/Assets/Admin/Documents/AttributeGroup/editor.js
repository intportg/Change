(function() {
	"use strict";

	function rbsGenericAttributeGroupEditor() {
		return {
			restrict: 'A',
			link: function(scope, element, attrs) {
				scope.$watch('document.label', function (label, oldLabel) {
					var d = scope.document, LCID = scope.localization.currentLCID();
					if (d && LCID) {
						if (d.LCID && d.LCID[LCID] && d.LCID[LCID].title == oldLabel) {
							d.LCID[LCID].title = label;
						}
					}
				});
			}
		};
	}
	angular.module('RbsChange').directive('rbsGenericAttributeGroupEditor', rbsGenericAttributeGroupEditor);
})();