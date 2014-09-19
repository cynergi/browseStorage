<?php
/**
 * Common classes and functions.
 * Should be included by other PHP scripts. Does nothing on its own, with the
 * exception of sending HTTP headers that state JSON output will follow.
 *
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


/**
 * browseStorage namespace.
 */
namespace browseStorage;


// ============================================================================
// ####  HTTP Headers for JSON  ###############################################
// ============================================================================

/**
 * MIME-type "application/json" is standardised in IETF draft:
 * http://www.ietf.org/internet-drafts/draft-crockford-jsonorg-json-04.txt
 * According to that doc, charset must be one of UTF-8, UTF-16 or UTF-32;
 * If we don't set charset to UTF-8, IE throws "system error -1072896658"
 * (not tested other UTF charsets);
 * If we use MIME-type "application/json", Opera 8 assumes charset of
 * (apparently) UTF-16, regardless of "charset" attribute
 */
header( "Content-Type: text/plain; charset=UTF-8" );

/**
 * Last-modified, expires and cache-control to force privacy
 * and most recent content always:
 */
header( "Last-Modified: " . gmdate("D, j M Y H:i:s") . " GMT" );
header( "Expires: Sat, 1 Jan 2000 00:00:00 GMT" );
	// forces most recent content always
header( "Pragma: no-cache" );
header( "Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0" );
	// ensures no copies of data are kept by caches
	// and any use of this file must be requested from the server
	// (this is a web-service after all!)


// ============================================================================
// ####  Classes  #############################################################
// ============================================================================


/**
 * Class for storing a raw SQL string.
 *
 * This class is used to distinguish when a filter assigns a string value to
 * a column, if that string should be escaped or not. If the string was
 * constructed with this class, it should not as it refers to a raw SQL
 * command / constant.
 */
class RawSQL
{
	/** The SQL string.
	 *  @var string */
	public $sql;

	/**
	 * Constructor creating this raw SQL string.
	 *
	 * @param  $sql string
	 *         The SQL string.
	 */
	function __construct( $sql )
	{
		$this->string = $sql;
	}
}


/**
 * Class for storing a data source connection for access to a specific table.
 *
 * Class provides grouping (packing) of contextualy similar variables,
 * without hiding them. It therefore provides incomplete object encapsulation.
 *
 * In practice this means that in order to reduce complexity and the amount of
 * code to write, parse and run by PHP, most object properties are public
 * even though they should be used read-only by code outside this class.
 */
class TableClass
{
	/** Data source of a type recognized by PHP's PDO.
	 *  @const int */
	const TYPE_PDO = 0;

	/** When used in the 'editable' property of a table in `$data_tables`,
	 *  specifies that the rows of the table are *not* editable.
	 *  @const int */
	const NOT_EDITABLE = 0;

	/** When used in the 'editable' property of a table in `$data_tables`,
	 *  specifies that the rows of the table are editable, but the user
	 *  must press an "Edit" button after viewing the row. Viewing the
	 *  row is what happens when the user selects it from a list.
	 *  @const int */
	const EDITABLE_ON_REQUEST = 1;

	/** When used in the 'editable' property of a table in `$data_tables`,
	 *  specifies that the rows of the table are immediately editable,
	 *  i.e., the user is taken to an editable representation of the row
	 *  when selecting it from a list.
	 *  @const int */
	const EDITABLE_IMMEDIATELY = 2;

	/** When used in the 'editable' property of a table in `$data_tables`,
	 *  specifies that the user can add/insert new rows.
	 *  Should be combined with one of the `*EDITABLE*` constants by
	 *  adding or ORing with them.
	 *  @const int */
	const CAN_INSERT = 4;

	/** When used in the 'editable' property of a table in `$data_tables`,
	 *  specifies that the user can delete rows.
	 *  Should be combined with one of the `*EDITABLE*` constants by
	 *  adding or ORing with them.
	 *  @const int */
	const CAN_DELETE = 8;

	/** A filter returns this value if it has setup `$json` by reference
	 *  to be ready to return to HTTP caller.
	 *  @const int */
	const FILTER_REQ_CALLER_RETURNS = 0;

	/** A filter returns this value if it made some processing, but still
	 *  needs caller to keep working on the next steps as usual.
	 *  @const int */
	const FILTER_REQ_CALLER_PROCEEDS = 1;


