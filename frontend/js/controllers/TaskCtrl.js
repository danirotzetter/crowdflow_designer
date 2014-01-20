/**
 * The controller for managing a task
 */
app.controller('TaskCtrl', function ($scope, $routeParams, Task, Tools) {

    //region Scope variables definition
    $scope.taskTypes = Task.getAllTaskTypes();
    $scope.outputMediaTypes = Tools.getAllMediaTypes();
    $scope.outputMappingTypes = Task.getAllOutputMappingTypes();
    //endregion


    //region Set default values if not set
    if($scope.item.task_type_id==undefined)
    $scope.item.task_type_id=2;
    if($scope.item.output_media_type_id==undefined)
    $scope.item.output_media_type_id=2;
    if($scope.item.output_ordered==undefined)
    $scope.item.output_ordered=0;
    if($scope.item.output_determined==undefined)
    $scope.item.output_determined=0;
    if($scope.item.output_mapping_type_id==undefined)
    $scope.item.output_mapping_type_id=12;
    //endregion


    // Adjust some settings, depending on the selected task type
    $scope.$watch('item.task_type_id', function() {
        var type = parseInt($scope.item.task_type_id);
        switch(type){
            case 1:
                // Categorization
                if($scope.item.data==undefined || $scope.item.data=={}){
                    // Prepare the input fields such that the options can be defined
                    $scope.item.data=[];
                    for(var i=0; i<7; i++){
                        $scope.item.data.push('');
                    }
                }

                // Select the appropriate properties
                $scope.item.output_media_type_id=3;
                $scope.item.output_determined=1;
                $scope.item.output_mapping_type_id=12;
                break;
            case 2:
                // Data transformation
                $scope.item.output_media_type_id=2;
                $scope.item.output_determined=0;

                break;
            case 3:
                // Data collection

                break;
            case 4:
                // External task

                break;

        }
    });


});// End controller