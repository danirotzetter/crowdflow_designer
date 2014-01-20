/**
 * The controller that sends tasks to the crowdsource provider
 */
app.controller('CrowdsourcerCtrl', function ($scope, $routeParams, $http, Workspace, Task, Platforms, Log, Config, Tools, Merger, Postprocessor, Splitter) {

    // region Define the scope properties
    $scope.workspace = false;
    $scope.isLoaded = false;
    $scope.mergers = [];
    $scope.tasks = [];
    $scope.splitters = []
    $scope.datasources = [];
    $scope.postprocessors = [];
    Log.clearMessages();
    $scope.messages = Log.messages;
    $scope.collapseMainPanelHeader = false;
    $scope.selectedItem = {}; // The item that is currently being edited
    $scope.connectionsIn = []; // The item's input connections
    $scope.taskIsLoaded = true;
    $scope.loadingMessage = 'Task data are being loaded from the database and from the crowdsourcing provider...';

    $scope.parameters = Task.getAllParameters(true);// Used for detailed parameter listing
    $scope.platformData = Task.getAllPlatformData(true);// Used for detailed platform data listing



    $scope.taskFormUrlBase = Config.BACKEND_SERVICE + 'CrowdsourceForms/';// Used to build the taskFormUrl
    $scope.taskFormUrl = false;// The URL pointing to the task form that will be crowdsourced

    //endregion


    //region General setup and management functions
    $scope.allItemsLoaded = function () {
        Workspace.readConnections($scope, $scope.refreshConnectionCallback, function () {
            $scope.isLoaded = true;
        });
    }
    /**
     * Function called when a connection was loaded from the server: we have to update the item's connections array such that these connections will be available through
     * $scope.<type>[0].connections
     */
    $scope.refreshConnectionCallback = function (csConnection, action) {
        /* In the crowdsourcer, no connections will be edited or removed.
         * The only time that this function is called is to load the connection (action=='establish').
         * Thus, we can ignore the action parameter and simply add the connection to the appropriate item's connections list
         */
        csConnection.from.item.connections.push(csConnection);
    }



    /**
     * Select an item to crowdsource
     * @param item
     */
    $scope.selectItem = function (item) {
        if ($scope.selectedItem.type != item.type || $scope.selectedItem.id != item.id) {
            $scope.taskIsLoaded = false;

            // Get detailed item information
            var type = item.type;
            $scope.selectedItem = item;
            var service = type.charAt(0).toUpperCase() + type.slice(1);

            // In the next line, we call the backend and ask for the full item information
            eval(service).get({id: item.id}, function (result) {
                    item = result.data
                    $scope.selectedItem = item;

                    /*
                     * The input queue is stored in the item's property. However, we want to display the input queue in the platform_data property.
                     * But we will limit the information to the size of the queue.
                     * To accomplish this, we have to set the corresponding value in the platform_data property.
                     */
                    if (item.input_queue != undefined)
                        item.platform_data.input_queue = item.input_queue.length + ' items pending';
                    else
                        item.platform_data.input_queue = 'No input pending';

                    if(item.platform_data.pendingAssignments!=undefined)
                        item.platform_data.pendingAssignments=item.platform_data.pendingAssignments.length + ' assignments';
                    else
                        item.platform_data.pendingAssignments='None';
                    if(item.platform_data.rejectedAssignments!=undefined)
                        item.platform_data.rejectedAssignments=item.platform_data.rejectedAssignments.length + ' assignments';
                    else
                        item.platform_data.rejectedAssignments='None';
                    if(item.platform_data.acceptedAssignments!=undefined)
                        item.platform_data.acceptedAssignments=item.platform_data.acceptedAssignments.length + ' assignments';
                    else
                        item.platform_data.acceptedAssignments='None';

                    if(item.platform_data.tasks!=undefined)
                        item.platform_data.tasks=item.platform_data.tasks.length + ' tasks';
                        else
                        item.platform_data.tasks='None';


                    // Then we need to get the connections to this item (in order to display the 'input' item)
                    $scope.connectionsIn = Workspace.getConnectionsToItem($scope, item);
                    var urlToLoad = $scope.taskFormUrlBase + 'ItemFormView?itemType='+$scope.selectedItem.type+'&itemId='+$scope.selectedItem.id+'&appMode=true';


                    /* Now we can decide: if we want to verify that the to-be-crowdsourced form preview is available, we have
                     * to send the form request to the backend. If an error happens, we can treat the error code.
                     * If no error happens, we can include the form in the web application.
                     * The downside would be that the form is generated twice, the first time for nothing.
                     *
                     */
                    var verifyBeforeDisplay = false;

                    if (verifyBeforeDisplay) {
                        // We will verify if there is such a form available (otherwize, the server will send an error code)
                        $http.get(urlToLoad).success(function () {
                            // Success - form available: update the URL that will be used in ng-include
                            $scope.taskFormUrl = urlToLoad;
                            $scope.taskIsLoaded = true;
                        }).error(function () {
                                // Failure: indicate that no form is available
                                $scope.taskFormUrl = false;
                                $scope.taskIsLoaded = true;
                            });
                    }// End verify before display
                    else {
                        // Do not verify that the form is available
                        $scope.taskFormUrl = urlToLoad;
                        $scope.taskIsLoaded = true;
                    }
                },// end success
                function () {
                    Log.log('warning', 'Cannot publish task - could not find detailed task information');
                    $scope.taskIsLoaded = true;
                }//End failure

            );// End get item
        }// End selecting new item (not the one already selected)
        else {
            // Un-select item
            $scope.selectedItem = {};
            $scope.connectionsIn = [];
        }

    }
    //endregion


    //region Task management methods
    $scope.publishTask = function (item) {
        Tools.showDialog(
            [
                {id: 'ok', text: 'Ok', class: 'btn btn-primary'},
                {text: 'Cancel', class: 'btn btn-warning'}
            ],
            {title: 'Publish task', text: 'Please confirm publishing this task.' +
                '<h4>Be careful</h4>This will cost you money'}
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
                    if (item == undefined) {
                        // As fallback: use currently selected item
                        if ($scope.selectedItem != undefined)
                            item = $scope.selectedItem;
                        else {
                            Log.log('warning', 'Cannot publish task - no item selected');
                            return;
                        }
                    }// End task was undefined
                    $scope.taskIsLoaded = false;
                    Platforms.publishTask(item, function (data, status, headers, config) {
                        if (data.success) {
                            Log.log('success', 'Successfully published task');
                            // Replace the new item data
                            $scope.selectedItem.platform_data = data.data.platform_data;
                        }
                        else {
                            Log.log('error', 'Could not publish task: ' + data.errors.join());
                        }
                        $scope.taskIsLoaded = true;
                    }, function (data, status, headers, config) {
                        if (data.errors != undefined) {
                            Log.log('error', 'Could not publish task: ' + data.errors.join());
                        }
                        else {
                            Log.log('error', 'Could not publish task:');
                        }
                        $scope.taskIsLoaded = true;
                    });// End publishTask call
                }// End result ok
            }// End handle result
        );// End dialog's then()
    };// End function

    //endregion

    // Load the items
    Workspace.loadCompleteWorkspaceToScope($scope, $routeParams.id, $scope.allItemsLoaded);

    $scope.collapseMainPanelHeader=true;
});// End controller
