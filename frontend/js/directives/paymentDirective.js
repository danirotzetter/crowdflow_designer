/* Directive to show a UI element during a long operation.
 * To do so, one can use a scope variable 'isLoading'.
 * As soon as a given condition evaluates to TRUE, isLoading is set to false.
 * May be used in combination with data-ng-show="isLoading"
 *
 */
/**
 * Displays the total amount for publishing the task
 */
app.directive(
    'csPayment',
    function ($compile, Tools) {
        function link($scope, element, attributes) {


            // Register for model changes
            $scope.$watch('['+attributes.ngModel+'.parameters[\'min_assignments\'],'+attributes.ngModel+'.parameters[\'max_assignments\'],'+attributes.ngModel+'.parameters[\'reward\']]', function (newValues) {

                var payment='';// the time string

                if(newValues==undefined || newValues.length!=3){
                    payment='N/A';
                }
                else{
                    //Valid model
                    var min_assignments = parseInt(newValues[0], 10);
                    var max_assignments = parseInt(newValues[1], 10);
                    var payment = parseFloat(newValues[2], 10);

                    var minPayment = Math.round(min_assignments*payment*100)/100;
                    var maxPayment = Math.round(max_assignments*payment*100)/100;

                    payment='Total: between '+minPayment+ ' and '+maxPayment+' USD, depending on accept rate';
                }

                // Generate the time indication element
                infoElement = angular.element('<div class="payment">'+payment+'</div>');

                // Add the info to the directive's element
                if(element.children().length==0)
                    element.append(infoElement)
                else
                    element.children()[0].innerHTML=payment;



            }, true);

        }// End link function

        return ({
            link: link,
            restrict: 'A'
        });
    }// End directive
);// And add directive
