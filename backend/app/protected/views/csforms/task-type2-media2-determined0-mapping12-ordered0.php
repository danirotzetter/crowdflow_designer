<?php
include('base_form_header.php');
// Task of transforming one input text into one output text

//Display the input text that will be transformed
$inputToDisplay = $inputData['input0'];

if ($appView) {
    echo '<h2 class="collapseHeader" data-ng-click="collapseInput = !collapseInput"><i class="icon-arrow-up" data-ng-show="collapseInput"/><i class="icon-arrow-down" data-ng-hide="collapseInput"/>Input data (click to collapse)</h2>';
    echo '<div data-collapse="collapseInput">' . $inputToDisplay . '</div>';
} else {
    echo '<h2 class="csForm">Input data</h2>';
    echo '<div class="inputData">' . $inputToDisplay . '</div>';
}


// Display the text area into which the translated text must be entered
echo '<h2 class="csForm">Transformed text</h2>';
echo '<label for="input0">Your input</label>';
echo '<textarea cols="120" rows="10" type="text" class="unconstraintWidth" id="input0" name="input0"'.($forValidation? ' readonly="readonly"':'').'>';
// If the form is displayed for validation: pre-fill the input fields with the assignment data
if($forValidation && $queueItem!=null && array_key_exists('data', $queueItem)){
    foreach($queueItem['data'] as $field){
        if($field['id']=='input0'){
            // Found a value from the crowd-source assignment: display it in the pre-filled form
            echo $field['value'];
        }
    }
}
echo'</textarea>';

include('base_form_footer.php');
?>