	/** Data source static array. Set directly by caller.
	 *  No setter method exists (for instance, to validate this array) as
	 *  that would run on every data access call, slowing performance.
	 *  We verify this array during usage (only the parts relevant to each
	 *  usage).
	 *  @var array
	 *  @see config.php */
	public static $data_sources = array();


	/** Data tables static array. Set directly by caller.
	 *  No setter method exists (for instance, to validate this array) as
	 *  that would run on every data access call, slowing performance.
	 *  We verify this array during usage (only the parts relevant to each
	 *  usage).
	 *  @var array
	 *  @see config.php */
	public static $data_tables = array();


	/** Type of data source. One of this class' TYPE_* constants.
	 *  Should be `protected` if providing full object encapsulation, and
	 *  a getter method added.
	 *  @var int */
	public $src_type;

	/** Lower-cased data source storage engine.
	 *  Should be `protected` if providing full object encapsulation, and
	 *  a getter method added.
	 *  @var string */
	public $src_engine;

	/** Data source identifier. Currently, PHP's PDO object.
	 *  Should be `protected` if providing full object encapsulation, and
	 *  a getter method added. Code complexity would increase as PHP
	 *  does not (yet) support `$obj->getSrc()->...`.
	 *  @var mixed */
	public $src;

	/** Data table key, as used when the constructor was called.
	 *  If that key was an automatic (composed) key, than included a `'|'`
	 *  character and a real table name, then that trailing sequence
	 *  (starting with the `'|'`) has been removed here.
	 *  @var int|string */
	public $table_key;

	/** Data table array. Copy of one of the values in
	 *  `self::$data_tables`' array.
	 *  If the table key, as used when the constructor was called, was an
	 *  automatic (composed) key, than included a `'|'` character and a
	 *  real table name, then that trailing table name has replaced the
	 *  `'table'` entry in this array.
	 *  @var string */
	public $tab;

	/**
	 * Template for common exception messages.
	 * The sprintf() string takes one string argument that will preceed
	 * the name of the `$data_table` entry in question.
	 * @var string
	 */
	public $error_sprintf;

	/**
	 * Called script name. Used in error messages.
	 * @var string
	 */
	public $error_script;


