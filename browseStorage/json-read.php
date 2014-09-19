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
 * * `"can_edit":` An integer with one of the `browseStorage\TableClass::*EDITABLE*`
 *   properties for this table. It's 0 for not editable, 1 for editable on
 *   request and 2 for editable immediately.
 * * `"can_insert":` A boolean stating wether new records can be added/inserted.
 * * `"can_delete":` A boolean stating wether records can be deleted.
 * * `"columns":` An object representing the returned table row.
 * * `"buttons":` An array of custom buttons to insert at the bottom of the
 *   entry.
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
 * The `buttons` array contains one object per button. Each of those objects has
 * the following properties:
 * * `"name":` Button name as it will be displayed.
 * * `"help":` Button help description, if any (may be an empty string, but it
 *             is never missing).
 * * `"url":` Button destination URL.
 * More column items may be used, as necessary.
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
			'columns'    => array(),
			'buttons'    => array()
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

	// New browseStorage\TableClass object
	$tab_obj = new TableClass( $table_key );
	$tab =& $tab_obj->tab;  // shortcut

	// Get configured table primary keys (IDs)
	$config_ids = $tab_obj->config_ids();

	// Retrieve IDs from $_POST into array(column_id => value)
	$req_ids = $tab_obj->req_ids( $config_ids );
	$no_req_ids = ( count($req_ids) <= 0 );

	// Setup common `$json` properties
	$json['can_edit'] = intval(@$tab['editable']) & (
			TableClass::NOT_EDITABLE        |
			TableClass::EDITABLE_ON_REQUEST |
			TableClass::EDITABLE_IMMEDIATELY );
	$json['can_insert'] = ( (intval(@$tab['editable']) & TableClass::CAN_INSERT) != 0 );
	$json['can_delete'] = ( (intval(@$tab['editable']) & TableClass::CAN_DELETE) != 0 );

	// Get configured table column names
	$config_names = $tab_obj->config_names();
	$auto_cols = ( count($config_names) <= 0 );

	// Retrieve columns names into `$json['columns']`, and setup $options_pending_sql
	$options_pending_sql = array();
	foreach( $config_names as $col => $a )
		{
		if( !is_array($a) )
			$a = array( $a );
		$json_columns[] = array(
			'column'   => $col,
			'name'     => ( is_string(@$a[0]) ? $a[0] : TableClass::ident_to_name($col) ),
			'control'  => ( isset($a[1]) ? strtolower($a[1]) : "text" ),
		);
		end( $json_columns );
		$names_xlat[$col] = key( $json_columns );
			// = the same as count($json_row)-1, but...
			// just in case PHP changes its algorithm
		if( strlen(@$a[2]) > 0 )
			$json_columns[$names_xlat[$col]]['help'] = strval( $a[2] );
		if( isset($a[3]) )
			{
			$a = $a[3];
			if( is_array($a) )
				$json_columns[$names_xlat[$col]]['options'] = $a;
			else
				{
				if( $a instanceof RawSQL )
					$a = $a->sql;
				if( is_string($a)  &&  !strncasecmp($a, "SELECT ", 7) )
					$options_pending_sql[$col] = $a;
				else
					throw new \Exception( sprintf($tab_obj->error_sprintf, "Bad options ['col_names'][...][3] in") );
				}
			}
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

	// Finally, let's find any column identifiers required by $tab['buttons']
	// that are not in $config_names
	$button_columns = array();  // Only those not it $config_names
	if( !$no_req_ids  &&  isset($tab['buttons']) )
		{
		if( !is_array($tab['buttons']) )
			throw new \Exception( sprintf($tab_obj->error_sprintf, "Key ['buttons'] is not an array in") );
		foreach( $tab['buttons'] as $button )
			{
			if( !is_array($button)  ||  count($button) < 4 )
				throw new \Exception( sprintf($tab_obj->error_sprintf, "One of ['buttons'] is not an array or has insufficient array items (<4) in") );
			unset( $button[0], $button[1], $button[2] );
			foreach( $button as $col )
				{
				if( is_array($col) )
					$col = $col[0];
				if( !isset($names_xlat[$col]) )
					$button_columns[$col] = array();
				}
			}
		}

	// Call "before" filter, if present
	// ====================================================================

	if( isset($tab['filter_read_before']) )
		{
		$fn = $tab['filter_read_before'];
		if( !function_exists($fn) )
			throw new Exception( "Missing before filter function '$fn()', when calling $tab_obj->error_script." );
		$filter_ret = $fn( $table_key, $tab_obj, $req_ids, $json );
		if( $filter_ret instanceof RawSQL )
			$filter_ret = $filter_ret->sql;
		}
	else
		$filter_ret = TableClass::FILTER_REQ_CALLER_PROCEEDS;

	// Do our own data retrieval, unless the filter asks for skipping this
	// ====================================================================

	if( $filter_ret !== TableClass::FILTER_REQ_CALLER_RETURNS )
		{
		switch( $tab_obj->src_type )
			{
			case TableClass::TYPE_PDO:
				if( is_string($filter_ret)  &&  !strncasecmp($filter_ret, "SELECT ", 7) )
					$select = $filter_ret;
				else
					{
					if( $auto_cols )
						$columns = "*";
					else
						$columns = implode( ", ", array_merge(array_keys($config_names), array_keys($button_columns)) );

					// prepare SQL FROM
					if( is_string($filter_ret)  &&  !strncasecmp(ltrim($filter_ret), "FROM ", 5) )
						$from = ltrim($filter_ret);
					else
						$from = "FROM " . $tab['table'];

					// prepare SQL WHERE
					if( is_string($filter_ret)  &&  !strncasecmp(ltrim($filter_ret), "WHERE ", 6) )
						$where = ltrim( $filter_ret );
					else
						$where = $tab_obj->where_from_req( $req_ids );

					// prepare SQL SELECT query
					$select = "SELECT $columns $from $where LIMIT 1";
					}

				// run the query and get the row
				$opt_pdo_params = array();
				$pdo_s = $tab_obj->src->query( $select );
				$row = $pdo_s->fetch( PDO::FETCH_ASSOC );
				if( $row !== false )
					{
					/*
					 * TODO: Uncomment when we take the time to filter only used parameters!
					 * (currently, values here that are not used cause PDO exception!)
					// prepare argument to PDO prepare, if it ever may be needed
					if( count($options_pending_sql) > 0 )
						{
						foreach( $row as $col => $value )
							$opt_pdo_params[":$col"] = $value;
						}
					*/

					// read each column
					foreach( $row as $col => $value )
						{
						if( $auto_cols )
							{
							$json_columns[] = array(
								'column'   => $col,
								'name'     => TableClass::ident_to_name($col),
								'control'  => "text",
								);
							end( $json_columns );
							$names_xlat[$col] = key( $json_columns );
								// = the same as count($json_row)-1, but...
								// just in case PHP changes its algorithm
							}
						if( !$no_req_ids )
							{
							if( isset($names_xlat[$col]) )
								{
								if( $json_columns[$names_xlat[$col]]['control'] == "number" )
									$value = ( strpos($value, '.') !== false ? floatval($value) : intval($value) );
									// required otherwise the "number" control won't display this!
								$json_columns[$names_xlat[$col]]['value'] = $value;
								}
							else if( isset($button_columns[$col]) )
								$button_columns[$col][0] = $value;
							}

						}
					}

				// look for columns missing custom SQL 'options'
				foreach( $json_columns as $key => $jcol )
					{
					$col = $jcol['column'];
					if( isset($options_pending_sql[$col]) )
						{
						$opt_pdo_s = $tab_obj->src->prepare(
							$options_pending_sql[$col],
							array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY) );
						$opt_pdo_s->execute( $opt_pdo_params );
						$opt_rows = $opt_pdo_s->fetchAll( PDO::FETCH_NUM );
						$options = array();
						foreach( $opt_rows as $opt_row )
							$options[ $opt_row[0] ] = $opt_row[1];
						$json_columns[$key]['options'] = $options;
						}
					}
				break;

			default:
				throw new \Exception( "Non-PDO data sources not yet supported (when calling $tab_obj->error_script)." );
				// TODO: This is incomplete support for other source types
			}

		// `$names_xlat` may have been updated, let's revisit `$json['col_name']`
		$json['col_name'] = ( isset($names_xlat[$col_name]) ? $names_xlat[$col_name] : 0 );

		// Setup common `$json` buttons property (except values)
		if( !$no_req_ids  &&  isset($tab['buttons']) )
			{
			foreach( $tab['buttons'] as $button )
				{
				$button_name = strval( @$button[0] );
				$button_url  = strval( @$button[1] );
				$button_help = strval( @$button[2] );
				unset( $button[0], $button[1], $button[2] );

				$url = 0;
				if( !strncasecmp($button_url, "list:", 5) )
					$url = 1;
				else if( !strncasecmp($button_url, "read:", 5) )
					$url = 2;
				else if( !strncasecmp($button_url, "write:", 6) )
					$url = 3;

				$params = array();
				if( $url != 0 )
					$table_key = rawurlencode( substr($button_url, ($url==3 ? 6:5)) );
				$num = 0;
				foreach( $button as $col )
					{
					if( is_array($col) )
						{
						$col_dest = $col[1];
						$col      = $col[0];
						}
					else
						$col_dest = $col;

					if( isset($names_xlat[$col]) )
						$value = $json_columns[$names_xlat[$col]]['value'];
					else if( isset($button_columns[$col], $button_columns[$col][0]) )
						$value = $button_columns[$col][0];
					else
						throw new \Exception( "Requested column identifier '$col' in ['col_names'] or ['buttons'] was not found in the table! (when calling $tab_obj->error_script)." );

					switch( $url )
						{
						case 1:  // "list:"
							$params[] = "col$num=" . rawurlencode($col_dest) . "&col${num}_value=" . rawurlencode($value);
							break;
						case 2:  // "read:" (fallthrough)
						case 3:  // "write:"
							$params[] = "id$num=" . rawurlencode($value);
							break;
						default:  // Free URL
							$params[] = rawurlencode( $value );
						}
					$num++;
					}

				switch( $url )
					{
					case 1:  // "list:"
						$url = '#/list/' . $table_key . '?' . implode( '&', $params );
						break;
					case 2:  // "read:" (fallthrough)
						$url = '#/read/' . $table_key . '?' . implode( '&', $params );
						break;
					case 3:  // "write:"
						$url = '#/write/' . $table_key . '?' . implode( '&', $params );
						break;
					default:  // Free URL
						$url = vsprintf( $button_url, array_values($params) );
					}

				$json['buttons'][] = array(
					'name' => $button_name,
					'help' => $button_help,
					'url'  => $url
					);
				}
			}
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
