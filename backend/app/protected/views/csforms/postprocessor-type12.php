
<?php
include('base_form_header.php');



// Include the platform result identifier for which the validation is done
echo '<input type="hidden" name="platformResultIdToValidate" id="platformResultIdToValidate" value="'.$inputData['platformResultIdToValidate'].'"></input>';

?>
<h2 class="csForm">Do you approve this answer?</h2>
    <div class="control-group">
        <div class="controls">
            <label>
                <input type="radio"  id="approve1" name="approve" value="1"/>
                <span>Yes</span>
            </label>
            <label>
                <input type="radio" id="approve0" name="approve" value="0"/>
                <span>No</span>
            </label>
        </div>
    </div>
<?php


include('base_form_footer.php');
?>