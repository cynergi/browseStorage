<?php
/**
 * Script configuration file.
 *
 * This is the only file you should change. Set these two arrays as specified
 * in the documentation, according to your needs. These arrays can also be set
 * based on information retrieved from `$_SESSION[]` or `$_GET[]` if you want
 * browseStorage to display different information in different parts of a
 * larger (e.g.: backoffice) application. The latter (`$_GET[]`) would be
 * preferred in order to properly support the browser's "back" buttons.
 * Do remember, however, that everything passed by GET will be visible in the
 * server logs, so limit this information to selection strings, i.e., something
 * you would use in an `if()`, `switch()` or as a folder or file name. Do not
 * use usernames or passwords in a GET request!
 *
 * Don't add code with side effects here! This file should only set one or
 * two global arrays with values. Adding code that has side effects is a
 * security issue if the file is called directly by a browser.
 *
 * Currently, to avoid global namespace pullution, this file will be included
 * inside a PHP function. This may cause restrictions and may be rethought at
 * a later date, so please heed to the above warning!
 *
 * Security warning:
 * These scripts do not validate user permission to use them, or ensure data
 * privacy (encryption). They should be hosted on a server that has these files
 * configured for access though some sort of authorization, and via HTTPS.
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


require_once( 'json-common.php' );

use browseStorage\TableClass;
use browseStorage\RawSQL;


// ============================================================================
// ####  Sources  #############################################################
// ============================================================================


TableClass::$data_sources = array(

	'mysql_demos' =>
	array(
		'engine' => "mysql",
		'server' => "localhost",
		'schema' => "demos",
		'user'   => "demos",  // ini_get("mysql.default_user")
		'passwd' => "xxx",    // ini_get("mysql.default_password");
	),

	'sqlite_demos' =>
	array(
		'engine' => "sqlite",
		'file'   => "../browseStorage-demos/demos.sq3",
	),
);


// ============================================================================
// ####  Tables  ##############################################################
// ============================================================================


$select_client   = "SELECT ClientID,   Name    FROM Clients    ORDER BY Name    ASC";
$select_trip     = "SELECT TripID,     Title   FROM Trips      ORDER BY Title   ASC";
$select_country  = "SELECT CountryID,  Name    FROM Countries  ORDER BY Name    ASC";
$select_currency = "SELECT CurrencyID, NameISO FROM Currencies ORDER BY NameISO ASC";


/**
 * Table keys should *not* contain:
 * * Forward slashes ('/') -- confuses AngularJS router arguments, even
 *   though this is not used at this time
 * * Pipes ('|') -- confuses automatically generated keys when table=*
 * * Newlines ('\n') or carriage returns ('\r') -- may confuse their
 *   usage in HTML/HTTP
 */
