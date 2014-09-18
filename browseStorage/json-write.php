<?php
/**
 * Write data to table.
 * Called with POST/GET. Returns JSON.
 * Requires "json-common.php" and "config.php".
 *
 * This script receives the following arguments:
 * * `$_POST['table_key']`: The table to write to.
 * * `$_POST['action']`: The write action to do. Must be one of "insert", "update" or "delete".
 * * `$_POST['id0']`: The value of the first primary key to match against.
 * * `$_POST['id1']`: The (optional) value of the second primary key to
 *   match against.
 * More `$_POST['id#']` arguments may be used, as necessary.
 * * `$_POST['col0']`: The identifier of the first column to set.
 * * `$_POST['col0_value']`: The value to set that first column to.
 * * `$_POST['col1']`: The identifier of the second column to set.
 * * `$_POST['col1_value']`: The value to set that second column to.
 * More `$_POST['col#']` and `$_POST['col#_value']` arguments may be used, as necessary.
 * If action is "delete", no column values may be specified.
 * If action is "insert", no ID values may be specified.
 * If action is "update" or "delete", ID values are mandatory.
 * If action is "insert" or "update", column values are mandatory.
 *
 * Returns a JSON object with one property:
 * * `"error":` Always `false` unless an error occurred (see bellow).
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


// Base classes and utilities
require_once( 'json-common.php' );

use browseStorage\TableClass;
use browseStorage\RawSQL;


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
$pdo_begin = false;  // true if we have started a transaction


try	{
	// Start catching PHP errors and warnings
	set_error_handler( 'error_handler', E_CORE_ERROR | E_PARSE | E_COMPILE_ERROR | E_ERROR | E_USER_ERROR | 4096 );
		// 4096 = E_RECOVERABLE_ERROR, only available since PHP 5.2.0
	error_reporting( E_ALL );


// ============================================================================
// ####  Load configuration  ##################################################
// ============================================================================


	/**
	 * Initializing function. Allows the developer to create any local
	 * variables in `config.php` without "polluting" the global namespace.
	 */
	function browseStorage_init()
	{
		require_once( 'config.php' );
	}

	browseStorage_init();


// ============================================================================
// ####  Transform an AngularJS JSON POST into PHP's $_POST  ##################
// ============================================================================


	if( strpos(@$_SERVER['HTTP_CONTENT_TYPE'], "/json") !== false  ||
	    strpos(@$_SERVER[     'CONTENT_TYPE'], "/json") !== false )
		$_POST = get_object_vars( json_decode(file_get_contents('php://input')) );
		// Note that $_REQUEST was not updated!


