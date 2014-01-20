Deployment
==========

This section gives a short instruction on how to deploy CrowdFlow
Designer. The entire code of this open-source toolkit is available for
free on GitHub [^1]

#### Pre-Requisites

As stated above, the server-side version CrowdFlow Designer is written
in PHP. Thus, the server on which the backend is deployed must support
PHP.The backend is serving HTTP requests from client implementations.
Thus, one has to make sure that the requests pass through any
firewall.As for the database, any RDBMS may be used that supports
PDO[^2].

If the prototype of the frontend included in the CrowdFlow Designer
distribution is used, then the client internet browser requires enabled
JavaScript. AngularJS is compatible with most of the common browsers
like Chrome[^3], Firefox[^4], Internet Explorer[^5] and some mobile
browsers. However, with the complex user interface and employment of CSS
v. 3[^6], the testing of the web application was limited to the most
recent versions of the Chrome (\>=v. 30) and Firefox (\>=v. 24) browser.
Note that AngularJS offers limited support for legacy Internet Explorer
versions.

#### Back-End Configuration

The CrowdFlow Designer backend must be configured in the designated
configuration file which is located in

This file contains an array containing different *configuration
sections*.The database server details and access credentials are defined
in the `db` section. One can also connect to two different databases,
depending on whether the access is coming from within the current
machine. This approach might be useful either for testing or to separate
(and secure) the internal from a public web application.

Any crowd-sourcing platform module that is used has to be declared in
the `components` section. It serves like the `import` or `using`
directives in the Java or C\# programming language, instructing the
interpreter to also include the specified components when the
application is run.

In the section `log`, the logging level and path for log files can be
indicated.

Finally, some application logic specific settings can be set in the
`params` section:

platform, string
:   At this place, the utilized crowd-sourcing provider is indicated.
    Currently, one platform, M-Turk, is supported. A partial module for
    MobileWorks is also implemented. With only the current user balance
    that can be requested, this module only serves as a proof-of-concept
    to demonstrate the platform openness.

payRejectedAnswers, boolean
:   One can decide to also pay submitted crowd-sourcing assignments that
    were actually *rejected*. This may be desired in order to prevent
    bad ratings from users and/ or to still give thanks to the
    accomplished job of a worker.

sandboxMode, boolean
:   When enabled, the sandbox modes are used in the attached
    crowd-sourcing platform modules. For examle, M-Turk’s API has two
    different URLs - one for the sandbox mode and one for the “real”
    mode.

