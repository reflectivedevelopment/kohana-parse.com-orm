<?php defined('SYSPATH') OR die('No direct script access.');
/**
 * Parse extending ORM
 */
class Kohana_Parse_ORM extends ORM implements serializable {

	/**
	 * Table primary key
	 * @var string
	 */
	protected $_primary_key = 'objectId';

	protected $_create = false;

	/**
	 * Prepares the model database connection, determines the table name,
	 * and loads column information.
	 *
	 * @return void
	 */
	protected function _initialize()
	{
		// Set the object name if none predefined
		if (empty($this->_object_name))
		{
			$this->_object_name = strtolower(substr(get_class($this), 6));
		}
		
		// Check if this model has already been initialized
		if ( ! $init = Arr::get(ORM::$_init_cache, $this->_object_name, FALSE))
		{
			$init = array(
				'_belongs_to' => array(),
				'_has_one'    => array(),
				'_has_many'   => array(),
			);
			
			// Set the object plural name if none predefined
			if ( ! isset($this->_object_plural))
			{
				$init['_object_plural'] = Inflector::plural($this->_object_name);
			}

			if ( ! $this->_errors_filename)
			{
				$init['_errors_filename'] = $this->_object_name;
			}

			if ( ! is_object($this->_db))
			{
				// Get parse instance
				$init['_db'] = Parse::instance($this->_db_group);
			}

			if (empty($this->_table_name))
			{
				// Table name is the same as the object name
				$init['_table_name'] = $this->_object_name;

				if ($this->_table_names_plural === TRUE)
				{
					// Make the table name plural
					$init['_table_name'] = Arr::get($init, '_object_plural', $this->_object_plural);
				}
			}
			
			$defaults = array();

			foreach ($this->_belongs_to as $alias => $details)
			{
				if ( ! isset($details['model']))
				{
					$defaults['model'] = str_replace(' ', '_', ucwords(str_replace('_', ' ', $alias)));
				}
				
				$defaults['foreign_key'] = $alias.$this->_foreign_key_suffix;

				$init['_belongs_to'][$alias] = array_merge($defaults, $details);
			}

			foreach ($this->_has_one as $alias => $details)
			{
				if ( ! isset($details['model']))
				{
					$defaults['model'] = str_replace(' ', '_', ucwords(str_replace('_', ' ', $alias)));
				}
				
				$defaults['foreign_key'] = $this->_object_name.$this->_foreign_key_suffix;

				$init['_has_one'][$alias] = array_merge($defaults, $details);
			}

			foreach ($this->_has_many as $alias => $details)
			{
				if ( ! isset($details['model']))
				{
					$defaults['model'] = str_replace(' ', '_', ucwords(str_replace('_', ' ', Inflector::singular($alias))));
				}
				
				$defaults['foreign_key'] = $this->_object_name.$this->_foreign_key_suffix;
				$defaults['through'] = NULL;
				
				if ( ! isset($details['far_key']))
				{
					$defaults['far_key'] = Inflector::singular($alias).$this->_foreign_key_suffix;
				}
				
				$init['_has_many'][$alias] = array_merge($defaults, $details);
			}
			
			ORM::$_init_cache[$this->_object_name] = $init;
		}
		
		// Assign initialized properties to the current object
		foreach ($init as $property => $value)
		{
			$this->{$property} = $value;
		}
		
		// Load column information
		$this->reload_columns();

		// Clear initial model state
		$this->clear();
	}

	public function start_create()
	{
		$this->_create = true;
	}

	public function end_create()
	{
		$this->_create = false;
		if ( ! empty($this->_cast_data))
		{
			// Load preloaded data from a database call cast
			$this->_load_values($this->_cast_data);

			$this->_cast_data = array();
		}
	}

	public function reload_columns($force = FALSE)
	{
		if ($force === TRUE OR empty($this->_table_columns))
		{
			throw new Exception('Not implemented! Please specify columns.');
		}
	}

	/**
	 * Binds another one-to-one object to this model.  One-to-one objects
	 * can be nested using 'object1:object2' syntax
	 *
	 * @param  string $target_path Target model to bind to
	 * @return ORM
	 */
	public function with($target_path)
	{
		throw new Exception('Not implemented!');
	}

