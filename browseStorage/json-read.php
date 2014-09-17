<?php
/**
 * Read data from table.
 * Called with POST/GET. Returns JSON.
 * Requires "json-common.php" and "config.php".
 *
 * This script receives the following arguments:
 * * `$_POST['table_key']`: The table to read from.
 * * `$_POST['id0']`: The value of the first primary key to match against.
 * * `$_POST['id1']`: The (optional) value of the second primary key to
 *   match against.
 * More `$_POST['id#']` arguments may be used, as necessary.
 * `json-read.php` will make sure the number of such arguments is exactly
 * the same as the number of primary keys defined for the table, or zero.
 * If zero, no row data is retrieved from the table, only the generic column
 * descriptions are returned.
 *
 * Returns a JSON object with three properties:
 * * `"error":` Always `false` unless an error occurred (see bellow).
 * * `"col_name":` An integer that has the `columns[]` key of the column that
 *   holds the title shown while viewing/editing this row.
 * * `"can_edit":` An integer with one of the `\browseStorage\TableClass::*EDITABLE*`
 *   properties for this table. It's 0 for not editable, 1 for editable on
 *   request and 2 for editable immediately.
 * * `"can_insert":` A boolean stating wether new records can be added/inserted.
 * * `"can_delete":` A boolean stating wether records can be deleted.
 * * `"columns":` An object representing the returned table row.
 * The `columns` object in turn has the following properties:
 * * `"column":` A string with the column identifier as it is known in the data
 *   source.
 * * `"name":` A string with the column name to show the user.
 * * `"control":` A string with the lowercased form input control type for this
 *   column.
 * * `"help":` A string with help text for filling-in this column. Can be
 *   missing if there is no such help text. It is never an empty string.
 * * `"value":` A string/integer/boolean with the current column value.
 * * `"options":` Optional that is only present if one of your filters adds it,
 *   or is present in the 4th item of the corresponding 'col_names'
 *   description.
 *   This is the list of values available for this column. This is an
 *   associative array `"value" => "text"` that will be displayed as a list
 *   of available radio buttons or an HTML select, depending on `"control"`.
 *   If `"control"` needs this property and it is missing, one will be created
 *   before returning.
 * If no `$_POST['id#']` arguments are presented to this script, the
 * `"value"` property will be missing.
 *
 * If an error occurs, the response will indicate an HTTP 500 error (Internal
 * Server Error) and the returned object will have (only) the following
 * properties:
 * * `"error":` Always `true`.
 * * `"code":` A string holding the error code.
 * * `"message":` A string holding the error message.
 * Error code and message are the values returned by PHP's Exception object's
 * `getCode()` and `getMessage()` respectively.
 *
 *
 * Security warning:
 * These scripts do not validate user permission to use them, or ensure data
 * privacy (encryption). They should be hosted on a server that has these files
 * configured for access though some sort of authorization, and via HTTPS.
 * These scripts already prefix the returned JSON with a pre-determined sequence
 * that AngularJS knows how to remove, for security reasons.
 * @see https://docs.angularjs.org/api/ng/service/$http
 *
 *
 * This file uses Zend Framework 2 coding standard with a few exceptions:
 *	http://framework.zend.com/manual/1.12/en/coding-standard.html
 * It does not follow the standard completely:
 * - Tabulation is done with the tab character
 *	For developers who upload these files to a Web server without
 *	compacting them using "php -w", the tab characters save at least 3
 *	bytes per line, or 3kb on a 1000-line file. This will make file
 *	transmission and parsing faster, at little or no cost to readability.
 * - Tabulation occurs on the default 8-character stops
 *	Not all developers use advanced tools where you can change tab size.
 *	And all developers occasionaly find themselves looking at PHP files
 *	on a Web server using standard "cat" or "vi" commands that haven't
 *	been configured for PHP use, and will display tabs with 8 characters.
 * - Indentation style follows Whitesmiths/Wishart (a variant of Allman/BSD)
 *	http://en.wikipedia.org/wiki/Indent_style
 *	Indentation style is usually a matter of preference. However, even the
 *	cited Wikipedia article on code indentation describes it as having the
 *	most advantages in terms of code readability at a glance, reducing the
 *	occurence of bugs.
 * - Other exceptions
 *	The code standard is broken when there seems to be valid reason
 *	(e.g.: code readability) on a case-by-case basis.
 */


// ============================================================================
// ####  Error handling  ######################################################
// ============================================================================


/**
 * PHP error handler.
 *
 * Converts a PHP error into an exception.
 * See PHP documentation for parameter meaning.
 *
 * @throws \Exception
 */
function error_handler( $code, $message, $file, $line )
{
	throw new \Exception( "$file:$line: $message", $code );
}

