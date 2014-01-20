/**
 * Manages post-processing operations
 */
angular.module('PostprocessorService', ['ngResource'])
    .config(
    function ($provide) {

        //region Define the factory
        var PostprocessorFactory = function ($resource, ConfigService, Tools) {
            var postprocessor = $resource(
                ConfigService.BACKEND_SERVICE + 'Postprocessor/:id/:action/',
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

            postprocessor.save = function (item, success, failure) {
                var result;
                var itemToSave = Tools.copyItem(item);
                if (itemToSave.id == undefined)
                    result = postprocessor.create(itemToSave, success, failure);
                else
                    result = postprocessor.update({id: itemToSave.id}, itemToSave, success, failure);
                return result;
            };

            /**
             * Get all postprocessor types
             * @returns {Array}
             */
            postprocessor.getAllPostprocessorTypes=function(){
                return [
                    {id:1, name:"Validation",
                        subTypes:[
                            {id:11, name:"Validated by the requester"},
                            {id:12, name:"Validated by the crowd"}
                        ]
                    }
                    /*
                    TODO allow the transformation (possibly disable it via html)
                    ,
                    {id:2, name:"Transformation",
                        subTypes:[
                            {id:21, name:"Transformed by a web service"},
                            {id:21, name:"Transformed by a mathematical expression"}
                        ]
                    }*/
                ]
            }
            /**
             * Get all validation types
             * @returns {Array}
             */
            postprocessor.getAllValidationTypes=function(){
                return [
                    {id:1, name:"Repeat task"},
                    {id:2, name:"Divert"},
                    {id:3, name:"Discard data"}
                ]
            }


            /**
             * A postprocessor of type 'validator' may require that a task is re-done, i.e. there is a data flow from the validator back to the task.
             * This property is visualized in the jsPlumb environment. This method is called to draw this connection
             * @param item The postprocessor item
             * @parem connectionsIn The array of connections that is the input ot the postprocessor. A postprocessor may only have one input, however the workspace service's method
             * to get an item input returns an array of connections. Thus, for convenience, this method also accepts an array and checks herein whether it is undefined or empty
             */
            postprocessor.drawConnections = function(item, connectionsIn){
                var hasConnections =connectionsIn != undefined && // A task input must be defined
                    connectionsIn.length > 0; // The task input must not be empty
                var backToTask =
                        hasConnections && // Postprocessor must have a source
                        item.postprocessor_type_id > 10 && item.postprocessor_type_id < 20 && // The postprocessor is a validator
                        item.validation_type_id!=undefined && item.validation_type_id == 1; // The validator is of type 'send back to task when rejected'
                if (backToTask) {
                    /*
                     All conditions fulfilled - the postprocessor has a flow back to the task the data comes from.
                     Hence, establish a connection to the corresponding task
                     */
                    var sourceTask = connectionsIn[0].from.item;
                    var fromEndpointUuid = 'out_' + item.type+item.id + '_out'; // TODO hardcoded postprocessor output endpoint name - check if this is usable
                    var toEndpointUuid = 'in_' + sourceTask.type + sourceTask.id + '_inValidator'; // TODO hardcoded postprocessor output endpoint name - check if this is usable
                    if(!jsPlumb.getEndpoint(toEndpointUuid).isFull()){
                    //var newConnection = jsPlumb.connect({uuids: [fromEndpointUuid, toEndpointUuid]});
                    }
                    else{
                        // Loopback already established
                    }

                }
                else if(!hasConnections){// Meaning that the postprocessor has no (more) source tasks
                //Detach the connections that were established before, pointing to a task
                    var sourceElementId = 'postprocessor'+item.id;
                    var conns = jsPlumb.getConnections({source:sourceElementId});
                    $.each(conns, function(index, connection){
                        if(connection.targetId.indexOf('task')!=-1){
                            // Target is a connection to a task
                            if(connection.endpoints.length>1 && connection.endpoints[1].getUuid().indexOf('_inValidator')!=-1){
                                /* The connection is not to any task but it is a connection that uses the task's endpoint designated for validator loop backs
                                * Long story short: we have found the jsPlumb connection that is showing a loopback which is no longer valid
                                */
                            jsPlumb.detach(connection);
                            return false;
                            }
                        }
                    })
                }
            }

            return postprocessor;
        };

        //Register the factory
        PostprocessorFactory.$inject = ['$resource', 'Config', 'Tools'];
        $provide.factory('Postprocessor', PostprocessorFactory);
        //endregion
    }
);