	/**
	 * Handles setting of columns
	 * Override this method to add custom set behavior
	 *
	 * @param  string $column Column name
	 * @param  mixed  $value  Column value
	 * @throws Kohana_Exception
	 * @return ORM
	 */
	public function set($column, $value)
	{
		if ( ! isset($this->_object_name) || $this->_create)
		{
			// Object not yet constructed, so we're loading data from a database call cast
			$this->_cast_data[$column] = $value;
			
			return $this;
		}
		
		if (in_array($column, $this->_serialize_columns))
		{
			$value = $this->_serialize_value($value);
		}

		if (array_key_exists($column, $this->_object))
		{
			// Filter the data
			$value = $this->run_filter($column, $value);

			// See if the data really changed
			if ($value !== $this->_object[$column])
			{
				$this->_object[$column] = $value;

				// Data has changed
				$this->_changed[$column] = $column;

				// Object is no longer saved or valid
				$this->_saved = $this->_valid = FALSE;
			}
		}
		elseif (isset($this->_belongs_to[$column]))
		{
			// Update related object itself
			$this->_related[$column] = $value;

			// Update the foreign key of this model
			$this->_object[$this->_belongs_to[$column]['foreign_key']] = ($value instanceof ORM)
				? $value->pk()
				: NULL;

			$this->_changed[$column] = $this->_belongs_to[$column]['foreign_key'];
		}
		else
		{
			$this->_object[$column] = $value;

			// Data has changed
			$this->_changed[$column] = $column;

			// Object is no longer saved or valid
			$this->_saved = $this->_valid = FALSE;
		}

		return $this;
	}


	/**
	 * Initializes the Database Builder to given query type
	 *
	 * @param  integer $type Type of Database query
	 * @return ORM
	 */
	protected function _build($type)
	{
		// Construct new builder object based on query type
		switch ($type)
		{
			case Database::SELECT:
				$this->_db_builder = Parse_DB::select();
			break;
			case Database::UPDATE:
				$this->_db_builder = Parse_DB::update(array($this->_table_name, $this->_object_name));
			break;
			case Database::DELETE:
				// Cannot use an alias for DELETE queries
				$this->_db_builder = Parse_DB::delete($this->_table_name);
		}

		// Process pending database method calls
		foreach ($this->_db_pending as $method)
		{
			$name = $method['name'];
			$args = $method['args'];

			$this->_db_applied[$name] = $name;

			call_user_func_array(array($this->_db_builder, $name), $args);
		}

		return $this;
	}

	/**
	 * Insert a new object to the database
	 * @param  Validation $validation Validation object
	 * @throws Kohana_Exception
	 * @return ORM
	 */
	public function create(Validation $validation = NULL)
	{
		if ($this->_loaded)
			throw new Kohana_Exception('Cannot create :model model because it is already loaded.', array(':model' => $this->_object_name));

		// Require model validation before saving
		if ( ! $this->_valid OR $validation)
		{
			$this->check($validation);
		}

		$data = array();
		foreach ($this->_changed as $column)
		{
			// Generate list of column => values
			$data[$column] = $this->_object[$column];
		}

		if (is_array($this->_created_column))
		{
			// Fill the created column
			$column = $this->_created_column['column'];
			$format = $this->_created_column['format'];

			$data[$column] = $this->_object[$column] = ($format === TRUE) ? time() : date($format);
		}

		$result = Parse_DB::insert($this->_table_name)
			->columns(array_keys($data))
			->values(array_values($data))
			->execute($this->_db);

		if ( ! array_key_exists($this->_primary_key, $data))
		{
			// Load the insert id as the primary key if it was left out
			$this->_object[$this->_primary_key] = $this->_primary_key_value = $result;
		}
		else
		{
			$this->_primary_key_value = $this->_object[$this->_primary_key];
		}

		// Object is now loaded and saved
		$this->_loaded = $this->_saved = TRUE;

		// All changes have been saved
		$this->_changed = array();
		$this->_original_values = $this->_object;

		return $this;
	}

	/**
	 * Updates a single record or multiple records
	 *
	 * @chainable
	 * @param  Validation $validation Validation object
	 * @throws Kohana_Exception
	 * @return ORM
	 */
	public function update(Validation $validation = NULL)
	{
		if ( ! $this->_loaded)
			throw new Kohana_Exception('Cannot update :model model because it is not loaded.', array(':model' => $this->_object_name));

		// Run validation if the model isn't valid or we have additional validation rules.
		if ( ! $this->_valid OR $validation)
		{
			$this->check($validation);
		}

		if (empty($this->_changed))
		{
			// Nothing to update
			return $this;
		}

		$data = array();
		foreach ($this->_changed as $column)
		{
			// Compile changed data
			$data[$column] = $this->_object[$column];
		}

		if (is_array($this->_updated_column))
		{
			// Fill the updated column
			$column = $this->_updated_column['column'];
			$format = $this->_updated_column['format'];

			$data[$column] = $this->_object[$column] = ($format === TRUE) ? time() : date($format);
		}

		// Use primary key value
		$id = $this->pk();

		// Update a single record
		Parse_DB::update($this->_table_name)
			->set($data)
			->where($this->_primary_key, '=', $id)
			->execute($this->_db);

		if (isset($data[$this->_primary_key]))
		{
			// Primary key was changed, reflect it
			$this->_primary_key_value = $data[$this->_primary_key];
		}

		// Object has been saved
		$this->_saved = TRUE;

		// All changes have been saved
		$this->_changed = array();
		$this->_original_values = $this->_object;

		return $this;
	}

