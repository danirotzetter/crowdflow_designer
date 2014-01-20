/**
 * The controller for task composition
 * Functions:
 * -edit item (add/ remove/ edit task, merger, ...)
 * -forward the connection edit to the Compose service
 */
app.controller('ComposeCtrl', function ($scope, $routeParams, $dialog, Task, Compose, Merger, Datasource, Splitter, Postprocessor, Workspace, Tools, Log, Connection, $timeout, Config) {

    self = this; // Reference to access the controller within dialog callbacks

    // region Define the scope properties
    $scope.workspace = false;
    $scope.isLoaded = false;
    $scope.loadingMessage = 'Please wait while data<br>is being downloaded';
    $scope.mergers = [];
    $scope.tasks = [];
    $scope.splitters = []
    $scope.datasources = [];
    $scope.postprocessors = [];
    Log.clearMessages();
    $scope.messages = Log.messages;
    $scope.draggingItem = undefined; // string indicating out of what item the user is dragging a connection
    $scope.draggingEndpoint = undefined;
    $scope.collapseMainPanelHeader = false; // Do not change this value - will end up in a circular call
    $scope.permanentlyDisableHeader=true;// Whether the header/ metadata should permanently be hidden in the UI
    $scope.showProgressInfo=false;// Whether the progress information should be displayed above each crowd-sourced item



    //endregion

    //region Define the scope functions

    //region Workspace related

    /**
     * Hide/ show the workspace header
     */
    $scope.toggleHeader = function () {
        var heightBefore = $('#collapsibleHeader').height();
        $scope.collapseMainPanelHeader = !$scope.collapseMainPanelHeader;

        $timeout(function () {
            // Measure the space difference that was caused by hiding/ showing the header
            var heightAfter = $('#collapsibleHeader').height();
            var diff = heightAfter - heightBefore;
            $.each(Workspace.getAllItems($scope, true), function (index, item) {
                // Adjust every item's position according to the measured space difference
                var domElement = $('#' + item.type + item.id);
                var topNew = domElement.offset().top + diff;
                domElement.offset({top: topNew});
            });
            jsPlumb.repaintEverything();
        }, 400);// Must wait for the end of the animation such that the DOM is 'stable' and the height measurements are correct
    };


    /**
     * Removes all items and connections from the current workspace (if confirmed by the user to do so)
     */
    $scope.clearWorkspace = function () {

        Tools.showDialog(
            [
                {id: 'ok', text: 'Ok', class: 'btn btn-primary'},
                {text: 'Cancel', class: 'btn btn-warning'}
            ],
            {title: 'Clear workspace', text: 'Please confirm removing every item from the workspace.' +
                '<div class="alert alert-block">' +
                '<h4>Warning!</h4>This action cannot be undone!</div>'}
        )
            .open()
            .then(
            function (result) {
                // Execute the required action, if there is one
                if (result == undefined) {
                    // User aborted dialog
                    return;
                }
                if (result.id != undefined && result.id == 'ok') {
                    self.updateItems(['task', 'merger', 'datasource'], 'delete');
                    Log.log('success', 'Workspace cleared');
                }
            }// End handle result
        );// End dialog's then()then(function(re))
    }// End clearWorkspace function



    /**
     * Store all workspace-related settings. Saves the items' positions and connections are adjusted.
     * However, in a later step within this method, the WorkspaceService's save() method is called, which triggers the storage of the workspace's direct attributes.
     */
    $scope.saveWorkspace = function () {
        $scope.loadingMessage = 'Please wait while data<br>is stored to the database';
        $scope.isLoaded = false;

        // To get the appropriate positions, the header must be in un-toggled state
        var delay = 0;
        if (!$scope.permanentlyDisableHeader && $scope.collapseMainPanelHeader) {
            delay = 500;// delays saving the items, such that they are positioned in the non-collapsed state in the meantime
            $scope.toggleHeader();
        }

        $timeout(function () {
            // Go through every item
            var allItems = Workspace.getAllItems($scope, true);

            var restConnections = Array();
            $.each(allItems, function (index, item) {
                // Update the item itself
                var service = item.type.charAt(0).toUpperCase() + item.type.slice(1);
                eval(service).save(item);


                /* Update the connections
                 * Instead of updating each connection separately, we will send the entire connection information in one REST request. The information is stored
                 * in an array consisting of the connections.
                 */
                if (item.connections != undefined) {
                    // Store the connections from this item
                    $.each(item.connections, function (idx, connection) {
                        var source = item;
                        var target = connection.to.item;

                        var workspace = $scope.workspace;
                        restConnections.push({sourceId: source.id, sourceType: source.type, targetId: target.id, targetType: target.type, workspaceId: workspace.id});
                    });// End for each connection
                }// End there are connections

            });// End for each item

            Workspace.save($scope.workspace);

            // Delete all connections, since they will be overwritten by the newly valid array of connections
            Workspace.deleteAllConnections({id: $scope.workspace.id}, function (result) {

                /* Store the values
                 * Since AngularJS requires a non-array object, we wrap the connections array in an artificial object with a
                 * property 'connections',
                 */
                Connection.saveBulk({connections: restConnections}, function (result) {
                    Log.log('success', 'Stored all connections');
                    $scope.isLoaded = true;
                    Log.log('success', 'Successfully stored workspace');
                }, function (result) {
                    Log.log('error', 'Could not store connections');
                });

            });// End delete all connections
        }, delay)// End timeout after displaying header
    }


    /**
     * Displays the result of the workspace
     */
    $scope.showResults= function () {

        // Define the dialog options
        var dialogOptions = {
            controller: 'ResultsCtrl',
            templateUrl: 'partials/workspace-results.html',
            dialogFade: true,
            backdropFade: true
        };
        // Define the dialog
        var dialog = $dialog.dialog(
            // Open the dialog in the new controller: submit the workspace object
            angular.extend(
                dialogOptions,
                {
                    resolve: {
                        workspace: $scope.workspace
                    }
                }
            )
        );
        // Open/ call the dialog
        dialog
            .open().then(
            function (result) {

            });


    }// End showResults function

//endregion


//region Connection related


    /**
     * Function to edit a connection. Called when a connection is being established, detached or edited.
     * Display a popup for connection confirmation
     * @param csConnection
     * @param action See ConnectionCtrl action parameters
     * 'Change' means directing the output of an existing connection to another point ('switch destination')
     * 'Edit' means setting different properties to an existing connection
     */
    $scope.editConnection = function (csConnection, action) {
        if (csConnection.change) {
            /**
             * The change event is called upon connection detach. So the parameter of this function
             * will be 'detach', whereas in reality, the connection is not detached but changed.
             * Therefore, we will override the action variable here to the correct value.
             */
            action = 'change';
        }

        // Define the dialog options
        var dialogOptions = {
            controller: 'ConnectionCtrl',
            templateUrl: 'partials/edit-connection.html',
            dialogFade: true,
            backdropFade: true
        };
        // Define the dialog
        var dialog = $dialog.dialog(
            // Open the dialog in the new controller: submit the connection as a parameter
            angular.extend(
                dialogOptions,
                {
                    resolve: {
                        csConnection: csConnection,
                        action: action
                    }
                }
            )
        );
        // Open/ call the dialog
        dialog
            .open().then(
            function (result) {
                if (result == undefined) {
                    // User aborted the dialog with ESC key
                    return;
                }
                var oldDestination = result.connection.toOld;
                var newDestination = result.connection.to;

                switch (result.action) {
                    //region confirmations
                    case 'establish':
                        // Update jsPlumb
                        $scope.refreshCsConnectionInWorkspace(result.connection, 'establish');
                        Log.log('success', 'Connection established');
                        break;
                    case 'detach':
                        // Update jsPlumb
                        $scope.refreshCsConnectionInWorkspace(result.connection, 'detach');
                        Log.log('success', 'Connection detached');
                        break;
                    case 'change':
                        // Update the source's connections
                        // Update jsPlumb
                        $scope.refreshCsConnectionInWorkspace(result.connection, 'change');
                        Log.log('success', 'Connection re-attached');
                        break;
                    case 'info':
                        break;

                    //endregion end confirmations

                    //region cancels
                    case 'cancel-establish':
                        Log.log('info', 'Cancelled');
                        break;
                    case 'cancel-change':
                        Log.log('info', 'Cancelled');
                        break;
                    case 'cancel-detach':
                        Log.log('info', 'Cancelled');
                        break;
                    //endregion end cancels

                }// End switch result.action

                /* Now, we have possibly handled a connection change (either confirmed or cancelled).
                 * The toOld variables are still present in the connection, so we have to remove them.
                 */
                result.connection.toOld = undefined;
                result.connection.change = false;



                // Immediately store the connection changes to the database
                $scope.saveWorkspace();

                Tools.refresh();
            }
        );
        Tools.refresh();
    };

    /**
     * Method called as soon as all connections have been read and added to the items
     */
    $scope.allConnectionsRead = function () {

        $scope.isLoaded = true;

        Log.log('success', 'All connections loaded');


        $scope.redrawPostprocessorConnections();


        jsPlumb.repaintEverything();// Re-arrange the endpoints


    }

    /**
     * Re-draw the appropriate connections of the postprocessors. This might be necessary if a connection to a post-processor
     * is established, and the postprocessor is a validator that, if the result is rejected, requires the task to be re-done
     * (which means that a looping connection will be drawn)
     */
    $scope.redrawPostprocessorConnections = function () {
        $.each($scope.postprocessors, function (index, item) {
            var connectionsIn = Workspace.getConnectionsToItem($scope, item);
            Postprocessor.drawConnections(item, connectionsIn);
        });
    }

    /**
     * Since we are mainly working on CsConnections, we have to propagate all csConnection changes to the JsPlumb library. This is done
     * in this method, where the information from csConnection is taken and transformed into jsPlumb statements.
     * This method is applied when for example a new connection is established, since the new connection is established by adding a csConnection -
     * the plumb gui would not be updated unless we add the connection to the jsPlumb library
     * @param csConnection
     * @param action 'establish', 'detach', 'change'
     * @param updateScopeItemConnections If set to TRUE, also the scope item's connections array is updated. Defaults to true
     */
    $scope.refreshCsConnectionInWorkspace = function (csConnection, action) {
        /*
         Must be handled before 'establish', since it may happen that an element's output has max 1 connections - a rule
         that would be broken on a change if the new moduleconnection was established first
         */
        if (action == 'change' || action == 'detach') {
            /* Remove the old connection
             *
             *
             * The second parameter results in returning the old connection from csConnection if the connection was changed (and thus the old connection must be removed).
             * If the connection is simply to be detached, the second parameter evaluates to FALSE, meaning that the 'normal' connection is returned and used for detaching
             */
            var oldConnection = Compose.csConnectionToPlumbConnection(csConnection, action == 'change');


            // Remove the connection object from the old source item. To do so, we first have to get the position of the connection to be deleted
            var removeFromItem = csConnection.from.item;
            var connectionIndex = -1;

            /* To find the position of the connection to be deleted, we first must know the destination of the connection.
             And here, we differentiate between a 'change' and a 'detach' operation (see the if branches' descriptions)
             */
            var connectionTargetType;
            var connectionTargetId;
            if (action == 'change') {
                // If a connection is changed, we have to delete the connection to the item stored as 'csConnection.toOld'
                connectionTargetId = csConnection.toOld.item.id;
                connectionTargetType = csConnection.toOld.item.type;
            }
            else {
                // Connection is simply deleted, not re-attached to another destination. In this case, the connection to be deleted is stored as 'csConnection.to'
                connectionTargetId = csConnection.to.item.id;
                connectionTargetType = csConnection.to.item.type;
            }
            $.each(removeFromItem.connections, function (index, connection) {
                // Browse through all connections of this item to find the connection to the item specified in the 'csConnection' target
                if (connection.to.item.id == connectionTargetId && connection.to.item.type == connectionTargetType) {
                    connectionIndex = index;
                }
            });
            // If the connection's index was found, we can delete the object
            if (connectionIndex != -1) {
                csConnection.from.item.connections.splice(connectionIndex, 1);
            }
            else {
                console.log('Could not delete connection from ' + csConnection.from.item.id + ' of type ' + csConnection.from.item.type + ' to ' + csConnection.to.item.id + ' of type ' + csConnection.to.item.type + ' - could not find the connection in the item\'s connecions array');
            }

            jsPlumb.detach(oldConnection, {forceDetach: true});
        }

        if (action == 'change' || action == 'establish') {
            // Establish the new connection
            try {
                var newConnection = jsPlumb.connect({uuids: [csConnection.from.endpointUuid, csConnection.to.endpointUuid]});
            } catch (e) {
                console.warn('Could not create connection from ' + csConnection.from.endpointUuid + ' to ' + csConnection.to.endpointUuid + ': ' + e.message);
                return;
            }
            csConnection.from.item.connections.push(csConnection);
        }

        $scope.redrawPostprocessorConnections();
    }

//endregion End connection-related


//region Entity related

    /**
     * Callback when all items have been loaded
     */
    $scope.allItemsLoaded = function () {
        Log.log('success', 'All items loaded, now restoring connections and item positions...')
        // When all items have been loaded, we can apply the connections, i.e. draw the connections that were stored in the database
        Workspace.readConnections($scope, $scope.refreshCsConnectionInWorkspace, $scope.allConnectionsRead);
    }

    /**
     * Operations on items (tasks, mergers, ...)
     * @param item
     * @param action 'add', 'delete', 'edit', 'duplicate'
     */
    $scope.editItem = function (itemParameter, action) {

        var item;
        if (action == 'duplicate') {
            // Duplicate handling: just use the existing item as a template, then proceed as if 'add' was chosen
            action = 'add';
            item = Tools.copyItem(itemParameter)
        // Shift the positions slightly in order to not cover the item copied by the duplicate
            item.pos_x = parseInt(itemParameter.pos_x)+ 100;
            item.pos_y = parseInt(itemParameter.pos_y)+ 100;
            delete(item.id);
        }
        else {
            // No duplicate handling - can use the item that was submitted as parameter 'directly'
            item = itemParameter;
        }

        var type = item.type;

        // Add the workspace which the item is related to
        item.workspace_id = $scope.workspace.id;

        // Get all incoming connections
        var connectionsIn = action == 'duplicate' ? Array() : Workspace.getConnectionsToItem($scope, item);// In case of duplication, do not use any existing connection


        // Define the dialog options
        var dialogOptions = {
            controller: 'ItemCtrl',
            templateUrl: 'partials/forms/' + type + '.html',
            dialogFade: true,
            backdropFade: true
        };
        // Define the dialog
        var dialog = $dialog.dialog(
            // Open the dialog in the new controller: submit the connection as a parameter
            angular.extend(
                dialogOptions,
                {
                    resolve: {
                        action: action,
                        item: item,
                        connectionsIn: connectionsIn
                    }
                }
            )
        );
        // Open/ call the dialog
        dialog
            .open().then(
            function (result) {
                if(result==undefined){
                    // User aborted via ESC key
                    return;
                }
                if (result.success) {
                    switch (result.data.action) {
                        case 'add':
                        {
                            var item = result.data.item;
                            item.isNew=true;// Indicate that this item was newly added (a property accessed for css styles in order to highlight new items - see class 'isNew')
                            self.updateItems(item, 'add');
                            // New item
                            switch (result.data.item.type) {
                                case 'task':
                                {
                                    Log.log('success', 'Added new task');
                                    break;
                                }
                                case 'merger':
                                {
                                    Log.log('success', 'Added new merger');
                                    break;
                                }
                                case 'datasource':
                                {
                                    Log.log('success', 'Added new datasource');
                                    break;
                                }
                                case 'splitter':
                                {
                                    Log.log('success', 'Added new splitter');
                                    break;
                                }
                            }
                            break;
                        }//End add
                        case 'edit':
                        {
                            var item = result.data.item;
                            self.updateItems(item, 'edit');
                            // Item edited
                            switch (result.data.item.type) {
                                case 'task':
                                {
                                    Log.log('success', 'Task edited');
                                    break;
                                }
                                case 'merger':
                                {
                                    Log.log('success', 'Merger edited');
                                    break;
                                }
                                case 'datasource':
                                {
                                    Log.log('success', 'Datasource edited');
                                    break;
                                }
                                case 'splitter':
                                {
                                    Log.log('success', 'Splitter edited');
                                    break;
                                }
                            }
                            break;
                        }//End edit
                        case 'delete':
                        {
                            var item = result.data.item;
                            self.updateItems(item, 'delete');
                            // Item edited
                            switch (result.data.item.type) {
                                case 'task':
                                {
                                    Log.log('success', 'Task deleted');
                                    break;
                                }
                                case 'merger':
                                {
                                    Log.log('success', 'Merger deleted');
                                    break;
                                }
                                case 'datasource':
                                {
                                    Log.log('success', 'Datasource deleted');
                                    break;
                                }
                                case 'splitter':
                                {
                                    Log.log('success', 'Splitter deleted');
                                    break;
                                }
                            }
                            break;
                        }//End delete
                        case 'cancel-add':
                        {
                            Log.log('info', 'Add cancelled');
                            break;
                        }
                        case 'cancel-edit':
                        {
                            Log.log('info', 'Edit cancelled');
                            break;
                        }
                        case 'cancel-delete':
                        {
                            Log.log('info', 'Delete cancelled');
                            break;
                        }
                    }//End switch result
                    $scope.redrawPostprocessorConnections();
                    jsPlumb.repaintEverything();
                }// End success
                else {
                    // Error detected
                    switch (result.data.action) {
                        case 'add':
                            var errorMsg = 'Could not add entity.';
                            if (result.errors != undefined) {
                                // Append additional information
                                errorMsg += ' ' + result.errors[0];
                            }
                            Log.log('error', errorMsg);
                            break;
                    }
                }// End failure
            }// End treat dialog result
        );// End open dialog
    };//End function editItem


//endregion End entity related


//region bind to jsplumb events
    /**
     * We will listen to the event where the user establishes a connection
     */
    jsPlumb.bind("beforeDrop", function (plumbConnection) {
        var action;
        // Check if the user is dragging an existing connection ('change') or creating a new one ('establish')
        if (plumbConnection.connection.suspendedEndpoint != undefined) {
            action = 'change';
        }
        else
            action = 'establish';
        var csConnection = Compose.plumbConnectionToCsConnection(plumbConnection);
        // Display the dialog to confirm establishing/ changing the connection
        $scope.editConnection(csConnection, action);
        return false;// Do not propagate the connection changes - will be handled after user confirmation programmatically
    });

    /**
     * Event when the user starts dragging a connection. We register the source type - the 'in' endpoints
     * will adapt their classes according to the drag source type, such that they can be hidden if they are not a valid destination.
     * See also the compose-item directive
     */
    jsPlumb.bind('connectionDrag', function (plumbConnection) {
        $scope.draggingEndpoint = plumbConnection.endpoints[0];
        $scope.draggingItem = $scope.draggingEndpoint.getParameter('item');

        Tools.refresh();
    });
    /**
     * Event when the user ends the connection dragging
     */
    jsPlumb.bind('connectionDragStop', function (plumbConnection) {
        $scope.draggingItem = undefined;

        Tools.refresh();
    });

//endregion End bind to events


//region Tools

    /**
     * Updates the array of items in the scope. This method is executed after an item was edited.
     * Instead of calling the $scope.array.push(item) method for each dialog depending on the item type,
     * this is handled here for simplicity and maintainability reasons.
     * @param item The item to update, or a string indicating the type of the items to update (e.g. 'task'), or an array of item types to update (e.g. ['task', 'merger']
     * @param action The action to perform. 'add', 'edit', 'delete'
     */
    this.updateItems = function (item, action) {

        // Instantiate connections property for each item
        if(item.connections==undefined)
            item.connections=Array();

        if (typeof item == 'object' && !(item instanceof Array)) {
            // Remove a specific (i.e. a single) item

            // Find the array in which the item was stored
            var items = eval('$scope.' + item.type + 's');

            // Add the item to this list, if the item is new
            if (action == 'add') {
                items.push(item);
                return;
            }


            else {
                // Item is not new
                items.forEach(function (arrItem, i) {
                    if (arrItem.id == item.id) {
                        switch (action) {
                            case 'edit':
                                // Update the item's values
                                items[i] = item;
                                return;
                            case 'delete':
                                // Remove the item from the array
                                items.splice(i, 1);
                                // Remove the JsPlumb endpoints
                                var elementId = '' + item.type + item.id;
                                jsPlumb.removeAllEndpoints(elementId);

                                // Remove all connections to this item
                                var allItems = Workspace.getAllItems($scope, true);
                                $.each(allItems, function (idx, itm) {
                                    var conIndexToDelete = -1;
                                    $.each(itm.connections, function (ix, cn) {
                                        if (cn.to.item.id == item.id && cn.to.item.type == item.type) {
                                            conIndexToDelete = ix;
                                            return false;
                                        }
                                    });// End for each connection of the item
                                    if (conIndexToDelete > -1) {
                                        itm.connections.splice(conIndexToDelete, 1);
                                    }
                                }); // End for each item
                                return;
                        }
                    }// End matching id
                    else if (i == items.length) {
                        // Went through all items, but item was not found
                        console.warn('Could not perform operation \'' + action + '\' for id \'' + item.id + '\': item not found in the scope array.');
                    }
                }); // End for each item in the $scope array
            }// End was not add
            Tools.refresh();
        }// End remove specific item

        else {
            // Bulk operation: suspend jsPlumb updates until after every modification is done
            jsPlumb.doWhileSuspended(function () {
                    var types;
                    if (typeof(item) == 'string')
                    // Single type to update
                        types = [item];
                    else {
                        // Array of types to update
                        types = item;
                    }
                    types.forEach(function (type) {
                            // Remove all items of the array
                            var items = eval('$scope.' + type + 's');
                            switch (action) {
                                case 'delete':
                                {
                                    // We first must remove all JsPlumb endpoints
                                    items.forEach(function (arrItem, i) {
                                        // Remove the JsPlumb endpoints
                                        var elementId = '' + arrItem.type + arrItem.id;
                                        jsPlumb.removeAllEndpoints(elementId);
                                    }); // end foreach function
                                    // Then, we can clear the array of objects
                                    eval('$scope.' + type + 's=[];');
                                }// End matching id
                            }// End switch action
                        }// End for each item type
                    );// End foreach function
                }// End do while jsPlumb suspended function
            ); // End do while jsPlumb is suspended
        }// End remove all items in the array (bulk processing)
    };// End function update items

//endregion End tools

    // When all functions have been defined, we can fill the workspace with items
    Workspace.loadCompleteWorkspaceToScope($scope, $routeParams.id, $scope.allItemsLoaded);
})
;// End controller






