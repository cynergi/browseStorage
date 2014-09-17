
/**
 * Create the global `browseStorage` object, if not yet created.
 * It should have been created by the caller, but...
 * @type Object
 */
if( browseStorage === undefined )
	var browseStorage = {};


browseStorage.app = angular.module( 'browseStorageApp', ['ngTouch', 'ngRoute', 'ui.bootstrap'] );


/**
 * Disable debug information (while in production).
 */
/*!!! not working; using unminified angular because of this
browseStorage.app.config(
[        '$compileProvider',
function( $compileProvider )
{
	$compileProvider.debugInfoEnabled( false );
}]);
*/


/**
 * Report an error using an AngularJS' UI Bootstrap modal.
 *
 * @param $modal Object
 *        AngularJS' $modal object.
 * @param title string
 *        The text to display as the modal's title.
 * @param status number
 *        HTTP error code.
 * @param json Object
 *        The returned JSON object (if any).
 * @param is_write boolean
 *        True if the error came from a request to change storage (save/delete).
 */
browseStorage.onerror =
function( $modal, title, status, json, is_write )
{
	var msg = "";

	if( status == 404 )
		msg = "Could not reach the storage service.\n" +
		      "Your Internet connection may not be working, or the service may be under maintenance." +
		      ( is_write ? '' : '\nPlease try again in a few minutes by refreshing your browser.');
	else
		msg = ( json.message != '' ? json.message :
			"Unknown error with HTTP code "+status+"." );

	if( is_write )
		msg += "\n" +
		       "The currently displayed information may not be what is at the storage service. " +
		       "Please refresh your browser to retrieve data from storage again.";

	$modal.open( {
		templateUrl: 'modal.html',
		controller:  'ModalCtrl',
		size:        'sm',
		resolve:{
			text: function () { return {
				title:   title,
				body:    msg,
				buttons: "Ok"
			};}
		}});
};


/**
 * Custom filter to convert an array of IDs into a sequence of URI parameters:
 * id0=...&id1=...&id2=...
 */
browseStorage.app.filter( 'encode_uri_ids',
function()
{
	return function( row_ids )
		{
		var id_num = 0;
		var uri = '';
		for( var key in row_ids )
			uri += '&id' + (id_num++) + '=' + encodeURIComponent(row_ids[key]);
		return uri.substring(1);
		};
});


/**
 * Custom filter to convert a control name string into
 * `<input placeholder="..." />` text.
 */
browseStorage.app.filter( 'control_to_placeholder',
function()
{
	return function( control )
		{
		switch( control.toLowerCase() )
			{
			case 'textarea':  // fall through
			case 'text':      // fall through
			case 'search':
				return '';
			case 'datetime':  // fall through
			case 'datetime-local':
				return 'Date & time';
			case 'email':
				return 'E-mail';
			case 'url':
				return 'URL';
			case 'tel':
				return 'Telephone number';
			}
		return control.substring(0,1).toUpperCase() + control.substring(1).toLowerCase();
		};
});


/**
 * Custom filter to determine how a value should be shown in read mode.
 * It adds a property named "show" to each columns[] object, that can be
 * one of "option" (show col.options[col.value]), "true", "false", "null"
 * or "other".
 */
browseStorage.app.filter( 'add_show_property',
function ()
{
	return function( columns )
		{
		var col;
		for( var key in columns )
			{
			col = columns[key];
			if( (col.control == "select"  ||  col.control == "radio")  &&
			    col.options            !== undefined                   &&
			    col.options[col.value] !== undefined )
				columns[key].show = "option";
			else if( col.value === true )
				columns[key].show = "true";
			else if( col.value === false )
				columns[key].show = "false";
			else if( col.value === null  ||  col.value === undefined )
				columns[key].show = "null";
			else
				columns[key].show = "other";
			}
		return columns;
		};
});


/**
 * Custom filter to convert a newline-separated message into an array of lines
 * in that message.
 */
browseStorage.app.filter( 'array_of_lines',
function ()
{
	return function( text )
		{
		return text.split(/\n/g);
		};
});


/**
 * Modal dialog box controller.
 */
