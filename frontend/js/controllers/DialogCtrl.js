/**
 * Controller for the dialog
 */
app.controller('DialogCtrl', function($scope, dialog, options, textParams){

    // Read the properties
    $scope.title=textParams.title;
    $scope.text=textParams.text;

    $scope.buttons = options;

    /**
     * When the user has pressed a button
     * @param button The button pressed, containing all the required information (like id, text,...)
     */
    $scope.clicked = function(button){
        dialog.close(button);
    }

});

