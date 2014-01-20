/**
 * The controller for managing an datasource
 */
app.controller('DatasourceCtrl', function ($scope, $routeParams, Datasource, Tools) {

    //region Scope variables definition
    $scope.datasourceTypes = Datasource.getAllDatasourceTypes();

    $scope.datasourceMediaTypes = Tools.getAllMediaTypes();
    //endregion


    //region Set default values if not set
    if($scope.item.datasource_type_id==undefined)
    $scope.item.datasource_type_id=5;
    if($scope.item.output_media_type_id==undefined)
    $scope.item.output_media_type_id=2;
    if($scope.item.output_ordered==undefined)
    $scope.item.output_ordered=1;
    if($scope.item.output_determined==undefined)
    $scope.item.output_determined=1;
    if($scope.item.items_count==undefined)
    $scope.item.items_count=10;
    else
    $scope.item.items_count=parseInt($scope.item.items_count);
    //endregion


    // Adjust some settings, depending on the selected task type
    $scope.$watch('item.datasource_type_id', function() {
        var type = parseInt($scope.item.datasource_type_id);
        switch(type){
            case 3:
                // Webservice access
                $scope.item.output_determined=0;
                $scope.item.output_media_type_id=5; // Select URL as medium
                // Set default url
                if($scope.item.data==undefined || $scope.item.data=='')
                    $scope.item.data='http://www.example.com';
                break;
            case 5:
                // Text form field
                $scope.item.output_determined=1;
                $scope.item.output_media_type_id=2; // Select text area as medium
                break;
        }
    });
});// End controller