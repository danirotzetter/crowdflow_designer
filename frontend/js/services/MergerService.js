/**
 * Manages merger operations
 */
angular.module('MergerService', ['ngResource'])
    .config(
    function ($provide) {

        //region Define the factory
        var MergerFactory = function ($resource, ConfigService, Tools) {
            var merger = $resource(
                ConfigService.BACKEND_SERVICE + 'Merger/:id/:action/',
                {
                    id: '@id'
                }, // Default parameters
                {
                    getInputs: {
                        method: "GET",
                        params: {
                            action: "datasources"
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

            merger.save = function (item, success, failure) {
                var result;
                var itemToSave = Tools.copyItem(item);
                if (itemToSave.id == undefined)
                    result = merger.create(itemToSave, success, failure);
                else
                    result = merger.update({id: itemToSave.id}, itemToSave, success, failure);
                return result;
            };

            /**
             * Get all merger types
             * @returns {Array}
             */
            merger.getAllMergerTypes=function(){
                return [
                    {id:1, name:"Selection",
                        subTypes:[
                            {id:11, name:"The requester makes the selection"},
                            {id:12, name:"Crowd-sourced voting"}
                        ]
                    },
                    {id:2, name:"Aggregation",
                        subTypes:[
                            {id:21, name:"Sum"},
                            {id:22, name:"Average"},
                            {id:23, name:"Highest result"},
                            {id:24, name:"Lowest result"},
                            {id:25, name:"Majority voting"}
                        ]
                    },
                    {id:3, name:"Web service",
                        subTypes:[
                            {id:31, name:"REST web service call"}

                        ]
                    }
                ]
            }

            return merger;
        };

        //Register the factory
        MergerFactory.$inject = ['$resource', 'Config', 'Tools'];
        $provide.factory('Merger', MergerFactory);
        //endregion
    }
);