	/**
	 * Updates or Creates the record depending on loaded()
	 *
	 * @chainable
	 * @param  Validation $validation Validation object
	 * @return ORM
	 */
	public function save(Validation $validation = NULL)
	{
		return $this->loaded() ? $this->update($validation) : $this->create($validation);
	}

	/**
	 * Deletes a single record while ignoring relationships.
	 *
	 * @chainable
	 * @throws Kohana_Exception
	 * @return ORM
	 */
	public function delete()
	{
		if ( ! $this->_loaded)
			throw new Kohana_Exception('Cannot delete :model model because it is not loaded.', array(':model' => $this->_object_name));

		// Use primary key value
		$id = $this->pk();

		// Delete the object
		Parse_DB::delete($this->_table_name)
			->where($this->_primary_key, '=', $id)
			->execute($this->_db);

		return $this->clear();
	}

	/**
	 * Returns the number of relationships 
	 *
	 *     // Counts the number of times the login role is attached to $model
	 *     $model->count_relations('roles', ORM::factory('role', array('name' => 'login')));
	 *     // Counts the number of times role 5 is attached to $model
	 *     $model->count_relations('roles', 5);
	 *     // Counts the number of times any of roles 1, 2, 3, or 4 are attached to
	 *     // $model
	 *     $model->count_relations('roles', array(1, 2, 3, 4));
	 *     // Counts the number roles attached to $model
	 *     $model->count_relations('roles')
	 *
	 * @param  string  $alias    Alias of the has_many "through" relationship
	 * @param  mixed   $far_keys Related model, primary key, or an array of primary keys
	 * @return integer
	 */
	public function count_relations($alias, $far_keys = NULL)
	{
		throw new Exception('Not implemented!');

		if ($far_keys === NULL)
		{
			return (int) Parse_DB::select(array(Parse_DB::expr('COUNT(*)'), 'records_found'))
				->from($this->_has_many[$alias]['through'])
				->where($this->_has_many[$alias]['foreign_key'], '=', $this->pk())
				->execute($this->_db)->get('records_found');
		}

		$far_keys = ($far_keys instanceof ORM) ? $far_keys->pk() : $far_keys;

		// We need an array to simplify the logic
		$far_keys = (array) $far_keys;

		// Nothing to check if the model isn't loaded or we don't have any far_keys
		if ( ! $far_keys OR ! $this->_loaded)
			return 0;

		$count = (int) Parse_DB::select(array(Parse_DB::expr('COUNT(*)'), 'records_found'))
			->from($this->_has_many[$alias]['through'])
			->where($this->_has_many[$alias]['foreign_key'], '=', $this->pk())
			->where($this->_has_many[$alias]['far_key'], 'IN', $far_keys)
			->execute($this->_db)->get('records_found');

		// Rows found need to match the rows searched
		return (int) $count;
	}

	/**
	 * Adds a new relationship to between this model and another.
	 *
	 *     // Add the login role using a model instance
	 *     $model->add('roles', ORM::factory('role', array('name' => 'login')));
	 *     // Add the login role if you know the roles.id is 5
	 *     $model->add('roles', 5);
	 *     // Add multiple roles (for example, from checkboxes on a form)
	 *     $model->add('roles', array(1, 2, 3, 4));
	 *
	 * @param  string  $alias    Alias of the has_many "through" relationship
	 * @param  mixed   $far_keys Related model, primary key, or an array of primary keys
	 * @return ORM
	 */
	public function add($alias, $far_keys)
	{
		throw new Exception('Not implemented!');

		$far_keys = ($far_keys instanceof ORM) ? $far_keys->pk() : $far_keys;

		$columns = array($this->_has_many[$alias]['foreign_key'], $this->_has_many[$alias]['far_key']);
		$foreign_key = $this->pk();

		$query = Parse_DB::insert($this->_has_many[$alias]['through'], $columns);

		foreach ( (array) $far_keys as $key)
		{
			$query->values(array($foreign_key, $key));
		}

		$query->execute($this->_db);

		return $this;
	}

