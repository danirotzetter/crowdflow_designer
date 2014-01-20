/**
 * The controller for task management
 */
app.controller('WorkspaceManagementCtrl', function ($scope, $routeParams, Task, Workspace, Macrotask, $dialog, Log, Tools) {


    //region Principal task management: handles listing and selecting the principal tasks, i.e. tasks with no parent

    // Properties
    $scope.workspaces = {};
    $scope.workspace={};// The currently selected workspace
    $scope.loadComplete=false;
    $scope.waitMessage = 'Retrieving available workspaces from database...';

    Log.clearMessages();
    $scope.messages=Log.messages;// Used by the logging service



    //region Functions
    /**
     * Retrieve all tasks
     */
    Workspace.query(function(data, status){
        $scope.workspaces = data.data;
        $scope.loadComplete=true;
    });
    /**
     * Selects a task for further treatment
     * @param workspace
     */
    $scope.selectWorkspace= function(workspaceNew) {
        if($scope.workspace==workspaceNew)
            // De-select
            $scope.workspace={};
        else{
            $scope.workspace = workspaceNew;
        }
    };


    /**
     * Operations on the workspace
     * @param action 'add', 'delete', 'edit'
     */
    $scope.editWorkspace= function (action) {
        // Reset the selected workspace, if a new one must be created
        if(action=='add')
        $scope.workspace={};


        // Define the dialog options
        var dialogOptions = {
            controller: 'WorkspaceCtrl',
            templateUrl: 'partials/edit-workspace.html',
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
                        workspace: $scope.workspace
                    }
                }
            )
        );
        // Open/ call the dialog
        dialog
            .open().then(
            function (result) {
                if(result==undefined){
                    // User aborted through escape key
                    return;
                }
                if (result.success) {
                    var workspaceNew = result.data.workspace;

                    switch (result.data.action) {
                        case 'add':
                            Log.log('success', 'New workspace created');
                            $scope.workspace=workspaceNew;
                            $scope.workspaces.push(workspaceNew);
                            break;
                        // End add
                        case 'edit':
                            Log.log('success', 'Workspace edited');
                            $scope.workspace=workspaceNew;
                            // Replace the edited workspace in the list
                            for (var i = 0; i < workspaces.length; i++)
                                if ($scope.workspaces[i].id == workspaceNew.id)
                                    $scope.workspaces[i]=workspaceNew;
                            break;
                        //End edit
                        case 'delete':
                            // Remove the deleted workspace in the list
                            for (var i = 0; i < $scope.workspaces.length; i++)
                                if ($scope.workspaces[i].id == workspaceNew.id){
                                    $scope.workspaces.splice(i, 1);
                                    $scope.workspace={};
                                    break;
                                }
                            Log.log('success', 'Workspace deleted');
                            break;
                        //End delete
                        case 'cancel-edit':
                            Log.log('info', 'Edit cancelled');
                            break;
                        case 'cancel-delete':
                            Log.log('info', 'Delete cancelled');
                            break;
                    }//End switch result
                }// End success
                else {
                    // Error detected
                    var errorMsg = 'Could not add entity.';
                    if (result.errors != undefined) {
                        // Append additional information
                        errorMsg += ' ' + result.errors[0];
                    }
                    Log.log('error', errorMsg);
                }// End failure
            }// End treat dialog result
        );// End open dialog
        Tools.refresh();
    };//End function edit workspace


    //endregion End functions

    //endregion


});// End controller