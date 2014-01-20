/* Displays information for the specified item.
 * Requires the ng-model of the item for which the form is defined.
 *
 * As a directive argument, the user can add parameters:
 * class: Optional class name that wraps the form. Defaults to the 'type' attribute of the item. If set to 'false', no class will be added.
 * ngClass: Optional class for the angular's ng-class directive
 * details: Indicates, how detailed the information should be. 'none', 'short', 'long', 'tooltipbox'. Defaults to 'short'. In case of 'none': just a div
 * with the appropriate class is created, which can be used for example as a rectangle without content to represent the item.
 * toolbar: Add buttons to edit the item. 'true', 'false'. Defaults to 'false'.
 * (e.g. 'data-cs-item-info="{class:'myClass'}")
 */
app.directive(
    'csItemInfo',
    function ($compile, Tools) {
        function link($scope, element, attributes) {
            // Read the directive parameters
            var parameters = $scope.$eval(attributes.csItemInfo);
            if (parameters == undefined)
                parameters = {}; // Prevents access to undefined when reading properties
            var modelName = attributes.ngModel;
            var item = $scope.$eval(modelName);
            var details = parameters.details == undefined ? 'short' : parameters.details;
            var toolbar = parameters.toolbar == undefined ? false : parameters.toolbar;

            /* It is possible that the directive is applied to elements that are not yet defined.
             * In this case, no html should be displayed.
             *
             * Example: in a csConnection, there is always an old destination item, i.e. the destination
             * of a connection that is about to be changed. But if the connection is not being changed,
             * then there is only one destination valid, and no old destination exists. However,
             * a html page may still be bound to an optional old destination, because the html template does
             * not yet know whether there is an old destination.
             */
            if (item == undefined) {
                element.append(angular.element('<div>item undefined</div>'));
                return;
            }

            // This property must be read after the previous item==undefined check, since we'll make use of the item.type property
            var itemInfoClass;
            if (parameters.class == undefined)
                itemInfoClass = ' class="' + item.type + '"'; // Set the type as class
            else if (parameters.class == false)
                itemInfoClass = ''; // Do not set any class
            else
                itemInfoClass = ' class="'+parameters.class+'"'; // Set the provided class

                // Additionally include the angular's class definition, if set
            if(parameters.ngClass!=undefined){
                itemInfoClass+=' data-ng-class="{'+parameters.ngClass+'}"';
            }

            var infoElement = angular.element(
                '<div' + itemInfoClass + '></div>');


            if (details != 'none') {
                // Adding some information
                switch (item.type) {
                    case 'task':
                        var infoContent;
                        var toolbarContent = '<div class="btn-group">' +
                            '<button class="btn btn-info" data-ng-click="editItem(' + modelName + ', \'edit\')" data-tooltip="Edit"><i class="icon-pencil"></i></button>' +
                            '<button class="btn btn-info" data-ng-click="editItem(' + modelName + ', \'delete\')" data-tooltip="Delete"><i class="icon-trash"></i></button>' +
                            '<button class="btn btn-info" data-ng-click="editItem(' + modelName + ', \'duplicate\')" data-tooltip="Duplicate"><i class="icon-plus"></i></button>' +
                            '</div>';
                        switch (details) {
                            case 'short':
                                infoContent = '<p>Task "{{' + modelName + '.name}}"</p>';
                                break;
                            case 'long':
                                infoContent = '<table class="table">' +
                                    '<thead>' +
                                    '<tr><th>Property</th><th>Value</th></tr>' +
                                    '</thead>' +
                                    '<tbody>' +
                                    '<tr><td>Task id</td><td>{{ ' + modelName + '.id}}</td></tr>' +
                                    '<tr><td>Name</td><td>{{ ' + modelName + '.name}}</td></tr>' +
                                    '<tr><td>Description</td><td>{{ ' + modelName + '.description}}</td></tr>' +


                                    // Do only show the parameters if the parameter definitions are loaded into the scope as 'parameters' variable (otherwize, we could not display the label of the parameter but only have actual values, which would be distracting)
                                    '<tr data-ng-show="parameters!=undefined && '+modelName+'.parameters!=undefined"><td>Parameters</td><td>' +
                                    '<div class="parametersTableDiv">' +
                                    '<table class="table parameters">' +
                                    '<thead>' +
                                    '<tr><th>Parameter</th><th>Value</th></tr></thead>' +
                                    '<tbody>' +
                                    '<tr data-ng-repeat="(parId, parVal) in ' + modelName + '.parameters track by $index"><td>{{ parameters[parId].label }}</td><td>{{ parVal }}</td></tr>' +
                                    '</tbody>' +
                                    '</table>' +
                                    '</div>' +

                                    // Do only show the parameters if the parameter definitions are loaded into the scope as 'platformData'
                                    '<tr data-ng-show="platformData!=undefined"><td>Crowdsource data</td><td>' +
                                    '<div class="platformDataTableDiv">' +
                                    '<table class="table platformData">' +
                                    '<thead>' +
                                    '<tr><th>Property</th><th>Value</th></tr></thead>' +
                                    '<tbody>' +
                                    '<tr data-ng-hide="pdVal==undefined || (pdVal==\'\' && pdVal!==false)" data-ng-repeat="(pdId, pdVal) in ' + modelName + '.platform_data track by $index"><td>{{ platformData[pdId].label }}</td><td>{{ pdVal }}</td></tr>' +
                                    '</tbody>' +
                                    '</table>' +
                                    '</div>' +


                                    '</td></tr>' +
                                    '</tbody>' +
                                    '</table>';
                                break;
                        }
                        if (toolbar)
                            infoContent += toolbarContent;
                        infoElement.append(infoContent);
                        break; //'task'
                    case 'postprocessor':
                        var infoContent;
                        var toolbarContent = '<div class="btn-group">' +
                            '<button class="btn btn-info" data-ng-click="editItem(' + modelName + ', \'edit\')" data-tooltip="Edit"><i class="icon-pencil"></i></button>' +
                            '<button class="btn btn-info" data-ng-click="editItem(' + modelName + ', \'delete\')" data-tooltip="Delete"><i class="icon-trash"></i></button>' +
                            '</div>';
                        switch (details) {
                            case 'short':
                                infoContent = '<p>Postprocessor "{{' + modelName + '.name}}"</p>';
                                break;
                            case 'tooltipbox':
                                infoContent = '<p><i class="icon-check"></i></p>';
                                var toolTip = '<div data-cs-item-info="{details:\'short\', class:false, toolbar:\'true\'}" data-ng-model="' + modelName + '"/>';
                                // In order to add a tooltip, we must have an angular element
                                infoContent = angular.element(infoContent);
                                infoElement = Tools.addTooltip(infoElement, toolTip);
                                break;
                            case 'long':
                                infoContent = '<table class="table">' +
                                    '<thead>' +
                                    '<tr><th>Property</th><th>Value</th></tr>' +
                                    '</thead>' +
                                    '<tbody>' +
                                    '<tr><td>Postprocessor id</td><td>{{ ' + modelName + '.id}}</td></tr>' +
                                    '<tr><td>Description</td><td>{{ ' + modelName + '.description}}</td></tr>' +


                                    // Do only show the parameters if the parameter definitions are loaded into the scope as 'parameters' variable (otherwize, we could not display the label of the parameter but only have actual values, which would be distracting)
                                    '<tr data-ng-show="parameters!=undefined && '+modelName+'.parameters!=undefined"><td>Parameters</td><td>' +
                                    '<div class="parametersTableDiv">' +
                                    '<table class="table parameters">' +
                                    '<thead>' +
                                    '<tr><th>Parameter</th><th>Value</th></tr></thead>' +
                                    '<tbody>' +
                                    '<tr data-ng-repeat="(parId, parVal) in ' + modelName + '.parameters track by $index"><td>{{ parameters[parId].label }}</td><td>{{ parVal }}</td></tr>' +
                                    '</tbody>' +
                                    '</table>' +
                                    '</div>' +

                                    // Do only show the parameters if the parameter definitions are loaded into the scope as 'platformData'
                                    '<tr data-ng-show="platformData!=undefined"><td>Crowdsource data</td><td>' +
                                    '<div class="platformDataTableDiv">' +
                                    '<table class="table platformData">' +
                                    '<thead>' +
                                    '<tr><th>Property</th><th>Value</th></tr></thead>' +
                                    '<tbody>' +
                                    '<tr data-ng-hide="pdVal==undefined || (pdVal==\'\' && pdVal!==false)" data-ng-repeat="(pdId, pdVal) in ' + modelName + '.platform_data track by $index"><td>{{ platformData[pdId].label }}</td><td>{{ pdVal }}</td></tr>' +
                                    '</tbody>' +
                                    '</table>' +
                                    '</div>' +
                                    '</td></tr>' +
                                    '</tbody>' +
                                    '</table>';
                                break;
                        }
                        if (toolbar)
                            infoContent += toolbarContent;
                        infoElement.append(infoContent);
                        break; //'postprocessor'


                    case 'merger':
                        var infoContent;
                        var toolbarContent = '<div class="btn-group">' +
                            '<button class="btn btn-info" data-ng-click="editItem(' + modelName + ', \'edit\')" data-tooltip="Edit"><i class="icon-pencil"></i></button>' +
                            '<button class="btn btn-info" data-ng-click="editItem(' + modelName + ', \'delete\')" data-tooltip="Delete"><i class="icon-trash"></i></button>' +
                            '</div>';
                        switch (details) {
                            case 'short':
                                infoContent = '<p>Merger "{{' + modelName + '.name}}"</p>';
                                break;
                            case 'tooltipbox':
                                infoContent = '<p><i class="icon-resize-small"></i></p>';
                                var toolTip = '<div data-cs-item-info="{details:\'short\', class:false, toolbar:\'true\'}" data-ng-model="' + modelName + '"/>';

                                // In order to add a tooltip, we must have an angular element
                                infoContent = angular.element(infoContent);
                                infoElement = Tools.addTooltip(infoElement, toolTip);
                                break;
                            case 'long':
                                infoContent = '<table class="table">' +
                                    '<thead>' +
                                    '<tr><th>Property</th><th>Value</th></tr>' +
                                    '</thead>' +
                                    '<tbody>' +
                                    '<tr><td>Merger id</td><td>{{ ' + modelName + '.id}}</td></tr>' +
                                    '<tr><td>Description</td><td>{{ ' + modelName + '.description}}</td></tr>' +


                                    // Do only show the parameters if the parameter definitions are loaded into the scope as 'parameters' variable (otherwize, we could not display the label of the parameter but only have actual values, which would be distracting)
                                    '<tr data-ng-show="parameters!=undefined && '+modelName+'.parameters!=undefined"><td>Parameters</td><td>' +
                                    '<div class="parametersTableDiv">' +
                                    '<table class="table parameters">' +
                                    '<thead>' +
                                    '<tr><th>Parameter</th><th>Value</th></tr></thead>' +
                                    '<tbody>' +
                                    '<tr data-ng-repeat="(parId, parVal) in ' + modelName + '.parameters track by $index"><td>{{ parameters[parId].label }}</td><td>{{ parVal }}</td></tr>' +
                                    '</tbody>' +
                                    '</table>' +
                                    '</div>' +

                                    // Do only show the parameters if the parameter definitions are loaded into the scope as 'platformData'
                                    '<tr data-ng-show="platformData!=undefined"><td>Crowdsource data</td><td>' +
                                    '<div class="platformDataTableDiv">' +
                                    '<table class="table platformData">' +
                                    '<thead>' +
                                    '<tr><th>Property</th><th>Value</th></tr></thead>' +
                                    '<tbody>' +
                                    '<tr data-ng-hide="pdVal==undefined || (pdVal==\'\' && pdVal!==false)" data-ng-repeat="(pdId, pdVal) in ' + modelName + '.platform_data track by $index"><td>{{ platformData[pdId].label }}</td><td>{{ pdVal }}</td></tr>' +
                                    '</tbody>' +
                                    '</table>' +
                                    '</div>' +
                                    '</td></tr>' +
                                    '</tbody>' +
                                    '</table>';
                                break;
                        }
                        if (toolbar)
                            infoContent +=
                                toolbarContent;
                        infoElement.append(infoContent);
                        break; //'merger'

                    case 'splitter':
                        var infoContent;
                        var toolbarContent = '<div class="btn-group">' +
                            '<button class="btn btn-info" data-ng-click="editItem(' + modelName + ', \'edit\')" data-tooltip="Edit"><i class="icon-pencil"></i></button>' +
                            '<button class="btn btn-info" data-ng-click="editItem(' + modelName + ', \'delete\')" data-tooltip="Delete"><i class="icon-trash"></i></button>' +
                            '</div>';
                        switch (details) {
                            case 'short':
                                infoContent = '<p>Splitter "{{' + modelName + '.name}}"</p>';
                                break;
                            case 'tooltipbox':
                                infoContent = '<p><i class="icon-resize-full"></i></p>';
                                var toolTip = '<div data-cs-item-info="{details:\'short\', class:false, toolbar:\'true\'}" data-ng-model="' + modelName + '"/>';
                                // In order to add a tooltip, we must have an angular element
                                infoContent = angular.element(infoContent);
                                infoElement = Tools.addTooltip(infoElement, toolTip);
                                break;
                            case 'long':
                                infoContent = '<table class="table">' +
                                    '<thead>' +
                                    '<tr><th>Property</th><th>Value</th></tr>' +
                                    '</thead>' +
                                    '<tbody>' +
                                    '<tr><td>Splitter id</td><td>{{ ' + modelName + '.id}}</td></tr>' +
                                    '<tr><td>Description</td><td>{{ ' + modelName + '.description}}</td></tr>' +


                                    // Do only show the parameters if the parameter definitions are loaded into the scope as 'parameters' variable (otherwize, we could not display the label of the parameter but only have actual values, which would be distracting)
                                    '<tr data-ng-show="parameters!=undefined && '+modelName+'.parameters!=undefined"><td>Parameters</td><td>' +
                                    '<div class="parametersTableDiv">' +
                                    '<table class="table parameters">' +
                                    '<thead>' +
                                    '<tr><th>Parameter</th><th>Value</th></tr></thead>' +
                                    '<tbody>' +
                                    '<tr data-ng-repeat="(parId, parVal) in ' + modelName + '.parameters track by $index"><td>{{ parameters[parId].label }}</td><td>{{ parVal }}</td></tr>' +
                                    '</tbody>' +
                                    '</table>' +
                                    '</div>' +

                                    // Do only show the parameters if the parameter definitions are loaded into the scope as 'platformData'
                                    '<tr data-ng-show="platformData!=undefined"><td>Crowdsource data</td><td>' +
                                    '<div class="platformDataTableDiv">' +
                                    '<table class="table platformData">' +
                                    '<thead>' +
                                    '<tr><th>Property</th><th>Value</th></tr></thead>' +
                                    '<tbody>' +
                                    '<tr data-ng-hide="pdVal==undefined || (pdVal==\'\' && pdVal!==false)" data-ng-repeat="(pdId, pdVal) in ' + modelName + '.platform_data track by $index"><td>{{ platformData[pdId].label }}</td><td>{{ pdVal }}</td></tr>' +
                                    '</tbody>' +
                                    '</table>' +
                                    '</div>' +
                                    '</td></tr>' +
                                    '</tbody>' +
                                    '</table>';
                                break;
                        }
                        if (toolbar)
                            infoContent +=
                                toolbarContent;
                        infoElement.append(infoContent);
                        break; //'splitter'

                    case 'datasource':
                        var infoContent;
                        var toolbarContent = '<div class="btn-group">' +
                            '<button class="btn btn-info" data-ng-click="editItem(' + modelName + ', \'edit\')" data-tooltip="Edit"><i class="icon-pencil"></i></button>' +
                            '<button class="btn btn-info" data-ng-click="editItem(' + modelName + ', \'delete\')" data-tooltip="Delete"><i class="icon-trash"></i></button>' +
                            '</div>';
                        switch (details) {
                            case 'tooltipbox':
                                infoContent = '<p><i class="icon-screenshot"></i></p>';
                                var toolTip = '<div data-cs-item-info="{details:\'short\', class:false, toolbar:\'true\'}" data-ng-model="' + modelName + '"/>';
                                // In order to add a tooltip, we must have an angular element
                                infoContent = angular.element(infoContent);
                                infoElement = Tools.addTooltip(infoElement, toolTip);
                                break;
                            case 'short':
                                infoContent = '<p>Datasource "{{' + modelName + '.name}}"</p>';
                                break;
                            case 'long':
                                infoContent = '<p>Datasource id: {{' + modelName + '.id}}</p>' +
                                    '<p>Datasource name: {{' + modelName + '.name}}</p>' +
                                    '<p>Datasource description: {{' + modelName + '.description}}</p>';
                                break;
                        }
                                if (toolbar)
                                    infoContent +=
                                        toolbarContent;

                        infoElement.append(infoContent);
                        break; //'datasource'
                    case 'workspace':
                        var infoContent;
                        var toolbarContent = '<div class="btn-group">' +
                            '<button class="btn btn-info" data-ng-click="showResults()" data-tooltip="Show results"><i class="icon-info-sign"></i></button>' +
                            '</div>';
                        switch (details) {
                            case 'tooltipbox':
                                infoContent = '<p><i class="icon-flag icon-white"></i></p>';
                                var toolTip = '<div data-cs-item-info="{details:\'short\', class:false, toolbar:\'true\'}" data-ng-model="' + modelName + '"/>';
                                // In order to add a tooltip, we must have an angular element
                                infoContent = angular.element(infoContent);
                                infoElement = Tools.addTooltip(infoElement, toolTip);
                                break;
                            case 'short':
                                infoContent = '<p>Final result of workspace {{ ' + modelName + '.id }}: {{' + modelName + '.name}}</p>';
                                break;
                            case 'long':
                                infoContent = '<p>Final result of workspace {{' + modelName + '.id}}</p>' +
                                    '<p>Workspace name: {{' + modelName + '.name}}</p>' +
                                    '<p>Workspace description: {{' + modelName + '.description}}</p>';
                                break;
                        }
                        if (toolbar)
                            infoContent +=
                                toolbarContent;
                        infoElement.append(infoContent);
                        break; //'datasource'
                }
            }// End has not NONE details

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
