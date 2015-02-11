/**
 * Copyright (C) 2014 Proximis
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */
(function() {
	"use strict";

	var app = angular.module('RbsChange');

	/**
	 * @ngdoc directive
	 * @id RbsChange.directive:rbsDocumentAttributesSection
	 * @name Document attributes panel
	 * @element fieldset
	 * @restrict A
	 *
	 * @description
	 * Used to display the <em>Attributes</em> section in Document editors.
	 *
	 * @example
	 * <pre>
	 *     <fieldset data-rbs-editor-section="attributes"
	 *        data-editor-section-label="{{ i18nAttr('m.rbs.admin.admin.attributes', ['ucf']) }}"
	 *        data-rbs-document-attributes-section="">
	 *     </fieldset>
	 * </pre>
	 */
	app.directive('rbsDocumentAttributesSection', ['RbsChange.REST', '$timeout', function(REST, $timeout) {
		return {
			restrict: 'A',
			templateUrl: 'Rbs/Admin/js/directives/document-attributes-section.twig',
			replace: false,

			link: function(scope, element, attrs) {
				scope.onRestoreContext = function(currentContext) {
					var key = currentContext.valueKey(), value = currentContext.value();
					if (key && key.split('.')[0] == 'attr') {
						scope.attributeContext = {valueKey: key, value: value, model: currentContext.param('model')};
					}
				};

				scope.$watch('document', function(newValue) {
					if (newValue !== undefined) {
						if (!angular.isObject(newValue.typology$)) {
							newValue.typology$ = { id: 0, values: {} };
						}
						else if (!angular.isObject(newValue.typology$.values) || angular.isArray(newValue.typology$.values)) {
							newValue.typology$.values = {};
						}
					}
				});

				scope.$watch('document.typology$.id', function(newValue, oldValue) {
					if (newValue) {
						REST.resource('Rbs_Generic_Typology', newValue).then(scope.generateAttributesEditor);
					}
					else if (oldValue) {
						scope.clearAttributesEditor();
					}
				});

				scope.clearAttributesEditor = function() {
					scope.attributeGroups = [];
					$timeout(function() {
						scope.$emit('Change:Editor:UpdateMenu');
					});
				};

				scope.generateAttributesEditor = function(typology) {
					scope.attributeGroups = [];
					var attributesDefinitions = typology['attributesDefinitions'];
					for (var groupIndex = 0; groupIndex < typology.groups.length; groupIndex++) {
						var group = typology.groups[groupIndex];
						var groupData = {
							id: group.id,
							label: group.label,
							attributes: []
						};

						for (var attributeIndex = 0; attributeIndex < group.attributes.length; attributeIndex++) {
							var attributeDefinition = attributesDefinitions[group.attributes[attributeIndex].id];
							if (!scope.isReferenceLanguage && !attributeDefinition['localized']) {
								continue;
							}

							var key = 'attr_' + attributeDefinition.id;
							attributeDefinition.key = key;
							if (!attributeDefinition) {
								continue;
							}
							groupData.attributes.push(attributeDefinition);

							// Handle default values.
							if (scope.document.typology$.values[key] === undefined) {
								scope.document.typology$.values[key] = attributeDefinition.defaultValue | null;
							}
						}

						if (groupData.attributes.length) {
							scope.attributeGroups.push(groupData);
						}
					}
					$timeout(function() {
						scope.$emit('Change:Editor:UpdateMenu');
						if (scope.attributeContext !== undefined) {
							scope.$broadcast('updateContextValue', scope.attributeContext);
							scope.attributeContext = undefined;
						}
					});
				};
			}
		};
	}]);
})();