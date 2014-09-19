<?php
/**
 * Retrieve all available tables.
 * Called with POST/GET. Returns JSON.
 * Requires "json-common.php" and "config.php".
 *
 * Returns a JSON object with two properties:
 * * `"error":` Always `false` unless an error occurred (see bellow).
 * * `"groups":` An array of objects representing each table group.
 * Each group object has in turn the following properties:
 * * `"name":` String with this group name in UTF-8.
 * * `"tables":` An array with the tables that exist in this group.
 * The `"tables"` property is again an array of objects. It has at least one
 * object (i.e., it cannot be empty). Each object has the following properties:
 * * `"table_key":` A number or a string that is an unique ID for this table.
 * * `"name":` The name of this table in UTF-8.
 * * `"icon":` A path+filename for an image file that has an icon for this table.
 * Both `"name"` and `"icon"` may be empty strings, but will always be present,
 * like `"table_key"` will also always be present.
 *
 * Return value changes slightly if `$_REQUEST['nogroups']` exists:
 * * `"error":` Always `false` unless an error occurred (see bellow).
 * * `"tables":` An array with the tables that exist in all groups.
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
// ####  Get available tables  ################################################
// ============================================================================


	/**
	 * This will store the array that will be turned into JSON to be returned.
	 * See the description at the top of this file for the syntax.
	 * @var array
	 */
	$json = array(	'error'  => false,
			'groups' => array()
	);

	/**
	 * Shortcut (reference) into `$json['groups']`.
	 * @var array
	 */
	$json_groups =& $json['groups'];

	/**
	 * This will translate group key (an auto-integer) to group name as specified
	 * in `browseStorage\TableClass::$data_tables`.
	 * This array is first searched for a group name using:
	 * * `array_search($group_name, $groups_xlat, true)`
	 * If found, the group's key is the value returned by this function.
	 * If not found (return value is `===false`), group name is added to this
	 * array using PHP array append syntax:
	 * * `$groups_xlat[]=...`
	 * The newly generated numeric key becomes that group's key.
	 * @var string[]
	 */
	$groups_xlat = array( 0 => "" );


	// Iterate browseStorage\TableClass::$data_tables
	// ====================================================================

	$nogroups = ( isset($_REQUEST['nogroups']) );
	$group_key = 0;

	foreach( TableClass::$data_tables as $table_key => $tab )
		{
		if( isset($tab['unlisted']) )
			continue;
		$group_name = strval( @$tab['group'] );
		if( !$nogroups )
			{
			$group_key = array_search( $group_name, $groups_xlat, true );
			if( $group_key === false )
				{
				$groups_xlat[] = $group_name;
				end( $groups_xlat );
				$group_key = key( $groups_xlat );
					// = the same as count($groups_xlat)-1, but...
					// just in case PHP changes its algorithm
				}
			}
		if( !isset($json_groups[$group_key]) )
			{
			$json_groups[$group_key] = array(
				'name'   => $group_name,
				'tables' => array()
				);
			}

		$name = ( strlen(@$tab['name']) > 0    ?
		          strval($tab['name'])         :
		          TableClass::ident_to_name($tab['table']) );
		$icon  = strval( @$tab['icon' ] );
		if( $tab['table'] === '*' )
			{
			$tab_obj = new TableClass( $table_key );
			if( $tab_obj->src_type != TableClass::TYPE_PDO )
				throw new Exception( sprintf($tab_obj->error_sprintf, "Can't retrieve all tables for a non-relational-database (SQL) data source ['table'] in") );
			$pdo_s = $tab_obj->src->query( "SHOW TABLES" );
			foreach( $pdo_s->fetchAll(PDO::FETCH_COLUMN, 0) as $tab_name )
				{
				if( !preg_match('/\\A[a-zA-Z_][a-zA-Z0-9_]*\\z/', $tab_name) )
					continue;
				$json_groups[$group_key]['tables'][] = array(
					'table_key' => "$table_key|$tab_name",
					'name'      => $name,
					'icon'      => $icon
					);
				}
			}

		else
			{
			$json_groups[$group_key]['tables'][] = array(
				'table_key' => $table_key,
				'name'      => $name,
				'icon'      => $icon
				);
			}
		}

	// Remove table grouping by groups, if caller prefers it that way
	// ====================================================================

	if( $nogroups )
		{
		$json['tables'] = $json_groups[0]['tables'];
		unset( $json_groups, $json['groups'] );
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