TableClass::$data_tables = array(

	'clients' => // -------------------------------------------------------
	array(
		'source'     => 'sqlite_demos',
		'table'      => "Clients",
		'name'       => "Customers",
		'col_id'     => 'ClientID',
		'col_list'   => array(	array( 'Name', 'ContactPhone', 'ContactEmail' ),  // Columns to list
					array( 'Name' ),  // Order By
		                ),
		'col_name'   => 'Name',
		'col_names'  => array(	'Name'             => array( "Name",               "text"     ),
					'Address'          => array( "Street address",     "textaddr" ),
					'CountryID'        => array( "Country",            "select", '', $select_country ),
					'TaxNumber'        => array( "Tax number",         "text"     ),
					'ContactPhone'     => array( "Telephone",          "tel"      ),
					'ContactEmail'     => array( "E-mail",             "email"    ),
					'PreferCurrencyID' => array( "Preferred currency", "select", '', $select_currency ),
		                ),
		'editable'   => TableClass::EDITABLE_ON_REQUEST |
				TableClass::CAN_INSERT,
		'buttons'    => array(
					array( 'Purchases', 'list:sales', 'Purchases made by this client', array('ClientID','ClientID') )
		                ),
	),

	'client-sales' => // --------------------------------------------------
	array(
		'unlisted'   => true,
		'source'     => 'sqlite_demos',
		'table'      => "Clients",
		'name'       => "Customers",
		'col_id'     => 'ClientID',
		'col_list'   => array(	array( 'Name', 'ContactPhone', 'ContactEmail' ),  // Columns to list
					array( 'Name' ),  // Order By
		                ),
		'col_name'   => 'Name',
		'col_names'  => array(	'Name'             => array( "Name",               "text"     ),
					'Address'          => array( "Street address",     "textaddr" ),
					'CountryID'        => array( "Country",            "select", '', $select_country ),
					'TaxNumber'        => array( "Tax number",         "text"     ),
					'ContactPhone'     => array( "Telephone",          "tel"      ),
					'ContactEmail'     => array( "E-mail",             "email"    ),
					'PreferCurrencyID' => array( "Preferred currency", "select", '', $select_currency ),
		                ),
		'editable'   => TableClass::EDITABLE_ON_REQUEST |
				TableClass::CAN_INSERT,
		'buttons'    => array(
					array( 'Purchases', 'list:sales', 'Purchases made by this client', array('ClientID','ClientID') )
		                ),
		'filter_list_before' => 'filter_clientsales_list_before',
	),

	'sales' => // ---------------------------------------------------------
	array(
		'source'     => 'sqlite_demos',
		'table'      => "Sales",
	//	'name'       => "Sales",  // Easily guessed as title-cased version of table identifier
		'col_id'     => 'SaleID',
		'col_list'   => array(	array( 'ClientName', 'TripCountryName', 'OnDate' ),  // Columns to list (will need a filter)
					array( '-OnDate' ),  // Order By
		                ),
		'col_name'   => 'OnDate',
		'col_names'  => array(	'ClientID'        => array( "Customer",         "select", '', $select_client ),
					'TripID'          => array( "Trip package",     "select", '', $select_trip ),
					'OnDate'          => array( "Date travelled",   "date"   ),
					'Description'     => array( "Special requests", "textarea", "Describe any special needs of the customer/trip." ),
					'Price'           => array( "Price to bill",    "number" ),
					'PriceCurrencyID' => array( "Price currency",   "select", '', $select_currency ),
		                ),
		'editable'   => TableClass::EDITABLE_ON_REQUEST |
				TableClass::CAN_INSERT          |
				TableClass::CAN_DELETE,
		'filter_list_before' => 'filter_sales_list_before',
		'buttons'    => array(
					array( 'Customer',     'read:clients', 'Show this customer',     'ClientID' ),
					array( 'Trip package', 'read:trips',   'Show this trip package', 'TripID'   ),
		                ),
	),

	'trips' => // ---------------------------------------------------------
	array(
		'source'     => 'sqlite_demos',
		'table'      => "Trips",
		'name'       => "Trip Packages",
		'col_id'     => 'TripID',
		'col_list'   => array(	array( 'TripCountryName', 'Title',  'UntilDate' ),  // Columns to list (will need a filter)
					array( 'TripCountryName', 'Title', '-UntilDate' ),  // Order By
		                ),
		'col_name'   => 'Title',
		'col_names'  => array(	'Title'                => array( "Package title",   "text", "Title used to summarize this trip package in lists." ),
					'DestinationCountryID' => array( "Trip to",         "select", '', $select_country ),
					'Description'          => array( "Description",     "textarea", "Describe the points of interest visited, provide some history and background, and describe what services this trip package consists of." ),
					'Price'                => array( "Price to bill",   "number" ),
					'PriceCurrencyID'      => array( "Price currency",  "select", '', $select_currency ),
					'UntilDate'            => array( "Available until", "date", "Date when this trip was/will be no longer available. Leave empty if not yet known." ),
		                ),
		'editable'   => TableClass::EDITABLE_ON_REQUEST |
				TableClass::CAN_INSERT          |
				TableClass::CAN_DELETE,
		'filter_list_before' => 'filter_trips_list_before',
	),

	'countries' => // -----------------------------------------------------
	array(
		'source'     => 'sqlite_demos',
		'table'      => "Countries",
		'name'       => "Selectable Countries",
		'col_id'     => 'CountryID',
		'col_list'   => array(	array( 'Name' ),  // Columns to list
					array( 'Name' ),  // Order By
		                ),
	//	'col_name'   => 'Name',  // Easily guessed as the first in 'col_list'
		'col_names'  => array( 'Name' => array( "Name", "text" ),
		                ),
		'editable'   => TableClass::EDITABLE_ON_REQUEST |
				TableClass::CAN_INSERT          |
				TableClass::CAN_DELETE,
		'buttons'    => array(
					array( 'Customers living in this country',   'list:clients',      'Customers living in this country',             array('CountryID','CountryID'           ) ),
					array( 'Customers traveled to this country', 'list:client-sales', 'Customers that have traveled to this country', array('CountryID','TripCountryID'       ) ),
					array( 'Trip packages to this country',      'list:trips',        'Available trips to this country',              array('CountryID','DestinationCountryID') ),
		                ),
	),

	'currencies' => // ----------------------------------------------------
	array(
		'source'     => 'sqlite_demos',
		'table'      => "Currencies",
		'name'       => "Selectable Currencies",
		'col_id'     => 'CurrencyID',
		'col_list'   => array(	array( 'NameISO', 'Name' ),  // Columns to list
					array( 'NameISO' ),  // Order By
		                ),
	//	'col_name'   => 'Name',  // Easily guessed as the first in 'col_list'
		'col_names'  => array( 'NameISO' => array( "3-letter 'ISO' name", "text" ),
		                       'Name'    => array( "Full name",           "text" ),
		                ),
		'editable'   => TableClass::EDITABLE_ON_REQUEST |
				TableClass::CAN_INSERT          |
				TableClass::CAN_DELETE,
		'buttons'    => array(
					array( 'Customers paying in this currency', 'list:clients', 'Customers that prefer to pay in this currency', array('CurrencyID','PreferCurrencyID') ),
					array( 'Sales in this currency',            'list:sales',   'Sales made in this currency',                   array('CurrencyID','PriceCurrencyID' ) ),
					array( 'Trip packages in this currency',    'list:trips',   'Available trips in this currency',              array('CurrencyID','PriceCurrencyID' ) ),
		                ),
	),
);