// Start catching unexpected output (that would damage JSON)
$ob_active = ob_start( NULL, 0, (version_compare(PHP_VERSION, '5.4.0', '>=') ? PHP_OUTPUT_HANDLER_CLEANABLE | PHP_OUTPUT_HANDLER_REMOVABLE : true) );


try	{
	// Start catching PHP errors and warnings
	set_error_handler( 'error_handler', E_CORE_ERROR | E_PARSE | E_COMPILE_ERROR | E_ERROR | E_USER_ERROR | 4096 );
		// 4096 = E_RECOVERABLE_ERROR, only available since PHP 5.2.0
	error_reporting( E_ALL );


// ============================================================================
// ####  Require  #############################################################
// ============================================================================

	// Initial code requirements
	require_once( 'json-common.php' );
	require_once( 'config.php' );


// ============================================================================
// ####  Transform an AngularJS JSON POST into PHP's $_POST  ##################
// ============================================================================


	if( strpos(@$_SERVER['HTTP_CONTENT_TYPE'], "/json") !== false  ||
	    strpos(@$_SERVER[     'CONTENT_TYPE'], "/json") !== false )
		$_POST = get_object_vars( json_decode(file_get_contents('php://input')) );
		// Note that $_REQUEST was not updated!


// ============================================================================
// ####  Read data from table  ################################################
// ============================================================================


	/**
	 * This will store the array that will be turned into JSON to be returned.
	 * See the description at the top of this file for the syntax.
	 * @var array
	 */
	$json = array(	'error'      => false,
			'col_name'   => 0,
			'can_edit'   => 0,
			'can_insert' => false,
			'can_delete' => false,
			'columns'    => array()
	);

	/**
	 * Shortcut (reference) into `$json['columns']`.
	 * @var array
	 */
	$json_columns =& $json['columns'];

	/**
	 * This will translate a column identifier, to the key used in `$json_row`.
	 * @var string[]
	 */
	$names_xlat = array();


	// Prepare arguments to be passed to the "before" filter
	// ====================================================================

	$table_key = strval( @$_POST['table_key'] );

	// New \browseStorage\TableClass object
	$tab_obj = new \browseStorage\TableClass( $table_key );
	$tab =& $tab_obj->tab;  // shortcut

	// Get configured table primary keys (IDs)
	$config_ids = $tab_obj->config_ids();

	// Retrieve IDs from $_POST into array(column_id => value)
	$req_ids = $tab_obj->req_ids( $config_ids );
	$no_req_ids = ( count($req_ids) <= 0 );

	// Setup common `$json` properties
	$json['can_edit'] = intval(@$tab['editable']) & (
			\browseStorage\TableClass::NOT_EDITABLE        |
			\browseStorage\TableClass::EDITABLE_ON_REQUEST |
			\browseStorage\TableClass::EDITABLE_IMMEDIATELY );
	$json['can_insert'] = ( (intval(@$tab['editable']) & \browseStorage\TableClass::CAN_INSERT) != 0 );
	$json['can_delete'] = ( (intval(@$tab['editable']) & \browseStorage\TableClass::CAN_DELETE) != 0 );

	// Get configured table column names
	$config_names = $tab_obj->config_names();
	$auto_cols = ( count($config_names) <= 0 );

	// Retrieve columns names into `$json['columns']`
	foreach( $config_names as $col => $a )
		{
		if( !is_array($a) )
			$a = array( $a );
		$json_columns[] = array(
			'column'   => $col,
			'name'     => ( is_string(@$a[0]) ? $a[0] : \browseStorage\TableClass::ident_to_name($col) ),
			'control'  => ( isset($a[1]) ? strtolower($a[1]) : "text" ),
		);
		end( $json_columns );
		$names_xlat[$col] = key( $json_columns );
			// = the same as count($json_row)-1, but...
			// just in case PHP changes its algorithm
		if( strlen(@$a[2]) > 0 )
			$json_columns[$names_xlat[$col]]['help'] = strval( $a[2] );
		if( is_array(@$a[3]) )
			$json_columns[$names_xlat[$col]]['options'] = strval( $a[3] );
		}

	// Now that we have all columns in `$names_xlat`, let's get the `$json['col_name']`
	if( !is_array(@$tab['col_list'])  ||  count($tab['col_list']) < 1 )
		throw new \Exception( sprintf($tab_obj->error_sprintf, "Missing key ['col_list'] (array) in") );
	//
	$list_col0 = $tab['col_list'][0];
	if( is_array($list_col0) )
		$list_col0 = reset( $list_col0 );
	//
	$col_name = ( isset($tab['col_name']) ? strval($tab['col_name']) : strval($list_col0) );
	$json['col_name'] = ( isset($names_xlat[$col_name]) ? $names_xlat[$col_name] : 0 );

	// Call "before" filter, if present
	// ====================================================================

	if( isset($tab['filter_read_before']) )
		{
		$fn = $tab['filter_read_before'];
		if( !function_exists($fn) )
			throw new Exception( "Missing before filter function '$fn()', when calling $tab_obj->error_script." );
		$filter_ret = $fn( $table_key, $tab_obj, $req_ids, $json );
		}
	else
		$filter_ret = \browseStorage\TableClass::FILTER_REQ_CALLER_PROCEEDS;

	// Do our own data retrieval, unless the filter asks for skipping this
	// ====================================================================

	if( $filter_ret != \browseStorage\TableClass::FILTER_REQ_CALLER_RETURNS )
		{
		switch( $tab_obj->src_type )
			{
			case \browseStorage\TableClass::TYPE_PDO:
				if( $auto_cols )
					$columns = "*";
				else
					$columns = implode( ", ", array_keys($config_names) );

				// prepare SQL WHERE
				$where = $tab_obj->where_from_req( $req_ids );

				// prepare SQL SELECT query
				$select = "SELECT $columns FROM " . $tab['table'] . $where . " LIMIT 1";

				// run the query and get the rows
				$pdo_s = $tab_obj->src->query( $select );
				$row = $pdo_s->fetch( PDO::FETCH_ASSOC );
				if( $row !== false )
					{
					foreach( $row as $col => $value )
						{
						if( $auto_cols )
							{
							$json_columns[] = array(
								'column'   => $col,
								'name'     => \browseStorage\TableClass::ident_to_name($col),
								'control'  => "text",
								);
							end( $json_columns );
							$names_xlat[$col] = key( $json_columns );
								// = the same as count($json_row)-1, but...
								// just in case PHP changes its algorithm
							}
						if( !$no_req_ids  &&  isset($names_xlat[$col]) )
							$json_columns[$names_xlat[$col]]['value'] = $value;
						}
					}
				break;

			default:
				throw new \Exception( "Non-PDO data sources not yet supported (when calling $tab_obj->error_script)." );
				// TODO: This is incomplete support for other source types
			}

		// `$names_xlat` may have been updated, let's revisit `$json['col_name']`
		$json['col_name'] = ( isset($names_xlat[$col_name]) ? $names_xlat[$col_name] : 0 );
		}

	// Call the "after" filter, if present
	// ====================================================================

	if( isset($tab['filter_read_after']) )
		{
		$fn = $tab['filter_read_after'];
		if( !function_exists($fn) )
			throw new Exception( "Missing after filter function '$fn()', when calling $tab_obj->error_script." );
		$fn( $table_key, $tab_obj, $req_ids, $json );
		}

	// Add any missing "options" arrays
	// ====================================================================

	foreach( $json_columns as $col => $a )
		{
		if( ($a['control'] == "select"  ||  $a['control'] == "radio")  &&
		    (!isset($a['options'])  ||  count($a['options']) <= 0) )
			{
			if( isset($a['value']) )
				$json_columns[$col]['options'] = array( $a['value'] => "*" );
			else
				$json_columns[$col]['options'] = array();
				// no value => no options to choose from
			}
		}

	// Finish
	// ====================================================================

	// Check if PHP made any (unexpected) output, and if so, report it to the caller as an error,
	// by throwing an exception.
	if( $ob_active )
		{
		$output = ob_get_clean();
		$ob_active = false;
		if( $output != "" )
			throw new \Exception( "Unexpected PHP output:\n$output" );
		}

	restore_error_handler();
	}

catch ( Exception $e )
	{
	// Exception handling
	// ====================================================================

	restore_error_handler();
	header( "HTTP/1.0 500 PHP Exception thrown" );
	$json = array(	'error'    => true,
			'code'     => $e->getCode(),
			'message'  => $e->getMessage()
		);

	// Retrieve any unexpected PHP output, as that may help in debugging
	if( $ob_active )
		{
		$output = ob_get_clean();
		$ob_active = false;
		if( $output != "" )
			$json['message'] .= "\nFurthermore, there was unexpected PHP output:\n$output";
		}
	}


// ============================================================================
// ####  Output feedback/result  ##############################################
// ============================================================================


// Setup to return GZip compressed result, if the browser supports it
if( (!ini_get("zlib.output_compression")  ||  !strcasecmp(ini_get("zlib.output_compression"), "Off"))  &&
    extension_loaded("zlib") )
	ob_start( "ob_gzhandler" );

// Output the result
echo  ")]}',\n",  // @see https://docs.angularjs.org/api/ng/service/$http
      json_encode( $json );
// This script returns an object as is therefore immune to the vulnerability
// that AngularJS fixes with this prefix. But we're still adding it
// "just in case" a similar vulnerability for objects is discovered.
