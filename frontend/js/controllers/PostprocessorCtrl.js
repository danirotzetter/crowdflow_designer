/**
 * The controller for managing a postprocessor
 */
app.controller('PostprocessorCtrl', function ($scope, $routeParams, Postprocessor, Tools) {

    //region Scope variables definition
    $scope.postprocessorTypes = Postprocessor.getAllPostprocessorTypes();
    $scope.postprocessorType = {};
    $scope.validationTypes = Postprocessor.getAllValidationTypes();
    $scope.validationType = {};
    //endregion


    //region Postprocessor type and subtypes
    if ($scope.item.postprocessor_type_id == undefined) {
        // No type set yet : set default
        $scope.item.postprocessor_type_id = 12;
    }
    $.each($scope.postprocessorTypes, function (index, type) {
        $.each(type.subTypes, function (idx, subType) {
            if (subType.id == $scope.item.postprocessor_type_id){
                $scope.postprocessorType = type;
            }
        });// End for each subType
    });// End for each type
    //endregion

    //region Validation type
    if ($scope.item.validation_type_id == undefined) {
        // No type set yet : set default
        $scope.item.validation_type_id = 1;
    }
    $.each($scope.validationTypes, function (index, type) {
        if (type.id == $scope.item.validation_type_id)
            $scope.validationType = type;
    });// End for each type


    /**
     * Different behaviour for saving the postprocessor
     */
    $scope.save = function () {
        if ($scope.action == 'add') {
            // New item: instantiate general properties
            $scope.item.pos_x = 0;
            $scope.item.pos_y = 0;
        } // End instantiate new item properties


        // Store the postprocessor in the database
        Postprocessor.save($scope.item,
            function (restResult) {
                var item = restResult.data;
                // Restore the connections (which were not returned from the REST request)
                if($scope.item.connections!=undefined)
                    item.connections=$scope.item.connections;
                $scope.dialog.close({success: restResult.success, data: {action: $scope.action, item: item}, errors: restResult.errors});
            });
    }

//endregion

});// End controller