// Reset the demo database every hour, on the hour (can be removed in production)
// ============================================================================

$browseStorage_demo = @TableClass::$data_sources['sqlite_demos']['file'];
if( is_string($browseStorage_demo) )
	{
	$browseStorage_demo_dir  = dirname ($browseStorage_demo) . PATH_SEPARATOR;
	$browseStorage_demo_file = basename($browseStorage_demo);
	$browseStorage_demo_ext  = strrchr ($browseStorage_demo_file, '.');
	$browseStorage_demo_file = substr( $browseStorage_demo_file, 0, -strlen($browseStorage_demo_ext) );

	$browseStorage_demo_original = $browseStorage_demo_dir . $browseStorage_demo_file . '-original' . $browseStorage_demo_ext;
	$browseStorage_demo_copy     = $browseStorage_demo_dir . $browseStorage_demo_file . '-copy'     . $browseStorage_demo_ext;

	if( file_exists($browseStorage_demo_original)                                 &&
	    gmdate('YmdH',filemtime($browseStorage_demo_original)) != gmdate('YmdH')  &&
	    !file_exists($browseStorage_demo_copy) )
		{
		touch ( $browseStorage_demo_original );
		// "atomic" duplication:
		copy  ( $browseStorage_demo_original, $browseStorage_demo_copy );
		chmod ( $browseStorage_demo_copy,     0666                     );
		rename( $browseStorage_demo_copy,     $browseStorage_demo      );
		}
	}


// ============================================================================
// ####  Filter functions for the tables  #####################################
// ============================================================================


