/**
 * Manages platform-related information.
 * This service deals with platform metadata like name, URL, restrictions etc.
 * This is related to the Platform models on the server side
 */
angular.module('PlatformsService', ['ngResource'])
    .config(
    function ($provide) {
        //region Define the factory
        var PlatformFactory = function ($resource, $http, $q, ConfigService) {
            var urlBase = ConfigService.BACKEND_SERVICE + 'platforms/';

            var platform={};

            /**
             * Get the current account balance
             * @param succCallback
             * @param errCallback
             */
            platform.getAccountBalance = function (succCallback, errCallback) {
                    $http.get(urlBase + 'accountBalance').success(function (result, status) {
                        if(succCallback!=undefined)
                        succCallback(result);
                    }).error(function (data, status) {
                        if(errCallback!=undefined)
                        errCallback(result);
                        }); //End $http.get
            };


            /**
             * Publishes a task to the crowd
             * @param item
             */
            platform.publishTask = function(item, succ, err){
                $http({
                    method:'POST',
                    url:urlBase+'publishTask?itemId='+item.id+'&itemType='+item.type
                }).success(succ).error(err);
            };


            /**
             * Get all tasks that are currently crowd-sourced on the current platform
             * @param succ
             * @param err
             */
            platform.getAllTasks= function(succ, err){
                $http({
                    method:'GET',
                    url:urlBase+'allTasks'
                }).success(succ).error(err);
            };

            /**
             * Delete all tasks that are published on the current platform
             * @param succ
             * @param err
             */
            platform.deleteAllTasks= function(succ, err){
                $http({
                    method:'GET',
                    url:urlBase+'deleteAllTasks'
                }).success(succ).error(err);
            };

            /**
             * Delete all tasks that are published on the current platform and reset their flow information
             * @param succ
             * @param err
             */
            platform.resetAllTasks= function(succ, err){
                $http({
                    method:'GET',
                    url:urlBase+'resetAllTasks'
                }).success(succ).error(err);
            };

            /**
             * Publish all tasks on the crowd-sourcing platform
             * @param succ
             * @param err
             */
            platform.publishAllTasks= function(succ, err){
                $http({
                    method:'GET',
                    url:urlBase+'publishAllTasks'
                }).success(succ).error(err);
            };


            return platform;
        };

        //Register the factory
        PlatformFactory.$inject = ['$resource', '$http', '$q', 'Config'];
        $provide.factory('Platforms', PlatformFactory);
        //endregion

    }
)
    .config(function($httpProvider){
        delete $httpProvider.defaults.headers.common['X-Requested-With'];
    })
;
