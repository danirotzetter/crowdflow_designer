/**
 * Manages splitter operations
 */
angular.module('SplitterService', ['ngResource'])
    .config(
    function ($provide) {

        //region Define the factory
        var SplitterFactory = function ($resource, ConfigService, Tools) {
            var splitter = $resource(
                ConfigService.BACKEND_SERVICE + 'Splitter/:id/:action/',
                {
                    id: '@id'
                }, // Default parameters
                {
                    getOutputs: {
                        method: "GET",
                        params: {
                            action: "outputs"
                        }
                    },
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

            splitter.save = function (item, success, failure) {
                var result;
                var itemToSave = Tools.copyItem(item);
                if (itemToSave.id == undefined)
                    result = splitter.create(itemToSave, success, failure);
                else
                    result = splitter.update({id: itemToSave.id}, itemToSave, success, failure);
                return result;
            };

            return splitter;

    };

        //Register the factory
        SplitterFactory.$inject = ['$resource', 'Config', 'Tools'];
        $provide.factory('Splitter', SplitterFactory);
        //endregion
    }
);
