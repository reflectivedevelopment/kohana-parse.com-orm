<?php defined('SYSPATH') OR die('No direct script access.'); 

class Kohana_Parse_Query extends Database_Query {

	// FROM
	protected $_from = NULL;

	/**
	 * Return the SQL query string.
	 *
	 * @return  string
	 */
	public function __toString()
	{
		try
		{
			// Return the SQL string
			return $this->compile(Parse::instance());
		}
		catch (Exception $e)
		{
			return Kohana_Exception::text($e);
		}
	}

	/**
	 * Compile the SQL query and return it. Replaces any parameters with their
	 * given values.
	 *
	 * @param   mixed  $db  Database instance or name of instance
	 * @return  string
	 */
	public function compile($db = NULL)
	{
		if ( ! is_object($db))
		{
			// Get the database instance
			$db = Parse::instance($db);
		}

		// Import the SQL locally
		$sql = $this->_sql;

		if ( ! empty($this->_parameters))
		{
			// Quote all of the values
			$values = array_map(array($db, 'quote'), $this->_parameters);

			// Replace the values in the SQL
			$sql = strtr($sql, $values);
		}

		return $sql;
	}

	/**
	 * Execute the current query on the given database.
	 *
	 * @param   mixed    $db  Database instance or name of instance
	 * @param   string   result object classname, TRUE for stdClass or FALSE for array
	 * @param   array    result object constructor arguments
	 * @return  object   Database_Result for SELECT queries
	 * @return  mixed    the insert id for INSERT queries
	 * @return  integer  number of affected rows for all other queries
	 */
	public function execute($db = NULL, $as_object = NULL, $object_params = NULL)
	{
		if ( ! is_object($db))
		{
			// Get the database instance
			$db = Parse::instance($db);
		}

		if ($as_object === NULL)
		{
			$as_object = $this->_as_object;
		}

		if ($object_params === NULL)
		{
			$object_params = $this->_object_params;
		}

		// Compile the SQL query
		$sql = $this->compile($db);

		if ($this->_lifetime !== NULL AND $this->_type === Database::SELECT)
		{
			// Set the cache key based on the database instance name and SQL
			$cache_key = 'Parse::query("'.$db.'", "'. $this->_from .'", "'.$sql.'")';

			// Read the cache first to delete a possible hit with lifetime <= 0
			if (($result = Kohana::cache($cache_key, NULL, $this->_lifetime)) !== NULL
				AND ! $this->_force_execute)
			{
				// Return a cached result
				return new Parse_Result_Cached($result, $sql, $as_object, $object_params);
			}
		}

		// Execute the query
		if ($this->_type == Database::SELECT)
		{
			$result = $db->objectQuery($this->_from, $sql);
			$result = new Database_Parse_Result($result, $this->_from, $sql, $as_object, $object_params);

		}

		if (isset($cache_key) AND $this->_lifetime > 0)
		{
			// Cache the result array
			Kohana::cache($cache_key, $result->as_array(), $this->_lifetime);
		}

		return $result;
	}

} // End Parse_Query
