/**
 * Manages macrotask operations
 */
angular.module('MacrotaskService', ['ngResource'])
    .config(
    function ($provide) {

        //region Define the factory
        var MacrotaskFactory = function ($resource, ConfigService, Tools) {
            var macrotask = $resource(
                ConfigService.BACKEND_SERVICE + 'Macrotask/:id/:action/',
                {
                    id: '@id'
                }, // Default parameters
                {
                    create: {
                        method: "POST",
                        params: {
                            action: "create"
                        }
                    },
                    update: {
                        method: "PUT",
                        params: {
                            action: "update"
                        }
                    },
                    delete: {
                        method: "DELETE"
                    },
                    query: {method: 'GET', isArray: false}
                }
            );

            macrotask.save = function (item, success, failure) {
                var result;

                if (item.id == undefined)
                    result = macrotask.create(item, success, failure);
                else
                    result = macrotask.update({id: item.id}, item, success, failure);
                return result;
            };


            return macrotask;
        };

        //Register the factory
        MacrotaskFactory.$inject = ['$resource', 'Config', 'Tools'];
        $provide.factory('Macrotask', MacrotaskFactory);
        //endregion
    }
);
