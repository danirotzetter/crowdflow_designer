/**
 * The controller for managing a worksapce
 */
app.controller('WorkspaceCtrl', function ($scope, $routeParams, Workspace, Tools, Macrotask, dialog, action, workspace) {

    $scope.workspace = workspace != undefined ? workspace : {};
    $scope.action = action;
    $scope.macrotasks = {};
    $scope.newMacrotask = 'true';// By default: create new macrotask

    // Update the 'description': set it to the title, if not overwritten
    $scope.$watch('workspace.name', function() {
        if(workspace.description==undefined || workspace.description=='' ||
            (workspace.name.indexOf(workspace.description)==0 && (workspace.name.length==workspace.description.length+1 || workspace.name.length==workspace.description.length+2)) ||//Length+2 to take into account space character
            (workspace.description.indexOf(workspace.name)==0 && (workspace.name.length==workspace.description.length-1 || workspace.name.length==workspace.description.length-2)) // When deleting characters
            ) {
            workspace.description=workspace.name;
        }
    });
// Update the 'description': set it to the title, if not overwritten
    $scope.$watch('workspace.macrotask.name', function() {
        if(workspace.macrotask.description==undefined || workspace.macrotask.description=='' ||
            (workspace.macrotask.name.indexOf(workspace.macrotask.description)==0 && (workspace.macrotask.name.length==workspace.macrotask.description.length+1 || workspace.macrotask.name.length==workspace.macrotask.description.length+2)) ||//Length+2 to take into account space character
            (workspace.macrotask.description.indexOf(workspace.macrotask.name)==0 && (workspace.macrotask.name.length==workspace.macrotask.description.length-1 || workspace.macrotask.name.length==workspace.macrotask.description.length-2)) // When deleting characters
            ) {
            workspace.macrotask.description=workspace.macrotask.name;
        }
    });

    // Adjust some settings, depending on the selected task type
    $scope.$watch('selectedMacrotaskId', function (newVal, oldVal) {
        if (newVal != undefined && oldVal != undefined && newVal == oldVal) {
            return;
        }
        $scope.updateMacrotaskSelection(newVal);
    });

    // Initialize some default properties
    if(jQuery.isEmptyObject($scope.workspace)){
        $scope.workspace.publish=0;
        $scope.workspace.isNew=true;
    }

    //region MacroTask handling
    $scope.selectedMacrotaskId = 0; // Used for select2, since binding select2's model to the workspace id would not set the corresponding macroTask in the $scope.workspace.macroTask element

    /**
     * Adjusts the selection of the workspace's macrotask
     * @param id The macrotask's id that is selected
     */
    $scope.updateMacrotaskSelection=function(id){
        if(id==undefined || ($scope.workspace.macrotask!=undefined && $scope.workspace.macrotask.id==id && $scope.selectedMacrotaskId==id)) return;
        // Retrieve the actual macroTask object in order to set it in the workspace object
        $.each($scope.macrotasks, function (index, item) {
            if (item.id == id) {
                $scope.workspace.macrotask = $scope.macrotasks[index];
                $scope.selectedMacrotaskId = $scope.workspace.macrotask.id;
                return false;
            }
        });
    }
    //Get the list of macrotasks
    Macrotask.query(function (result) {
        if (result.success) {
            $scope.macrotasks = result.data;
            if($scope.workspace!=undefined && $scope.workspace.macrotask!=undefined)
                $scope.updateMacrotaskSelection($scope.workspace.macrotask.id);
        }
    }, function (result) {
        console.warn('Could not retrieve the list of macrotasks');
    });
    //endregion



    //region Functions
    /**
     * Save, i.e. edit/ create new
     */
    $scope.save = function () {


        // Must save the macrotask first (to get the correct macrotask id used as reference in the workspace)
        Macrotask.save($scope.workspace.macrotask, function (restMacrotaskResult) {
            $scope.workspace.macrotask = restMacrotaskResult.data;
            $scope.workspace.macrotask_id = $scope.workspace.macrotask.id;
            // Only now we can save the workspace
            Workspace.save($scope.workspace,
                function (restResult) {
                    var workspace = restResult.data;
                    // Re-fetch the macrotask object since the result of a save procedure in the backend only returns the attributes, i.e. the macrotask's id
                    // TODO check if this adjustments must also be done for different objects after create()
                    workspace.macrotask = $scope.workspace.macrotask;
                    var allSuccess = restResult.success && restMacrotaskResult.success;
                    var allErrors = $.merge(restResult.errors == undefined ? [] : restResult.errors, restMacrotaskResult.errors == undefined ? [] : restMacrotaskResult.errors);
                    dialog.close({success: allSuccess, data: {action: $scope.action, workspace: workspace}, errors: allErrors});
                });// End save macrotask
        });// End save workspace
    };
    /**
     * Delete
     */
    $scope.delete = function () {
        Workspace.delete({id: $scope.workspace.id},
            function (restResult) {
                //Success
                var item = restResult.data;
                dialog.close({success: restResult.success, data: {action: $scope.action, workspace: workspace}, errors: restResult.errors});
            });
    };
    /**
     * Do nothing at all
     */
    $scope.cancel = function () {
        dialog.close({success: true, data: {action: 'cancel-' + $scope.action, workspace: $scope.workspace}});
    };
    //endregion


});// End controller