The subsequent listing depicts the configuration file with the main
application parameters.

    ...
    /* Crowd-Sourcing Platform Modules */
    'components' => array(
            'MTurk' => array(
                'class' => 'application.components.platforms.mturk.MTurk',
            ),
    ...
    /* Database Parameters */
    'db' =>
            array(
                'connectionString' => 'mysql:host=<TODEFINE:URL_TO_DATABASE>;dbname=<TODEFINE:DATABASE_NAME>',
                'username' => '<TODEFINE:USER_NAME>',
                'password' => '<TODEFINE:PASSWORD>',
            ),
    ...
    /* Logging Parameters */
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
    ...
    'params' => array(
            'platform'=> 'MTurk',// Specifies the to be used crowd-sourcing platform. Possible values: 'MTurk, 'MobileWorks', custom modules etc. This value case-sensitive!
            'payRejectedAnswers' => true,// Whether the answers from the crowd should be paid even if the answer is not accepted
            'prefix' => '',// A prefix that will be added to each published task
            'addWebAppData' => false,// Whether web-application specific item data should be added to the title of the crowd-sourced task (i.e. item type and ID as well as the publication date)
            'sandboxMode' => true, // Whether instead of using the 'real' platform with payments, the sandbox mode should be enabled for development & testing
            'backendBaseUrl' => '<TODEFINE:URL_TO_BACKEND>/index.php',// The absolute URL to the web application back-end
        ),

#### Front-End Configuration

If the shipped GUI of CrowdFlow Designer is deployed for the front-end,
some minor parameterization have to be carried out. As the crowd-sourced
tasks require an URL to which the forms are sent, an absolute REST
service address has to be set. This is accomplished in the file .

The corresponding address must be replaced in the lines listed
hereafter.

    var ConfigService = function (\$http, \$q) {
            this.BACKEND\_SERVICE\_HEROKU = '<TODEFINE:PATH\_TO\_SITE\_BACKEND>/app/index.php/'; 
            ...
    };

#### Crowd-Sourcing Platform Configuration: M-Turk

The module for running CrowdFlow Designer on M-Turk is implemented. In
order to operate on M-Turk’s API, the requester’s credentials must be
available. These are defined in .

On M-Turk, the credentials are not of the form username - password, but
instead make use of a so-called “Secret Access Key” and “Access Key ID”.
These strings are to be set in the lines shown in the following listing.

    private function getSecretAccessKey()
    {
            return '<TODEFINE:SECRET_ACCESS_KEY>';
    }

    private function getAccessKeyId()
    {
            return '<TODEFINE:ACCESS_KEY_ID>';
    }

#### Background Scheduler

To ensure proper working, the items that are being processed in the
application must be updated regularly. There are several steps involved
in this process. Thus, one has to make sure that the application’s state
is refreshed frequently. This can be achieved by availing oneself of the
supplied *update script*. This component automatically triggers the
required steps. Thus, on the server, a *Cron Job* (Linux) or *Scheduler
Task* (Windows Server) must be installed to launch the file.

On a Linux machine, the cron job editor is opened by issueing the
following command

``` {.bash language="bash" caption="Lauch" Linux="" cron="" job="" editor=""}
$ crontab -e
```

Then, a new entry specifies that the php file is run every minute:

``` {.bash language="bash" caption="Instruction" to="" run="" a="" Linux="" cron="" job="" each="" minute=""}
#min hour day month weekday command
$ */1 * * * * /usr/local/bin/php /<basedir>/cron/cronrun.php
```

To modify this command according to the publisher’s needs, some
documentation can be found online (e.g.
<https://help.ubuntu.com/community/CronHowto>).

On a Windows server machine, a scheduler can be created in the terminal
with the command

``` {style="dos" caption="Instruction" to="" run="" a="" Windows="" task="" each="" minute=""}
schtasks /create /sc minute /mo 1 /tn "WebApp" /tr <basedir>\cron\cronrun.bat
```

Please refer to the Microsoft help pages for further customization of
the scheduler (e.g.
<http://technet.microsoft.com/en-us/library/cc725744.aspx>).

The PHP file that is launched by the scheduler[^7] contains the
instructions to run the controller that is responsible for the flow
refresh. Depending on the machine setup, the path to the bootstrap and
application configuration files (`yii.php` and `cron.php`) might also
have to be adjusted. Besides, the same configuration as stated in the
paragraph “Configuration” must also be performed in the cron job’s
configuration file [^8].

Extension
=========

Platform Modules
----------------

In this paragraph, it is shortly explained, how an additional
crowd-sourcing platform can be integrated into CrowdFlow Designer. A
custom platform module must comply with the standards defined for
CrowdFlow Designer. This means that the JSON data format presented in
section “DataFormat” must be understood.

Throughout the application flow, several times, the crowd-sourcing
platform is addressed with information requests or task publication
orders. The methods that the platform module has to provide are listed
subsequently.

Note that parameters with the name `$modelOrId` can be either a task
object from the database or a platform-specific identifier. This
openness is needed since in some situations, CrowdFlow Designer has only
knowledge of the platform task identifier, whereas in others, the system
works with data objects.

A parameter named `$model` represents a crowd-sourced CrowdFlow Designer
item that is stored in the database. It contains platform-related
attributes like creation date, the task expiration date, the running
status.

The running status describes, how the task is currently available on the
platform. The possible values are “running”, “unpublished”, “disabled”.

Assignment status indicate, whether an assignment by a crowd-sourcing
worker was already handled or not. Assignments can be “submitted”,
“approved” or “rejected”. If an assignment is not found on the
crowd-sourcing platform, it has status “notfound”.

The return type describes the `data` part of the JSON-encoded answer
object described in section “DataFormat”.

#### `public function getFormBaseUrl(), returns string`

In CrowdFlow Designer, the forms that are presented to the workers on
the crowd-sourcing platform are HTML-encoded, containing the `form`
element. This method returns the URL that must appear in the form’s
`action` parameter. This means that the URL represents the address to
which a completed crowd-sourcing form must be sent.

#### `public function getAccountBalance(), returns string`

Returns the amount that is available to publish tasks on the
crowd-sourcing platform.

#### `public function getAllTasks(), returns array`

Returns an array of all tasks that are currently published on the
crowd-sourcing platform for the CrowdFlow Designer.

#### `public function getExecutionInformation($platformTaskId), returns array`

Returns an array of crowd-sourcing related information for the specified
platform identifier. THe values returned contain “status”,
“CreationDate”, “ExpirationDate” and “ReviewStatus” and
“MaxAssignments”.

#### `public function publishTask($model, $maxAssignmentsOverride)`

Publishes the task on the crowd-sourcing platform. Optionally, the
number of requested assignments can be overridden with the submitted
parameter.

#### `public function validateAssignment($assignmentId, $approve, $message)`

The specified assignment with the platform-specific identifier is
validated according to the `approve` (boolean) parameter. If a message
is given, then this text should be used inform the worker why an
assignment was rejected or accepted.

#### `public function getTaskResults($modelOrId, $assignmentStatus)`, returns array

Retrieves all assignments that were submitted for the given
crowd-sourced task. The `assignmentStatus` (string) parameter may be
applied to restrict the assignments by their status. The array of these
assignments is returned.

#### `public function getAssignmentStatus($platformId), returns string`

Return the status of the assignment supplied as a parameter.

#### `public function parseResult($model, $platformResult), returns object`

All results of a platform’s task are represented in a special,
platform-specific way. Throughout the application, CrowdFlow Designer
may encounter platform-encoded assignments. In order to process these,
they have to be converted into CrowdFlow Designer’s specific assignment
format. This transformation is done in this method. The `model` is the
crowd-sourced task, whereas the `platformResult` is a string that is
encoded on the platform-specific way. The parsed assignment is returned.

Component Modules
-----------------

The web application CrowdFlow Designer is a rather complex system. Each
component like a task type or a merger type must be programmed
individually. Each component should furthermore support as many kinds of
input as possible, and its output must be consumable by the subsequent
component. This leads to a vast amount of possible compositions and data
flows, such that it was not possible to produce an all-encompassing
solution for the first CrowdFlow Designer release.

For evaluation purposes and as a proof of concept, a subset of modules
was implemented.

Hereafter, the currently allowed input for each component type is
specified, and the list of implemented modules is given.

#### Common

The flow of data is handled by making use of input queues. Each
component implementation must thus be able to process and consume its
input queue (except for the datasource, which - serving as a flow origin
- do not have an input connection).

As a hook, the web application will call a component’s
`processInputQueue` method. It is then the component’s task to fetch the
input queue, parse it and thereby taking into account the various data
formats and structures that the queue item might have. For example, an
implementation of a merger type must be able to process data coming from
any of the so-far implemented task, postprocessor, splitter and merger
types, since all these components might “feed” the merger.

#### Tasks

Almost any kind of task is imaginable to be crowd-sourced. But the big
amount of property combinations, which define a task, indicates that all
types of tasks can hardly be implemented or presented in the same way.
For example, a form published on a crowd-sourcing platform for a text
processing MicroTask requires one or multiple text input form elements,
whereas a video item can be a URL or an upload form.This also applies to
the form used in the MicroTask definition (which is filled out by the
requester). Let us consider the case where not one worker answer is
accepted, but a series of answers, like in the task “describe the image
with keywords”. Then, besides the regular crowd-sourcing parameters like
amount paid and lifetime of the task, the form must have an input field
for an additional parameter, i.e. the number of keyword fields that a
crowd-sourced form shows.Thus, each micro task type not only requires a
queue processing method but also needs an implementation for the
*crowd-sourced form* and one for the *task definition form*.

**Accepted Input Components**

-   Task

-   Splitter

-   Merger

-   Datasource

-   Postprocessor

**Currently Implemented Types**

-   Transformation tasks with text as input and text as output, having
    results that have no natural order. The result contains multiple
    answers (one-to-many mapping).

-   Transformation tasks with text as input and text as output, having
    results that have no natural order. The result is a non-binary
    selection (one-to-one mapping, but with multiple options).

#### Post-Processors

Post-processors perform operations on their incoming data. Some
post-processor types are crowd-sourced, such that in this case a
crowd-sourcing form is required.

**Accepted Input Components**

-   Task

**Currently Implemented Types**

-   Post-Processor for crowd-sourced validation. When this module is
    used, a web form is generated and published on the crowd-sourcing
    platform for each flow item of the post-processor’s input queue. The
    form allows the worker to accept or reject the assignment. The flow
    item’s original input is also displayed, such that the worker can
    see what input another worker has transformed into which output.

#### Splitters

Splitters divide the input data into multiple output data items. There
are no splitter types - only one splitter implementation exists.

**Accepted Input Components**

-   Task

-   Postprocessor

**Currently Implemented Types**

-   Input queue splitting. This module splits each flow item from the
    input queue into multiple output elements. It assumes that the input
    queue item has an array key “data”, composed of an array of
    sub-data.

#### Mergers

Mergers transform items of their input queue into fewer output data
items.A crowd-sourcing from also exsits for the implemented module
“selection by the crowd”.

**Accepted Input Components**

-   Task

-   Splitter

-   Merger

-   Postprocessor

**Currently Implemented Types**

-   Web service call. The input queue items are sent to an external web
    service that performs a custom merging procedure. The result of the
    service is the list of merged items. If the web service response is
    successful, the input queue of the merger is emptied, and the
    returned items are forwarded to the subsequent component.

-   Selection by the crowd. The requester defines a number as a
    threshold. Once such a number of assignments were submitted from the
    merger’s predecessing task, the merger is crowd-sourced. This newly
    published task then asks workers to select the best among the
    submitted assignments. The requester also indicates with another
    parameter, after how many “merger” votes the most often selected
    assignment should be processed.This module comes close to, but must
    not be confused with the concept of Majority Voting. In majority
    voting, the most often submitted answer is accepted, whereas in this
    merger type, the crowd explicitely selects the best opion among a
    series of submitted results. Note that this module, in the first
    CrowdFlow Designer version, is unstable and should not yet be used
    in production mode.

-   MajorityVoting. In this type of merger, the requester is asked to
    define a threshold in percents. This number specifies the minimum
    agreement among workers for one task, such that a result is
    accepted.The algorithm takes into account the potential future
    replies. Assuming for example a task that is published five times,
    and three equal assignments were already submitted. If the threshold
    is set to 50%, the system recognizes that in any case the minimum
    agreement will be at least 60% and therefore already processes the
    merger.By contrast, it might happen that the threshold is not
    reached (e.g. if in the previous example, only two assignments
    correspond with each other). In this particular case, the most often
    returned result is accepted instead.

#### Data Sources

A datasource does not process any input. Instead, it is the origin of
flow items. It thus only produces data.

**Currently Implemented Types**

-   Text. For this datasource type, a simple input field is given, where
    a text can be pasted.

-   WebService for images. A URL of a web service must be typed in a
    form field. The web service must return an image.

[^1]: To get the source code clone the repository using the URL
    [git@github.com:danirotzetter/crowdflow\_designer.git](git@github.com:danirotzetter/crowdflow_designer.git)

[^2]: <http://php.net/manual/en/book.pdo.php>

[^3]: <https://www.google.com/intl/en/chrome/browser/>

[^4]: <http://www.mozilla.org/en-US/firefox/new/>

[^5]: <http://windows.microsoft.com/en-us/internet-explorer/download-ie>

[^6]: CSS is a language used in the web to format and layout web pages
