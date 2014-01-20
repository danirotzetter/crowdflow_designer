//Inserts a log info box at the element's position
app.directive(
    'csLog',
    function ($compile) {
        function link($scope, element, attributes) {
            var parameters = $scope.$eval(attributes.csLog);

            var infoBoxElement;
            if(parameters.type=='log'){
//                Shows all messages stored in the current scope in the 'messages' variable
            infoBoxElement = angular.element(
                '<div class="infoBox">' +
                    '<h3>Message log</h3>' +
                    '<div class="messages">' +
                    '<div data-ng-repeat="message in messages" >' +
                    '<span>{{message.content}}</span>' +
                    '<span style="float:right;">{{message.time | date:\'HH:mm:ss\'}}</span>'+
                    '</div>' +
                    '</div>' +
                    '</div>'
            );
            }

            else if(parameters.type=='alert'){
                // When the user closes the message, the type will be set to 'dismissed' and hence the class to 'alert-dismissed', such that the element can be hidden in css
            // Using last message
            // infoBoxElement = angular.element('<div class="message alert alert-{{message.type}}" data-ng-repeat="message in messages.slice(-1)"><button data-ng-click="message.type=\'dismissed\';" type="button" class="close" data-dismiss="alert">&times;</button>{{message.content}}</div>');
            // Using first message
            infoBoxElement = angular.element('<div class="message alert alert-{{message.type}}" data-ng-repeat="message in messages.slice(0, 1)"><button data-ng-click="message.type=\'dismissed\';" type="button" class="close" data-dismiss="alert">&times;</button>{{message.content}}</div>');
            }

            // Compile the infoBox element
            var compiled = $compile(infoBoxElement);
            // Replace the old element with the enhanced log infoBox
            element.replaceWith(
                infoBoxElement
            );
            //Finally apply the compiled element to the scope
            compiled($scope);
        }// End link function

        return ({
            link: link,
            restrict: 'A'
        });
    }// End timeout
);// And add directive
