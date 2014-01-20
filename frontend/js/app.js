/**
 * This is the 'entry' point for the application
 * @type {*}
 */
var app = angular.module('CrowdsourcingManagement', ['ui.bootstrap', 'ui', 'PlatformsService', 'RestService', 'ConfigService', 'ToolsService', 'LogService', 'TaskService', 'DatasourceService', 'ComposeService', 'MergerService', 'WorkspaceService','SplitterService', 'ConnectionService', 'PostprocessorService', 'MacrotaskService']).
	config(['$routeProvider','$compileProvider', routeConfig]);

function routeConfig($routeProvider, $compileProvider){

    // Remove the unsafe attribute when creating links (create links 'blob:...' instead of 'unsafe:blob:...'
    //$compileProvider.aHrefSanitizationWhitelist(/^\s*(https?|ftp|file|blob):|data:image\//):/); //new syntax
    $compileProvider.urlSanitizationWhitelist(/^\s*(https?|ftp|file|blob):|data:image\//); // old syntax

	$routeProvider.
		when('/home', {templateUrl: 'partials/home.html', controller: 'HomeCtrl'}).
		when('/balance/', {templateUrl: 'partials/platformAccount.html', controller: 'AccountCtrl'}).
		when('/workspaceManagement/', {templateUrl: 'partials/workspaceManagement.html', controller: 'WorkspaceManagementCtrl'}).
		when('/csManagement/', {templateUrl: 'partials/csManagement.html', controller: 'CsManagementCtrl'}).
		when('/compose/:id', {templateUrl: 'partials/compose.html', controller: 'ComposeCtrl'}).
		when('/crowdsource/:id', {templateUrl: 'partials/crowdsource-tasks.html', controller: 'CrowdsourcerCtrl'}).



		otherwise({redirectTo: '/home'});
}