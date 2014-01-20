/**
 * The controller for managing compose connections
 * action: 'establish', 'detach', 'change', 'edit', 'info'
 */
app.controller('ConnectionCtrl', function ($scope, $routeParams, dialog, csConnection, action) {


    //region Properties
    $scope.connection = csConnection;
    $scope.action=action;
    //endregion

    //region Functions
    /**
     * Establish a new connection
     */
    $scope.close = function(confirm){
        dialog.close({action:confirm? action:'cancel-'+action, connection:$scope.connection});
    };
//endregion


});// End controller