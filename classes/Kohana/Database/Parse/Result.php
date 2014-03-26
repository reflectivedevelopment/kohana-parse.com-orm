<?php defined('SYSPATH') OR die('No direct script access.');
/**
 * Parse.com database result.   See [Results](/database/results) for usage and examples.
 *
 */
class Kohana_Database_Parse_Result extends Database_Result {

	protected $_internal_row = 0;
	protected $_from = NULL;

	public function __construct($result, $from, $sql, $as_object = FALSE, array $params = NULL)
	{
		$result = $result['results'];
		$this->_from = $from;

		parent::__construct($result, $sql, $as_object, $params);

		// Find the number of rows in the result
		$this->_total_rows = count($result);
	}

	public function __destruct()
	{
		// Parse results do not use resources
	}

	public function seek($offset)
	{
		if ($this->offsetExists($offset))
		{
			// Set the current row to the offset
			$this->_current_row = $this->_internal_row = $offset;

			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}

	public function current()
	{
		if ($this->_current_row !== $this->_internal_row AND ! $this->seek($this->_current_row))
			return NULL;

		if ($this->_as_object === TRUE)
		{
			// Return an stdClass
			return (object)$this->_result[$this->_internal_row];
		}
		elseif (is_string($this->_as_object))
		{
			$values = $this->_result[$this->_internal_row];
			if ($this->_object_params != NULL)
			{
				foreach (array_keys($values) as $key)
				{
					if ( ! array_key_exists($key, $values))
					{
						unset($values[$key]);
					}
				}
			}			

			$result = new $this->_as_object();

			/* TODO

			Kohana ORM will make all the values in the changed state by doing this. Can't really get around it in pure PHP. Need to fix upstream ORM at some point if this is really a problem.

			*/
			foreach ($values as $key => $value)
				$result->$key = $value;

			return $result;
		}
		else
		{
			// Return an array of the row
			return $this->_result[$this->_internal_row];
		}
	}

} // End Database_MySQL_Result_Select
