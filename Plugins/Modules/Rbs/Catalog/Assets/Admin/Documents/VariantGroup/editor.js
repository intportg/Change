(function() {
	"use strict";

	function Editor(REST, $routeParams, ArrayUtils, $q) {
		return {
			restrict: 'A',
			require: '^rbsDocumentEditorBase',

			link: function(scope, elm, attrs, editorCtrl) {
				scope.Attributes = {axesToAdd: []};

				function initAxesConfiguration() {
					var oldAxesConfiguration = scope.document.axesConfiguration,
						newAxesConfiguration = [],
						axisConfiguration;

					scope.Attributes.axesConfiguration = [];
					angular.forEach(scope.document.axesAttributes, function(attribute) {
						axisConfiguration = null;
						angular.forEach(oldAxesConfiguration, function(conf) {
							if (conf.id == attribute.id) {
								axisConfiguration = conf;
							}
						});
						if (axisConfiguration == null) {
							axisConfiguration = {id: attribute.id, url: false, categorizable: true}
						}
						newAxesConfiguration.push(axisConfiguration);
					});

					// Set url configuration to true for the last.
					if (newAxesConfiguration.length > 0 && newAxesConfiguration[newAxesConfiguration.length - 1].url !== true) {
						newAxesConfiguration[newAxesConfiguration.length - 1].url = true;
					}

					scope.document.axesConfiguration = newAxesConfiguration;

					scope.checkNeedCategorizable();
				}

				scope.initDocument = function() {
					if ($routeParams.hasOwnProperty('variantGroupId')) {
						var docId = $routeParams.variantGroupId;
						if (docId != 'new') {
							return REST.resource('Rbs_Catalog_VariantGroup', parseInt($routeParams.variantGroupId, 10));
						}
						else {
							var deferred = $q.defer();
							var promise = deferred.promise;
							var document = REST.newResource('Rbs_Catalog_VariantGroup');
							deferred.resolve(document);
							return promise;
						}
					}
					return null;
				};

				scope.onLoad = function() {
					if (scope.document.isNew()) {
						scope.document.newSkuOnCreation = true;
					}

					if (!angular.isArray(scope.document.axesAttributes)) {
						scope.document.axesAttributes = [];
						scope.document.axesConfiguration = [];
					}
					if (!angular.isArray(scope.document.othersAttributes)) {
						scope.document.othersAttributes = [];
					}

					initAxesConfiguration();
				};

				scope.onReady = function() {
					if ($routeParams.productId) {
						//Creation : get Product
						REST.resource('Rbs_Catalog_Product', $routeParams.productId).then(function(product) {
							scope.document.rootProduct = product;
						});
					}
				};

				scope.checkNeedCategorizable = function() {
					var middleAxesUrlCounter = 0;
					for (var i = 0; i < scope.document.axesConfiguration.length - 1; i++) {
						var conf = scope.document.axesConfiguration[i];
						if (conf.url === true) {
							conf.categorizable = true;
							middleAxesUrlCounter = middleAxesUrlCounter + 1;
						}
						else {
							conf.categorizable = false;
						}
					}
					angular.forEach(scope.document.axesConfiguration, function(conf) {

					});
					if (scope.document.axesConfiguration.length == 1 || middleAxesUrlCounter < 1) {
						scope.needCategorizable = true;
					}
					else {
						scope.document.axesConfiguration[scope.document.axesConfiguration.length - 1].categorizable = false;
						scope.needCategorizable = false;
					}
				};

				scope.$watchCollection('Attributes.axesToAdd', function(newValue) {
					if (scope.form.hasOwnProperty('axesToAdd')) {
						if (angular.isArray(newValue) && newValue.length > 0) {
							scope.form.axesToAdd.$setValidity("axesToAddLength", false);
						}
						else {
							scope.form.axesToAdd.$setValidity("axesToAddLength", true);
						}
					}
				});

				scope.$watchCollection('document.axesAttributes', function(newValue) {
					if (scope.form.hasOwnProperty('axesAttributesLength')) {
						if (angular.isArray(newValue) && newValue.length > 0) {
							scope.form['axesAttributesLength'].$setValidity("axesAttributesLength", true);
						}
						else {
							scope.form['axesAttributesLength'].$setValidity("axesAttributesLength", false);
						}
					}
				});

				scope.getAxisLabel = function(index) {
					return scope.document.axesAttributes[index].label;
				};

				scope.addAxisAttributesFromPicker = function() {
					angular.forEach(scope.Attributes.axesToAdd, function(axisAttribute) {
						var axisId = axisAttribute.id;
						angular.forEach(scope.document.axesAttributes, function(item) {
							if (item.id == axisId) {
								axisAttribute = null;
							}
						});
						if (axisAttribute != null) {
							scope.document.axesAttributes.push(axisAttribute);
							initAxesConfiguration();
						}
					});
					scope.Attributes.axesToAdd = [];
				};

				scope.moveTop = function(index) {
					var attribute = scope.document.axesAttributes[index];
					if (attribute && index > 0) {
						ArrayUtils.move(scope.document.axesAttributes, index, 0);
						initAxesConfiguration();
					}
				};

				scope.moveUp = function(index) {
					var attribute = scope.document.axesAttributes[index];
					if (attribute && index > 0) {
						ArrayUtils.move(scope.document.axesAttributes, index, index - 1);
						initAxesConfiguration();
					}
				};

				scope.moveBottom = function(index) {
					var attribute = scope.document.axesAttributes[index];
					var lastIndex = scope.document.axesAttributes.length - 1;
					if (attribute && index < lastIndex) {
						ArrayUtils.move(scope.document.axesAttributes, index, lastIndex);
						initAxesConfiguration();
					}
				};

				scope.moveDown = function(index) {
					var attribute = scope.document.axesAttributes[index];
					var lastIndex = scope.document.axesAttributes.length - 1;
					if (attribute && index < lastIndex) {
						ArrayUtils.move(scope.document.axesAttributes, index, index + 1);
						initAxesConfiguration();
					}
				};

				scope.remove = function(index) {
					var attribute = scope.document.axesAttributes[index];
					if (attribute) {
						scope.document.axesAttributes.splice(index, 1);
						initAxesConfiguration();
					}
				};
			}
		};
	}

	Editor.$inject = ['RbsChange.REST', '$routeParams', 'RbsChange.ArrayUtils', '$q'];
	angular.module('RbsChange').directive('rbsDocumentEditorRbsCatalogVariantGroupNew', Editor);
	angular.module('RbsChange').directive('rbsDocumentEditorRbsCatalogVariantGroupEdit', Editor);
})();