
<?php

echo '<hr>';

if(!$forValidation){
    // Validation forms are just partial DOM elements and do not need a form directive etc.
echo '<button type="'.($previewMode? 'button':'submit').'" class="btn" '.($previewMode? 'disabled':'').'>Submit</button>';
echo '</form>';
}



if($appView || $forValidation){?>

<?php }
else{
    // External form call, not from within the AngularJS application

    ?>

    <!--    Include feedback form -->
    <?php

    if(Yii::app()->params['feedbackForm']){
    ?>
    <p id="toggleFeedbackForm">Feedback (click to toggle/ requires activated JavaScript)</p>
<div id="feedbackFormContainer">
    <form id="feedbackForm" name="feedbackForm">
<p>
<!--    <label for="comments">Feedback</label>-->
    <textarea cols="80" rows="6" id="comments" name="comments"></textarea>
</p>
        <input type="hidden" id="flowItemId" name="flowItemId" value="<?php echo $flowItemId; ?>">
        <input type="hidden" id="assignmentId" name="assignmentId" value="<?php echo $assignmentId; ?>">
        <input type="hidden" id="item" name="item" value="<?php echo $model->getItemInfo(); ?>">
<input disabled="disabled" type="submit" class="btn btn-info" id="submitFeedbackForm" value="Must allow javascript to activate form">
        </form>
</div>
</div>


    <?php
    }// End show feedback form
    else{
        echo 'Please send comments to <a href="mailto:cfd_feedback@sensemail.ch">cfd_feedback@sensemail.ch</a> - Thank you very much.';

    }
    echo '</div>';// fluid-row
    echo '</div>';// span

    if(Yii::app()->params['feedbackForm']){
        ?>
    <script>

        // Display the form upon click
            $('#feedbackFormContainer').hide();
        $('#toggleFeedbackForm').click(function(){
            $('#feedbackFormContainer').toggle();
        }
        );

        // Only when JS is enabled, the submit button will be activated
        $('#submitFeedbackForm').val('Submit feedback').removeAttr('disabled');

        $('#submitFeedbackForm').click(function(){

            var url = 'sendMail';
            url = '<?php echo Yii::app()->params['backendBaseAlternativeUrl'];?>CrowdsourceForms/sendMail';
            $.ajax({
                url: url,
                type:'POST',
                data: $("#feedbackForm").serialize(),
                success: function(msg){
                    $('#feedbackFormContainer').html(msg);
                },
                error: function(jqXHR, textStatus, errorThrown ){
                    // Ignore same origin policy error - the message was sent anyway
                // $('#feedbackFormContainer').html(textStatus+': '+errorThrown);
            }
            });
            $('#feedbackFormContainer').html('The message was submitted, thank you');
            return false; // avoid to execute the actual submit of the form
        });
    </script>
        <?php
    } // End display feedback form

        ?>
    </body>
    </html>
<?php
}
?>