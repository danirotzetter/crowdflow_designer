app.controller('HomeCtrl', function($scope, $routeParams, Platforms, $http, Config){

    $scope.serverInfo = 'N/A';

    $http.get(Config.BACKEND_SERVICE+'site/about').success(function(data, status, headers, config){
        $scope.serverInfo=data;
    })
});