/**
 * Filter called before changing (insert/update/delete) an entry from the
 * actual storage.
 *
 * @param  $table_key string
 *         Table key.
 * @param  $tab_obj
 *         A `browseStorage\TableClass` object already open for the specified
 *         `$table_key`.
 * @param  $do_count bool
 *         True if caller requested a count of all listable rows on this table.
 * @param  $req_row_start int|false
 *         Start row offset to return. Always positive or zero. False if caller
 *         did not specify a start offset.
 * @param  $req_row_limit int|false
 *         Number of rows to return. Always positive or zero. False if caller
 *         did not specify a row limit.
 * @param  $req_col_values string[]
 *         Associative array of strings. Keys are column identifiers and values
 *         are column values. These represent columns to match/filter against.
 * @param  $req_search string|false
 *         A string with a search term to find in any of the columns shown on
 *         the list. The string length is always 1 or more. False for no search
 *         (e.g.: the caller specified an empty string), in which case *all*
 *         rows should be returned.
 * @param  $json array
 *         A pre-prepared JSON associative array that will be returned to the
 *         HTTP caller. All values are set except for the rows that is an
 *         empty array.
 *
 * @return int|string
 *         Returns `browseStorage\TableClass::FILTER_REQ_CALLER_PROCEEDS`
 *         if the caller should complete the data update, or returns
 *         `browseStorage\TableClass::FILTER_REQ_CALLER_RETURNS` if the caller
 *         should return the modified `$json` array to the HTTP caller.
 *         Note that in this latter case, your "filter after" will still be
 *         called (if it exists).
 *         As a special case, this function can also return a string or
 *         `browseStorage\RawSQL`object:
 *         * If it begins with `"SELECT "`, it is taken as a full SQL query to
 *           fetch the data. Note that everything between `SELECT` and `FROM`
 *           is replaced by `COUNT(*)` if the caller requests a count: if this
 *           is not suitable, you need to perform all code yourself.
 *         * If it begins with `"FROM "`, it will be used as the `FROM`
 *           part of the produced SQL statement. The string may include joins.
 *         * If it begins with `"WHERE "`, it will be used as the `WHERE`
 *           part of the produced SQL statement.
 *
 * @throws \Exception
 *         To report an error to the HTTP caller.
 */
function browseStorage_filter_list_before( $table_key, &$tab_obj, &$do_count, &$req_row_start, &$req_row_limit, &$req_col_values, &$req_search, &$json )
{
	return TableClass::FILTER_REQ_CALLER_PROCEEDS;
}

function filter_clientsales_list_before( $table_key, &$tab_obj, &$do_count, &$req_row_start, &$req_row_limit, &$req_col_values, &$req_search, &$json )
{
	return "FROM ClientSales";
}

function filter_sales_list_before( $table_key, &$tab_obj, &$do_count, &$req_row_start, &$req_row_limit, &$req_col_values, &$req_search, &$json )
{
	return "FROM SalesList";
}

function filter_trips_list_before( $table_key, &$tab_obj, &$do_count, &$req_row_start, &$req_row_limit, &$req_col_values, &$req_search, &$json )
{
	return "FROM TripsList";
}


/**
 * Filter called after changing (insert/update/delete) entry from the actual
 * storage.
 *
 * If your storage is a PDO-supported database, you can get the last insert ID
 * by calling:
 *	`if( $tab_obj->src_type == browseStorage\TableClass::TYPE_PDO )`
 *		`$id = $tab_obj->src->lastInsertId(`...`)`
 *
 * @param  $table_key string
 *         Table key.
 * @param  $tab_obj
 *         A `browseStorage\TableClass` object already open for the specified
 *         `$table_key`.
 * @param  $do_count bool
 *         True if caller requested a count of all listable rows on this table.
 * @param  $req_row_start int|false
 *         Start row offset to return. Always positive or zero. False if caller
 *         did not specify a start offset.
 * @param  $req_row_limit int|false
 *         Number of rows to return. Always positive or zero. False if caller
 *         did not specify a row limit.
 * @param  $req_col_values string[]
 *         Associative array of strings. Keys are column identifiers and values
 *         are column values. These represent columns to match/filter against.
 * @param  $req_search string|false
 *         A string with a search term to find in any of the columns shown on
 *         the list. The string length is always 1 or more. False for no search
 *         (e.g.: the caller specified an empty string), in which case *all*
 *         rows should be returned.
 * @param  $json array
 *         A pre-prepared JSON associative array that will be returned to the
 *         HTTP caller. All values are set including the rows.
 *
 * @throws \Exception
 *         To report an error to the HTTP caller.
 */
