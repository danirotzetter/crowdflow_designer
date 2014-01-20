/* Displays information for a workspace
* Parameters:
* details: 'short' or 'long'. Defaults to 'long'
 */
app.directive(
    'csWorkspaceInfo',
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

                        var infoContent='<div data-ng-hide="'+modelName+'==undefined">';
                        switch (details) {
                            case 'short':
                                infoContent += '<p>Workspace "{{' + modelName + '.name}}"</p>';
                                break;
                            case 'long':
                                infoContent += '<table border="0" class="taskMetadata table">' +
                                    '<tr>' +
                                    '<td>Workspace id</td>' +
                                    '<td>{{ ' + modelName + '.id }}</td>' +
                                    '</tr>' +
                                    '<td>Workspace name</td>' +
                                    '<td>{{' + modelName + '.name}}</td>' +
                                    '</tr>' +
                                    '<tr data-tooltip="To enable the tasks, click on the \'Workspace\' menu and select \'Activate\'">' +
                                    '<td>Is active</td>' +
                                    '<td><div data-ng-show="'+modelName+'.publish==1" class="textOk">Yes, tasks on platform are enabled</div><div data-ng-show="'+modelName+'.publish==0" class="textNotOk">No, tasks on crowd are invisible</div></td>' +
                                    '</tr>' +
                                    '<tr>' +
                                    '<td>Description</td>' +
                                    '<td>{{ ' + modelName + '.description }}</td>' +
                                    '</tr>' +
                                    '<tr>' +
                                    '<tr data-ng-hide="tasks==undefined">' +
                                    '<td>Composed of</td>' +
                                    '<td>' +
                                    '{{tasks.length}} tasks, {{datasources.length}} datasources, {{mergers.length}} mergers, {{splitters.length}} splitters, {{postprocessors.length}} postprocessors' +
                                    '</td>' +
                                    '</tr>' +
                                    '<tr>' +
                                    '<td>Creation date</td>' +
                                    '<td>{{' + modelName + '.date_created}}</td>' +
                                    '</tr>' +
                                    '</table>';
                                break;
                        }
                                    infoContent+='</div>';//End collapse if model undefined
            infoContent+='<div data-collapse="'+modelName+'!=undefined">None selected</div>';
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