	/**
	 * Removes a relationship between this model and another.
	 *
	 *     // Remove a role using a model instance
	 *     $model->remove('roles', ORM::factory('role', array('name' => 'login')));
	 *     // Remove the role knowing the primary key
	 *     $model->remove('roles', 5);
	 *     // Remove multiple roles (for example, from checkboxes on a form)
	 *     $model->remove('roles', array(1, 2, 3, 4));
	 *     // Remove all related roles
	 *     $model->remove('roles');
	 *
	 * @param  string $alias    Alias of the has_many "through" relationship
	 * @param  mixed  $far_keys Related model, primary key, or an array of primary keys
	 * @return ORM
	 */
	public function remove($alias, $far_keys = NULL)
	{
		throw new Exception('Not implemented!');

		$far_keys = ($far_keys instanceof ORM) ? $far_keys->pk() : $far_keys;

		$query = Parse_DB::delete($this->_has_many[$alias]['through'])
			->where($this->_has_many[$alias]['foreign_key'], '=', $this->pk());

		if ($far_keys !== NULL)
		{
			// Remove all the relationships in the array
			$query->where($this->_has_many[$alias]['far_key'], 'IN', (array) $far_keys);
		}

		$query->execute($this->_db);

		return $this;
	}

	/**
	 * Count the number of records in the table.
	 *
	 * @return integer
	 */
	public function count_all()
	{
		throw new Exception('Not implemented!');

		$selects = array();

		foreach ($this->_db_pending as $key => $method)
		{
			if ($method['name'] == 'select')
			{
				// Ignore any selected columns for now
				$selects[] = $method;
				unset($this->_db_pending[$key]);
			}
		}

		if ( ! empty($this->_load_with))
		{
			foreach ($this->_load_with as $alias)
			{
				// Bind relationship
				$this->with($alias);
			}
		}

		$this->_build(Database::SELECT);

		$records = $this->_db_builder->from(array($this->_table_name, $this->_object_name))
			->select(array(Parse_DB::expr('COUNT('.$this->_db->quote_column($this->_object_name.'.'.$this->_primary_key).')'), 'records_found'))
			->execute($this->_db)
			->get('records_found');

		// Add back in selected columns
		$this->_db_pending += $selects;

		$this->reset();

		// Return the total number of records in a table
		return (int) $records;
	}

	/**
	 * Adds addition tables to "JOIN ...".
	 *
	 * @param   mixed   $table  column name or array($column, $alias) or object
	 * @param   string  $type   join type (LEFT, RIGHT, INNER, etc)
	 * @return  $this
	 */
	public function join($table, $type = NULL)
	{
		throw new Exception('Not implemented!');

		// Add pending database call which is executed after query type is determined
		$this->_db_pending[] = array(
			'name' => 'join',
			'args' => array($table, $type),
		);

		return $this;
	}

	/**
	 * Adds "ON ..." conditions for the last created JOIN statement.
	 *
	 * @param   mixed   $c1  column name or array($column, $alias) or object
	 * @param   string  $op  logic operator
	 * @param   mixed   $c2  column name or array($column, $alias) or object
	 * @return  $this
	 */
	public function on($c1, $op, $c2)
	{
		throw new Exception('Not implemented!');

		// Add pending database call which is executed after query type is determined
		$this->_db_pending[] = array(
			'name' => 'on',
			'args' => array($c1, $op, $c2),
		);

		return $this;
	}

	/**
	 * Creates a "GROUP BY ..." filter.
	 *
	 * @param   mixed   $columns  column name or array($column, $alias) or object
	 * @param   ...
	 * @return  $this
	 */
	public function group_by($columns)
	{
		throw new Exception('Not implemented!');
		$columns = func_get_args();

		// Add pending database call which is executed after query type is determined
		$this->_db_pending[] = array(
			'name' => 'group_by',
			'args' => $columns,
		);

		return $this;
	}

	/**
	 * Alias of and_having()
	 *
	 * @param   mixed   $column  column name or array($column, $alias) or object
	 * @param   string  $op      logic operator
	 * @param   mixed   $value   column value
	 * @return  $this
	 */
	public function having($column, $op, $value = NULL)
	{
		throw new Exception('Not implemented!');
		return $this->and_having($column, $op, $value);
	}

	/**
	 * Creates a new "AND HAVING" condition for the query.
	 *
	 * @param   mixed   $column  column name or array($column, $alias) or object
	 * @param   string  $op      logic operator
	 * @param   mixed   $value   column value
	 * @return  $this
	 */
	public function and_having($column, $op, $value = NULL)
	{
		throw new Exception('Not implemented!');
		// Add pending database call which is executed after query type is determined
		$this->_db_pending[] = array(
			'name' => 'and_having',
			'args' => array($column, $op, $value),
		);

		return $this;
	}

