/* Displays information for the specified connection
 /* Displays information for the specified connection
 * Requires the ng-model of the item for which the form is defined.
 *
 * As a directive argument, the user can add parameters:
 * class: Optional class name
 * details: Indicates, how detailed the information should be. 'short', 'long'. Defaults to 'long'
 * toolbar: Add buttons to edit the item. 'true', 'false'. Defaults to 'false'.
 * displayFrom: Indicates if the connection source should be displayed. Defaults to 'true'
 * displayTo: Indicates if the connection destination should be displayed. Defaults to 'true'
 * displayToOld: Indicates if the old connection destination (from a connection that has a new destination) should be displayed. Defaults to 'true'
 * header: Indicates whether a header should be used (indicating 'Input' and 'Output'
 * (e.g. 'data-cs-item-info="{class='myClass'}")
 */
app.directive(
    'csConnectionInfo',
    function ($compile, Tools) {
        function link($scope, element, attributes) {
            var modelName = attributes.ngModel;
            var connection = $scope.$eval(modelName);

            // Read the directive parameters
            var parameters = $scope.$eval(attributes.csConnectionInfo);
            if (parameters == undefined)
                parameters = {}; // Prevents access to undefined when reading properties

            // Set default values for undefined parameters
            var details = parameters.details == undefined ? 'long' : parameters.details;
            var header = parameters.header == undefined ? true : parameters.header;
            var toolbar = parameters.toolbar == undefined ? false : parameters.toolbar;
            var displayFrom = parameters.displayFrom == undefined ? true : parameters.displayFrom;
            var displayTo = parameters.displayTo == undefined ? true : parameters.displayTo;
            var displayToOld = parameters.displayToOld == undefined ? true : parameters.displayToOld;


            // This property must be read after the previous item==undefined check, since we'll make use of the item.type property
            var itemInfoClass;
            if (parameters.class != undefined)
                itemInfoClass = parameters.class; // Set the provided class
            else
                itemInfoClass = ''; // Do not set any class


            var infoElement = angular.element(
                /*
                 * It is possible that the directive is applied to elements that are not yet defined.
                 * In this case, no html should be displayed.
                 *
                 */
                '<div data-ng-show="'+modelName+'==undefined"></div>'+
                    // Else, if there is a connection - display the connection details
                '<div data-ng-hide="'+modelName+'==undefined" ' + itemInfoClass + '></div>');


            if (details != 'none') {
                // Adding some information
                var infoContent = '';

                // Prepare the toolbar
                var toolbarContent =
                    '<div class="btn-group">' +
                        '<button class="btn" data-ng-click="editConnection(' + modelName + ', \'info\')"><i class="icon-info-sign"></i></button>' +
                        '<button class="btn" data-ng-click="editConnection(' + modelName + ', \'detach\')"><i class="icon-trash"></i></button>' +
                        '</div>';

                // Prepare the main content
                var detailsLong =details == 'long';
                if (displayFrom) {
//                    Display the source
                    if (header)
                        infoContent += '<h2>Input for this item</h2>';
                    infoContent +=
                        '<div data-cs-item-info="{'+(detailsLong?'':'class:false, ')+'details: \'' + details + '\'}" data-ng-model="' + modelName + '.from.item" data-ng-hide="' + modelName + '.from.item==undefined"/>'+
                        '<div data-ng-show="' + modelName + '.from.item==undefined">Not set</div>';
                }
                if (displayTo) {
//                    Display the destination
                    if (header)
                        infoContent += '<h2>Destination of item\'s output</h2>';
                    infoContent +=
                        '<div data-cs-item-info="{'+(detailsLong?'':'class:false, ')+'details: \'' + details + '\'}" data-ng-model="' + modelName + '.to.item"/>';
                }
                if (displayToOld) {
                    //If the connection is edited, then another item is involved, the "old" destination
                    infoContent +=
                        '<div data-ng-show="'+modelName+'.toOld!=undefined && '+modelName+'.toOld.item!=undefined && '+modelName+'.toOld.item.id!=undefined">';
                    if(header)
                    infoContent+='<h2>Old destination of type {{ ' + modelName + '.toOld.item.type }}</h2>';
                    infoContent+=
                            '<div data-cs-item-info="{'+(detailsLong?'':'class:false, ')+'details: \'' + details +
                            '\'}" data-ng-model="connection.toOld.item"/></div>' +
                            '</div>';// End ng-show
                }

                // Stick toolbar and content together into the final element
                if (toolbar)
                    infoContent += toolbarContent;
                infoElement.append(infoContent);

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
