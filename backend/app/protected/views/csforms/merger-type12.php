<!--Select one answer out of a selection-->
<?php
include('base_form_header.php');

    ?>

    <div class="control-group">
        <label class="control-label">Please select the best answer</label>
        <div class="controls">
            <?php foreach($inputData as $inputDataOption){
                $flowItemId=$inputDataOption['flowItemId'];
                ?>
            <label>
                <input type="radio" id="selection<?php echo $flowItemId ?>" name="selection" value="<?php echo $flowItemId ?>"/>
                <?php
                // Find the transformed text
                foreach($inputDataOption['data'] as $inputDataOptionField){
                    $idStartsWithInput =!strncmp($inputDataOptionField['id'], 'input', strlen('input'));
                    if($idStartsWithInput){
                        // Found the text
                         echo '<span>'.$inputDataOptionField['value'].'</span>';
                    }
                }
                ?>
            </label>
            <?php } // End foreach option ?>
        </div>
    </div>

<?php

include('base_form_footer.php');
?>