browseStorage.app.controller( 'ModalCtrl',
[        '$scope', '$modalInstance', 'text',
function( $scope,   $modalInstance,   text )
{
	$scope.text = text;

	$scope.is_ok    = ( !text.buttons  ||  text.buttons.toLowerCase() == "ok"    );
	$scope.is_yesno = (  text.buttons  &&  text.buttons.toLowerCase() == "yesno" );

	$scope.text.ok     = ( $scope.is_yesno ? "Yes" : "Ok"     );
	$scope.text.cancel = ( $scope.is_yesno ? "No"  : "Cancel" );

	$scope.ok = function () {
		$modalInstance.close( true );
		};

	$scope.cancel = function () {
		$modalInstance.dismiss( 'cancel' );
		};
}]);


/**
 * All controllers append `browseStorage.initial_search` to the URL.
 * Appending `browseStorage.initial_search` passes any arguments back to
 * the PHP scripts for possible `$_GET[]`-based configuration.
 * We don't want to use $location, as we don't want this to reflect any
 * internal life-cycle (routing?), we want the original `index.html` arguments.
 * @see `config.php`.
 */


/**
 * Table List controller.
 */
browseStorage.app.controller( 'TableListCtrl',
[        '$scope', '$http', '$modal',
function( $scope,   $http,   $modal )
{
	$scope.json = {};
	$scope.search = "";  // Set and used as search filter in `page-list.html`

	$http.get( 'browseStorage/json-tables.php?nogroups&'+browseStorage.initial_search.substring(1) ).
		success( function(json) {
			$scope.json = json;
		}).
		error( function(json,status) {
			$scope.json = {};
			browseStorage.onerror( $modal, "Error reading available tables!", status, json, false );
		});
}]);


/**
 * Row List controller for a table.
 */
browseStorage.app.controller( 'RowListCtrl',
[        '$scope', '$routeParams', '$http', '$location', '$modal',
function( $scope,   $routeParams,   $http,   $location,   $modal )
{
	var params = {
		table_key: $routeParams.table_key
		};

	$scope.table = {};

	$scope.listClick = function( event ) {
		$location.url( ($scope.table.can_edit>=2 || ($scope.table.can_edit==1 && (event.altKey || event.shiftKey)) ? '/write/':'/read/') + params.table_key + '?' + event.target.parentElement.id.substring(4) );
		};

	$scope.addNewClick = function() {
		$location.url( '/write/' + params.table_key );
		};

	$http.post( 'browseStorage/json-list.php'+browseStorage.initial_search, params ).
		success( function(json) {
			$scope.table = json;
		}).
		error( function(json,status) {
			$scope.table = {};
			browseStorage.onerror( $modal, "Error reading table summary!", status, json, false );
		});
}]);


/**
 * Row Data controller for a table row.
 */
browseStorage.app.controller( 'RowDataCtrl',
[        '$scope', '$routeParams', '$http', '$location', '$modal', '$q',
function( $scope,   $routeParams,   $http,   $location,   $modal,   $q )
{
	var params = {
		table_key: $routeParams.table_key
		};

	$scope.row = {};
	$scope.datepicker_open = false;

	// Add `id0`, `id1`, `id2`, etc. parameters
	var id_num = 0;
	while( $routeParams['id'+id_num] !== undefined )
		{
		params['id'+id_num] = $routeParams['id'+id_num];
		id_num++;
		}
	$scope.add_new = ( id_num <= 0 );

	$scope.editClick = function() {
		$location.url( '/write' + $location.url().substring($location.url().lastIndexOf('/')) );
		};

	$scope.saveClick = function() {

		var delete_location_url = ( event.altKey || event.shiftKey ? '' : $location.url() );
		var col_obj, params_action = {};
		if( $scope.add_new )
			{
			params_action.table_key = params.table_key;
			params_action.action = "insert";
			}
		else
			{
			params_action = angular.copy( params );
			params_action.action = "update";
			}
		var col_num = 0;
		for( var key in $scope.row.columns)
			{
			col_obj = $scope.row.columns[key];
			if( col_obj.control != "label" )
				{
				params_action["col"+col_num] = col_obj.column;
				params_action["col"+col_num+"_value"] = col_obj.value;
				col_num++;
				}
			}
		$http.post( 'browseStorage/json-write.php'+browseStorage.initial_search, params_action ).
			success( function(json) {
				$scope.input_form.$setPristine();
				if( delete_location_url == $location.url() )
					window.history.back();
			}).
			error( function(json,status) {
				browseStorage.onerror( $modal, "Error saving entry!", status, json, true );
			});
		};

	$scope.deleteClick = function( event ) {

		var delete_location_url = $location.url();
		var promise;

		if( event.altKey || event.shiftKey )
			{
			var deferred = $q.defer();
			deferred.resolve( true );
			promise = deferred.promise;
			}
		else
			{
			promise = $modal.open( {
				templateUrl: 'modal.html',
				controller:  'ModalCtrl',
				size:        'sm',
				resolve:{
					text: function () { return {
						title:   "Proceed to delete?",
						body:    "This action cannot be undone.",
						buttons: "YesNo"
					};}
				}}).result;
			}

		promise.then( function () {

			var params_action = angular.copy( params );
			params_action.action = "delete";
			$http.post( 'browseStorage/json-write.php'+browseStorage.initial_search, params_action ).
				success( function(json) {
					if( delete_location_url == $location.url() )
						window.history.back();
				}).
				error( function(json,status) {
					browseStorage.onerror( $modal, "Error deleting entry!", status, json, true );
				});
			});
		};

	$http.post( 'browseStorage/json-read.php'+browseStorage.initial_search, params ).
		success( function(json) {
			$scope.row = json;
		}).
		error( function(json,status) {
			$scope.row = {};
			browseStorage.onerror( $modal, "Error reading entry!", status, json, false );
		});
}]);


