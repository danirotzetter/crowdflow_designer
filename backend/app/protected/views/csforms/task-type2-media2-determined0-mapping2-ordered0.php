
<?php
include('base_form_header.php');
// Task of splitting input text into multiple output texts

//Display the input text that will be split
$inputToDisplay = $inputData['input0'];

if ($appView) {
    echo '<h2 class="collapseHeader" data-ng-click="collapseInput = !collapseInput"><i class="icon-arrow-up" data-ng-show="collapseInput"/><i class="icon-arrow-down" data-ng-hide="collapseInput"/>Input data (click to collapse)</h2>';
    echo '<div data-collapse="collapseInput">' . $inputToDisplay . '</div>';
} else {
    echo '<h2 class="csForm">Input data</h2>';
    echo '<div class="inputData">' . $inputToDisplay . '</div>';
}


// Display the text areas into which the split text parts must be copied
echo '<h2 class="csForm">Text parts</h2>';
for($i=0; $i<$model->parameters['number_results']; $i++){
    echo '<label for="input'.$i.'">Input '.($i+1).'</label>';
    echo '<textarea cols="160" rows="10" type="text" class="unconstraintWidth" id="input'.$i.'" name="input'.$i.'"'.($forValidation? ' readonly="readonly"':'').'>';

    // If the form is displayed for validation: pre-fill the input fields with the assignment data
    if($forValidation && $queueItem!=null && array_key_exists('data', $queueItem)){
        foreach($queueItem['data'] as $field){
            if($field['id']=='input'.$i){
                // Found a value from the crowd-source assignment: display it in the pre-filled form
                echo $field['value'];
            }
        }
    }

    echo '</textarea>';
}
include('base_form_footer.php');
?>