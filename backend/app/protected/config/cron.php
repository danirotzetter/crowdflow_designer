<?php
return array(
    'basePath' => dirname(__FILE__) . DIRECTORY_SEPARATOR . '..',
    'name' => 'Cron',

    // preloading 'log' component
    'preload' => array('log'),

    'import' => array(
        'application.models.*',
        'application.controllers.*',
        'application.components.*',
        'application.components.platforms.mturk.*',
        'application.components.platforms.mturk.lib.*',
        'application.components.platforms.mobileworks.*',
        'application.components.platforms.mobileworks.lib.*',
    ),
    'modules' => array(
        'gii' => array(
            'class' => 'system.gii.GiiModule',
            'password' => 'gii',
            // If removed, Gii defaults to localhost only. Edit carefully to taste.
            //'ipFilters'=>array('127.0.0.1','::1'),
            'ipFilters' => array('*.*.*.*', '::1'),
        ),
    ),

    // application components
    'components' => array(
        'user' => array(
            // enable cookie-based authentication
            'allowAutoLogin' => true,
        ),
        'MTurk' => array(
            'class' => 'application.components.platforms.mturk.MTurk',
        ),
        'MobileWorks' => array(
            'class' => 'application.components.platforms.mobileworks.MobileWorks',
        ),


        'urlManager' => array(
            'urlFormat' => 'path',
            'rules' => array(

                // Platforms url: redirect a platform-specific call to the Api controller of the specified CS platform
                'platforms/<platform:\w+>' => array('platforms/<platform>/api/Call', 'caseSensitive' => false),

                // Default routing
                '<controller:\w+>/<id:\d+>/' => array('<controller>/view', 'verb' => 'GET, PUT'),


                '<controller:\w+>/<id:\d+>/<action:\w+>' => array('<controller>/<action>', 'verb' => 'GET, PUT'),
                '<controller:\w+>/<id:\d+>' => array('<controller>/delete', 'verb' => 'DELETE'),

                // Special case for connections, where there is not just one id (attributes identifying the connection will be sent with the header)
                'Connection/' => array('Connection/view', 'verb' => 'GET'),
                // Special case for positions, where there is not just one id (attributes identifying the connection will be sent with the header)
                'Position/' => array('Position/view', 'verb' => 'GET'),

                // Allow deleting connections for a workspace
                'Workspace/<id:\d+>/connections' => array('Workspace/connections', 'verb' => 'DELETE'),


                /*
                                '<controller:\w+>/<id:\d+>'=>'<controller>/view',
                                '<controller:\w+>/<action:\w+>/<id:\d+>'=>'<controller>/<action>',
                                '<controller:\w+>/<action:\w+>'=>'<controller>/<action>',*/
            ),
        ),

        /*
         * Optional: support different database, depending on whether the access is from within the local host
         * db'=>(in_array($_SERVER['HTTP_HOST'], array('127.0.0.1', 'localhost') ))?
         * array(local_db_config):
         * array(remote_db_config),
         */
        'db' =>
        array(
            'connectionString' => 'mysql:host=<TODEFINE:URL_TO_DATABASE>;dbname=<TODEFINE:DATABASE_NAME>',
            'username' => '<TODEFINE:USER_NAME>',
            'password' => '<TODEFINE:PASSWORD>',
            'charset' => 'utf8',
        ),

        'errorHandler' => array(
            // use 'site/error' action to display errors
            'errorAction' => 'site/error',
        ),
        'log' => array(
            'class' => 'CLogRouter',
            'routes' => array(
                array(
                    'class' => 'CFileLogRoute',
                    'levels' => 'error, warning, trace, info, debug',
                    'enabled' => true,
                ),
            ),
            array(
                // Log special analysis events to a custom file
                'class' => 'CsAnalysisFileLogRoute',
                'levels' => 'error, warning, trace, info, debug',
                'enabled' => true,
                'categories'=>'Analysis',
                'logFile'=>'analysis.log'
            ),
        ),
    ),
    // application-level parameters that can be accessed
    // using Yii::app()->params['paramName']
    'params' => array(
        'platform'=> 'MTurk',// Specifies the to be used crowd-sourcing platform. Possible values: 'MTurk, 'MobileWorks', custom modules etc. This value case-sensitive!
        // Whether the answers from the crowd should be paid even if the answer is not accepted
        'payRejectedAnswers' => true,
        'prefix' => '',// A prefix that will be added to each published task
        'addWebAppData' => false,// Whether web-application specific item data should be added to the title of the crowd-sourced task (i.e. item type and ID as well as the publication date)
        'sandboxMode' => true, // Whether instead of using the 'real' platform with payments, the sandbox mode should be enabled for development & testing
        'backendBaseUrl' => '<TODEFINE:URL_TO_BACKEND>/index.php',// The absolute URL to the web application back-end
    ),
);
