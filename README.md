kohana-parse.com
================

Kohana Module For Parse.com

Notes:

pointers and dates have special behaviors with the exception of createAt and updateAt, which should be listed as a string type.

ACL is an array, and is not specially treated in anyways. All data is converted to json by json_encode.

Example:

class Model_Example extends Parse_ORM {

        protected $_table_name = 'Trip';

        protected $_belongs_to = array(
                'user' => array('model' => 'User', 'foreign_key' => 'User'),
                'business' => array('model' => 'Business', 'foreign_key' => 'BusinessID'),
        );

        protected $_table_columns = array(
                'objectId'      => array('type' => 'string'),
                'BusinessID'    => array('type' => 'string'),
                'MyDate'    	=> array('type' => 'date'),
                'User'          => array('type' => 'pointer', 'class' => '_User'),
                'createdAt'     => array('type' => 'string'),
                'updatedAt'     => array('type' => 'string'),
                'ACL'           => array('type' => 'array'),
        );
}
