/**
 * Manages workspace operations
 */
angular.module('WorkspaceService', ['ngResource'])
    .config(
    function ($provide) {

        //region Define the factory
        var WorkspaceFactory = function ($resource, $timeout, ConfigService, Compose, Log, Tools) {
            var workspace = $resource(
                ConfigService.BACKEND_SERVICE + 'Workspace/:id/:action/',
                {id: '@id'}, // Default parameters
                {
                    getItems: {
                        method: "GET",
                        params: {
                            action: "items"
                        }
                    },
                    getAllConnections: {
                        method: "GET",
                        params: {
                            action: "connections"
                        }
                    },
                    deleteAllConnections: {
                        method: "DELETE",
                        params: {
                            action: "connections"
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
                    query: {
                        method: 'GET',
                        isArray: false
                    }
                }
            );


            /**
             * Saves the workspace to the database. Note that this method only stores the attributes related directly to the workspace and ignores items and positions.
             * The attached items, their positions and connections must be stored using the ComposeCtrl's saveWorkspace() method!
             * @param item
             * @param success
             * @param failure
             * @returns {*}
             */
            workspace.save = function (itemToSave, success, failure) {
                var result;
                if (itemToSave.id == undefined)
                    result = workspace.create(itemToSave, success, failure);
                else
                    result = workspace.update({id: itemToSave.id}, itemToSave, success, failure);
                return result;
            };


            /**
             * Auxiliary function to load the workspace and all related items int a scope.
             * Stores the workspace into the variable $scope.workspace and the items in their respective array variable (e.g. $scope.tasks)
             * @param $scope
             * @param id
             * @param callbackAfterItemTypeLoaded
             */
            workspace.loadCompleteWorkspaceToScope = function ($scope, id, callbackAfterItemTypeLoaded) {
                workspace.get({id: id}, function (result) {
                    $scope.workspace = result.data;
                    $scope.type='workspace';

                    Log.log('success', 'Successfully loaded workspace metadata');

                    workspace.getItems({id: id}, function (result) {
                        var allItems = result.data;


                        /* Add each item to the array of the corresponding type. To do so, we first sort the items into an associative
                         * array (type=>items) and replace the array of the scope afterwards
                         */
                        var sorted = {};

                        $.each(allItems, function (type, item) {
                            if (sorted[item.type] == undefined) {
                                sorted[item.type] = [];
                            }
                            item.connections = [];
                            sorted[item.type].push(item);
                        });// End for each item

                        // Replace the array of items of the corresponding type in the scope
                        $.each(sorted, function (type, items) {
                            eval('$scope.' + type + 's = items');
                        });

                        Log.log('success', 'Successfully loaded all items');

                        if (callbackAfterItemTypeLoaded != undefined) {
                            $timeout(function () {
                                callbackAfterItemTypeLoaded();
                            }, 1000);
                            /*
                             TODO check if this is an appropriate way of letting AngularJS evaluate the directives.
                             Note: this workaround was introduced since all compose-item directives must be evaluated before everything else
                             is done (like connecting endpoints - since the endpoints are defined within the compose-item directives)
                             */
                        }
                    });// End got all items

                });// End got workspace object
            };// End function load complete workspace to scope


            /**
             * Get all connections that have as destination the specified item
             * @param item
             * @returns {Array}
             */
            workspace.getConnectionsToItem = function ($scope, item) {
                var connections = [];
                var allItems = workspace.getAllItems($scope, true);
                $.each(allItems, function (index, srcItem) {
                    if(srcItem.connections==undefined){
                        consle.info('Cannot read connections from item '+srcItem.type+srcItem.id+': no connections available');
                        return;
                    }
                    else{
                    $.each(srcItem.connections, function (idx, connection) {
                        if (connection.to.item.id == item.id && connection.to.item.type == item.type)
                            connections.push(connection);
                    });
                    }
                });
                return connections;
            }// End getConnectionsToItem function


            /**
             * Read the connections for all items - the connections will be added to the corresponding object as a 'connections' property
             * @param refreshConnectionCallback A function accepting as first argument the read csConnection and as a second argument the action (e.g. 'establish'). This method will be called for each CsConnection that was retrieved from the server.
             * @param allConnectionsReadCallback A function that is called when all connections have been read
             */
            workspace.readConnections = function ($scope, refreshConnectionCallback, allConnectionsReadCallback) {
                //Get all connections for this workspace from the server
                workspace.getAllConnections({id: $scope.workspace.id}, function (result) {
                    var connections = result.data;
                    // Fetch all possible destination items
                    var items = workspace.getAllItems($scope, true);

                    /*
                     * Initialize the connections for each item with an empty array - if there ARE connections
                     * out of the item, they will be set in the next step
                     */
                    if (items != undefined)
                        $.each(items, function (index, itm) {
                            if (itm != undefined && itm.connections == undefined)
                                itm.connections = [];
                        });// End for each item

                    // Iterate through all connections to add them to the corresponding source item javascript objects
                    $.each(connections, function (index, connection) {
                        // Find the source item
                        var sourceItem;
                        if (items != undefined)
                            $.each(items, function (idx, itm) {
                                if (itm.id == connection.sourceId && itm.type == connection.sourceType) {
                                    sourceItem = itm;
                                    return false;// break loop
                                }
                            });
                        if (sourceItem == undefined) {
                            console.warn('Could not create connection from \'' + connection.sourceId + '\' of type \'' + connection.sourceType + '\': source item not found');
                            return; // Continue connections loop
                        }
                        // Create the csConnection
                        var csConnection = Compose.restConnectionToCsConnection(connection, sourceItem, items);
                        if (!csConnection) {
                            console.warn('Could not create connection from \'' + connection.sourceId + '\' of type \'' + connection.sourceType + '\': could not convert REST to CsConnection');
                            return;//  Connection not found - continue loop
                        }
                        else{
                            // Connection has been found and created
                        }

                        if (refreshConnectionCallback != undefined)
                            refreshConnectionCallback(csConnection, 'establish');
                    });// End for each connection

                    if (allConnectionsReadCallback != undefined)
                        allConnectionsReadCallback();

                });// end got connections from server
            }; // End drawConnectionsFromServer

            /**
             * Retrieve all items of this workspace
             *
             * @param $scope The scope in which the items are stored
             * @param singleArray If true returns an array of items. If set to false, returns an array of array of items, i.e. all arrays that contain a single type (e.g. one array of 'mergers', one array of 'tasks' etc.)
             * Defaults to true
             */
            workspace.getAllItems = function ($scope, singleArray) {
                var result = Array();
                result.push($scope.mergers);
                result.push($scope.tasks);
                result.push($scope.datasources);
                result.push($scope.splitters);
                result.push($scope.postprocessors);
                result.push($scope.workspace);

                if (singleArray == undefined || singleArray === true) {
                    // Must 'flatten' the array
                    var flattened = Array();
                    $.each(result, function (index, item) {
                        flattened = flattened.concat(item);
                    });
                    result = flattened;
                }
                return result;
            }// End getAllItems function
            return workspace;
        };


        //Register the factory
        WorkspaceFactory.$inject = ['$resource', '$timeout', 'Config', 'Compose', 'Log', 'Tools'];
        $provide.factory('Workspace', WorkspaceFactory);
        //endregion

    }// End config function
);// End configuration
