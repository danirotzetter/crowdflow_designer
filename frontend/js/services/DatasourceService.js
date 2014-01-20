/**
 * Manages datasource operations
 */
angular.module('DatasourceService', ['ngResource'])
    .config(
    function ($provide) {

        //region Define the factory
        var DatasourceFactory = function ($resource, ConfigService, Tools) {
            var datasource = $resource(
                ConfigService.BACKEND_SERVICE + 'Datasource/:id/:action/',
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

            datasource.save = function (item, success, failure) {
                var result;
                var itemToSave = Tools.copyItem(item);
                if (itemToSave.id == undefined)
                    result = datasource.create(itemToSave, success, failure);
                else
                    result = datasource.update({id: itemToSave.id}, itemToSave, success, failure);
                return result;
            };


            /**
             * Get all datasource types
             * @returns {Array}
             */
           datasource.getAllDatasourceTypes=function(){
                return [
                    {id:1, name:"Single file upload"},
                    {id:2, name:"Multiple files upload"},
                    {id:3, name:"Webservice returning one item"},
                    {id:4, name:"Webservice returning multiple items"},
                    {id:5, name:"Form field (for text medium only)"}
                ]
            }

            return datasource;
        };


        //Register the factory
        DatasourceFactory.$inject = ['$resource', 'Config', 'Tools'];
        $provide.factory('Datasource', DatasourceFactory);
        //endregion
    }
);
