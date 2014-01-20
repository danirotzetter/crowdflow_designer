/**
 * Additional tools to be used across the application
 */
angular.module('ToolsService', ['ngResource'])
    .config(
    function ($provide) {
        //region Define the service
        var ToolsService = function ($compile, $dialog, $rootScope) {

            var self=this; // Needed to access the ToolsService instance when coming from a dialog

            /**
             * Add a tooltip to an element. The tooltip will be added within the tooltip holder, but will get a visibility:none through css.
             * The visibility will be set to visible as soon as the mouse is hovering over the owner.
             * This is a more interactive tooltip than angular's data-tooltip, since this one here allows HTML and interaction with the scope such
             * as button click events.
             * @param tooltipOwnerElement Tooltip owner element
             * @param tooltipElement The tooltip as a string or as an angular element
             * @param scope Optional scope. If set, then the tooltip is compiled against the scope.
             */
            this.addTooltip = function(tooltipOwnerElement, tooltipElement, scope) {
                if (typeof(tooltipElement) == 'string') {
                    // Argument is pure HTML: we need to convert them to an angular element first
                    tooltipElement = angular.element(tooltipElement);
                }
                tooltipElement.addClass('tooltip');

                if (scope != undefined) {
                    // Compile
                    tooltipElement = $compile(tooltipElement)(scope);
                }

                tooltipOwnerElement.addClass('tooltipOwner');
                tooltipOwnerElement.prepend(tooltipElement);
                return tooltipOwnerElement;
            };


            /**
             * Copies an item from the composer in a 'clean' way, i.e. such that the copy can be stored/ send to the database.
             * This is used such that not the entire JSON object is sent in the request (reducing payload) and such that the server does not receive attributes that it cannot treat (which would result in an error when storing the item's attributes into the database)
             * @param item
             * @returns The clean copy as JSON without functions and circular dependencies
             */
            this.copyItem=function(item){

                // At first, retrieve the attributes of the item that are copied for all item type
                var itemCopy = {
                    id:item.id,
                    type:item.type,
                    pos_x:item.pos_x,
                    pos_y:item.pos_y,
                    name:item.name,
                    description:item.description,
                    user_id:item.user_id,
                    date_created:item.date_created,
                    workspace_id:item.workspace_id
                }
                // Then copy individual properties that are just valid for a specific type
                switch(item.type){
                    case 'task':
                        itemCopy.name=item.name;
                        itemCopy.description =item.description;
                        itemCopy.task_type_id=item.task_type_id;
                        itemCopy.output_media_type_id=item.output_media_type_id;
                        itemCopy.output_determined =item.output_determined;
                        itemCopy.output_mapping_type_id=item.output_mapping_type_id;
                        itemCopy.output_ordered =item.output_ordered;
                        itemCopy.parameters=item.parameters;
                        itemCopy.data=item.data;
                        // itemCopy.platform_data=item.platform_data; // Handled internally on the server
                        break;
                    case 'datasource':
                        itemCopy.datasource_type_id=item.datasource_type_id;
                        itemCopy.output_media_type_id=item.output_media_type_id;
                        itemCopy.output_determined =item.output_determined;
                        itemCopy.output_ordered =item.output_ordered;
                        itemCopy.data=item.data;
                        itemCopy.items_count=item.items_count;
                        break;
                    case 'splitter':
                        itemCopy.parameters=item.parameters;
                        break;
                    case 'merger':
                        itemCopy.merger_type_id=item.merger_type_id;
                        itemCopy.data=item.data;
                        itemCopy.parameters=item.parameters;
                        break;
                    case 'postprocessor':
                        itemCopy.postprocessor_type_id=item.postprocessor_type_id;
                        itemCopy.validation_type_id=item.validation_type_id;
                        itemCopy.parameters=item.parameters;
                        break;
                }
                return itemCopy;
            }

            /**
             * On external events like a modal dialog showing up after an onclick event, AngularJS
             * does not know that the visuals have changed. In this case, we must ask it specifically
             * to update the visual elements.
             */
            this.refresh = function () {
                if (!$rootScope.$$phase) {
                    /* Do only call the apply() method if no other digest or apply is in progress, which may
                     occur if the visual change has been called by angular itself.
                     Example: when a dialog shows up with onclick="showDialog()", then we must apply specifically.
                     If however, we user an angularJS controller  data-ng-onclick="showDialog", Angular applies the
                     changes itself.
                     */
                    $rootScope.$apply();
                }
            };


            /**
             * Show the dialog
             * @param options The dialog options. Defines an array of buttons with their actions.
             * Has the properties 'id', 'text', 'class'. The 'id' can be used to handle the result.
             *     Defaults to
             {id:'ok',
             text:'Ok',
             class: 'btn-primary'
             }
             * @param textParams JSON with the properties
             * 'title' Defaults to 'Confirmation required'
             * 'text' Defaults to 'Please confirm the action'
             *
             */
            this.showDialog = function (options, textParams) {
// Read the dialog properties
                var theOptions = options == undefined ? {} : options;
                if (options == undefined) {
                    theOptions = [
                        {id:'ok',
                            text: 'Ok',
                            class: 'btn-primary'
                        }
                    ]
                }

                var theTextParams = textParams == undefined ? {} : textParams;
                if (theTextParams.title == undefined) theTextParams.title = 'Confirmation required';
                if (theTextParams.text == undefined) theTextParams.text = 'Please confirm the action';


                // Define the dialog options
                var dialogOptions = {
                    controller: 'DialogCtrl',
                    templateUrl: 'partials/dialog.html',
                    dialogFade: true,
                    backdropFade: true
                };
                // Define the dialog
                var dialog = $dialog.dialog(
                    // Open the dialog in the new controller: submit the connection as a parameter
                    angular.extend(
                        dialogOptions,
                        {
                            resolve: {
                                options: theOptions,
                                textParams: theTextParams
                            }
                        }
                    )
                );// End define dialog
                return dialog;
            };// End showDialog

            /**
             * Get all media types that are supported as input or output in this application
             * @returns {Array}
             */
            this.getAllMediaTypes=function(){
                return [
                    {id:1, name:"Text field"},
                    {id:2, name:"Text area"},
                    {id:3, name:"Number/ identifier"},
                    {id:4, name:"Binary (yes/ no)"},
                    {id:5, name:"URL"}
                ]
            }

        };// End define ToolsService

        ToolsService.$inject = ['$compile', '$dialog', '$rootScope'];
        $provide.service('Tools', ToolsService);
        //endregion
    }
);