	/**
	 * Creates a new "OR HAVING" condition for the query.
	 *
	 * @param   mixed   $column  column name or array($column, $alias) or object
	 * @param   string  $op      logic operator
	 * @param   mixed   $value   column value
	 * @return  $this
	 */
	public function or_having($column, $op, $value = NULL)
	{
		throw new Exception('Not implemented!');
		// Add pending database call which is executed after query type is determined
		$this->_db_pending[] = array(
			'name' => 'or_having',
			'args' => array($column, $op, $value),
		);

		return $this;
	}

	/**
	 * Alias of and_having_open()
	 *
	 * @return  $this
	 */
	public function having_open()
	{
		throw new Exception('Not implemented!');
		return $this->and_having_open();
	}

	/**
	 * Opens a new "AND HAVING (...)" grouping.
	 *
	 * @return  $this
	 */
	public function and_having_open()
	{
		throw new Exception('Not implemented!');
		// Add pending database call which is executed after query type is determined
		$this->_db_pending[] = array(
			'name' => 'and_having_open',
			'args' => array(),
		);

		return $this;
	}

	/**
	 * Opens a new "OR HAVING (...)" grouping.
	 *
	 * @return  $this
	 */
	public function or_having_open()
	{
		throw new Exception('Not implemented!');
		// Add pending database call which is executed after query type is determined
		$this->_db_pending[] = array(
			'name' => 'or_having_open',
			'args' => array(),
		);

		return $this;
	}

	/**
	 * Closes an open "AND HAVING (...)" grouping.
	 *
	 * @return  $this
	 */
	public function having_close()
	{
		throw new Exception('Not implemented!');
		return $this->and_having_close();
	}

	/**
	 * Closes an open "AND HAVING (...)" grouping.
	 *
	 * @return  $this
	 */
	public function and_having_close()
	{
		throw new Exception('Not implemented!');
		// Add pending database call which is executed after query type is determined
		$this->_db_pending[] = array(
			'name' => 'and_having_close',
			'args' => array(),
		);

		return $this;
	}

	/**
	 * Closes an open "OR HAVING (...)" grouping.
	 *
	 * @return  $this
	 */
	public function or_having_close()
	{
		throw new Exception('Not implemented!');
		// Add pending database call which is executed after query type is determined
		$this->_db_pending[] = array(
			'name' => 'or_having_close',
			'args' => array(),
		);

		return $this;
	}

	/**
	 * Start returning results after "OFFSET ..."
	 *
	 * @param   integer   $number  starting result number
	 * @return  $this
	 */
	public function offset($number)
	{
		throw new Exception('Not implemented!');
		// Add pending database call which is executed after query type is determined
		$this->_db_pending[] = array(
			'name' => 'offset',
			'args' => array($number),
		);

		return $this;
	}

	/**
	 * Enables the query to be cached for a specified amount of time.
	 *
	 * @param   integer  $lifetime  number of seconds to cache
	 * @return  $this
	 * @uses    Kohana::$cache_life
	 */
	public function cached($lifetime = NULL)
	{
		throw new Exception('Not implemented!');
		// TODO can we cache depending upon the login method?

		// Add pending database call which is executed after query type is determined
		$this->_db_pending[] = array(
			'name' => 'cached',
			'args' => array($lifetime),
		);

		return $this;
	}

	/**
	 * Adds "USING ..." conditions for the last created JOIN statement.
	 *
	 * @param   string  $columns  column name
	 * @return  $this
	 */
	public function using($columns)
	{
		throw new Exception('Not implemented!');

		// Add pending database call which is executed after query type is determined
		$this->_db_pending[] = array(
			'name' => 'using',
			'args' => array($columns),
		);

		return $this;
	}

	/**
	 * Checks whether a column value is unique.
	 * Excludes itself if loaded.
	 *
	 * @param   string   $field  the field to check for uniqueness
	 * @param   mixed    $value  the value to check for uniqueness
	 * @return  bool     whteher the value is unique
	 */
	public function unique($field, $value)
	{
		throw new Exception('Not implemented!');

		$model = ORM::factory($this->object_name())
			->where($field, '=', $value)
			->find();

		if ($this->loaded())
		{
			return ( ! ($model->loaded() AND $model->pk() != $this->pk()));
		}

		return ( ! $model->loaded());
	}
} // End ORM

