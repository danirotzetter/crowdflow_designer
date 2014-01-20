/**
 * This is the controller that serves as a container for the entire application. It can handle global functionalities like user management etc.
 */
app.controller('AppCtrl', function($scope, $location){


    /**
     * JavaScript function to go to the specified path
     * @param path The path the application has to navigate to within the WebApp. Without '#'. Example: "link('/crowdsource/2')"
     */
    $scope.link = function ( path ) {
        $location.path( path );
    };
});