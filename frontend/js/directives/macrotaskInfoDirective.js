/* Displays information for a macrotask
* Parameters:
* details: 'short' or 'long'. Defaults to 'long'
 */
app.directive(
    'csMacrotaskInfo',
    function ($compile, Tools) {
        function link($scope, element, attributes) {

            // Read the directive parameters
            var parameters = $scope.$eval(attributes.csItemInfo);
            if (parameters == undefined)
                parameters = {}; // Prevents access to undefined when reading properties
            var modelName = attributes.ngModel;
            var item = $scope.$eval(modelName);
            var details = parameters.details == undefined ? 'long' : parameters.details;


            var infoElement = angular.element(
                    '<div></div>');
                        var infoContent='<div data-collapse="'+modelName+'==undefined">';
                        switch (details) {
                            case 'short':
                                infoContent += '<p>Macrotask "{{' + modelName + '.name}}"</p>';
                                break;
                            case 'long':
                                infoContent += '  <table border="0" class="taskMetadata table">' +
                                    '<tr>' +
                                    '<td>Name</td>' +
                                    '<td>{{' + modelName+ '.name}}</td>' +
                                    '</tr>' +
                                    '<tr>' +
                                    '<td>Description</td>' +
                                    '<td>{{' + modelName+ '.description}}</td>' +
                                    '</tr>' +
                                    '<tr>' +
                                    '<td>Creation date</td>' +
                                    '<td>{{' + modelName+ '.date_created}}</td>' +
                                    '</tr>' +
                                    '</table>';
                                break;
                        }
                                    infoContent+='</div>';//End collapse if model undefined
            infoContent+='<div data-collapse="'+modelName+'!=undefined">No macrotask selected</div>';
                        infoElement.append(infoContent);

            // Compile the form element
            var compiled = $compile(infoElement);

            // Add the info to the directive's element
            element.append(infoElement);

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
