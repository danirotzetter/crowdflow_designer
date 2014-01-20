/**
 * The controller for managing items
 */
app.controller('ItemCtrl', function ($scope, $routeParams, Task, Merger, Splitter, Postprocessor, Datasource, dialog, action, item, connectionsIn) {


    //region Properties
    $scope.item=item;
    $scope.action=action;
    $scope.connectionsIn= connectionsIn;
    $scope.dialog=dialog; // Needed for access from child controllers (e.g. PostprocessorCtrl)
    $scope.parameters = Task.getAllParameters(true);// This is NOT a typo - the parameters for crowd-sourcing are defined in the task service

    /**
     * Gets the appropriate service, depending on the type. E.g. 'task'=>TaskService. Used for CRUD operations.
     */
    var type = item.type;
    var typeFirstLetterUppercase = type.charAt(0).toUpperCase() + type.slice(1);
    $scope.service = eval(typeFirstLetterUppercase);

    //region Set default parameters
    // Initialize the parameters object
    if($scope.item.parameters==null)
        $scope.item.parameters={};

    // Parse parameters if necessary
    if (typeof $scope.item.parameters == 'string' || $scope.item.parameters instanceof String){
        $scope.item.parameters = jQuery.parseJSON( $scope.item.parameters )
    }


    // Set default parameter values
    $.each($scope.parameters, function(idx, par){
        var itemsPar = $scope.item.parameters[par.id];
        if(itemsPar==undefined || itemsPar==''){
            // The user did not yet set a specific value: use the default value
            $scope.item.parameters[par.id]=par.defaultValue;
        }
        if(par.type=='number'){
            // By default, when loaded from the database, the values are still in 'string' format. However, to succeed the number test from angularJS, they must be parsed to a num
         $scope.item.parameters[par.id]=parseFloat($scope.item.parameters[par.id]);
        }
    });
    //endregion

// Update the 'description': set it to the title, if not overwritten
    $scope.$watch('item.name', function() {
        if(item.description==undefined || item.description=='' ||
            (item.name.indexOf(item.description)==0 && (item.name.length==item.description.length+1 || item.name.length==item.description.length+2)) ||//Length+2 to take into account space character
                (item.description.indexOf(item.name)==0 && (item.name.length==item.description.length-1 || item.name.length==item.description.length-2)) // When deleting characters
               ) {
            item.description=item.name;
        }
    });

    //endregion

    //region Functions
    /**
     * Save, i.e. edit/ create new
     * This function envelops the concrete implementation of the items service, i.e. this method is executed before calling the concrete save() method of the item like 'Task.save()'.
     * By doing so, we adjust properties of the item that are valid for all item types like setting the user and workspace id.
     */
    $scope.save = function(){
        if(action=='add'){
            // New item: instantiate general properties
            if(item.pos_x==undefined)
            item.pos_x=0;
            if(item.pos_y==undefined)
        item.pos_y=0;
            } // End instantiate new item properties


        $scope.service.save($scope.item,
            function(restResult){
            var item = restResult.data;
                // Restore the connections (which were not returned from the REST request)
                if($scope.item.connections!=undefined)
                item.connections=$scope.item.connections;
            dialog.close({success:restResult.success, data:{action:$scope.action, item:item}, errors:restResult.errors});
        });
    };
    /**
     * Delete
     */
    $scope.delete = function(){
        $scope.service.delete({id:$scope.item.id},
            function(restResult){
                //Success
                var item = restResult.data;
            dialog.close({success:restResult.success, data:{action:$scope.action, item:item}, errors:restResult.errors});
        });
    };
    /**
     * Do nothing at all
     */
    $scope.cancel= function(){
        dialog.close({success:true, data:{action:'cancel-'+$scope.action, item:$scope.item}});
    };
    //endregion


});// End controller