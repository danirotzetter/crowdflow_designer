/**
 * Create an overlay for a connection
 * Note: connections are stored at each source. Using this directive, we can iterate through the connections of the element.
 * With the aid of the converter, we can get a reference to the plumb connection. Finally, we can instruct jsPlumb to add
 * the overlay to this connection.
 */
app.directive(
    'csConnection',
    function ($timeout,Tools, $compile, Config, Compose) {
        function link($scope, element, attributes) {
            var model = attributes.ngModel;

//Define the connector overlay
                var connectorOverlay = [ "Custom", {
                    create: function (component) {

                        var source = component.endpoints[0].getParameter('item');
                        var target = component.endpoints[1].getParameter('item');

                        var infoBoxDiv =
                            '<div>'+
                                '<div class="hoverPart">' +
                                '<h1>'+source.type+source.id+' -> '+target.type+target.id+'</h1>' +
                                '</div>' +
                                '<div class="nonHoverPart">' +
                                'Hover for details'+
                                '</div>' +
                                '<div class="hoverPart">' +
                                '<div class="btn-group">' +
                                '<button class="btn" data-ng-click="editConnection('+model+', \'info\')"><i class="icon-info-sign"></i></button>' +
                                '<button class="btn" data-ng-click="editConnection('+model+', \'detach\')"><i class="icon-trash"></i></button>' +
                                '</div>' +
                                '</div>' +
                                '</div>';
                        var e = angular.element(infoBoxDiv);
                        $compile(e)($scope);
                        return e;
                    },
                    location: 0.5,
                    cssClass: "overlayBox"
                }];

            //Get the actual angular model to work with
            var csConnection = $scope.$eval(model);
            // Retrieve the plumb connection where the overlay will be added
            var plumbConnection = Compose.csConnectionToPlumbConnection(csConnection);
            //Finally, add the element
            plumbConnection.addOverlay(connectorOverlay);
            Tools.refresh();



        }// End link function


        return ({
            link: link,
            restrict: 'A',
            require:'ngModel'
        });
    }// End timeout
);// And add directive
