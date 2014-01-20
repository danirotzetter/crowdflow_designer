/**
 * Manages connection operations
 */
angular.module('ConnectionService', ['ngResource'])
    .config(
    function ($provide) {

        //region Define the factory
        var ConnectionFactory = function ($resource, ConfigService) {
            var connection = $resource(
                ConfigService.BACKEND_SERVICE + 'Connection/:action/',
                {

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
                    query: {method: 'GET', isArray: false},
                    saveBulk:{
                        method:'POST',
                        params: {
                            action: "saveBulk"
                        }
                    }

                }
            );

            connection.save = function (sourceItem, targetItem, workspace, success, failure) {
                var result;
                var parameters={
                    sourceId:sourceItem.id,
                    sourceType:sourceItem.type,
                    targetId:targetItem.id,
                    targetType:targetItem.type,
                    workspaceId:workspace.id
                }
                if (connection.get(parameters)==undefined)
                    result = connection.create(parameters, success, failure);
                else
                    result = connection.update(parameters, success, failure);
                return result;
            };

            return connection;
        };

        //Register the factory
        ConnectionFactory.$inject = ['$resource', 'Config'];
        $provide.factory('Connection', ConnectionFactory);
        //endregion
    }
);