	/**
	 * Constructor connecting to a data table.
	 *
	 * @throws \Exception
	 *         On detected configuration errors, or PDO errors not thrown.
	 */
	function __construct( $table_key )
	{
		if( is_string($table_key)  &&  ($pos=strpos($table_key, '|')) !== false )
			{
			$t         = substr( $table_key, $pos+1         );
			$table_key = substr( $table_key,      0, $pos-1 );
			if( !preg_match('/\\A[a-zA-Z_][a-zA-Z0-9_]*\\z/', $t) )
				throw new \Exception( 'Constructor for class '.__CLASS__.' called with bad automatic $table_key.' );
			}
		else
			$t = false;

		$this->error_script  = basename( $_SERVER['PHP_SELF'] );
		$this->error_sprintf = 'Misconfiguration in browseStorage/config.php: %s '.__CLASS__.'::$data_table['.var_export($table_key,true).'], when calling ' . $this->error_script . '.';

		if( !is_array(self::$data_tables)  ||  !is_array(@self::$data_tables[$table_key]) )
			throw new \Exception( 'Constructor for class '.__CLASS__.' called with non-existing data table '.__CLASS__.'::$data_tables['.var_export($table_key,true).'].' );

		$this->table_key = $table_key;
		$this->tab = self::$data_tables[$table_key];
		if( $t !== false )
			{
			if( $this->tab['table'] !== '*' )
				throw new \Exception( 'Constructor for class '.__CLASS__.' called with automatic $table_key, when the corresponding '.__CLASS__.'::$data_tables['.var_export($table_key,true)."]['table'] doesn't support it." );
			$this->tab['table'] = $t;
			}
		else
			{
			if( $this->tab['table'] === '*' )
				throw new \Exception( 'Constructor for class '.__CLASS__.' called without automatic $table_key, when the corresponding '.__CLASS__.'::$data_tables['.var_export($table_key,true)."]['table'] requires it." );
			}

		if( !is_string(@$this->tab['source']) )
			throw new \Exception( 'Misconfiguration in browseStorage/config.php: Table '.var_export(@$this->tab['source'],true)." missing 'source' key, or key's value is not a string." );

		$source = $this->tab['source'];
		$sprintf_error = 'Misconfiguration in browseStorage/config.php: %s '.__CLASS__.'::$data_sources['.var_export($source,true).'].';

		if( !is_array(self::$data_sources)  ||  !is_array(@self::$data_sources[$source]) )
			throw new \Exception( sprintf($sprintf_error, "Missing data source") );

		$s = self::$data_sources[$source];

		$this->src_type = $this->src = false;  // just in case...
		$pdo_attr_both = array( \PDO::ATTR_PERSISTENT => TRUE,
		                        \PDO::ATTR_ERRMODE    => \PDO::ERRMODE_EXCEPTION );
		$pdo_attr_err  = array( \PDO::ATTR_ERRMODE    => \PDO::ERRMODE_EXCEPTION );

		$this->src_engine = strtolower( @$s['engine'] );
		switch( $this->src_engine )
		{
			case "sqlite":
				if( !isset($s['file']) )
					throw new \Exception( sprintf($sprintf_error, "Missing key ['file'] in") );
				$this->src_type = self::TYPE_PDO;
				$this->src  = new \PDO( "sqlite:$s[file]", NULL, NULL, $pdo_attr_err );
					// don't use: ,NULL,NULL,array(PDO::ATTR_PERSISTENT => TRUE)
					// so that any pending transactions are cleared
					// (SQLite is known to sometimes give errors of "database/table locked")
				break;
			case "mysql":
				if( !isset($s['server'], $s['schema'] /*,$s['user'],$s['passwd']*/) )
					throw new \Exception( sprintf($sprintf_error, "Missing key ['server'] and/or ['schema'] in") );
					// allow 'user' and 'passwd' to be missing (NULL)
				$this->src_type = self::TYPE_PDO;
				$this->src  = new \PDO( "mysql:host=$s[server];dbname=$s[schema];charset=UTF8", $s['user'], $s['passwd'], $pdo_attr_both );
				break;
			case "odbc":
				if( !isset($s['schema'] /*,$s['user'],$s['passwd']*/) )
					throw new \Exception( sprintf($sprintf_error, "Missing key ['schema'] in") );
					// allow 'user' and 'passwd' to be missing (NULL)
				$this->src_type = self::TYPE_PDO;
				$this->src  = new \PDO( "odbc:$s[schema]", $s['user'], $s['passwd'], $pdo_attr_err );
					// not supported for Microsoft Jet, but would cause lingering ".ldb" files anyway:
					// ,array(PDO::ATTR_PERSISTENT => TRUE)
				break;
			default:
				throw new \Exception( sprintf($sprintf_error, "Missing or unsupported ['engine'] in") );
		}

		if( !$this->src )
			throw new \Exception( 'Could not connect to data source '.__CLASS__.'::$data_sources['.var_export($source,true).'].' );
	}


	/**
	 * Converts an identifier (table/column name) into text that can be
	 * easily read and understood by a human.
	 *
	 * @abstract
	 * Supports ASCII characters only. Unicode or other accented characters
	 * will not be modified (upper- or lower-cased).
	 *
	 * @param  string $ident
	 *         The identifier name.
	 *
	 * @return string
	 *         The text to be displayed for the column name.
	 */
	public static function ident_to_name( $ident )
	{
		$ident = ucfirst( $ident == strtoupper($ident) ? strtolower($ident) : $ident );
		$ident = trim( preg_replace('/(?<!\ )[A-Z]+/', ' $0', $ident) );
		return $ident;
	}


	/**
	 * Get the configured table primary keys (IDs).
	 *
	 * @abstract
	 * Validates and converts the `['col_id']` key in `$data_tables`,
	 * which can have many formats, into an array of strings.
	 *
	 * @return string[]
	 *         The set of column identifiers that specify (primary) keys
	 *         for this storage table.
	 *
	 * @throws \Exception
	 */
	public function config_ids()
	{
		if( !isset($this->tab['col_id']) )
			throw new \Exception( sprintf($this->error_sprintf, "Missing key ['col_id'] in") );
		//
		$config_ids = $this->tab['col_id'];
		if( !is_array($config_ids) )
			$config_ids = array( strval($config_ids) );
		else if( count($config_ids) <= 0 )
			throw new \Exception( sprintf($this->error_sprintf, "Number of columns specified in key ['col_id'] is zero, in") );
		return $config_ids;
	}


