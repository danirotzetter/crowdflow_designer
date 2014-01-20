/**
 * The controller for managing a merger
 */
app.controller('MergerCtrl', function ($scope, $routeParams, Merger, Task, Tools, Config) {

    //region Scope variables definition
    $scope.mergerTypes = Merger.getAllMergerTypes();
    $scope.mergerType={};
    //endregion

    //region Fill in default values
    if($scope.item.merger_type_id==undefined){
    // No merger type set yet : as default, use a web service
        $scope.item.merger_type_id=31;
    }
    //endregion Fill in default values


    // If the merger data is a number, parse it
    if(!isNaN($scope.item.data)){
        $scope.item.data=parseInt($scope.item.data);
    }

        // Retrieve the appropriate merger type object. The reason is that in the database, only the id is stored, but to display the form, the entire JSON object is needed. Hence, the subType's id is replaced with the entire subType object
        $.each($scope.mergerTypes, function(index, type){
            $.each(type.subTypes, function(idx, subType){
            if(subType.id==$scope.item.merger_type_id)
                $scope.mergerType = type;
            });// End for each subType
        });// End for each mergerType


    // Adjust some settings, depending on the selected task type
    $scope.$watch('item.merger_type_id', function() {
        var type = parseInt($scope.item.merger_type_id);
        switch(type){
            case 12:
                // Crowd-sourced voting
                if($scope.item.data==undefined || $scope.item.data==''){
                    $scope.item.data=5; // Take the most-voted answer after so many crowd-sourced selections
                }
                break;
            case 25:
                // Majority voting
                if($scope.item.data==undefined || $scope.item.data=='' || isNaN($scope.item.data)){
                    $scope.item.data=70; //Threshold in percent
                }
                break;
            case 31:
                // Web-service
                if($scope.item.data==undefined || $scope.item.data==''){
                    $scope.item.data='e.g. Merger/process?type=filter&filterattribute=categoryid&filtervalue=1&dataattribute=original or  Merger/process?type=concatenate&threshold=4';
                }
                break;
        }
    });


});// End controller