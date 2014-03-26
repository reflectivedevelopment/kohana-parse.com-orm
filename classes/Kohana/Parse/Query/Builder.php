<?php defined('SYSPATH') OR die('No direct script access.'); 

class Kohana_Parse_Query_Builder extends Parse_Query {

	/**
	 * Compiles an array of JOIN statements into an SQL partial.
	 *
	 * @param   object  $db     Database instance
	 * @param   array   $joins  join statements
	 * @return  string
	 */
	protected function _compile_join(Parse $db, array $joins)
	{
		throw new Exception('Not implemented!');

		$statements = array();

		foreach ($joins as $join)
		{
			// Compile each of the join statements
			$statements[] = $join->compile($db);
		}

		return implode(' ', $statements);
	}

	protected function _pop_stack($sql_stack, $logic_stack, $condition_stack, $current_sql, $to_condition=NULL)
	{
		if (count($sql_stack) <= 0)
		{
			return array($sql_stack, $logic_stack, $condition_stack, $current_sql);
		}

		do {
			$last_sql = array_pop($sql_stack);
			$last_logic = array_pop($logic_stack);
			$last_condition = array_pop($condition_stack);
			if ($last_logic == 'AND' || $last_logic == NULL)
			{
				$new_sql = array_merge($last_sql, $current_sql); // This silently merges keys together.
			}
			elseif ($last_logic == 'OR')
			{
				if ($last_condition == '$or' || $last_condition == '(')
				{
					$new_sql = array('$or' => array($last_sql, $current_sql));
				}
				else
				{
					$new_sql = $last_sql;
					array_push($new_sql['$or'], $current_sql);
				}
			}
			else
			{
				throw new Exception('Ahh, snap!');
			}
			$current_sql = $new_sql;
		} while ($to_condition != $last_condition && count($sql_stack) > 0);
		
		return array($sql_stack, $logic_stack, $condition_stack, $current_sql);
	}

	/**
	 * Compiles an array of conditions into an SQL partial. Used for WHERE
	 * and HAVING.
	 *
	 * @param   object  $db          Database instance
	 * @param   array   $conditions  condition statements
	 * @return  string
	 */
	protected function _compile_conditions(Parse $db, array $conditions)
	{
		$sql_stack = array();
		$logic_stack = array();
		$condition_stack = array();

		$current_sql = array();

		foreach ($conditions as $group)
		{
			// Process groups of conditions
			foreach ($group as $logic => $condition)
			{
				if ($condition === '(')
				{
					array_push($sql_stack, $current_sql);
					array_push($logic_stack, $logic);
					array_push($condition_stack, '(');
					$current_sql = array();
				}
				elseif ($condition === ')')
				{
					list($sql_stack, $logic_stack, $condition_stack, $current_sql) = $this->_pop_stack($sql_stack, $logic_stack, $condition_stack, $current_sql, '(');
				}
				else
				{
					if ($logic == 'OR')
					{
						if (count($logic_stack) > 0 && $logic_stack[count($logic_stack) - 1] == 'OR')
						{
							list($sql_stack, $logic_stack, $condition_stack, $current_sql) = $this->_pop_stack($sql_stack, $logic_stack, $condition_stack, $current_sql, $condition_stack[count($condition_stack) - 1]);
						}
						if (array_key_exists('$or', $current_sql))
						{
							$new_sql = $current_sql['$or'];
							array_push($sql_stack, $current_sql);
							array_push($logic_stack, 'OR');
							array_push($condition_stack, '$or_array');
							$current_sql = array();
						}
						else
						{
							array_push($sql_stack, $current_sql);
							array_push($logic_stack, 'OR');
							array_push($condition_stack, '$or');
							$current_sql = array();

						}
					}
					// Split the condition
					list($column, $op, $value) = $condition;


					// Database operators are always uppercase
					$op = strtoupper($op);

	/*				if ($op === 'BETWEEN' AND is_array($value))
					{
						// BETWEEN always has exactly two arguments
						list($min, $max) = $value;

						// Quote the min and max value
						$value = $min.' AND '.$max;
					}*/

					if ($op != NULL && array_key_exists($column, $current_sql))
					{
						throw new Exception ("$column already is in where clause!");
					}
					if ($op == '=')
					{
						$current_sql[$column] = $value;
					}
					elseif ($op == '!=')
					{
						$current_sql[$column] = array('$ne' => $value);
					}
					elseif ($op == 'IN')
					{
						$current_sql[$column] = array('$in' => $value);
					}
					elseif ($op == 'NOT IN')
					{
						$current_sql[$column] = array('$nin' => $value);
					}
					else
					{
						$current_sql[$column] = array($op => $value);
					}

				}
			}
		}

		list($sql_stack, $logic_stack, $condition_stack, $current_sql) = $this->_pop_stack($sql_stack, $logic_stack, $condition_stack, $current_sql, NULL);

		return $current_sql;
	}

	/**
	 * Compiles an array of set values into an SQL partial. Used for UPDATE.
	 *
	 * @param   object  $db      Database instance
	 * @param   array   $values  updated values
	 * @return  string
	 */
	protected function _compile_set(Parse $db, array $values)
	{
		throw new Exception('Not implemented!');

		$set = array();
		foreach ($values as $group)
		{
			// Split the set
			list ($column, $value) = $group;

			// Quote the column name
			$column = $db->quote_column($column);

			if ((is_string($value) AND array_key_exists($value, $this->_parameters)) === FALSE)
			{
				// Quote the value, it is not a parameter
				$value = $db->quote($value);
			}

			$set[$column] = $column.' = '.$value;
		}

		return implode(', ', $set);
	}

	/**
	 * Compiles an array of GROUP BY columns into an SQL partial.
	 *
	 * @param   object  $db       Database instance
	 * @param   array   $columns
	 * @return  string
	 */
	protected function _compile_group_by(Parse $db, array $columns)
	{
		throw new Exception('Not Implemented!');

		$group = array();

		foreach ($columns as $column)
		{
			if (is_array($column))
			{
				// Use the column alias
				$column = $db->quote_identifier(end($column));
			}
			else
			{
				// Apply proper quoting to the column
				$column = $db->quote_column($column);
			}

			$group[] = $column;
		}

		return 'GROUP BY '.implode(', ', $group);
	}

	/**
	 * Compiles an array of ORDER BY statements into an SQL partial.
	 *
	 * @param   object  $db       Database instance
	 * @param   array   $columns  sorting columns
	 * @return  string
	 */
	protected function _compile_order_by(Parse $db, array $columns)
	{
		throw new Exception('Not Implemented!');

		$sort = array();
		foreach ($columns as $group)
		{
			list ($column, $direction) = $group;

			if (is_array($column))
			{
				// Use the column alias
				$column = $db->quote_identifier(end($column));
			}
			else
			{
				// Apply proper quoting to the column
				$column = $db->quote_column($column);
			}

			if ($direction)
			{
				// Make the direction uppercase
				$direction = ' '.strtoupper($direction);
			}

			$sort[] = $column.$direction;
		}

		return 'ORDER BY '.implode(', ', $sort);
	}


} // End Database_Query_Builder
