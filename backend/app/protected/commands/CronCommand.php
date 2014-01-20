<?php

/**
 * This is the command that is executed when replacing
 *
 * $app = Yii::createWebApplication($config)
 * by
* $app = Yii::createConsoleApplication($config)->run();
 * in /cron/cronrun.php
 * Class CronCommand
 */
class CronCommand extends CConsoleCommand
{
		public function actionUpdateFlow($forceReload='false'){
				Yii::log('Updating flow', 'info', 'CronCommand');
            $action = Yii::createComponent('application.components.UpdateFlowAction',$this,'notify');
            $action->run();
            // Currently, this hook is not used, since the webApplication is started.

		}//actionUpdateFlow

}


?>