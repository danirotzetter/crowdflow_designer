/* Directive to show a UI element during a long operation.
 * To do so, one can use a scope variable 'isLoading'.
 * As soon as a given condition evaluates to TRUE, isLoading is set to false.
 * May be used in combination with data-ng-show="isLoading"
 *
 */
/**
 * Converts a time in seconds to a human-readable duration
 */
app.directive(
    'csDuration',
    function ($compile, Tools) {
        function link($scope, element, attributes) {


            // Register for model changes
            $scope.$watch(attributes.ngModel, function (secsFromModel) {

                // Generate the time indication element
                var infoElement=null;
                var time='';// the time string

                if(isNaN(secsFromModel)){
                    // Invalid model
                    time='NaN';
                }
                else{
                    //Valid model
                    var sec_num = parseInt(secsFromModel, 10); // don't forget the second parm
                    var days = Math.floor(sec_num / 86400);
                    var hours = Math.floor((sec_num -(days*86400))/3600);
                    var minutes = Math.floor((sec_num - (days*86400)-(hours * 3600)) / 60);
                    var seconds = sec_num - (days*86400)-(hours * 3600) - (minutes * 60);

                    var time='';
                    if(days>0)
                        time+=days+' days ';
                    if(hours>0)
                        time+=hours+' hours ';
                    if(minutes>0)
                        time+=minutes+' minutes ';
                    if(seconds>0)
                        time+=seconds+' seconds';
                }

                // Generate the time indication element
                infoElement = angular.element('<div class="duration">'+time+'</div>');

                // Add the info to the directive's element
                if(element.children().length==0)
                    element.append(infoElement)
                else
                    element.children()[0].innerHTML=time;



            }, true);

        }// End link function

        return ({
            link: link,
            restrict: 'A'
        });
    }// End directive
);// And add directive
