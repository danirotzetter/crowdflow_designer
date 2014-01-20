/**
 * Common configuration is done in this service.
 * This is also the place where global variables are set
 */
angular.module('ConfigService', ['ngResource'])
    .config(
    function ($provide) {
        //region Define the service
        var ConfigService = function ($http, $q) {
            this.BACKEND_SERVICE = '../backend/app/index.php/';
            this.BACKEND_SERVICE_HEROKU = '<TODEFINE:PATH_TO_SITE_BACKEND>/app/index.php/';

            var connectorHoverStyle= {
                gradient: {stops: [ // Gradient because the non-hover version also uses gradients
                    [0, "#f60"],
                    [1, "#f60"]
                ]}
            };
            var hoverPaintStyle = {fillStyle: "#f60"};
            var connectorStyle = {
                /*gradient: {stops: [
                    [0, "#ff0"],
                    [1, "#3c3"]
                ]},*/
                lineWidth: 5,
                strokeStyle: "#FF9D34",
                dashstyle: "2 2"
            };
            this.jsPlumb = {
                defaults:{
                    Endpoint : ["Rectangle", {width:10, height:10}],
                    ReattachConnections:true,
                    Connector: [
                        "Flowchart",
                        { stub: [40, 60],
                            gap: 10,
                            midpoint: 0.1
                        }
                    ],
                    ConnectorOverlays: [
                        [ "Arrow", {
                            location: 0.9,
                            length: 14,
                            foldback: 0.8
                        } ],
                        [ "Arrow", {
                            location: 0.1,
                            length: 14,
                            foldback: 0.8
                        } ]
                    ]// Connector overlays
                },
                endpoints: {
                    task: {
                        out: {
                            endpoint: ["Image", {src: "img/arrow-right.png"}],
                            cssClass: "endpointOut",
                            connectorStyle: connectorStyle,
                            hoverPaintStyle: hoverPaintStyle,
                            connectorHoverStyle: connectorHoverStyle,
                            maxConnections: 1,
                            isSource: true,
                            isTarget: false
                        },//end endpoint task out
                        in: {
                            endpoint: ["Image", {src: "img/arrow-right.png"}],
                            cssClass: "endpointIn",
                            hoverPaintStyle: hoverPaintStyle,
                            isSource: false,
                            isTarget: true
                        },//end endpoint task in
                        inValidator: {
                            endpoint:"Blank",
                            anchor:"TopCenter",
                            cssClass: "endpointIn",
                            hoverPaintStyle: hoverPaintStyle,
                            isSource: false,
                            isTarget: true
                        }//end endpoint task inValidator
                    },// end endpoints task
                    merger: {
                        out: {
                            endpoint: ["Image", {src: "img/arrow-right.png", height: 16, width: 16}],
                            hoverPaintStyle: hoverPaintStyle,
                            connectorStyle:connectorStyle,
                            connectorHoverStyle: connectorHoverStyle,
                            isSource: true,
                            isTarget: false,
                            maxConnections: 1
                        },// end endpoint merger out
                        in: {
                            endpoint: ["Image", {src: "img/merger.png", height: 16, width: 16}],
                            hoverPaintStyle: hoverPaintStyle,
                            isSource: false,
                            isTarget: true,
                            maxConnections:-1
                        }// end endpoint merger in
                    },// end endpoints merger
                    postprocessor: {
                        out: {
                            endpoint: ["Image", {src: "img/arrow-right.png", height: 16, width: 16}],
                            hoverPaintStyle: hoverPaintStyle,
                            connectorStyle:connectorStyle,
                            connectorHoverStyle: connectorHoverStyle,
                            isSource: true,
                            isTarget: false,
                            maxConnections: -1
                        },// end endpoint postprocessor out
                        in: {
                            endpoint: ["Image", {src: "img/incoming_small.png", height: 16, width: 16}],
                            hoverPaintStyle: hoverPaintStyle,
                            isSource: false,
                            isTarget: true,
                            maxConnections: 1
                        }// end endpoint postprocessor in
                    },// end endpoints postprocessor
                    splitter: {
                        out: {
                            endpoint: ["Image", {src: "img/splitter.png", height: 16, width: 16}],
                            hoverPaintStyle: hoverPaintStyle,
                            connectorStyle:connectorStyle,
                            connectorHoverStyle: connectorHoverStyle,
                            isSource: true,
                            isTarget: false,
                            maxConnections: -1
                        },// end endpoint splitter out
                        in: {
                            endpoint: ["Image", {src: "img/incoming_small.png", height: 16, width: 16}],
                            endpoint: ["Image", {src: "img/incoming_small.png", height: 16, width: 16}],
                            hoverPaintStyle: hoverPaintStyle,
                            isSource: false,
                            isTarget: true,
                            maxConnections: 1
                        }// end endpoint splitter in
                    },// end endpoints splitter
                    datasource: {
                        out: {
                            anchor:"BottomCenter",
                            endpoint: ["Image", {src: "img/datasources.png", height: 32, width: 32}],
                            hoverPaintStyle: hoverPaintStyle,
                            connectorStyle:connectorStyle,
                            connectorHoverStyle: connectorHoverStyle,
                            isSource: true,
                            isTarget: false,
                            maxConnections: -1
                        }// end endpoint datasource out
                    },// end endpoints datasource
                    workspace: {
                        in: {
                            anchor:"TopCenter",
                            endpoint: ["Image", {src: "img/finish.png", height: 32, width: 32}],
                            hoverPaintStyle: hoverPaintStyle,
                            connectorStyle:connectorStyle,
                            connectorHoverStyle: connectorHoverStyle,
                            isSource: false,
                            isTarget: true,
                            maxConnections: -1
                        }// end endpoint workspace out
                    }// end endpoints workspace
                }// end endpoints
            };//End jsPlumb variables
        };//End config service definition
        ConfigService.$inject = [];
        $provide.service('Config', ConfigService);
        //endregion
    }
);
