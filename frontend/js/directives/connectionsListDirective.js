/**
 * Display the information for connections of a series of items
 * As an attribute, a json object can be supplied with the form
 * {name: <The name that should be displayed in the header>
 *     }
 */
app.directive(
    'csConnectionsList',
    function ($timeout, Tools, $compile, Config, Compose) {
        function link($scope, element, attributes) {

            var model = attributes.ngModel;

            // Read the parameters
            var parameters = $scope.$eval((attributes.csConnectionsList));


            // Define the element, i.e. the content of the connections list
            var content = {};
            // Check for parameters validity
            if (parameters == undefined) {
                // Invalid parameters
                console.warn('Cannot build list of items - parameters are invalid');
                content = angular.element('<div>Invalid</div>');
            }
            else {
                // Valid parameters
                var connectionsName = parameters.name;
                content = angular.element('' +
                    '<div class="connections ' + connectionsName + '">' +
                    '<h3>' + connectionsName + '</h3>' +
                    '<div data-ng-repeat="item in ' + model + '"' +
                        '>' +
                        //'<h4>{{item.type}} {{item.id}} ({{item.connections.length}} connections)</h4>' +
                        '<h4>{{item.type}} {{item.id}}</h4>' +
                            '<div data-ng-repeat="connection in item.connections" class="connection">' +
                                'To {{connection.to.item.type}} of id {{connection.to.item.id}}' +
                            '</div>' + // End repeat connections
                    '<div data-ng-show="item.connections.length==0">&lt;No connections&gt;</div>'+
                    '</div>' + // end repeat items
                    '<div data-ng-show="'+model+'.length==0">&lt;No items&gt;</div>'+
                    '</div>'// End connections class
                );
            }// End parameters are valid


            // Compile the form element
            var compiled = $compile(content);

            // Add the info to the directive's element
            element.append(content);

            //Finally apply the compiled element to the scope
            compiled($scope);

        }// End link function


        return ({
            link: link,
            restrict: 'A',
            require: 'ngModel'
        });
    }// End timeout
);// And add directive
