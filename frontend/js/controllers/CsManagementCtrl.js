app.controller('CsManagementCtrl', function($scope, $http, $routeParams, Platforms, Config, Log, Tools){

    //region Define the variables
    $scope.balance;
    $scope.tasks;
    $scope.hidePlatformMetadata=true;
    $scope.hidePublishedTasks=true;
    $scope.hideCsFunctions=true;
    $scope.isLoaded=true;
    $scope.loadingMessage='Please wait while data is being loaded...';
    Log.clearMessages();
    $scope.messages = Log.messages;
    //endregion Define the variables


    //region Define the management functions
    /**
     * Update the flow of all items, e.g. publish tasks that have a non-empty input queue etc.
     */
    $scope.updateFlow = function () {
        $scope.isLoaded = false;
        $scope.loadingMessage='Please wait while the flow of items is being updated...';
        $http.get(Config.BACKEND_SERVICE + 'Platforms/updateFlow').success(function (data, status, headers, config) {
            if (data.success)
                Log.log('success', data.data);
            else
                Log.log('error', data.errors.join(', '));
            $scope.isLoaded = true;
        }).error(function (data, status, headers, config) {
                Log.log('error', 'Could not update the flow');
                $scope.isLoaded = true;
            });
    }


    /**
     * Delete all tasks published on the crowd-sourcing platform
     */
    $scope.deleteAllTasks= function () {
        Tools.showDialog(
            [
                {id: 'ok', text: 'Ok', class: 'btn btn-primary'},
                {text: 'Cancel', class: 'btn btn-warning'}
            ],
            {title: 'Delete tasks on crowd', text: 'Please confirm deleting all tasks on the crowd-sourcing platform.' +
                '<div class="alert alert-block">' +
                '<h4>Warning!</h4>This operation cannot be undone' +
                '</div>'}
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
                    $scope.isLoaded = false;
                    $scope.loadingMessage='Please wait while the tasks are being deleted...';
                    Platforms.deleteAllTasks(function (data, status, headers, config) {
                        if (data.success) {
                            Log.log('success', 'Successfully deleted all tasks: '+data.data);
                        }
                        else {
                            Log.log('error', 'Could not deleted tasks: ' + data.errors.join());
                        }
                        $scope.getCsData();
                        $scope.isLoaded = true;
                    }, function (data, status, headers, config) {
                        if (data.errors != undefined) {
                            Log.log('error', 'Could not delete tasks: ' + data.errors.join());
                        }
                        else {
                            Log.log('error', 'Could not delete tasks:');
                        }
                        $scope.getCsData();
                        $scope.taskIsLoaded = true;
                    });// End delete all tasks call
                }// End result ok
            }// End handle result
        );// End dialog's then()
    };// End function
    /**
     * Reset all tasks published on the crowd-sourcing platform
     */
    $scope.resetAllTasks= function () {
        Tools.showDialog(
            [
                {id: 'ok', text: 'Ok', class: 'btn btn-primary'},
                {text: 'Cancel', class: 'btn btn-warning'}
            ],
            {title: 'Reset all tasks', text: 'Please confirm deleting clearing all flow information in the web application.' +
                '<div class="alert alert-block">' +
                '<h4>Warning!</h4>This operation cannot be undone' +
                '</div>'}
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
                    $scope.isLoaded = false;
                    $scope.loadingMessage='Please wait while the tasks are being reset...';
                    Platforms.resetAllTasks(function (data, status, headers, config) {
                        if (data.success) {
                            Log.log('success', 'Successfully reset all tasks: '+data.data);
                        }
                        else {
                            Log.log('error', 'Could not reset tasks: ' + data.errors.join());
                        }
                        $scope.getCsData();
                        $scope.isLoaded = true;
                    }, function (data, status, headers, config) {
                        if (data.errors != undefined) {
                            Log.log('error', 'Could not reset tasks: ' + data.errors.join());
                        }
                        else {
                            Log.log('error', 'Could not reset tasks:');
                        }
                        $scope.getCsData();
                        $scope.taskIsLoaded = true;
                    });// End delete all tasks call
                }// End result ok
            }// End handle result
        );// End dialog's then()
    };// End function
    /**
     * Publish all tasks that can be crowd-sourced
     */
    $scope.publishAllTasks= function () {
        Tools.showDialog(
            [
                {id: 'ok', text: 'Ok', class: 'btn btn-primary'},
                {text: 'Cancel', class: 'btn btn-warning'}
            ],
            {title: 'Publish tasks to the crowd', text: 'Please confirm publishing all tasks on the crowd-sourcing platform.' +
                '<div class="alert alert-block">' +
                '<h4>Warning!</h4>This is very costly' +
                '</div>'}
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
                    $scope.isLoaded = false;
                    $scope.loadingMessage='Please wait while the tasks are being published...';
                    Platforms.publishAllTasks(function (data, status, headers, config) {
                        if (data.success) {
                            Log.log('success', 'Successfully published all tasks: '+data.data);
                        }
                        else {
                            Log.log('error', 'Could not publish tasks: ' + data.errors.join());
                        }
                        $scope.getCsData();
                        $scope.isLoaded = true;
                    }, function (data, status, headers, config) {
                        if (data.errors != undefined) {
                            Log.log('error', 'Could not publish tasks: ' + data.errors.join());
                        }
                        else {
                            Log.log('error', 'Could not publish tasks:');
                        }
                        $scope.getCsData();
                        $scope.taskIsLoaded = true;
                    });// End delete all tasks call
                }// End result ok
            }// End handle result
        );// End dialog's then()
    };// End function

    /**
     * Deletes a task with the given identifier
     * @param hitId
     */
    $scope.deleteTask = function (hitId) {
        Tools.showDialog(
            [
                {id: 'ok', text: 'Ok', class: 'btn btn-primary'},
                {text: 'Cancel', class: 'btn btn-warning'}
            ],
            {title: 'Delete task', text: 'Please confirm deleting this task.' +
                '<div class="alert alert-block">' +
                '<h4>Warning!</h4>This will remove the task completely from the crowd-sourcing platform'}
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
                    $scope.isLoaded = false;
                    var urlToLoad = Config.BACKEND_SERVICE+'/platforms/deleteTask?platformTaskIdentifier='+hitId;
                    $http.get(urlToLoad).success(function (data, status, headers, config) {
                        if (data.success) {
                            Log.log('success', 'Successfully deleted task');
                            // Reload tasks
                            Platforms.getAllTasks(function(result){
                                if(result.success){
                                    $scope.tasks=result.data;
                                }
                                    $scope.isLoaded=true;
                            }
                            );
                        }
                        else {
                            if (data.errors != undefined) {
                                Log.log('error', 'Could not delete task: ' + data.errors.join());
                            }
                            else {
                                Log.log('error', 'Could not delete task');
                            }
                        }
                        $scope.taskIsLoaded = true;
                    }).error(function(data, status, headers, config) {
                        Log.log('error', 'Could not delete task: ' + data.errors.join());
                        $scope.taskIsLoaded = true;
                    });// End deleteTask call
                }// End result ok
            }// End handle result
        );// End dialog's then()
    };// End function

    //endregion Define the management functions


    //region Initialize the data
    $scope.getCsData = function(){
    // Get the current account balance
    Platforms.getAccountBalance(function(result){
        if(result.success){
        $scope.balance=result.data+'. Wow, you are soooo rich!';
        }
        else{
        $scope.balance=result.errors+'';// Array-to-string conversion
        }
    });

    // Get all tasks published on the crowd-sourcing platform
    Platforms.getAllTasks(function(result){
        if(result.success){
        $scope.tasks=result.data;
        }
        else{
        console.error('Could not fetch tasks.');
        }
    });
    }
    //endregion Initialize the data


    $scope.getCsData();



});