/**
 * Application route provider. Should be last thing as manual states that
 * `controller` properties should have controler names that *are already registered*.
 */
browseStorage.app.config( [
         '$routeProvider',
function( $routeProvider )
{
	$routeProvider.
		when('/about', {
			templateUrl: 'browseStorage/page-about.html'
		}).
		when('/docs', {
			templateUrl: 'browseStorage/page-docs.html'
		}).
		when('/list/:table_key', {
			templateUrl: 'browseStorage/page-list.html',
			controller:  'RowListCtrl'
		}).
		when('/read/:table_key', {
			templateUrl: 'browseStorage/page-read.html',
			controller:  'RowDataCtrl'
		}).
		when('/write/:table_key', {
			templateUrl: 'browseStorage/page-write.html',
			controller:  'RowDataCtrl'
		}).
		otherwise({
			redirectTo: '/about'
		});
}]);


/**
 * Defer loading CSS (run once upon AngularJS startup).
 * Suggested by Google:
 * https://developers.google.com/speed/docs/insights/OptimizeCSSDelivery#example
 *
 * Mimmics a deferred:
 * `<link rel="stylesheet" type="text/css" href="//netdna.bootstrapcdn.com/bootstrap/3.1.1/css/bootstrap.min.css" />`
 *
 * This has been removed as it fails to take advantage of browser caching,
 * always flickering the screen as the browser uses the fallback style and
 * then the Bootstrap style. An alternative using `<link onload="..."/>` has
 * since been preferred. Flickering still exists but is *much* shorter and
 * almost as good as the unavoidable flickering of having the default browser
 * CSS be used before the Boostrap CSS loads.
 *
 * Support:
 * http://pieisgood.org/test/script-link-events/
 */
/*
browseStorage.app.run( [
         '$http',
function( $http )
{
	$http.get( '//netdna.bootstrapcdn.com/bootstrap/3.1.1/css/bootstrap.min.css' ).
		success( function(css, s, h, config) {

			var s_new, s_old = document.getElementById( 'temp-style' );

			try	{
				// Adjust all url()s
				css = css.replace( /url\s*\(\s*['"]?/ig, "$&" + config.url.substring(0, config.url.lastIndexOf('/')+1).replace(/[$]/g, '$$') );

				s_new = document.createElement( 'style' );
				s_new.type = 'text/css';

				if( s_new.styleSheet )
					s_new.styleSheet.cssText = css;  // IE
				else
					s_new.innerHTML = css;           // W3C
					/+ Safari 3 returned a DOM exception
					   "no modification allowed" here,
					   hence the try/catch +/
				}

			catch( err )
				{
				s_new = document.createElement( 'link' );
				s_new.href = config.url;
				s_new.rel  = 'stylesheet';
				s_new.type = 'text/css';
				}

			// Now replace the previous (temporary) stylesheet to avoid side-effects
			s_old.parentNode.replaceChild( s_new, s_old );
		});
}]);
*/
