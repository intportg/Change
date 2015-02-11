(function() {
	"use strict";

	function Editor(Models) {
		return {
			restrict: 'A',
			require: '^rbsDocumentEditorBase',

			link: function(scope, elm, attrs, editorCtrl) {
				scope.onReady = function() {
					if (scope.document.documentType) {
						scope.documentTypeLabel = Models.getModelLabel(scope.document.documentType);
					}
				};
			}
		};
	}

	Editor.$inject = ['RbsChange.Models'];
	angular.module('RbsChange').directive('rbsDocumentEditorRbsGenericAttributeNew', Editor);
	angular.module('RbsChange').directive('rbsDocumentEditorRbsGenericAttributeEdit', Editor);
})();