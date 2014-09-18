<?php
/**
 * Retrieve all available rows from a table.
 * Called with POST/GET. Returns JSON.
 * Requires "json-common.php" and "config.php".
 *
 * This script receives the following arguments:
 * * `$_POST['table_key']`: The table to list.
 * * `$_POST['count']`: If present, return the total number of rows on the
 *   table.
 * * `$_POST['row_start']`: The first row number to return. If missing,
 *   defaults to 0 (the first row).
 * * `$_POST['row_limit']`: The number of rows to return. If missing,
 *   defaults to all rows.
 * * `$_POST['search']`: A string to search in all visible columns returned.
 *   If missing or an empty string, no search is performed and all rows are
 *   returned.
 * * `$_POST['col0']`: The identifier of the first column to match/filter.
 * * `$_POST['col0_value']`: The value to match/filter that first column to.
 * * `$_POST['col1']`: The identifier of the second column to match/filter.
 * * `$_POST['col1_value']`: The value to match/filter that second column to.
 * More `$_POST['col#']` and `$_POST['col#_value']` arguments may be used,
 * as necessary.
 *
 * Returns a JSON object with three properties:
 * * `"error":` Always `false` unless an error occurred (see bellow).
 * * `"name":` A string with the title shown while listing this table
 *   (usually the table name).
 * * `"can_edit":` An integer with one of the `\browseStorage\TableClass::*EDITABLE*`
 *   properties for this table. It's 0 for not editable, 1 for editable on
 *   request and 2 for editable immediately.
 * * `"can_insert":` A boolean stating wether new records can be added/inserted.
 * * `"can_delete":` A boolean stating wether records can be deleted.
 * * `"count":` A number indicating the total number of rows available. Only
 *   present if `$_POST['count']` was present as well.
 * * `"row_start":` A number indicating the row number of the first returned row.
 * * `"row_cols":` An object that names the columns and descriptions for each
 *   returned row value.
 * * `"rows":` An array of arrays representing each returned table row.
 * The `"row_cols"` object in turn has the following properties:
 * * `"col_id":` An array of strings of column names of primary key columns.
 * * `"col_list":` An array of strings of columns names of values to list.
 * * `"names_list":` An array of strings of headers for each column to list.
 * Each of the `"rows"` arrays in turn have two items:
 * * `[0]` An array of strings/numbers/booleans of values of primary key
 *   columns (`"val_id"`).
 * * `[1]` An array of strings/numbers/booleans of values to list
 *   (`"val_list"`).
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
// ####  Get available rows in table  #########################################
// ============================================================================


	/**
	 * This will store the array that will be turned into JSON to be returned.
	 * See the description at the top of this file for the syntax.
	 * @var array
	 */
	$json = array(	'error'      => false,
			'name'       => "*",
			'can_edit'   => 0,
			'can_insert' => false,
			'can_delete' => false,
			'row_start'  => 0,
			'row_cols'   => array( 'col_id'     => array(),
					       'col_list'   => array(),
					       'names_list' => array() ),
			'rows'       => array()
	);

	/**
	 * Shortcut (reference) into `$json['row_cols']`.
	 * @var array
	 */
	$json_row_cols =& $json['row_cols'];

	/**
	 * Shortcut (reference) into `$json['rows']`.
	 * @var array
	 */
	$json_rows =& $json['rows'];


	// Prepare arguments to be passed to the "before" filter
	// ====================================================================

	$table_key = strval( @$_POST['table_key'] );

	// New \browseStorage\TableClass object
	$tab_obj = new \browseStorage\TableClass( $table_key );
	$tab =& $tab_obj->tab;  // shortcut

	// Get configured table primary keys (IDs)
	$config_ids = $tab_obj->config_ids();

	// Get configured table list columns
	if( !is_array(@$tab['col_list'])             ||
		((count($tab['col_list'])-1) & ~1) != 0 )  // i.e., !=(1 or 2)
	throw new \Exception( sprintf($tab_obj->error_sprintf, "Missing key ['col_list'] (array) in") );
	//
	$config_list_cols  =  $tab['col_list'][0];
	$config_list_order = @$tab['col_list'][1];
	//
	if( !is_array($config_list_cols) )
		$config_list_cols = array( strval($config_list_cols) );
	if( !is_array($config_list_order) )
		$config_list_order = ( $config_list_order === NULL ? array() : array(strval($config_list_order)) );
	if( count($config_list_cols) <= 0 )
		throw new \Exception( sprintf($tab_obj->error_sprintf, "Number of columns specified in first item of key ['col_list'] is zero, in") );
	//
	$json_row_cols['col_id']   = $config_ids;
	$json_row_cols['col_list'] = $config_list_cols;
	foreach( $config_list_cols as $col )
		{
		$name = @$tab['col_names'][$col];
		if( is_array($name) )
			$name = @$name[0];
		if( $name === NULL )
			$name = \browseStorage\TableClass::ident_to_name( $col );
		$json_row_cols['names_list'][] = strval( $name );
		}

	// Prepare list of columns values to match/filter
	$req_col_values = $tab_obj->req_col_values();

	// Setup common `$json` properties
	$json['name'] = ( isset($tab['name']) ? strval($tab['name']) : \browseStorage\TableClass::ident_to_name(@$tab['table']) );
	$json['can_edit'] = intval(@$tab['editable']) & (
		\browseStorage\TableClass::NOT_EDITABLE        |
		\browseStorage\TableClass::EDITABLE_ON_REQUEST |
		\browseStorage\TableClass::EDITABLE_IMMEDIATELY );
	$json['can_insert'] = ( (intval(@$tab['editable']) & \browseStorage\TableClass::CAN_INSERT) != 0 );
	$json['can_delete'] = ( (intval(@$tab['editable']) & \browseStorage\TableClass::CAN_DELETE) != 0 );

	// Rows, etc.
	$req_do_count  = isset( $_POST['count'] );
	$req_row_start = ( is_numeric(@$_POST['row_start']) ? intval($_POST['row_start']) : false );
	$req_row_limit = ( is_numeric(@$_POST['row_limit']) ? intval($_POST['row_limit']) : false );
	$req_search    = ( strlen(@$_POST['search']) > 0 ? strval($_POST['search']) : false );
	//
	if( $req_row_start < 0  ||  $req_row_limit < 0 )
		throw new \Exception( "$tab_obj->error_script called with negative row_start or row_limit." );
	if( $req_search !== false )
		throw new \Exception( "Searches not yet supported (when calling $tab_obj->error_script)." );
		// TODO: This is incomplete support for searches

	// Call "before" filter, if present
	// ====================================================================

	if( isset($tab['filter_list_before']) )
		{
		$fn = $tab['filter_list_before'];
		if( !function_exists($fn) )
			throw new Exception( "Missing before filter function '$fn()', when calling $tab_obj->error_script." );
		$filter_ret = $fn( $table_key, $tab_obj, $req_do_count, $req_row_start, $req_row_limit, $req_col_values, $req_search, $json );
		}
	else
		$filter_ret = \browseStorage\TableClass::FILTER_REQ_CALLER_PROCEEDS;

	// Do our own data retrieval, unless the filter asks for skipping this
	// ====================================================================

	if( $filter_ret != \browseStorage\TableClass::FILTER_REQ_CALLER_RETURNS )
		{
		// Count all rows
		if( $req_do_count )
			{
			switch( $tab_obj->src_type )
				{
				case \browseStorage\TableClass::TYPE_PDO:
					$pdo_s = $tab_obj->src->query( "SELECT COUNT(*) FROM ".$tab['table'] );
					$c = $pdo_s->fetchAll( PDO::FETCH_COLUMN, 0 );
					$json['count'] = intval( @$c[0] );
					break;
				default:
					throw new \Exception( "Non-PDO data sources not yet supported (when calling $tab_obj->error_script)." );
					// TODO: This is incomplete support for other source types
				}
			}

		switch( $tab_obj->src_type )
			{
			case \browseStorage\TableClass::TYPE_PDO:
				if( is_string($filter_ret)  &&  !strncasecmp($filter_ret, "SELECT ", 7) )
					$select = $filter_ret;
				else
					{
					$columns = implode( ", ", array_unique(array_merge($config_ids, $config_list_cols), SORT_REGULAR) );

					// prepare SQL WHERE
					if( is_string($filter_ret)  &&  !strncasecmp(ltrim($filter_ret), "WHERE ", 6) )
						$where = " " . ltrim($filter_ret);
					else
						$where = $tab_obj->where_from_req( $req_col_values );

					// prepare SQL ORDER BY
					$orderby = "";
					foreach( $config_list_order as $col )
						{
						if( $col{0} == '-' )
							$col = substr($col, 1) . " DESC";
						if( strlen($orderby) > 0 )
							$orderby .= ", ";
						$orderby .= $col;
						}
					if( strlen($orderby) > 0 )
						$orderby = " ORDER BY $orderby";

					// prepare SQL SELECT query
					$select = "SELECT $columns FROM ".$tab['table'] . $where . $orderby;
					if( $req_row_limit !== false )
						$select .= " LIMIT $req_row_limit";
					if( $req_row_start !== false )
						{
						$json['row_start'] = $req_row_start;
						$select .= " OFFSET $req_row_start";
						}
					}

				// run the query and get the rows
				$pdo_s = $tab_obj->src->query( $select );
				while( ($row = $pdo_s->fetch(PDO::FETCH_ASSOC)) !== false )
					{
					$val_id = array();
					foreach( $config_ids as $col )
						$val_id[] = @$row[$col];
					$val_list = array();
					foreach( $config_list_cols as $col )
						$val_list[] = @$row[$col];
					$json_rows[] = array( $val_id, $val_list );
					/* This was modified for performance reasons
					$json_rows[] = array(
						'val_id'   => $val_id,
						'val_list' => $val_list,
						);
					*/
					}
				break;

			default:
				throw new \Exception( "Non-PDO data sources not yet supported (when calling $tab_obj->error_script)." );
				// TODO: This is incomplete support for other source types
			}
		}

	// Call the "after" filter, if present
	// ====================================================================

	if( isset($tab['filter_list_after']) )
		{
		$fn = $tab['filter_list_after'];
		if( !function_exists($fn) )
			throw new Exception( "Missing after filter function '$fn()', when calling $tab_obj->error_script." );
		$fn( $table_key, $tab_obj, $req_do_count, $req_row_start, $req_row_limit, $req_col_values, $req_search, $json );
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