function browseStorage_filter_list_after( $table_key, &$tab_obj, $do_count, $req_row_start, $req_row_limit, $req_col_values, $req_search, &$json )
{
	return;
}


/**
 * Filter called before reading an entry from the actual storage.
 *
 * @param  $table_key string
 *         Table key.
 * @param  $tab_obj
 *         A `browseStorage\TableClass` object already open for the specified
 *         `$table_key`.
 * @param  $req_ids string[]
 *         Associative array of strings. Keys are column identifiers and values
 *         are column values. These specify the primary keys that identify the
 *         row (entry) we're supposed to read.
 *         If this array is empty, the intention is to return just this base
 *         `$json` object with the table and column properties, and default
 *         values for creating a new entry.
 * @param  $json array
 *         A pre-prepared JSON associative array that will be returned to the
 *         HTTP caller. All values are set except for the column values.
 *         However, if browseStorage is asked to detect column names
 *         automatically (i.e., missing ['col_names'] key in configuration),
 *         the `$json['columns']` will be an empty array.
 *         Note that with automatic column names, `$json['col_name']` may
 *         hold an incorrect (`0`) value at this time.
 *
 * @return int
 *         Returns `browseStorage\TableClass::FILTER_REQ_CALLER_PROCEEDS`
 *         if the caller should complete the data retrieval, or returns
 *         `browseStorage\TableClass::FILTER_REQ_CALLER_RETURNS` if the caller
 *         should return the modified `$json` array to the HTTP caller.
 *         Note that in this latter case, your "filter after" will still be
 *         called (if it exists).
 *         As a special case, this function can also return a string or
 *         `browseStorage\RawSQL`object:
 *         * If it begins with `"SELECT "`, it is taken as a full SQL query to
 *           fetch the data.
 *         * If it begins with `"FROM "`, it will be used as the `FROM`
 *           part of the produced SQL statement. The string may include joins.
 *         * If it begins with `"WHERE "`, it will be used as the `WHERE`
 *           part of the produced SQL statement.
 *
 * @throws \Exception
 *         To report an error to the HTTP caller.
 */
function browseStorage_filter_read_before( $table_key, &$tab_obj, &$req_ids, &$json )
{
	return TableClass::FILTER_REQ_CALLER_PROCEEDS;
}


/**
 * Filter called after reading an entry from the actual storage.
 *
 * @param  $table_key string
 *         Table key.
 * @param  $tab_obj
 *         A `browseStorage\TableClass` object already open for the specified
 *         `$table_key`.
 * @param  $req_ids string[]
 *         Associative array of strings. Keys are column identifiers and values
 *         are column values. These specify the primary keys that identify the
 *         row (entry) we're supposed to read.
 *         If this array is empty, the intention is to return just this base
 *         `$json` object with the table and column properties, and default
 *         values for creating a new entry.
 * @param  $json array
 *         A pre-prepared JSON associative array that will be returned to the
 *         HTTP caller. All values are set, including the column values.
 *
 * @throws \Exception
 *         To report an error to the HTTP caller.
 */
function browseStorage_filter_read_after( $table_key, &$tab_obj, $req_ids, &$json )
{
	return;
}


