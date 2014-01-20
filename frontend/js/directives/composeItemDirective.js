/**
 * Describes an item on the composer interface.
 * E.g. a task is wrapped into a rectangle, containing the binding for displaying the appropriate information (like Id, name etc.).
 * In this directive, the endpoint for JsPlumb are added.
 * The directive arguments are of the form {endpoints:[{type:'in', name:'name'},...]}.
 * Defaults to [{type:'in', name:'in'}, {type:'out', name:'out'}], i.e. one in and one out endpoint
 */
app.directive(
    'csComposeItem',
    function ($timeout, Tools, $compile, Config, Log, Task, Datasource, Splitter, Merger, Workspace, Postprocessor) {
        function link($scope, element, attributes) {
            var parameters = $scope.$eval(attributes.csComposeItem);
            var model = attributes.ngModel;

            //Using timeout: delay function s.t. the DOM has been loaded
            $timeout(function () {

                // Initialize item properties
                var item = $scope.$eval(attributes.ngModel);
                if (item.connections == undefined)
                    item.connections = [];

                var type = item.type;


                // Now, add endpoints to the element that can be connected
                var endpointProperties = Config.jsPlumb.endpoints; // In this object, all endpoint properties are stored in a JSON variable


                // Initialize endpoints to undefined, if no parameters are provided (needed for the subsequent undefined check to define the defaults)
                var endpoints = parameters == undefined ? undefined : parameters.endpoints;
                if (endpoints == undefined) {
                    // Set defaults
                    endpoints = [
                        {type: 'in', name: 'in'},
                        {type: 'out', name: 'out'}
                    ];
                }
                // Then initialize each endpoint that is defined
                for (var i = 0; i < endpoints.length; i++) {
                    /*
                     For each endpoint, add the corresponding parameters which are defined in the ConfigService.
                     For example the merger out endpoints are defined in the path 'endpointProperties.merger.out'
                     */
                    var endpoint = endpoints[i];
                    var name = endpoint.name;
                    var type = endpoint.type;
                    var defaultEndpointTypeProperties = eval('endpointProperties.' + item.type + '.' + name); // Fetches the properties for the current type of endpoint. The full path includes the item type.
                    var anchor = defaultEndpointTypeProperties.anchor; // Where the endpoint should be placed
                    if (anchor == undefined) {
                        // Use default anchor positions: left for in, right for out
                        switch (type) {
                            case 'in':
                                anchor = 'LeftMiddle';
                                break;
                            case 'out':
                                anchor = 'RightMiddle';
                                break;
                        }
                    }
                    var uuid = type + "_" + attributes.id + "_" + name;
                    var itemSpecificProperties = { anchor: anchor, uuid: uuid, parameters: {item: item} }; // Endpoint properties that are valid for the current item only
                    var plumbEndpoint = jsPlumb.addEndpoint(attributes.id, itemSpecificProperties, defaultEndpointTypeProperties);

                    if (type == 'in') {
                        // Special treatment for endpoints that handle incoming connections
                        /*
                         Bind the classes to the dragging type
                         Class 'destinationPossible' if the dragging source can be connected to the endpoint
                         Class 'destinationImpossible' if the dragging source cannot be connected to the endpoint
                         Note that we will use also the destinationPossible class such that we can visually emphasize that the destination is possible,
                         whilst impossible destination endpoints will simply be hidden
                         */
                        var destinationPossibleIf;
                        /*
                        * Warning for modifying 'destinationPossibleIf'. If this information is modified, make sure that an
                        * appropriate table exists in the backend! Also, the backend's
                        * function getAllPossibleTargetTypesOfSourceType() must be adapted accordingly
                         */
                        switch (item.type) {
                            case 'task':
                                if(name=='in'){
                                    // Types that are allowed as a 'regular' task input
                                // See warning in comment above switch
                                destinationPossibleIf = 'draggingItem.type ==\'task\' || ' +
                                    'draggingItem.type==\'datasource\' || ' +
                                    'draggingItem.type==\'splitter\' || ' +
                                    'draggingItem.type==\'postprocessor\' || ' +
                                    'draggingItem.type==\'merger\'';
                                }
                                else if(name=='inValidator'){
                                    /*
                                     *Validator input endpoints are only used programmatically, and never by a user's action - thus no type is allowed in the user interface
                                      */
                                    destinationPossibleIf = 'false';
                                }
                                break;
                            case 'merger':
                                // See warning in comment above switch
                                destinationPossibleIf = 'draggingItem.type ==\'task\' || draggingItem.type ==\'postprocessor\' || draggingItem.type==\'merger\' || draggingItem.type==\'splitter\'';
                                break;
                            case 'splitter':
                                // See warning in comment above switch
                                destinationPossibleIf = 'draggingItem.type ==\'task\' || draggingItem.type ==\'postprocessor\' ||  draggingItem.type==\'datasource\'';
                                break;
                            case 'postprocessor':
                                // See warning in comment above switch
                                destinationPossibleIf = 'draggingItem.type ==\'task\'';
                                break;
                            case 'workspace':
                                // See warning in comment above switch
                                destinationPossibleIf = 'draggingItem.type ==\'task\' || draggingItem.type ==\'postprocessor\' || draggingItem.type==\'merger\' || draggingItem.type==\'splitter\'';
                                break;
                        }
                        var ngClassValue = '{destinationPossible: draggingItem && ' + destinationPossibleIf + ', destinationImpossible: draggingItem && !(' + destinationPossibleIf + ')}';
                        plumbEndpoint.endpoint.canvas.setAttribute('data-ng-class', ngClassValue);


                        // Interrupt if maximum nb of connections is reached
                        plumbEndpoint.bind("maxConnections", function (endpoint) {
                            switch (type) {
                                case 'task':
                                    Log.log('error', 'A task can have only one datasource');
                                    break;
                            }
                        });
                    }// End endpoint is target
                    else if (type == 'out') {
                        // Special treatment for endpoints that handle outgoing connections
                        switch (item.type) {

                        }
                    }// End endpoint is source


                        // We need to compile the canvas due to the ng-attributes
                        $compile(plumbEndpoint.endpoint.canvas)($scope);
                }// End for each endpoint


                //endregion End visuals

                //Make the element draggable
                element.css('position', 'absolute');



                //Adjust the positions
                element.css('top', item.pos_x);
                element.css('left', item.pos_y);

                // Bind the element's positions to the model (these values will only be needed and used to store the workspace)
                element.draggable({
                    drag: function (event, ui) {
                        // Must re-draw item, i.e. re-orient the endpoints
                        jsPlumb.repaint(element[0].id);
                    },
                    stop: function (event, ui) {
                        // Adjust the positions in the model
                        $scope.$eval(model+'.pos_x="'+ui.position.left+'"');
                        $scope.$eval(model+'.pos_y="'+ui.position.top+'"');
                        // Must re-draw item, i.e. re-orient the endpoints
                        jsPlumb.repaint(element[0].id);

                        // Immediately store the new position
                        var service = item.type.charAt(0).toUpperCase() + item.type.slice(1);
                        $timeout(function(){
                        eval(service).save(item,
                            function(data){
                                Log.log('Item updated');},
                            function(data){
                                Log.log('Item update failure');}
                                );
                        });
                    }
                });
                element.css('cursor', 'move');

                //region Handle positioning
                if(item.pos_x == undefined || item.pos_x == 0 || item.pos_y==undefined || item.pos_y==0){
                    // Handle the case where the position has not yet been defined - make sure it appears in the visible area. This means, we read the position of the element this item is contained in.
                var parentPos = element.parent().offset();
                element.offset({left:parentPos.left+150, top: parentPos.top+150}); // Buffer of 150 to avoid sticking too close to the border
                }
                else{
                    // Position is stored: place the item at the specified position
                    element.offset({top:item.pos_y, left:item.pos_x});
                }
                //endregion End handle positioning


                // Finally, in order to display the connections correctly, we need to add the csConnection directive
                var connectionsList = angular.element('<div data-ng-repeat="connection in '+model+'.connections" data-cs-connection data-ng-model="connection"/>');
                element.append(connectionsList);
                $compile(connectionsList)($scope);


            });//End timeout

        }// End link function

        return ({
            link: link,
            restrict: 'A',
            require: 'ngModel'
        });
    }// End timeout
);// And add directive
