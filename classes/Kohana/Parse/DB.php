<?php defined('SYSPATH') OR die('No direct script access.'); 

class Kohana_Parse_DB extends DB {

	public static function query($type, $sql)
	{
		return new Parse_Query($type, $sql);
	}

	public static function select($columns = NULL)
	{
		return new Parse_Query_Builder_Select(func_get_args());
	}

	public static function select_array(array $columns = NULL)
	{
		return new Parse_Query_Builder_Select($columns);
	}

	public static function insert($table = NULL, array $columns = NULL)
	{
		return new Parse_Query_Builder_Insert($table, $columns);
	}

	public static function update($table = NULL)
	{
		return new Parse_Query_Builder_Update($table);
	}

	public static function delete($table = NULL)
	{
		return new Parse_Query_Builder_Delete($table);
	}

	public static function expr($string, $parameters = array())
	{
		return new Parse_Expression($string, $parameters);
	}

} // End DB