/**
 * Filter called before changing (insert/update/delete) an entry from the
 * actual storage.
 *
 * Filter called after begining a transaction on the underlying storage, if
 * supported. The transaction will be rolled back if this filter throws an
 * exception. This means this filter can call/trigger external services
 * and throw exceptions if those services report errors, while maintaining a
 * consistent state throughout.
 *
 * @param  $table_key string
 *         Table key.
 * @param  $tab_obj
 *         A `browseStorage\TableClass` object already open for the specified
 *         `$table_key`.
 * @param  $action string
 *         The action the HTTP caller requested, validated and abbreviated into
 *         a single letter: 'I' for 'insert', 'U' for 'update' and 'D' for
 *         'delete'.
 * @param  $req_ids string[]
 *         Associative array of strings. Keys are column identifiers and values
 *         are column values. These specify the primary keys that identify the
 *         row (entry) we're supposed to write to.
 *         If this array is empty, the intention is to return just this base
 *         `$json` object with the table and column properties, and default
 *         values for creating a new entry.
 * @param  $req_col_values array
 *         Associative array of strings. Keys are column identifiers and values
 *         are column values. These specify the values to change in the
 *         specified record.
 *         This filter function can change this array (like many others), but
 *         with one added functionality. Column values can be set to objects of
 *         class `browseStorage\RawSQL` (imported as just `RawSQL`).
 *         These will not be escaped when creating the SQL string, allowing you
 *         to create NULL values, computed dates, sub-queries and so on.
 * @param  $json array
 *         A pre-prepared JSON associative array that will be returned to the
 *         HTTP caller. All values are set except for the column values.
 *         However, if browseStorage is asked to detect column names
 *         automatically (i.e., missing ['col_names'] key in configuration),
 *         the `$json['columns']` will be an empty array.
 *         Note that with automatic column names, `$json['col_name']` may
 *         hold an incorrect (`0`) value at this time.
 *
 * @return int
 *         Returns `browseStorage\TableClass::FILTER_REQ_CALLER_PROCEEDS`
 *         if the caller should complete the data update, or returns
 *         `browseStorage\TableClass::FILTER_REQ_CALLER_RETURNS` if the caller
 *         should return the modified `$json` array to the HTTP caller.
 *         Note that in this latter case, your "filter after" will still be
 *         called (if it exists).
 *
 * @throws \Exception
 *         To report an error to the HTTP caller. This will also roll back any
 *         changes to the underlying storage, if supported.
 */
function browseStorage_filter_write_before( $table_key, &$tab_obj, $action, &$req_ids, &$req_col_values, &$json )
{
	return TableClass::FILTER_REQ_CALLER_PROCEEDS;
}


/**
 * Filter called after changing (insert/update/delete) entry from the actual
 * storage.
 *
 * Filter called after begining a transaction on the underlying storage, if
 * supported. The transaction will be rolled back if this filter throws an
 * exception. This means this filter can call/trigger external services
 * and throw exceptions if those services report errors, while maintaining a
 * consistent state throughout.
 *
 * If your storage is a PDO-supported database, you can get the last insert ID
 * by calling:
 *	`if( $tab_obj->src_type == browseStorage\TableClass::TYPE_PDO )`
 *		`$id = $tab_obj->src->lastInsertId(`...`)`
 *
 * @param  $table_key string
 *         Table key.
 * @param  $tab_obj
 *         A `browseStorage\TableClass` object already open for the specified
 *         `$table_key`.
 * @param  $action string
 *         The action the HTTP caller requested, validated and abbreviated into
 *         a single letter: 'I' for 'insert', 'U' for 'update' and 'D' for
 *         'delete'.
 * @param  $req_ids string[]
 *         Associative array of strings. Keys are column identifiers and values
 *         are column values. These specify the primary keys that identify the
 *         row (entry) we're supposed to write to.
 *         If this array is empty, the intention is to return just this base
 *         `$json` object with the table and column properties, and default
 *         values for creating a new entry.
 * @param  $req_col_values array
 *         Associative array of strings. Keys are column identifiers and values
 *         are column values. These specify the values to change in the
 *         specified record.
 * @param  $json array
 *         A pre-prepared JSON associative array that will be returned to the
 *         HTTP caller. All values are set, including the column values.
 * @param  $affected_rows bool|integer
 *         An integer with the number of modified rows, `false` if the
 *         underlying storage does not support this.
 *
 * @throws \Exception
 *         To report an error to the HTTP caller. This will also roll back any
 *         changes to the underlying storage, if supported.
 */
function browseStorage_filter_write_after( $table_key, &$tab_obj, $action, $req_ids, $req_col_values, &$json, $affected_rows )
{
	return;
}
