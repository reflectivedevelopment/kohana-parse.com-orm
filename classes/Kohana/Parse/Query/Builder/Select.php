<?php defined('SYSPATH') OR die('No direct script access.'); 

class Kohana_Parse_Query_Builder_Select extends Parse_Query_Builder_Where {

	// DISTINCT
	protected $_distinct = FALSE;

	// OFFSET ...
	protected $_offset = NULL;

	// The last JOIN statement created
	protected $_last_join;

	/**
	 * Sets the initial columns to select from.
	 *
	 * @param   array  $columns  column list
	 * @return  void
	 */
	public function __construct(array $columns = NULL)
	{
		// Start the query with no actual SQL statement
		parent::__construct(Database::SELECT, '');
	}

	/**
	 * Enables or disables selecting only unique columns using "SELECT DISTINCT"
	 *
	 * @param   boolean  $value  enable or disable distinct columns
	 * @return  $this
	 */
	public function distinct($value)
	{
		$this->_distinct = (bool) $value;

		return $this;
	}

	/**
	 * Choose the columns to select from.
	 *
	 * @param   mixed  $columns  column name or array($column, $alias) or object
	 * @return  $this
	 */
	public function select($columns = NULL)
	{
		// NOOP
		return $this;
	}

	/**
	 * Choose the columns to select from, using an array.
	 *
	 * @param   array  $columns  list of column names or aliases
	 * @return  $this
	 */
	public function select_array(array $columns)
	{
		// NOOP
		return $this;
	}

	/**
	 * Choose the table to select "FROM ..."
	 *
	 * @param   mixed  $table  table name or array($table, $alias) or object
	 * @return  $this
	 */
	public function from($table)
	{
		list($this->_from, ) = $table;

		return $this;
	}

	/**
	 * Start returning results after "OFFSET ..."
	 *
	 * @param   integer   $number  starting result number or NULL to reset
	 * @return  $this
	 */
	public function offset($number)
	{
		$this->_offset = $number;

		return $this;
	}

	/**
	 * Compile the SQL query and return it.
	 *
	 * @param   mixed  $db  Database instance or name of instance
	 * @return  string
	 */
	public function compile($db = NULL)
	{

		if ( ! is_object($db))
		{
			// Get the database instance
			$db = Database::instance($db);
		}

		// Start a selection query
		$where = '';

	/*	if ( ! empty($this->_from))
		{
			// Set tables to select from
			$query .= ' FROM '.implode(', ', array_unique(array_map($quote_table, $this->_from)));
		}
*/
		if ( ! empty($this->_where))
		{
			// Add selection conditions
			$where .= json_encode($this->_compile_conditions($db, $this->_where));

		}
/*
		if ( ! empty($this->_order_by))
		{
			// Add sorting
			$where .= ' '.$this->_compile_order_by($db, $this->_order_by);
		}

		if ($this->_limit !== NULL)
		{
			// Add limiting
			$where .= ' LIMIT '.$this->_limit;
		}

		if ($this->_offset !== NULL)
		{
			// Add offsets
			$where .= ' OFFSET '.$this->_offset;
		}
*/
		$this->_sql = $where;

		return parent::compile($db);
	}

	public function reset()
	{
		$this->_select   =
		$this->_join     =
		$this->_where    =
		$this->_group_by =
		$this->_having   =
		$this->_order_by =
		$this->_union = array();

		$this->_distinct = FALSE;

		$this->_from      =
		$this->_limit     =
		$this->_offset    =
		$this->_last_join = NULL;

		$this->_parameters = array();

		$this->_sql = NULL;

		return $this;
	}

} // End Database_Query_Select
