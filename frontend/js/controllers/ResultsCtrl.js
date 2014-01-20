/**
 * The controller for dealing with workspace results
 */
app.controller('ResultsCtrl', function ($scope, $routeParams, $http, Workspace, Tools, Config, Log, Macrotask, dialog, workspace) {

    $scope.workspace = workspace != undefined ? workspace : {};
    $scope.results={};
    $scope.flowData={};
    $scope.isLoaded=false;
    $scope.downloadUrl='';
    $scope.loadingMessage='The results for workspace are being<br/>downloaded from the server, please wait...';
    $scope.detailed=1; // Whether also result metadata should be displayed (as opposed to only show the 'data' attribute)

    // Logging handling
    Log.clearMessages();
    $scope.messages = Log.messages;


    /**
     * Adjust the results if the detail level was changed
     */
    $scope.$watch('detailed', function() {
        $scope.reloadData();
    });

    /**
     * Download the data/ the results from the server according to the set detail level
     */
    $scope.reloadData =function(){
    // Load the results
    $scope.downloadUrl = Config.BACKEND_SERVICE+'Workspace/'+$scope.workspace.id+'/results?clean='+($scope.detailed=='0'? 'true':'false');

    $http.get($scope.downloadUrl).success(function (data, status, headers, config) {
        if (data.success){
            // Could successfully retrieve the results
            $scope.results=data.data.results;
            $scope.flowData=data.data.flowData;
            $scope.isLoaded=true;

            // Create the download
            var blob = new Blob([ JSON.stringify(data.data) ], { type : 'text/plain' });
            $scope.downloadUrl = URL.createObjectURL( blob );

        }
        else{
            // An error occurred
            $scope.isLoaded=true;
            Log.log('error', 'The server reported an error when preparing the results: '+data.errors.join(', '));
        }
    }).error(function (data, status, headers, config) {
            $scope.isLoaded=true;
            // An error occurred while retrieving the results
            Log.log('error', 'Failed to send request to the server using the url '+urlToLoad);
        });
    }


    //region Functions
    /**
     * Close the results dialog
     */
    $scope.close = function () {
         dialog.close();
    };
    //endregion


});// End controller