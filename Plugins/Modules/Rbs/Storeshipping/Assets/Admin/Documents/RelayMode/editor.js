(function() {
	"use strict";

	function rbsDocumentEditorRbsStoreshippingRelayMode() {
		return {
			restrict: 'A',
			require: '^rbsDocumentEditorBase',

			link: function(scope, element, attrs, editorCtrl) {
				scope.addVariable = function(property, variable) {
					if (scope.document[property]) {
						scope.document[property] += '{' + variable + '}';
					}
					else {
						scope.document[property] = '{' + variable + '}';
					}
				};

				scope.onLoad = function() {
					if (!angular.isObject(scope.document.cartFilterData) || angular.isArray(scope.document.cartFilterData)) {
						scope.document.cartFilterData = {};
					}
				};
			}
		}
	}

	angular.module('RbsChange').directive('rbsDocumentEditorRbsStoreshippingRelayModeNew', rbsDocumentEditorRbsStoreshippingRelayMode);
	angular.module('RbsChange').directive('rbsDocumentEditorRbsStoreshippingRelayModeEdit', rbsDocumentEditorRbsStoreshippingRelayMode);
})();