<?php defined('SYSPATH') OR die('No direct script access.'); 

class Kohana_Parse_Query_Builder_Insert extends Parse_Query_Builder_Update {

	
	protected $_columns = array();
	protected $_values = array();


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
		return Parse_Query_Builder_Where::__construct(Database::INSERT, '');
	}


	/**
	 * Set the columns that will be inserted.
	 *
	 * @param   array  $columns  column names
	 * @return  $this
	 */
	public function columns(array $columns)
	{
		$this->_columns = $columns;

		return $this;
	}

	/**
	 * Adds or overwrites values. Multiple value sets can be added.
	 *
	 * @param   array   $values  values list
	 * @param   ...
	 * @return  $this
	 */
	public function values(array $values)
	{
		if ( ! is_array($this->_values))
		{
			throw new Kohana_Exception('INSERT INTO ... SELECT statements cannot be combined with INSERT INTO ... VALUES');
		}

		// Get all of the passed values
		$values = func_get_args();

		$this->_values = array_merge($this->_values, $values);

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

		if (is_array($this->_values) && count($this->_values) > 1)
		{
			throw Exception("Cannot do group inserts!");
		}

		$this->_set = array_combine($this->_columns, $this->_values[0]);

		return parent::compile($db);;
	}

	public function reset()
	{
		$this->_table = NULL;

		$this->_columns =
		$this->_values  = array();

		$this->_parameters = array();

		$this->_sql = NULL;

		return parent::reset();
	}

} // End Database_Query_Builder_Insert
