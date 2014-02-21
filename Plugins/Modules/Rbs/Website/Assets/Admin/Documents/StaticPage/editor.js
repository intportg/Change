(function ($) {

	"use strict";

	var app = angular.module('RbsChange');

	function changeEditorWebsitePage($routeParams, REST, Breadcrumb, $q, i18n) {
		return {
			restrict: 'A',
			templateUrl: 'Document/Rbs/Website/StaticPage/editor.twig',
			replace: false,
			require: 'rbsDocumentEditor',

			link: function (scope, element, attrs, editorCtrl) {

				var contentSectionInitialized = false;

				scope.onLoad = function () {
					if (!scope.document.section){
						var nodeId =  Breadcrumb.getCurrentNodeId();
						if (nodeId) {
							REST.resource(nodeId).then(function (doc){ scope.document.section = doc})
						}
					}
					if (scope.document.isNew() && $routeParams.website && !scope.document.website) {
						scope.document.website = $routeParams.website;
						REST.resource($routeParams.website).then(function (doc){ scope.document.website = doc});
					}
				};

				scope.initSection = function (sectionName) {
					if (sectionName === 'content') {
						scope.loadTemplate();
						contentSectionInitialized = true;
					}
				};

				scope.$on('Navigation.saveContext', function (event, args) {
					args.context.savedData('pageTemplate', scope.pageTemplate);
				});

				scope.onRestoreContext = function (currentContext) {
					scope.pageTemplate = currentContext.savedData('pageTemplate');
				};

				scope.loadTemplate = function () {
					var pt = scope.document.pageTemplate;
					if (pt) {
						if (!scope.pageTemplate || scope.pageTemplate.id != pt.id)
						{
							REST.resource(pt).then(function (template) {
								scope.pageTemplate = {id:template.id, html: template.htmlForBackoffice, data: template.editableContent};
							});
						}
					}
				};

				scope.leaveSection = function (section) {
					if (section === 'content') {
						$('#rbsWebsitePageDefaultAsides').show();
						$('#rbsWebsitePageBlockPropertiesAside').hide();
					}
				};

				scope.enterSection = function (section) {
					if (section === 'content') {
						$('#rbsWebsitePageDefaultAsides').hide();
						$('#rbsWebsitePageBlockPropertiesAside').show();
					}
				};

				scope.finalizeNavigationContext = function (context) {
					if (context.params.blockId) {
						scope.$broadcast(
							'Change:StructureEditor.setBlockParameter',
							{
								blockId: context.params.blockId,
								property: context.params.property,
								value: context.result
							}
						);
					}
				};

				editorCtrl.init('Rbs_Website_StaticPage');

				// This is for the "undo" dropdown menu:
				// Each item automatically activates its previous siblings.
				$('[data-role=undo-menu]').on('mouseenter', 'li', function () {
					$(this).siblings().removeClass('active');
					$(this).prevAll().addClass('active');
				});

				scope.$watch('document.pageTemplate', function (pageTemplate) {
					scope.loadTemplate();
				}, true);

				scope.preSave = function (document) {
					var p = $q.defer();
					if (!document.pageTemplate.mailSuitable) {
						p.resolve();
					}
					else {
						var error = {
							code: 999999,
							message: i18n.trans('m.rbs.website.admin.page_template_not_suitable_help | ucf'),
							httpStatus: 500
						};
						p.reject(error);
					}
					return p.promise;
				};
			}
		};

	}

	changeEditorWebsitePage.$inject = [
		'$routeParams',
		'RbsChange.REST',
		'RbsChange.Breadcrumb',
		'$q',
		'RbsChange.i18n'
	];
	app.directive('rbsDocumentEditorRbsWebsiteStaticpage', changeEditorWebsitePage);

})(window.jQuery);