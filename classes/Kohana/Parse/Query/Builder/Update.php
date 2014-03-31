<?php defined('SYSPATH') OR die('No direct script access.'); 

class Kohana_Parse_Query_Builder_Update extends Parse_Query_Builder_Where {

	// UPDATE ...
	protected $_table;

	// SET ...
	protected $_set = array();

	/**
	 * Set the table for a update.
	 *
	 * @param   mixed  $table  table name or array($table, $alias) or object
	 * @return  void
	 */
	public function __construct($table = NULL)
	{
		if ($table)
		{
			// Set the inital table name
			$this->_table = $table;
		}

		// Start the query with no SQL
		return parent::__construct(Database::UPDATE, '');
	}

	/**
	 * Sets the table to update.
	 *
	 * @param   mixed  $table  table name or array($table, $alias) or object
	 * @return  $this
	 */
	public function table($table)
	{
		$this->_table = $table;

		return $this;
	}

	/**
	 * Set the values to update with an associative array.
	 *
	 * @param   array   $pairs  associative (column => value) list
	 * @return  $this
	 */
	public function set(array $pairs)
	{
		foreach ($pairs as $column => $value)
		{
			$this->_set[$column] = $value;
		}

		return $this;
	}

	/**
	 * Set the value of a single column.
	 *
	 * @param   mixed  $column  table name or array($table, $alias) or object
	 * @param   mixed  $value   column value
	 * @return  $this
	 */
	public function value($column, $value)
	{
		$this->_set[] = array($column, $value);

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

		$query = array();

		// Add the columns to update
		$query['values'] = json_encode($this->_compile_set($db, $this->_set));

		if ( ! empty($this->_where))
		{
			if (count($this->_where) > 1)
			{
				throw new Exception('Please provide primary key only!');
			}
			if (!array_key_exists('AND', $this->_where[0]))
			{
				throw new Exception('Must be AND logic!');
			}
			if ($this->_where[0]['AND'][0] != 'objectId')
			{
				throw new Exception('objectId is only valid where in update');
			}

			// Add selection conditions
			$query['where'] = $this->_where[0]['AND'][2];
		}

		$this->_sql = $query;

		return parent::compile($db);
	}

	public function reset()
	{
		$this->_table = NULL;

		$this->_set   =
		$this->_where = array();

		$this->_limit = NULL;

		$this->_parameters = array();

		$this->_sql = NULL;

		return $this;
	}


} // End Database_Query_Builder_Update
