
<?php
include('base_form_header.php');
// Task of assigning a category to an image

//Display the input text that will be splitted
$inputToDisplay = $inputData['input0'];

    echo '<h2 class="csForm">Image to tag</h2>';
    echo '<div class="inputData">' . $inputToDisplay . '</div>';


// Display the options for a categorization task
echo '<h2 class="csForm">Categories</h2>';
echo '<input type="hidden" name="resultfield" id="resultfield" value="categoryId">'; // Indicates which result is significant for further processing (e.g. majority voting)
foreach($model->data as $index=>$category){
    if($category==null || $category=='')
        // Skip empty categories
        continue;


    // If the form is displayed for validation: show selected category
    $isChecked=false;
    if($forValidation && $queueItem!=null && array_key_exists('data', $queueItem)){
        foreach($queueItem['data'] as $field){
            if($field['id']=='categoryId' && $field['value']==$index){
                // User has selected this category
                $isChecked=true;
            }
        }
    }
    echo '<input type="hidden" name="categoryName" id="categoryName'.$index.'" value="'.$category.'">'; // Do also send all category names such that they can be used in the further item flow, instead of an integer identifier
    echo '<input type="radio" name="categoryId" id="categoryId'.$index.'" value="'.$index.'" '.($isChecked? ' checked="checked"':'').($forValidation? ' disabled="disabled"':'').'>';
    echo '<label style="display:inline;margin-left: 1em;" for="categoryId'.$index.'">'.$category.'</label>';


    echo '<br/>';

}//End for each category
include('base_form_footer.php');
?>