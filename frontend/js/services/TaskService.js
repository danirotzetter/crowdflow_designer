/**
 * Manages task operations
 */
angular.module('TaskService', ['ngResource'])
	.config(
		function($provide){

            //region Define the factory
			var TaskFactory = function($resource, ConfigService, Tools){
				var task = $resource(
									ConfigService.BACKEND_SERVICE+'Task/:id/:action/',
                    {id:'@id'}, // Default parameters
                    {
                        create: {
                            method: "POST",
                            params: {
                                action: "create"
                            }
                        },
                        update: {
                            method: "PUT",
                            params: {
                                action: "update"
                            }
                        },
                        delete: {
                            method: "DELETE"
                        },
                        query: {method:'GET', isArray:false}
                }
                );

                /**
                 * Saves the task. If the id is undefined, a new item is generated. Otherwize, the existing item is updated.
                 * @param item
                 * @param success
                 * @param failure
                 * @returns {*}
                 */
            task.save = function (item, success, failure) {
                var result;
                var itemToSave = Tools.copyItem(item);
                if (itemToSave.id == undefined)
                    result = task.create(itemToSave, success, failure);
                else
                    result = task.update({id: itemToSave.id}, itemToSave, success, failure);
                return result;
            };


                /**
                 * Get all task types
                 * @returns {Array}
                 */
            task.getAllTaskTypes=function(){
                return [
                    {id:1, name:"Categorization"},
                    {id:2, name:"Data transformation"},
                    {id:3, name:"Data collection"},
                    {id:4, name:"External task"}
                ]
            }


                /**
                 * Get all output mapping types
                 * @returns {Array}
                 */
                task.getAllOutputMappingTypes=function(){
                    return [
                        {id:1, name:"Single output",
                            subTypes:[
                                {id:11, name:"Binary/ Agreement"},
                                {id:12, name:"Non-Binary/ Selection"}
                            ]
                        },
                        {id:2, name:"Multiple results"}
                    ]
                }

                /**
                 * Get all parameters needed to crowd-source an item
                 * Note that the 'show' property will be applied to the item to which the parameter is bound. E.g. show:'item.type==2'
                 * @parameter asAssociativeArray If true, instead of an array of parameters, a list of key-value is returned with key=parameter.id and value=parameter
                 * @returns {Array}
                 */
                task.getAllParameters = function(asAssociativeArray){
                    var allPars = [
                    {id:'number_results', label:'How many results are allowed?', show:'item.type==\'task\' && item.output_mapping_type_id==2', defaultValue:'4', type:'number', min:'1'},
                    {id:'reward', label:'How big should the reward (in USD) be for this completed task?', show:'true', defaultValue:'0.01', type:'number', min:'0.01', step:'0.01'},
                    {id:'assignment_duration', label:'How much time does the worker have to complete the task? In seconds, minimum 30', show:'true', defaultValue:'600', type:'number', isDuration:true},
                    {id:'lifetime', label:'For how long should the task be available to be executed by the crowd? In seconds, minimum 30', show:'true', defaultValue:'864000', type:'number', min:'30', isDuration:true},
                    {id:'keywords', label:'Specify the tasks\'s keywords (comma-separated)', show:'true', defaultValue:'keyword', type:'text'},
                    {id:'min_assignments', label:'How many accepted assignments are required at least, for each processed item (e.g. for each translation)?', show:'true', defaultValue:'1', type:'number', min:'1'},
                    {id:'max_assignments', label:'How many assignments are the maximum for each crowd-sourced item?', show:'true', defaultValue:'10', type:'number'}
                        ];

                    if(asAssociativeArray){
                        // Convert the array into an associative array with the parameter's id as key
                        var assoc = {};
                        $.each(allPars, function(index, par){
                            assoc[par.id]=par;
                        });
                        return assoc;
                    }
                    else
                        return allPars;
                }
                /**
                 * Get all platform related parameters
                 * @parameter asAssociativeArray If true, instead of an array, a list of key-value is returned
                 * @returns {Array}
                 */
                task.getAllPlatformData= function(asAssociativeArray){
                    var allData = [
                    {id:'tasks', label:'Solved and unsolved tasks published so far in total'},
                    {id:'input_queue', label:'Tasks published and active/ solvable'},
                        {id:'pendingAssignments', label:'Assignments pending but not evaluated'},
                        {id:'acceptedAssignments', label:'Assignments accepted'},
                        {id:'rejectedAssignments', label:'Assignments rejected'}
                        ];

                    if(asAssociativeArray){
                        // Convert the array into an associative array with the parameter's id as key
                        var assoc = {};
                        $.each(allData, function(index, par){
                            assoc[par.id]=par;
                        });
                        return assoc;
                    }
                    else
                        return allData;
                }


				return task;
			};



            //Register the factory
            TaskFactory.$inject = ['$resource', 'Config', 'Tools'];
            $provide.factory('Task', TaskFactory);
            //endregion

        }// End config function
	);// End configuration