// ============================================================================
// ####  Write data to table  #################################################
// ============================================================================


	/**
	 * This will store the array that will be turned into JSON to be returned.
	 * See the description at the top of this file for the syntax.
	 * @var array
	 */
	$json = array( 'error' => false );


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

	// Get configured table column names
	$config_names = $tab_obj->config_names();
	$auto_cols = ( count($config_names) <= 0 );

	// Prepare list of columns values to write
	$req_col_values = $tab_obj->req_col_values();

	// Security and sanity checks
	// ====================================================================

	// Read-only table security check
	if( !isset($tab['editable'])  ||  $tab['editable'] <= \browseStorage\TableClass::NOT_EDITABLE )
		throw new \Exception( sprintf($tab_obj->error_sprintf, "Data table set as not editable in") );

	// Read-only column security check
	if( !$auto_cols )
		{
		foreach( $req_col_values as $col => $value )
			if( !isset($config_names[$col])  ||  strval(@$config_names[$col][1]) == "label" )
				throw new \Exception( "\$_POST['col#']='$col' is trying to set a column that cannot be modified, when calling $tab_obj->error_script." );
		}

	// Set `$action` to the uppercased first character of the action, and validate each of their arguments
	switch( strtolower(@$_POST['action']) )
		{
		case "insert":
			if( !$no_req_ids  ||  count($req_col_values) <= 0 )
				throw new \Exception( "Insert action cannot have \$_POST['id#'] and must have \$_POST['col#'], when calling $tab_obj->error_script." );
			$action = 'I';
			break;
		case "update":
			if( $no_req_ids  ||  count($req_col_values) <= 0 )
				throw new \Exception( "Insert action must have \$_POST['id#'] and \$_POST['col#'], when calling $tab_obj->error_script." );
			$action = 'U';
			break;
		case "delete":
			if( $no_req_ids  ||  count($req_col_values) > 0 )
				throw new \Exception( "Insert action must have \$_POST['id#'] and cannot have \$_POST['col#'], when calling $tab_obj->error_script." );
			$action = 'D';
			break;
		default:
			throw new \Exception( "\$_POST['action'] is missing or has an unknown action value when calling $tab_obj->error_script." );
		}

	// Start transaction
	// ====================================================================

	if( $tab_obj->src_type == \browseStorage\TableClass::TYPE_PDO )
		$pdo_begin = $tab_obj->src->beginTransaction();

	// Call "before" filter, if present
	// ====================================================================

	if( isset($tab['filter_write_before']) )
		{
		$fn = $tab['filter_write_before'];
		if( !function_exists($fn) )
			throw new Exception( "Missing before filter function '$fn()', when calling $tab_obj->error_script." );
		$filter_ret = $fn( $table_key, $tab_obj, $action, $req_ids, $req_col_values, $json );
		}
	else
		$filter_ret = \browseStorage\TableClass::FILTER_REQ_CALLER_PROCEEDS;

	// Do our own data retrieval, unless the filter asks for skipping this
	// ====================================================================

	$affected_rows = false;
	if( $filter_ret != \browseStorage\TableClass::FILTER_REQ_CALLER_RETURNS )
		{
		switch( $tab_obj->src_type )
			{
			case \browseStorage\TableClass::TYPE_PDO:

				// prepare SQL WHERE
				$where = $tab_obj->where_from_req( $req_ids );

				// prepare SQL query
				switch( $action )
					{
					case 'I':
						$select = "";
						foreach( $req_col_values as $col => $value )
							{
							if( $value instanceof \browseStorage\RawSQL )
								$select .= $value->sql . ", ";
							else
								$select .= $tab_obj->src->quote($value) . ", ";
							}
						$select = "INSERT INTO " . $tab['table'] . " (" . implode(", ", array_keys($req_col_values)) . ") VALUES (" . substr($select, 0, -2) . ")" . $where;
						break;
					case 'U':
						$select = "";
						foreach( $req_col_values as $col => $value )
							{
							if( $value instanceof \browseStorage\RawSQL )
								$select .= $col . "=" . $value->sql . ", ";
							else
								$select .= $col . "=" . $tab_obj->src->quote($value) . ", ";
							}
						$select = "UPDATE " . $tab['table'] . " SET " . substr($select, 0, -2) . $where;
						break;
					case 'D':
						$select = "DELETE FROM " . $tab['table'] . $where;
						break;
					// If due to an error, `$action` is something else, do nothing
					}

				// run the query
				$affected_rows = $tab_obj->src->exec( $select );
				break;

			default:
				throw new \Exception( "Non-PDO data sources not yet supported (when calling $tab_obj->error_script)." );
				// TODO: This is incomplete support for other source types
			}
		}

	// Call the "after" filter, if present
	// ====================================================================

	if( isset($tab['filter_write_after']) )
		{
		$fn = $tab['filter_write_after'];
		if( !function_exists($fn) )
			throw new Exception( "Missing after filter function '$fn()', when calling $tab_obj->error_script." );
		$fn( $table_key, $tab_obj, $action, $req_ids, $req_col_values, $json, $affected_rows );
		}

	// Commit transaction
	// ====================================================================

	if( $pdo_begin )
		{
		$tab_obj->src->commit();
		$pdo_begin = false;
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

	// Roll back transaction
	// ====================================================================

	if( $pdo_begin )
		{
		$tab_obj->src->rollBack();
		$pdo_begin = false;
		}

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
