(function() {
	"use strict";

	function Editor(Models) {
		return {
			restrict: 'A',
			require: '^rbsDocumentEditorBase',

			link: function(scope, elm, attrs, editorCtrl) {
				scope.onReady = function() {
					if (scope.document.modelName) {
						scope.modelLabel = Models.getModelLabel(scope.document.modelName);
					}
				};
			}
		};
	}

	Editor.$inject = ['RbsChange.Models'];
	angular.module('RbsChange').directive('rbsDocumentEditorRbsGenericTypologyNew', Editor);
	angular.module('RbsChange').directive('rbsDocumentEditorRbsGenericTypologyEdit', Editor);
})();