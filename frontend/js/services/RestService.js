/**
 * Providing easy access for REST calls
 */
angular.module('RestService', ['ngResource'])
	.config(
		function($provide){


            //region Define the service
            var RestService = function($http, $q){
                this.call=function(url){
                    var deferred = $q.defer();
                    $http.get(url).success(function(data, status){
                        deferred.resolve(data);
                    }).error(function(data, status){
                            deferred.reject(data);
                        }); //End $http.get
                    return deferred.promise;
                }
            };

            RestService.$inject =  ['$http', '$q'];
            $provide.service('Rest', RestService);
            //endregion
        }
	);
