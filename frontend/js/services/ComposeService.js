/**
 * Manages the compose gui
 * Functions:
 * -connection conversion (csConnection <-> plumbConnection)
 * -edit connections (establish, detach, modify, change)
 * -register the browser's scroll event to update the plumb gui
 */
angular.module('ComposeService', [])
    .config(
    function ($provide) {

        //region Define the service
        var ComposeService = function ($dialog, $rootScope, ConfigService, Log, Tools) {
            var self = this; // To access the variables from within function definitions

            //region Functions

            //region Connection-related
            /**
             * Connection converter
             * @param csConnection
             * @param useOld When there was a connection change, i.e. a connection is attached to a new destination, then we can also
             * use this converter to get the jsPlumb connection from the old connection
             * @returns {*}
             */
            this.csConnectionToPlumbConnection = function (csConnection, useOld) {

                //region Set element ids
                // Set the elements if not yet defined
                if(csConnection.from.elementId==undefined){
                    var endpoint = jsPlumb.getEndpoint(csConnection.from.endpointUuid);
                    csConnection.from.elementId=endpoint.elementId;
                }
                if(csConnection.to.elementId==undefined){
                    var endpoint = jsPlumb.getEndpoint(csConnection.to.endpointUuid);
                    csConnection.to.elementId=endpoint.elementId;
                }
                if(csConnection.toOld!= undefined && csConnection.toOld.item != undefined && csConnection.toOld.elementId==undefined){
                    var endpoint = jsPlumb.getEndpoint(csConnection.toOld.endpointUuid);
                    csConnection.toOld.elementId=endpoint.getElement().id;
                }
                //endregion

                var cons = jsPlumb.getConnections({scope: csConnection.scopeId, source: csConnection.from.elementId, target: useOld ? csConnection.toOld.elementId : csConnection.to.elementId});
                var connection = cons[0];
                return connection;
            };

            /**
             * Shortcut to generate a CsConnection that can be handled by the application
             */
            this.plumbConnectionToCsConnection = function (plumbConnection) {

                var sourceEndpoint;
                var targetEndpoint;
                var scopeId;
                var change;
                var oldTargetEndpoint;


                if (plumbConnection.connection != undefined) {
                    sourceEndpoint = plumbConnection.connection.endpoints[0];
                    targetEndpoint = plumbConnection.dropEndpoint;
                    oldTargetEndpoint = plumbConnection.connection.suspendedEndpoint;
                    scopeId = plumbConnection.connection.scope;

                    if (oldTargetEndpoint != undefined && oldTargetEndpoint.elementId != targetEndpoint.elementId) {
                        // Connection is being re-attached
                        change = true;
                        oldTargetEndpoint = {
                            elementId: oldTargetEndpoint.elementId,
                            item: oldTargetEndpoint.getParameter("item"),
                            endpointUuid: oldTargetEndpoint.getUuid()
                        }
                    }
                    else {
                        // New connection is being attached
                        change = false;
                    }
                }

                var csConnection =
                {
                    from: {
                        elementId: sourceEndpoint.elementId,
                        item: sourceEndpoint.getParameter("item"),
                        endpointUuid: sourceEndpoint.getUuid()
                    },
                    to: {
                        elementId: targetEndpoint.elementId,
                        item: targetEndpoint.getParameter("item"),
                        endpointUuid: targetEndpoint.getUuid()
                    },
                    toOld: oldTargetEndpoint,
                    scopeId: scopeId,
                    change: change
                };

                return csConnection;
            };

            /**
             * Converts a connection coming from the server to a csConnection
             * @param restConnection The connection stored on the server
             * @param item The source item
             * @param itemsToFindTargetIn In order to create a cs, we need a reference to both the source and the target object.
             * In this method, the itemsToFindTargetIn array will be queried to find the requested target type with the appropriate id.
             * @return the CsConnection, if found. If not found, false is returned.
             */
            this.restConnectionToCsConnection = function(restConnection, item, itemsToFindTargetIn){
                // In order to create the csConnection, we need the reference to the target item.
                // To retrieve this reference, we again have to iterate over the objects of the specified target type
                var sourceType = restConnection.sourceType;
                var sourceElementId=sourceType+restConnection.sourceId; // Identifies the element in the HTML document
                var targetType = restConnection.targetType;
                var targetElementId=targetType+restConnection.targetId; // Identifies the element in the HTML document


                var csConnection=false;// The result


                // We go through every single item of the target type array to find the target item of the connection
                $.each(itemsToFindTargetIn, function(i, targetTypeItem){
                    if(targetTypeItem.type+targetTypeItem.id==targetElementId){
                        // Found the target item of the connection
                        // Now that the item is found, we can create the actual connection
                        var fromEndpointUuid='out_'+sourceElementId+'_out'; // By default, assume that the 'regular' out endpoint has name 'out' TODO check if this has to be adapted
                        var toEndpointUuid='in_'+targetElementId+'_in';// By default, assume that the 'regular' out endpoint has name 'in' TODO check if this has to be adapted
                        csConnection = {
                            from:{
                                item:item,
                                endpointUuid:fromEndpointUuid
                            },
                            to:{
                                item:targetTypeItem,
                                endpointUuid:toEndpointUuid
                            }
                        };
                        return false;// break loop
                    }// End found target item
                });//End for each item of the corresponding target type
                if(csConnection){
                    // Conversion succeeded
                    return csConnection;
                }
                else{
                console.error('Could not convert rest connection from '+sourceElementId+' to '+targetElementId);
                    return false;
                }
            };// // End function definition





            //endregion End connection-related functions


            //endregion End functions


            // Configure jsPlumb
            jsPlumb.importDefaults(
                ConfigService.jsPlumb.defaults
            );
            jsPlumb.endpointDropAllowedClass = 'dropAllowed';
            jsPlumb.endpointDropForbiddenClass = 'dropForbidden';




            // We have to repaint everything when the window is scrolled in order to fix the endpoint and connection offsets
            $(window).bind("scroll", function () {
                //jsPlumb.repaintEverything();
            });

            //endregion

        };


        //Register the service
        ComposeService.$inject = ['$dialog', '$rootScope', 'Config', 'Log', 'Tools'];
        $provide.service('Compose', ComposeService);
        //endregion
    }
);