	/**
	 * Get the configured table column names.
	 *
	 * @abstract
	 * Validates and converts the `['col_names']` key in `$data_tables`,
	 * which can have many formats, into an associative array of strings.
	 *
	 * @return string[]
	 *         An associative array of strings where the keys are column
	 *         identifiers and the values are column names to show.
	 *
	 * @throws \Exception
	 */
	public function config_names()
	{
		$config_names = ( isset($this->tab['col_names']) ? $this->tab['col_names'] : array() );
		if( !is_array($config_names) )
			throw new \Exception( sprintf($this->error_sprintf, "Key ['col_names'] is not an array, in") );
		return $config_names;
	}


	/**
	 * Get the requested primary keys (IDs).
	 *
	 * @abstract
	 * Validates and converts the `$_POST[id#]` values.
	 * If `$_POST[id0]` does not exist, it assumes no IDs were specified
	 * and returns an empty array.
	 *
	 * @param  $config_ids string[]
	 *         The set of column identifiers that specify (primary) keys
	 *         for this storage table.
	 *
	 * @return string[]
	 *         Associative array of strings. Keys are column identifiers and values
	 *         are column values. These specify the primary keys that identify the
	 *         row (entry).
	 *         Can be an empty array if `$_POST[id0]` does not exist,
	 *         assuming HTTP caller did not want to specify a specific
	 *         row.
	 *
	 * @throws \Exception
	 *         On detected configuration errors, or PDO errors not thrown.
	 */
	public function req_ids( $config_ids )
	{
		$req_ids = array();
		if( isset($_POST['id0']) )
			{
			$id_num = 0;
			foreach( $config_ids as $col )
				{
				if( !isset($_POST["id$id_num"]) )
					throw new \Exception( "Missing \$_POST['id$id_num'] when calling $this->error_script." );
				if( isset($req_ids[$col]) )
					throw new \Exception( sprintf($this->error_sprintf, "Duplicated column name in key ['col_id'], in") );
				$req_ids[$col] = strval( $_POST["id$id_num"] );
				$id_num++;
				}
			}
		return $req_ids;
	}


	/**
	 * Get the requested columns and their values.
	 *
	 * @abstract
	 * Validates and reads the `$_POST[col#]` values.
	 * These are used as matching/filter criteria for listing, and as
	 * update values for writing.
	 *
	 * @return string[]
	 *         Associative array of strings. Keys are column identifiers and values
	 *         are column values.
	 *
	 * @throws \Exception
	 *         On detected configuration errors, or PDO errors not thrown.
	 */
	public function req_col_values()
	{
		$req_col_values = array();
		$col_num = 0;
		while( isset($_POST["col$col_num"]) )
			{
			$col = strval( $_POST["col$col_num"] );
			/* Safari 7.1 auto-complete seems to have a bad interaction with Angular, and no value is set
			   see: https://github.com/angular/angular.js/issues/1460
			if( !isset($_POST["col${col_num}_value"]) )
				throw new \Exception( "Missing \$_POST['col${col_num}_value'] for corresponding \$_POST['col${col_num}'] when calling $this->error_script." );
			*/
			if( isset($req_col_values[$col]) )
				throw new \Exception( "\$_POST['col${col_num}'] indicates a repeated column name when calling $this->error_script." );
			$req_col_values[$col] = strval( @$_POST["col${col_num}_value"] );
			$col_num++;
			}
		return $req_col_values;
	}


	/**
	 * Returns an SQL WHERE clause from the argument `$req`.
	 *
	 * @abstract
	 * All non-integer/float values are presented as strings, letting the
	 * storage engine perform its own automatic conversions.
	 *
	 * @param  $req string[]
	 *         Associative array of strings. Keys are column identifiers and values
	 *         are column values. These can come from `$req_ids` or `$req_col_values`.
	 * @return string
	 *         Returns a string with the SQL WHERE clause, including
	 *         "`WHERE`" (without leading space).
	 *
	 * @throws \Exception
	 *         On detected configuration errors, or PDO errors not thrown.
	 */
	public function where_from_req( $req )
	{
		$where = "";
		foreach( $req as $col => $value )
			{
			if( strlen($where) > 0 )
				$where .= " AND ";
			$where .= $col . " = " . ( is_int($value) || is_float($value) ? $value : $this->src->quote($value) );
				// $values from $_POST are always strings,
				// but the "before" filter may have changed this
			}
		if( strlen($where) > 0 )
			$where = "WHERE $where";
		return $where;
	}
}
