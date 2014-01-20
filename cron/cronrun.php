<?php

/*

Config file: protected/config/cron.php

Cronjob command:
php cronrun.php <CommandName> <ActionName> --param1=Param1 --param2=Param2 --param3=Param3


Linux cron jobs
$ crontab -e
$ *\/1 * * * * /usr/local/bin/php /<basedir>/cron/cronrun.php

Windows cron jobs
schtasks /create /sc minute /mo 1 /tn "WebApp" /tr <basedir>\cron\cronrun.bat
schtasks /delete /tn "WebApp"

*/

// change the following paths if necessary
$yii=dirname(__FILE__).'/../backend/framework/yii.php';
$config=dirname(__FILE__).'/../backend/app/protected/config/cron.php';
//$config=dirname(__FILE__).'/../backend/app/protected/config/main.php';

// remove the following lines when in production mode
defined('YII_DEBUG') or define('YII_DEBUG',false);
// specify how many levels of call stack should be shown in each log message
defined('YII_TRACE_LEVEL') or define('YII_TRACE_LEVEL',9);

require_once($yii);

// Not running console application since the essential functions are currently implemented in controllers
$app = Yii::createConsoleApplication($config)->run();

//$app = Yii::createWebApplication($config);
//$app->runController('Platforms/updateFlow');
?>