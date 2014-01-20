/**
 * Logging service
 * error, success, info, block
 */
angular.module('LogService', ['ngResource'])
	.config(
		function($provide){
            //region Define the service
            var LogService = function(){
                this.messages=[];
                /**
                 *
                 * @param type 'info', 'success', 'error', 'warning'
                 * @param content
                 */
                this.log = function(type, content){
//                    this.messages.push({type:type, content:content, time:new Date().getTime()});
                    this.messages.unshift({type:type, content:content, time:new Date().getTime()});// Prepend instead of append
                }

                /**
                 * Reset the log messages
                 */
                this.clearMessages = function(){
                    this.messages=[];
                }
            };

            LogService.$inject =  [];
            $provide.service('Log', LogService);
            //endregion
        }
	);
