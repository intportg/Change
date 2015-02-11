/**
 * Copyright (C) 2014 Ready Business System
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */
(function($) {
	"use strict";

	var app = angular.module('RbsChange'),
		CORRECTION_CSS_CLASS = 'correction',
		editorSectionDirective;

	/**
	 * Checks whether the given `obj` is a Promise or not.
	 * @param obj
	 * @returns {*}
	 */
	function isPromise(obj) {
		return angular.isObject(obj) && angular.isFunction(obj.then);
	}

	/**
	 * @ngdoc directive
	 * @id RbsChange.directive:rbsDocumentEditorBase
	 * @name Document editor (base controller)
	 * @scope
	 * @restrict A
	 *
	 * @description
	 * Base directive for Document editors.
	 *
	 * ### Public API of the Directive's Controller ###
	 * (these methods can be called from the child Directives that require this one):
	 *
	 * - `prepareContext()`
	 * - `submit()`
	 * - `prepareCreation()`
	 * - `prepareEdition()`
	 * - `clearInvalidFields()`
	 * - `getCurrentSection()`
	 * - `addMenuEntry()`
	 * - `getMenuEntries()`
	 * - `getProperties()`
	 * - `addHeaderMessage()`
	 * - `getDocumentModelName()`
	 *
	 * ### Editor's Scope ###
	 *
	 * #### Methods ####
	 *
	 * - `isUnchanged()`
	 * - `reset()`
	 * - `submit()`
	 * - `hasStatus()`
	 * - `hasCorrection()`
	 *
	 * #### Properties ####
	 *
	 * - `document`: the edited Document
	 * - `original`: copy of the edited Document, used as a reference to check the changes
	 * - `changes`: array containing the name of the modified properties
	 * - `modelInfo`: object containing Model's information
	 *
	 * @param {String} model The full model name of the Documents that can be edited in this editor.
	 */
	function editorDirective($rootScope, $routeParams, $q, $location, $compile, EditorManager, Utils, ArrayUtils, i18n, REST,
		Events, Settings, NotificationCenter, Navigation, ErrorFormatter, UrlManager, Breadcrumb) {
		return {
			restrict: 'A',
			scope: true,

			controller: ['$scope', '$element', function($scope, $element) {
				var initializedSections = {},
					hasContextData = false,
					menuEntries = [],
					properties = {},
					createNewDocumentId,
					modelInfoPromise,
					editorUrl,
					documentReady = false,
					shouldSaveLocalCopy = true;

				//-----------------------------------------------//
				//                                               //
				// Scope methods and properties                  //
				//                                               //
				//-----------------------------------------------//

				$scope.document = {};
				$scope.changes = [];

				$scope.isUnchanged = isUnchanged;
				$scope.reset = reset;
				$scope.submit = submit;
				$scope.hasStatus = hasStatus;
				$scope.modelName = getDocumentModelName();

				$scope.navigationContext = Navigation.getCurrentContext();
				var lastLocationChangeSuccessUrl = $location.absUrl();

				$scope.$on('$locationChangeSuccess', function(event) {
					var url = $location.absUrl();
					if (lastLocationChangeSuccessUrl != url) {
						$scope.navigationContext = Navigation.getCurrentContext();
						lastLocationChangeSuccessUrl = url;
					}
				});

				// Load Model's information.
				modelInfoPromise = REST.modelInfo(getDocumentModelName());
				modelInfoPromise.then(function(modelInfo) {
					$scope.modelInfo = modelInfo;
					delete $scope.modelInfo.links;
				});

				$scope.canGoBack = function() {
					return $scope.isUnchanged();
				};

				$scope.goBack = function(event) {
					if ($scope.navigationContext) {
						$scope.rejectNavigationContext();
					}
					else {
						if (angular.isFunction($scope.onCancel)) {
							$scope.onCancel(event);
						}
					}
				};

				$scope.hasCorrection = function() {
					return Utils.hasCorrection($scope.document);
				};

				$scope.onCancel = function() {
					Breadcrumb.goParent();
				};

				$scope.rejectNavigationContext = function() {
					Navigation.setSelectionContextValue();
				};

				$scope.isDocumentReady = function() {
					return documentReady;
				};

				$scope.isPropertyModified = function(propertyName) {
					return angular.isArray($scope.changes) && $scope.changes.indexOf(propertyName) !== -1;
				};

				//-----------------------------------------------//
				//                                               //
				// Context and Navigation methods                //
				//                                               //
				//-----------------------------------------------//

				function getContextData() {
					var currentContext = $scope.navigationContext, data;

					if (currentContext) {
						data = currentContext.savedData('editor_' + getDocumentModelName());
						if (angular.isObject(data) && data.hasOwnProperty('document')) {
							hasContextData = true;
							return data;
						}
					}
					return null;
				}

				function prepareContext() {
					var document,
						contextData = getContextData(),
						currentContext,
						cascadeKey,
						splitKey;

					if (contextData) {
						currentContext = Navigation.getCurrentContext();
						document = contextData.document;
						if (angular.isFunction($scope.onRestoreContext)) {
							$scope.onRestoreContext(currentContext);
						}
						cascadeKey = currentContext.valueKey();
						if (cascadeKey) {
							splitKey = cascadeKey.split('.');
							if (splitKey.length === 2 && splitKey[0] === 'editor') {
								var propertyName = splitKey[1];
								var v = currentContext.value();
								if (v !== undefined) //go back has undefined value
								{
									if (angular.isArray(v)) {
										if (!angular.isArray(document[propertyName])) {
											document[propertyName] = [];
										}
										angular.forEach(v, function(doc) {
											document[propertyName].push(doc);
										});
									}
									else {
										document[propertyName] = v;
									}
								}
							}
						}

						if (document.isNew()) {
							prepareCreation(document);
						}
						else {
							prepareEdition(document);
						}

						// Done by picker.
						//Navigation.popContext(currentContext);
						return document;
					}
					return null;
				}

				//-----------------------------------------------//
				//                                               //
				// Save process                                  //
				//                                               //
				//-----------------------------------------------//

				$scope.saveProgress = {
					"running": false,
					"error": false,
					"success": false,
					"operation": null
				};

				function saveOperation(op) {
					switch (op) {
						case 'error':
							$scope.saveProgress.running = false;
							$scope.saveProgress.error = true;
							$scope.saveProgress.operation = null;
							$scope.saveProgress.success = !$scope.saveProgress.error;
							break;

						case 'success':
							$scope.saveProgress.running = false;
							$scope.saveProgress.error = false;
							$scope.saveProgress.operation = null;
							$scope.saveProgress.success = !$scope.saveProgress.error;
							break;

						default :
							$scope.saveProgress.running = true;
							$scope.saveProgress.error = false;
							$scope.saveProgress.success = false;
							$scope.saveProgress.operation = op;
					}
				}

				/**
				 * Sends the changes to the server, via a POST (creation) or a PUT (update) request.
				 * @public
				 */
				function submit() {
					var promises = [];
					$scope.$broadcast('Change:EditorPreSubmit', $scope.document, promises);

					if (promises.length) {
						saveOperation("Checking Correction");
						$q.all(promises).then(doSubmit, cancelSubmit);
					}
					else {
						doSubmit();
					}
				}

				function cancelSubmit() {
					saveOperation('success');
				}

				/**
				 * Submits the changes to the server.
				 * If there are files to upload, they will be processed before the document is really saved.
				 */
				function doSubmit() {
					var preSavePromises = [],
						promise;

					// Check for files to upload...
					if ($element) {
						$element.find('rbs-uploader,[rbs-uploader]').each(function() {
							var scope = angular.element($(this)).scope();
							if (angular.isFunction(scope.upload)) {
								if (isPromise(promise = scope.upload())) {
									preSavePromises.push(promise);
								}
							}
							else {
								throw new Error("Could not find 'upload()' method in rbsUploader's scope.");
							}
						});
					}

					// Call 'preSave' if present in the Scope: it should return null or a Promise.
					if (angular.isFunction($scope.preSave)) {
						if (isPromise(promise = $scope.preSave($scope.document))) {
							preSavePromises.push(promise);
						}
					}

					// Broadcast an event before the document is saved.
					// The "promises" array can be filled in with promises that will be resolved BEFORE
					// the document is saved.
					saveOperation("Processing pre-save Promises");
					$rootScope.$broadcast(
						Events.EditorPreSave,
						{
							"document": $scope.document,
							"promises": preSavePromises
						}
					);

					if (preSavePromises.length) {
						$q.all(preSavePromises).then(
							// Success
							executeSaveAction,
							// Error
							function(err) {
								saveOperation("error");
								console.warn("Editor: pre-save Promises error: ", err);
								NotificationCenter.error(i18n.trans('m.rbs.admin.adminjs.save_error'),
									ErrorFormatter.format(err), 'EDITOR');
							}
						);
					}
					else {
						executeSaveAction();
					}
				}

				/**
				 * Sends the POST/PUT request to the server and dispatches response to the callbacks.
				 */
				function executeSaveAction() {
					saveOperation("Saving Document");
					var pList = $scope.changes;
					if (!ArrayUtils.inArray('documentVersion', pList)) {
						pList.push('documentVersion');
					}
					REST.save($scope.document, pList).then(saveSuccessHandler, saveErrorHandler);
				}

				function saveSuccessHandler(doc) {
					var postSavePromises = [],
						result;

					if (!doc.META$.tags) {
						doc.META$.tags = [];
					}
					angular.extend(doc.META$.tags, $scope.document.META$.tags);

					clearInvalidFields();

					// Call 'postSave' if present in the Scope: it should return null or a Promise.
					if (angular.isFunction($scope.postSave)) {
						result = $scope.postSave($scope.document);
						if (isPromise(result)) {
							postSavePromises.push(result);
						}
					}

					// Broadcast an event after the document has been successfully saved.
					// The "promises" array can be filled in with promises that will be resolved AFTER
					// the document is saved.
					saveOperation("Processing post-save Promises");
					$rootScope.$broadcast(Events.EditorPostSave, {
						"document": doc,
						"promises": postSavePromises
					});

					$scope.original = angular.copy(doc);
					$scope.reset();
					EditorManager.removeLocalCopy(doc);

					function terminateSave() {
						saveOperation("success");
						$rootScope.$broadcast('Change:DocumentSaved', doc);

						if (createNewDocumentId) {
							EditorManager.removeCreationLocalCopy(doc, createNewDocumentId);

							var context = Navigation.getCurrentContext(),
								edtKey,
								model,
								propertyType;

							if (context) {
								edtKey = context.valueKey();
								if (edtKey && edtKey.split('.')[0] === 'editor') {
									model = context.param('model');
									propertyType = context.param('propertyType');
									if (model === doc.model) {
										if (propertyType === 'DocumentArray') {
											context.value([doc]);
										}
										else if (propertyType === 'Document') {
											context.value(doc);
										}
										else if (propertyType === 'DocumentId') {
											context.value(doc.id);
										}
									}
								}
							}
						}

						if (angular.isFunction($scope.terminateSave)) {
							$scope.terminateSave(doc);
						}
						else if (angular.isFunction($scope.onReload)) {
							$scope.onReload($scope.document);
						}
					}

					if (postSavePromises.length) {
						$q.all(postSavePromises).then(terminateSave);
					}
					else {
						terminateSave();
					}
				}

				function saveErrorHandler(reason) {
					saveOperation("error");
					NotificationCenter.error(
						i18n.trans('m.rbs.admin.adminjs.save_error'),
						ErrorFormatter.format(reason),
						'EDITOR',
						{
							$propertyInfoProvider: $scope._chgFieldsInfo
						});

					if (angular.isObject(reason) && angular.isObject(reason.data)) {
						if (angular.isObject(reason.data['properties-errors'])) {
							angular.forEach(reason.data['properties-errors'], function(messages, propertyName) {
								markFieldAsInvalid(propertyName, messages);
							});
						}
						else if (reason.code === "INVALID-VALUE-TYPE") {
							var propertyName = reason.data.name;
							markFieldAsInvalid(propertyName, reason.message);
						}
					}
				}

				//-----------------------------------------------//
				//                                               //
				// Menu, sections and fields management          //
				//                                               //
				//-----------------------------------------------//

				/**
				 * Returns the section entry for the given named field.
				 * @param fieldName
				 * @returns {*}
				 */
				function getSectionOfField(fieldName) {
					var result = null;
					angular.forEach(menuEntries, function(entry) {
						if (angular.isArray(entry.fields)) {
							angular.forEach(entry.fields, function(field) {
								if (field.id === fieldName) {
									result = entry;
								}
							});
						}
					});
					return result;
				}

				/**
				 * Marks the given named field as invalid in this editor.
				 * @param fieldName
				 */
				function markFieldAsInvalid(fieldName) {
					$element.find('.form-group[property="' + fieldName + '"]').addClass('error')
						.find('.controls :input').first().focus();
					var section = getSectionOfField(fieldName);
					if (section) {
						section.invalid.push(fieldName);
					}
					else {
						console.log('RbsDocumentEditor.markFieldAsInvalid()', 'Section not found for field: ' + fieldName);
					}
				}

				/**
				 * Removes all invalid fields markers.
				 */
				function clearInvalidFields() {
					$element.find('.form-group.property.error').removeClass('error');
					angular.forEach(menuEntries, function(entry) {
						ArrayUtils.clear(entry.invalid);
					});
				}

				/**
				 * Call `scope.initSection(sectionName)` once per section to do some initialization for the given
				 * section.
				 *
				 * Implement the `initSection()` in your Editor's Scope to initialize the section given as argument.
				 * `initSection()` will be called only once for each section, when the user first switches to it.
				 *
				 * @param section
				 */
				function initSectionOnce(section) {
					if (!initializedSections[section] && angular.isFunction($scope.initSection)) {
						$scope.initSection(section);
						initializedSections[section] = true;
					}
				}

				/**
				 * Returns the form section currently displayed.
				 * @returns {string}
				 */
				function getCurrentSection() {
					return $routeParams.section || $location.search()['section'] || '';
				}

				$scope.$on('$routeUpdate', function() {
					var s = getCurrentSection();
					if (s !== $scope.section) {
						$scope.section = s;
						$rootScope.$broadcast('Change:EditorSectionChanged', $scope.section);
					}
				});

				// Watch for section changes to initialize them if needed.
				$scope.$watch('section', function(section, previousSection) {
					if (section !== undefined && section !== null) {
						initSectionOnce(section);
					}

					if (angular.isDefined(previousSection) && previousSection !== section &&
						angular.isFunction($scope.leaveSection)) {
						$scope.leaveSection(previousSection);
					}
					if (angular.isDefined(section) && angular.isFunction($scope.enterSection)) {
						$scope.enterSection(section);
					}
				});

				/**
				 * Adds an entry in the menu structure for this editor.
				 * @param entry
				 */
				function addMenuEntry(entry) {
					menuEntries.push(entry);
					angular.forEach(entry.fields, function(f) {
						properties[f.id] = f.label;
					});
					$scope.$emit('Change:UpdateEditorMenu', menuEntries);
				}

				/**
				 * Returns currently registered menu entries for this editor.
				 * @returns {Array}
				 */
				function getMenuEntries() {
					return menuEntries;
				}

				$scope.$on('Change:Editor:UpdateMenu', function() {
					// 1) Clear all menu entries.
					menuEntries.length = 0;
					properties = {};
					// 2) Ask every section to update itself with the fields it contains.
					$scope.$broadcast('Change:Editor:SectionsUpdateMenu');
					// 3) Tell the aside to update.
					$scope.$emit('Change:UpdateEditorMenu', menuEntries);
				});

				//-----------------------------------------------//
				//                                               //
				// Document management                           //
				//                                               //
				//-----------------------------------------------//

				/**
				 * Prepares the Editor for the edition of the given `doc`.
				 * @param doc
				 */
				function prepareEdition(doc) {
					$scope.document = doc;
					$scope.isReferenceLanguage = ($scope.document.refLCID === $scope.document.LCID);
					$scope.isLocalized = angular.isDefined($scope.document.refLCID);
					$scope.locales = doc.META$.locales;

					$scope.language = $scope.document.LCID || Settings.get('LCID');
					$scope.parentId = $routeParams.parentId || null;

					modelInfoPromise.then(function() {
						var loadedPromises = [],
							p;

						// Call `$scope.onLoad()` if present.
						if (angular.isFunction($scope.onLoad)) {
							p = $scope.onLoad();
							if (isPromise(p)) {
								loadedPromises.push(p);
							}
						}

						// Trigger `Events.EditorLoaded`.
						$rootScope.$broadcast(Events.EditorLoaded, {
							"scope": $scope,
							"document": $scope.document,
							"promises": loadedPromises
						});

						// At this point, `$scope.document` has been loaded and may have been tweaked by the `onLoad()`
						// function in the Scope (if present) and by the handlers listening on `Events.EditorLoaded`.
						// We consider that the document is now ready: we make a copy of it to create the reference
						// document used to check for changes in the editor.
						if (loadedPromises.length) {
							$q.all(loadedPromises).then(initReferenceDocument);
						}
						else {
							initReferenceDocument();
						}
					});
				}

				/**
				 * Prepares the Editor for the edition of the given `doc`.
				 * @param doc
				 */
				function prepareCreation(doc) {
					$scope.document = doc;
					$scope.isReferenceLanguage = ($scope.document.refLCID === $scope.document.LCID);
					$scope.isLocalized = angular.isDefined($scope.document.refLCID);
					$scope.locales = doc.META$.locales;

					$scope.language = $scope.document.LCID || Settings.get('LCID');
					$scope.parentId = $routeParams.parentId || null;

					createNewDocumentId = $scope.document.id;

					modelInfoPromise.then(function() {
						// Apply default values for new documents.
						if (!hasContextData) {
							applyDefaultValues($scope.document, $scope.modelInfo);
						}

						var loadedPromises = [],
							p;

						// Call `$scope.onLoad()` if present.
						if (angular.isFunction($scope.onLoad)) {
							p = $scope.onLoad();
							if (isPromise(p)) {
								loadedPromises.push(p);
							}
						}

						// Trigger `Events.EditorLoaded`.
						$rootScope.$broadcast(Events.EditorLoaded, {
							"scope": $scope,
							"document": $scope.document,
							"promises": loadedPromises
						});

						// At this point, `$scope.document` has been loaded and may have been tweaked by the `onLoad()`
						// function in the Scope (if present) and by the handlers listening on `Events.EditorLoaded`.
						// We consider that the document is now ready: we make a copy of it to create the reference
						// document used to check for changes in the editor.
						if (loadedPromises.length) {
							$q.all(loadedPromises).then(initReferenceDocument);
						}
						else {
							initReferenceDocument();
						}
					});
				}

				/**
				 * Applies the default values defined in the ModelInfo on the given document.
				 * (only called for new documents).
				 *
				 * @param doc
				 * @param modelInfo
				 */
				function applyDefaultValues(doc, modelInfo) {
					angular.forEach(modelInfo.properties, function(propObject, name) {
						if (propObject.hasOwnProperty('defaultValue') && propObject.defaultValue !== null) {
							doc[name] = propObject.defaultValue;
						}
					});
				}

				/**
				 * Checks for Correction on the Document and updates the UI to highlight the fields that have
				 * a Correction.
				 */
				function initCorrection() {
					if (Utils.hasCorrection($scope.original)) {
						angular.forEach($scope.original.META$.correction.propertiesNames, function(property) {
							$element.find('[property="' + property + '"]').addClass(CORRECTION_CSS_CLASS);
						});
					}
				}

				/**
				 * Creates the reference document (original) from the current document.
				 * Triggers the `Events.EditorReady` event.
				 */
				function initReferenceDocument() {
					var contextData = getContextData();
					if (contextData) {
						$scope.original = contextData.original;
						EditorManager.removeLocalCopy($scope.document);
					}
					else {
						$scope.original = angular.copy($scope.document);
					}

					editorUrl = $location.absUrl();
					initCorrection();

					documentReady = true;

					// Call `$scope.onReady()` if present.
					if (angular.isFunction($scope.onReady)) {
						$scope.onReady();
					}

					$rootScope.$broadcast(Events.EditorReady, {
						"scope": $scope,
						"document": $scope.document
					});

					// Computes a list of changes on the fields in each digest cycle.
					ArrayUtils.clear($scope.changes);
					$scope.$watch('document', function editorDocumentWatch() {
						ArrayUtils.clear($scope.changes);
						angular.forEach($scope.document, function(value, name) {
							var original = angular.isDefined($scope.original[name]) ? $scope.original[name] : '';
							if (name !== 'META$' && $scope.changes.indexOf(name) === -1) {
								if (Utils.isDocument(original) && Utils.isDocument(value)) {
									if (original.id !== value.id) {
										$scope.changes.push(name);
									}
								}
								else if (!angular.equals(original, value)) {
									$scope.changes.push(name);
								}
							}
						});
					}, true);

					$scope.$on('$routeChangeStart', function() {
						if ($scope.changes.length > 0 && shouldSaveLocalCopy) {
							EditorManager.saveLocalCopy($scope.document, editorUrl);
						}
					});
				}

				/**
				 * Reset the form back to the originally loaded document (scope.original).
				 */
				function reset() {
					$scope.document = angular.copy($scope.original);
					EditorManager.removeLocalCopy($scope.document);
					$scope.saveProgress.error = false;
					clearInvalidFields();
					NotificationCenter.clear();
				}

				/**
				 * Tells whether the editor has changes or not.
				 * @return Boolean
				 */
				function isUnchanged() {
					var p, dv, ov;
					for (p in $scope.document) {
						if (p !== 'META$' && $scope.document.hasOwnProperty(p)) {
							dv = $scope.document[p];
							ov = $scope.original ? $scope.original[p] : undefined;
							// For sub-documents, we only need to check the ID.
							if (Utils.isDocument(dv) && Utils.isDocument(ov)) {
								if (dv.id !== ov.id) {
									return false;
								}
							}
							else {
								if (!angular.equals(dv, ov)) {
									return false;
								}
							}
						}
					}
					return true;
				}

				/**
				 * Tells whether the current Document has the given publication status or not.
				 * @param status
				 * @returns {*}
				 */
				function hasStatus(status) {
					if (!$scope.document) {
						return false;
					}
					var args = [$scope.document];
					ArrayUtils.append(args, arguments);
					return Utils.hasStatus.apply(Utils, args);
				}

				function getDocumentModelName() {
					return $element.attr('data-model') || $element.attr('model');
				}

				$scope.$on(Events.EditorUpdateDocumentProperties, function onUpdateDocumentPropertiesFn(event, properties) {
					angular.extend($scope.document, properties);
					submit();
				});

				$scope.$on('Navigation.saveContext', function(event, args) {
					shouldSaveLocalCopy = false;
					args.context.label($scope.document.label || i18n.trans('m.rbs.admin.adminjs.new_element | ucf'));
					args.context.savedData(
						'editor_' + $scope.document.model,
						{ document: $scope.document, original: $scope.original }
					);
					if (angular.isFunction($scope.onSaveContext)) {
						$scope.onSaveContext(args.context);
					}
				});

				//-----------------------------------------------//
				//                                               //
				// Public API of Editor Controller               //
				//                                               //
				// These methods may be called from the other    //
				// directives bound to this editor.              //
				//                                               //
				//-----------------------------------------------//

				return {
					prepareContext: prepareContext,

					submit: submit,
					prepareCreation: prepareCreation,
					prepareEdition: prepareEdition,

					clearInvalidFields: clearInvalidFields,
					getCurrentSection: getCurrentSection,
					addMenuEntry: addMenuEntry,
					getMenuEntries: getMenuEntries,

					getProperties: function() {
						return properties;
					},

					addHeaderMessage: function(html) {
						var container = $element.find('rbs-page-header');
						if (container.length) {
							return container.after($compile(html)($scope));
						}
						else {
							return $element.prepend($compile('<div class="col-md-12">' + html + '</div>')($scope));
						}
					},

					getDocumentModelName: getDocumentModelName
				};

			}],

			link: function linkFn(scope, element, attrs, ctrl) {
				scope.section = ctrl.getCurrentSection();
			}
		};
	}

	editorDirective.$inject = [
		'$rootScope', '$routeParams', '$q', '$location', '$compile',
		'RbsChange.EditorManager', 'RbsChange.Utils',
		'RbsChange.ArrayUtils', 'RbsChange.i18n', 'RbsChange.REST',
		'RbsChange.Events', 'RbsChange.Settings', 'RbsChange.NotificationCenter',
		'RbsChange.Navigation', 'RbsChange.ErrorFormatter', 'RbsChange.UrlManager', 'RbsChange.Breadcrumb'
	];

	app.directive('rbsDocumentEditorBase', editorDirective);

	/**
	 * @ngdoc directive
	 * @id RbsChange.directive:rbsDocumentEditorNew
	 * @name Document editor (creation)
	 * @restrict A
	 * @element form
	 *
	 * @description
	 * Directive used to create new Documents.
	 *
	 * This directive requires the {@link change/RbsChange.directive:rbsDocumentEditorBase `rbs-document-editor-base`}
	 * to be present on an ancestor.
	 *
	 * @example
	 * <pre>
	 *     <div rbs-document-editor-base="" model="...">
	 *         ...
	 *         <div rbs-document-editor-new="">
	 *             ...
	 *         </div>
	 *     </div>
	 * </pre>
	 */
	app.directive('rbsDocumentEditorNew', [
		'RbsChange.REST', 'RbsChange.Settings', '$location', '$routeParams', 'RbsChange.NotificationCenter', 'RbsChange.i18n',
		function(REST, Settings, $location, $routeParams, NotificationCenter, i18n) {
			return {
				restrict: 'A',
				require: '^rbsDocumentEditorBase',
				scope: false,
				priority: 900,

				compile: function(tElement) {
					tElement.attr('name', 'form');
					tElement.addClass('form-horizontal');

					return function rbsDocumentEditorNewLink(scope, iElement, iAttrs, ctrl) {
						// First, we check if there is a Navigation Context available for this editor.

						// If there is one, it will be resolved by the prepareContext() method, and we don't need
						// to do anything here.
						if (!ctrl.prepareContext()) {
							// No navigation Context:
							var promise;

							// Is there a 'initDocument()' method in the scope?
							// TODO remove initDocument?
							if (angular.isFunction(scope.initDocument)) {
								promise = scope.initDocument();
							}

							if (isPromise(promise)) {
								promise.then(function(doc) {
									prepareEditor(doc);
								});
							}
							else {
								prepareEditor(REST.newResource(ctrl.getDocumentModelName(), Settings.get('LCID')));
							}
						}

						function prepareEditor(doc) {
							ctrl.prepareCreation(doc);

							// Check if there is a 'from' parameter in the route.
							// In that case, we load the corresponding reference Document and populate the new Document with
							// the properties of the reference Document.
							if ($routeParams['from']) {
								var fromId = parseInt($routeParams['from'], 10);
								if (!isNaN(fromId)) {
									ctrl.addHeaderMessage('<div data-rbs-document-editor-create-from-message="createFromReferenceDocument"></div>');

									REST.resource(ctrl.getDocumentModelName(), fromId, doc.LCID).then(
										// Success
										function(refDoc) {
											scope.createFromReferenceDocument = refDoc;
											angular.forEach(ctrl.getProperties(), function(label, id) {
												scope.document[id] = refDoc[id];
											});
										},
										// Error
										function() {
											NotificationCenter.error(i18n.trans('m.rbs.admin.admin.reference_document_could_not_be_loaded'),
												'', 'EDITOR');
										}
									);
								}
							}
						}

						// Function called when a creation has been done.
						scope.terminateSave = function(doc) {
							$location.path(doc.url());
						};
					};
				}
			};
		}
	]);

	/**
	 * @ngdoc directive
	 * @id RbsChange.directive:rbsDocumentEditorEdit
	 * @name Document editor (edition)
	 * @restrict A
	 * @element form
	 *
	 * @description
	 * Directive used to edit an existing Document.
	 *
	 * This directive requires the {@link change/RbsChange.directive:rbsDocumentEditorBase `rbs-document-editor-base`}
	 * to be present on an ancestor.
	 *
	 * @example
	 * <pre>
	 *     <div rbs-document-editor-base="" model="...">
	 *         ...
	 *         <div rbs-document-editor-edit="">
	 *            ...
	 *         </div>
	 *     </div>
	 * </pre>
	 */
	app.directive('rbsDocumentEditorEdit',
		['$filter', '$routeParams', '$location', 'RbsChange.NotificationCenter', 'RbsChange.REST', 'RbsChange.i18n',
			'RbsChange.Utils',
			function($filter, $routeParams, $location, NotificationCenter, REST, i18n, Utils) {
				return {
					restrict: 'A',
					require: '^rbsDocumentEditorBase',
					scope: false,
					priority: 900,

					controller: function() {},

					compile: function(tElement) {
						tElement.attr('name', 'form');
						tElement.addClass('form-horizontal');

						return function rbsDocumentEditorEditLink(scope, iElement, iAttrs, ctrl) {
							// First, we check if there is a Navigation Context available for this editor.

							// If there is one, it will be resolved by the prepareContext() method, and we don't need
							// to do anything here.
							if (!ctrl.prepareContext()) {
								// No navigation Context:
								// Load Document from the server with id and LCID coming from the route's params.
								REST.resource(ctrl.getDocumentModelName(), parseInt($routeParams.id, 10), $routeParams.LCID).then(
									// Success
									function(doc) {
										// Check the model name of the loaded Document:
										// It it's not the same as the expected one ("model" attribute), the user is redirected
										// to the right editor for the loaded Document.
										if (doc.model !== ctrl.getDocumentModelName()) {
											$location.path($filter('rbsURL')(doc, 'edit'));
										}
										else {
											ctrl.prepareEdition(doc);
										}
									},
									// Error
									function() {
										NotificationCenter.error(
											i18n.trans('m.rbs.admin.admin.document_does_not_exist | ucf') + ' ' +
											'<a href="' + $filter('rbsURL')(ctrl.getDocumentModelName(), 'new') + '">' +
											i18n.trans('m.rbs.admin.admin.create | ucf | etc') +
											'</a>', '', 'EDITOR'
										);
									}
								);
							}

							scope.$on('Change:DocumentChanged', function(event, doc) {
								if (doc && scope.document.id === doc.id) {
									scope.reload();
								}
							});

							scope.reload = function() {
								if (Utils.isDocument(scope.document)) {
									REST.resource(scope.document).then(ctrl.prepareEdition);
								}
							};
						};
					}
				};
			}]);

	/**
	 * @ngdoc directive
	 * @id RbsChange.directive:rbsDocumentEditorTranslate
	 * @name Document editor (translation)
	 * @restrict A
	 * @element form
	 *
	 * @description
	 * Directive used to translate an existing Document.
	 *
	 * This directive requires the {@link change/RbsChange.directive:rbsDocumentEditorEdit `rbs-document-editor-edit`}
	 * to be present on the same element.
	 *
	 * @example
	 * <pre>
	 *     <div rbs-document-editor-base="" model="...">
	 *         ...
	 *         <div rbs-document-editor-edit="" rbs-document-editor-translate="">
	 *            ...
	 *         </div>
	 *     </div>
	 * </pre>
	 */
	app.directive('rbsDocumentEditorTranslate',
		['$location', '$q', 'RbsChange.Events', 'RbsChange.NotificationCenter', 'RbsChange.REST', 'RbsChange.i18n',
			function($location, $q, Events, NotificationCenter, REST, i18n) {
				return {
					restrict: 'A',
					require: 'rbsDocumentEditorEdit',
					scope: false,
					priority: 890,

					compile: function(tElement) {
						tElement.prepend(
							'<div class="form-group property">' +
								'<label class="col-lg-3 control-label">' + i18n.trans('m.rbs.admin.admin.lcid | ucf') +
								'</label>' +
								'<div class="col-lg-3 controls">' +
									'<select class="form-control" data-ng-model="currentLCID" data-ng-options="lcid as locale.label for (lcid, locale) in availableTranslations"></select>' +
								'</div>' +
							'</div><hr/>'
						);

						return function(scope, iElement, iAttrs, ctrl) {
							// Add watch on currentLCID to let the user switch between languages in the editor.
							scope.$watch('currentLCID', function(lcid, old) {
								if (lcid !== old) {
									$location.url(scope.document.translateUrl(lcid));
								}
							});

							scope.$on(Events.EditorLoaded, function(event, data) {
								var p1 = REST.getAvailableLanguages(),
									p2 = REST.resource(scope.document.model, scope.document.id, scope.document.refLCID);
								$q.all([p1, p2]).then(function(results) {
									scope.currentLCID = scope.document.LCID;
									scope.refDocument = results[1];
									scope.availableLanguages = results[0].items;

									scope.availableTranslations = {};
									angular.forEach(scope.availableLanguages, function(l, id) {
										if (id !== scope.document.refLCID) {
											scope.availableTranslations[id] = l;
										}
									});
								});
								data.promises.push(p1);
								data.promises.push(p2);
							});
						};
					}
				};
			}]);

	/**
	 * @ngdoc directive
	 * @id RbsChange.directive:rbsDocumentEditorSection
	 * @name Document editor (section)
	 * @restrict A
	 *
	 * @description
	 * Placed on a <code>&lt;fieldset/&gt;</code> to declare a section in the editor.
	 *
	 * This directive looks for the fields in the <code>&lt;fieldset/&gt;</code> and registers a menu entry in the
	 * {@link change/RbsChange.directive:rbsDocumentEditorBase editor's Controller}.
	 *
	 * This directive requires the <code>rbs-document-editor-base=""</code> to be present on an ancestor.
	 */
	editorSectionDirective = ['RbsChange.Utils', '$location', function(Utils, $location) {
		var defaultSectionIcons = {
			publication: 'icon-globe',
			attributes: 'icon-th-list',
			activation: 'icon-time',
			systeminfo: 'icon-info-sign',
			permissions: 'icon-lock',
			'': 'icon-edit'
		};

		return {
			restrict: 'A',
			scope: false,
			require: '^rbsDocumentEditorBase',

			compile: function(tElement) {
				// First, the Section is hidden.
				// It will be shown when the user selects a section in the menu
				// (see event 'Change:EditorSectionChanged' below).
				tElement.hide();

				return function rbsEditorSectionLink(scope, iElement, iAttrs, ctrl) {
					var sectionId = iAttrs['rbsEditorSection'] || '',
						entry = {
							'id': sectionId,
							'label': iAttrs['editorSectionLabel'],
							'icon': iAttrs['editorSectionIcon'] || defaultSectionIcons[sectionId] || 'icon-edit',
							'fields': [],
							'required': [],
							'invalid': [],
							'corrected': [],
							'index': iElement.index()
						}, p;

					entry.url = Utils.makeUrl($location.absUrl(), { section: (sectionId.length ? sectionId : null) });
					if ((p = entry.url.indexOf('#')) !== -1) {
						entry.url = entry.url.substring(0, p);
					}

					function refreshEntry() {
						entry.fields.length = 0;
						// Search for fields (properties) in this section.
						iElement.find('[property]').each(function(index, ctrlGrp) {
							var $ctrlGrp = $(ctrlGrp),
								$lbl = $ctrlGrp.find('label[for]').first(),
								propertyName = $ctrlGrp.attr('property');

							entry.fields.push({
								id: propertyName,
								label: $lbl.text()
							});
							if ($ctrlGrp.hasClass('required')) {
								entry.required.push(propertyName);
							}
							if ($ctrlGrp.hasClass(CORRECTION_CSS_CLASS)) {
								entry.corrected.push(propertyName);
							}
						});
						ctrl.addMenuEntry(entry);
					}

					refreshEntry();
					scope.$on('Change:Editor:SectionsUpdateMenu', refreshEntry);

					// Show/hide the section
					function update(section) {
						if (section === sectionId) {
							iElement.show();
						}
						else {
							iElement.hide();
						}
					}

					update(ctrl.getCurrentSection());

					scope.$on('Change:EditorSectionChanged', function(event, section) {
						update(section);
					});
				};
			}
		};
	}];
	app.directive('rbsEditorSection', editorSectionDirective);
	app.directive('rbsDocumentEditorSection', editorSectionDirective);

	/**
	 * @name RbsChange.directive:rbsDocumentEditorCreateFromMessage
	 * @restrict A
	 *
	 * @description
	 * Displays a message when creating a Document from another one.
	 *
	 * @param {Document} rbs-document-editor-create-from-message The reference Document.
	 */
	app.directive('rbsDocumentEditorCreateFromMessage', ['RbsChange.i18n', function(i18n) {
		return {
			restrict: 'A',
			template: '<div class="alert alert-info">' +
					'<p data-ng-if="refDoc">' +
						'<i class="icon-info-sign icon-3x pull-left"></i> ' +
						i18n.trans('m.rbs.admin.admin.creating_a_new_document_from') +
						' <strong><a href target="_blank" data-ng-href="(= refDoc | rbsURL =)"><span data-ng-bind="refDoc.label"></span> <i class="icon-external-link"></i></a></strong>.<br/>' +
						i18n.trans('m.rbs.admin.admin.creating_a_new_document_from_tip') +
					'</p>' +
					'<p data-ng-if="! refDoc">' +
						'<i class="icon-spin icon-spinner"></i> ' +
						i18n.trans('m.rbs.admin.admin.loading_reference_document | ucf | etc') +
					'</p>' +
				'</div>',
			scope: {
				refDoc: '=rbsDocumentEditorCreateFromMessage'
			}
		};
	}]);
})(window.jQuery);