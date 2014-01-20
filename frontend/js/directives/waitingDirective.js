/* Directive to show a UI element during a long operation.
* To do so, one can use a scope variable 'isLoading'.
* As soon as a given condition evaluates to TRUE, isLoading is set to false.
 * May be used in combination with data-ng-show="isLoading"
*
*/
/**
 * This directive replaces an HTML-element by a "please wait" dialog.
 * Usage: add the 'data-cs-wait-for' attribute to the element to replace. As attribute value, specify an expression.
 * As long as the expression evaluates to FALSE, the element is replaced by a wait dialog.
 * One can specify a custom message by supplying the data-ng-model, referencing a string
 * Example: data-cs-wait-for="loadComplete"
 */
app.directive(
    'csWaitFor',
    function($compile){
     function link($scope, element, attributes){
        // Element must have the id attribute
         if(attributes.id==undefined){
             console.warn("Cannot set wait directive for element without id");
             return;
         }

         var model = attributes.ngModel;

         // If expression is true: show element, hide wait dialog
            var waitForExpression = attributes.csWaitFor;

         // Read transition duration
         var duration = "slow";

         // Generate a wrapping element that contains both the actual content element and a dialog element, one of which will be displayed whilst the other is hidden
         var dialogWrap = angular.element('<div class="waitDialogWrap"></div>');
         // Get the custom load message if available
         var dialogContent;
         if(model!=undefined){
             // Custom wait message
             dialogContent = '<div data-ng-bind-html-unsafe="'+model+'"';
         }
         else{
             // No custom message defined: use default message
             dialogContent = 'Please wait while <br>the data is being loaded...';
         }
         var dialog = angular.element('<div class="waitDialog" id="'+attributes.id+'_wait"><p><img src="img/loading.gif"/></p><p>'+dialogContent+'</p></div>');


         var compiled = $compile(dialog);

         element.wrap(dialogWrap);
         element.parent().prepend(dialog);// dialogWrap.append(dialog); didn't work

         compiled($scope);


         // Watch for changes in the expression to show or hide the wait dialog
         //TODO check to uncomment
         $scope.$watch(
         waitForExpression,
         function(newValue, oldValue){
                    if(!newValue){
                        // Load not complete: hide element, show wait dialog
                        element
                            .stop( true, true )
                            .fadeTo( 0, 0 );// No fading, as otherwize a non-complete HTML element would already be shown, just to disappear after a few MS
                        dialog
                            .stop(true, true)
                            .fadeIn(duration);
                    }// End hide element
                    else{
                        // Is not loading: show element, hide wait dialog
                        dialog
                            .stop(true, true)
                            .fadeOut(duration, function(){
                                // Show element only after dialog has disappeared
                                element
                                    .stop( true, true)
                                    .fadeTo( duration, 1 );
                            });
                    }// End show element
                }// End watch's execution function
            );// End watch definition
        }// End link function

        return ({
            link: link,
            restrict: 'A'
        });
    }// End directive
);// And add directive
