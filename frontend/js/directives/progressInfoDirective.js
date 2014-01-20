

/* Displays the progress for a crowd-sourced item
 *
 * As a directive argument, the user can add parameters:
 * boolean, Whether a progressBar should be displayed.
 * (e.g. 'data-cs-progress-info="item.item_type==11")
 */
app.directive(
    'csProgressInfo',
    function ($compile, Tools) {
        function link($scope, element, attributes) {
            // Read the directive parameters
            var attr = $scope.$eval(attributes.csProgressInfo);

            var modelName = attributes.ngModel;
            var item = $scope.$eval(modelName);


            // Check if the item is crowd-sourced at all. The attribute evaluates to true for all values !=='false'
            var isPublished=attr!==false;
            if(item.platform_data==undefined || item.parameters.max_assignments==undefined){
                // Data not available - item does not contain enough information or is not crowd-sourced
                isPublished=false;
            }

            // Initialize the input queue and processed flow items array if they are not set
            if(item.input_queue==undefined || item.input_queue==null || item.input_queue=='null')
                item.input_queue=[];
            if(item.processed_flowitems==undefined || item.processed_flowitems==null || item.processed_flowitems=='null')
                item.processed_flowitems=[];

            // Initialize the 'base' element
            var infoElement = angular.element(
                '<div data-ng-show="showProgressInfo"></div>');


            if(isPublished){

            // Get the data necessary to display the progress bar
            var submitted = 0;
                if(item.platform_data.pendingAssignments!=undefined){
                    jQuery.each(item.platform_data.pendingAssignments, function(index, value){
                        submitted=submitted+Object.keys(value).length;
                    });
                }
            var approved = 0;
                if(item.platform_data.acceptedAssignments!=undefined){
                    jQuery.each(item.platform_data.acceptedAssignments, function(index, value){
                        approved=approved+Object.keys(value).length;
                    });
                }
            var rejected= 0;
                if(item.platform_data.rejectedAssignments!=undefined){
                    jQuery.each(item.platform_data.rejectedAssignments, function(index, value){
                        rejected=rejected+Object.keys(value).length;
                    });
                }
                var available = item.input_queue.length-submitted;
                var assignmentsTotalSoFar = submitted+approved+rejected;
            var total=(available + assignmentsTotalSoFar); // TODO incorrect formula

                // Calculate the percentage for representation in the progressBar
                // Must make sure that a maximum of 100 is reached
                var availablePerc = (100*(available))/total;// Percentage of the assignments that are available in the input queue, among the amount of requested assignments
            availablePerc = Math.max(2, availablePerc); //  For better visualization
            var submPerc = (100*submitted)/total;
                submPerc=Math.max(2, submPerc);
            var apprPerc = (100*approved)/total;// Percentage of approved assignments
                apprPerc=Math.max(2, apprPerc);
                apprPerc = Math.min(apprPerc, (100-submPerc-availablePerc-submPerc));
            var rejPerc = (100*rejected)/total; // Percentage of rejected assignments
                rejPerc = Math.max(2, rejPerc);
                rejPerc = Math.min(rejPerc, (100-submPerc-availablePerc-submPerc-apprPerc));


            var progressInfo = '';

                //region Assignment information
            var assignmentInfo = '<div><div class="progress progress-striped active">'+
                    '<div class="bar bar-info" style="width: '+availablePerc+'%"/>'+
                    '<div class="bar bar-warning" style="width: '+submPerc+'%"/>'+
                    '<div class="bar bar-success" style="width: '+apprPerc+'%"/>'+
                    '<div class="bar bar-danger" style="width: '+rejPerc+'%"/>' +
                    '</div></div>';
                assignmentInfo += '<div class="keys">' +
                    '<span class="available">Avl.'+available+' </span>/<span class="submitted">Subm.'+submitted+'</span>/<span class="approved">Appr.'+approved+'</span><span class="rejected">/Rej.'+rejected+'</span>('+total+')' +
                    '</div>';
                // Add the assignment information to the final element
                progressInfo+='<div class="assignmentInfo">'+assignmentInfo+'</div>';
                //endregion Assignment information



            }// End item is published
            else{
                // Item is not published
                return;
            }// End item is not published

                infoElement.append(progressInfo);


            // Compile the element
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
