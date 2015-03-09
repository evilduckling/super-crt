<?php
/**
 * TODO gérer des connections statiques ?
 * TODO créer un "execute"
 * 
 * TODO remonter les exception mysql
 */

/**
 * Manage request wrapping and database connections.
 */
class DbHandler {

	protected $tableName = NULL;
	protected $fields = array();
	protected $conditions = array("1");

	protected $affected = NULL;

	/**
	 * /!\ Warning one connection is open for each request.
	 */
	protected static $connection = NULL;

	protected $modeDebug = FALSE;
	protected static $dump = array();

	public function __construct() {
		$this->connect();
	}

	public function __destruct() {
		$this->close();
	}

	/**
	 * Handle magic methodes.
	 *
	 * Allows use of whereBy* and whereDiffer* instead of where where clause.
	 */
	public function __call($name, $arguments) {

		if(strpos($name, 'whereBy') === 0 and strlen($name) > 7) {

			$field = $this->prepareColumn(lcfirst(substr($name, 7)));
			$values = array_pop($arguments);
			if($values === NULL) {
				$value = 'NULL';
				$operator = 'IS';
			} else if(is_array($values) === FALSE) {
				$value = $this->prepareValue($values);
				$operator = "=";
			} else {
				if(empty($values)) {
					throw new Exception("This parameter can't be empty");
				}
				$tempValues = array();
				foreach($values as $v) {
					$tempValues[] = $this->prepareValue($v);
				}
				$value = "(".implode(', ', $tempValues).")";
				$operator = "IN";
			}

			return $this->where($field." ".$operator." ".$value);

		} else if(strpos($name, 'whereDiffer') === 0 and strlen($name) > 11) {

			$field = $this->prepareColumn(lcfirst(substr($name, 11)));
			$values = array_pop($arguments);
			if($values === NULL) {
				$value = 'NULL';
				$operator = 'IS NOT';
			} else if(is_array($values) === FALSE) {
				$value = $this->prepareValue($values);
				$operator = "!=";
			} else {
				if(empty($values)) {
					throw new Exception("This parameter can't be empty");
				}
				$tempValues = array();
				foreach($values as $v) {
					$tempValues[] = $this->prepareValue($v);
				}
				$value = "(".implode(', ', $tempValues).")";
				$operator = "NOT IN";
			}

			return $this->where($field." ".$operator." ".$value);

		}

		throw new Exception("Uh Oh");

	}

	/**
	 * Init connection to the db.
	 */
	protected function connect() {
		if(self::$connection === NULL) {
			self::$connection = new mysqli(DB_HOST, DB_LOGIN, DB_PASSWORD);
		}
	}

	/**
	 * Close connection to the db.
	 */
	protected function close() {
		if(self::$connection !== NULL) {
			self::$connection->close();
			self::$connection = NULL;
		}
	}

	/**
	 * Select db.
	 */
	protected function selectDatabase() {

		$bean = new $this->tableName;
		self::$connection->select_db($bean->getDatabase());
	}

	/**
	 * Set debug mode
	 */
	public function setModeDebug($modeDebug) {
		
		$this->modeDebug = $modeDebug;

		return $this;
	}

	/**
	 * Choose the table
	 */
	public function table($name) {

		require_once(CRT_MODEL_DIRECTORY.'/'.$name.'.shell.php');
		require_once(CRT_MODEL_DIRECTORY.'/'.$name.'.bean.php');
		$this->tableName = $name;

		$this->selectDatabase();

		return $this;
	}

	/**
	 * Prepare table name
	 */
	protected function getPreparedTableName() {
		if($this->tableName === NULL) {
			throw new Exception("Table Name not set");
		}
		return $this->prepareColumn(lcfirst($this->tableName));
	}

	/**
	 * Add fields to the selection
	 */
	public function field($array) {
		$this->fields = array_merge($array, $this->fields);

		return $this;
	}

	/**
	 * Add where close to the request
	 */
	public function where($condition) {
		array_push($this->conditions, "(".self::$connection->real_escape_string($condition).")");

		return $this;
	}

	/**
	 * Build and execute the select query
	 */
	public function select($index = NULL, $limit = NULL) {

		$results = $preparedFields = array();

		if(empty($this->fields)) {
			// Force to select all fields
			$preparedFields = '*';
		} else {
			// Prepare selected fields
			foreach($this->fields as $field) {
				$preparedFields[] = $this->prepareColumn($field);
			}
			$preparedFields = implode(", ", $preparedFields);
		}

		$query = "SELECT ".$preparedFields." FROM ".$this->getPreparedTableName()." WHERE ".implode(' AND ', $this->conditions);
		if($index !== NULL and $limit !== NULL and is_int($index) and is_int($limit)) {
			$query .= " LIMIT $index, $limit";
		}
		$query .= ";";

		$this->recordQuery($query);

		$res = self::$connection->query($query);

		while($res !== FALSE and $data = $res->fetch_object($this->tableName)) {
			$results[] = $data;
		}

		return $results;

	}

	/**
	 * Prepare a value for a query
	 */
	protected function prepareValue($value) {

		if($value instanceof DbStatement) {
			return $value->getStatement();
		} else if(is_int($value)) {
			return $value;
		} else if($value === NULL) {
			return "NULL";
		} else {
			return "'".self::$connection->real_escape_string($value)."'";
		}
	}

	/**
	 * Prepare a column name
	 */
	protected function prepareColumn($column) {
		return "`".self::$connection->real_escape_string($column)."`";
	}

	/**
	 * Build and execute an update query.
	 */
	public function update($object) {

		$updates = array();
		foreach($this->fields as $field) {
			$updates[] = $this->prepareColumn($field)." = ".$this->prepareValue($object->{'get'.ucfirst($field)}());
		}

		if(empty($updates)) {
			throw new Exception('Fields to update MUST be specified');
		}

		$query = "UPDATE ".$this->getPreparedTableName()." SET ".implode(", ", $updates)." WHERE ".implode(' AND ', $this->conditions).";";

		$this->recordQuery($query);

		$res = self::$connection->query($query);

		if($res !== FALSE) {
			$this->affected = self::$connection->affected_rows;
		}

		return $this;

	}

	/**
	 * Build and execute an insert query.
	 */
	public function insert($object) {

		if(empty($object)) {
			throw new Exception();
		}

		$preparedColumns = array();
		$preparedValues = array();
//		foreach($object as $attr => $value) {
//			$preparedColumns[] = $this->prepareColumn($attr);
//			$preparedValues[] = $this->prepareValue($value);
//		}

		foreach($object->getModifiedAttributes() as $attrName) {
			$preparedColumns[] = $this->prepareColumn($attrName);
			$preparedValues[] = $this->prepareValue($object->{'get'.ucfirst($attrName)}());
		}

		$query = "INSERT INTO ".$this->getPreparedTableName()." (".implode(', ', $preparedColumns).") VALUES (".implode(", ", $preparedValues).");";

		$this->recordQuery($query);

		$res = self::$connection->query($query);

		if($res !== FALSE) {
			$this->affected = self::$connection->affected_rows;
		}

		return $this;

	}

	/**
	 * Build and execute a delete query
	 */
	public function delete() {

		$query = "DELETE FROM ".$this->getPreparedTableName()." WHERE ".implode(' AND ', $this->conditions).";";

		$this->recordQuery($query);

		$res = self::$connection->query($query);

		if($res !== FALSE) {
			$this->affected = self::$connection->affected_rows;
		}

		return $this;
	}

	/**
	 * return the number of rows affected by the last query
	 */
	public function getAffectedRows() {
		return $this->affected;
	}

	/**
	 * Print a stack of all the query executed.
	 */
	public static function dump() {
		foreach(self::$dump as $piece) {
			echo '<pre style="background-color:grey; color:white;">'.$piece.'</pre>';
		}
	}

	/**
	 * Record an executed query.
	 */
	protected function recordQuery($query) {
		if($this->modeDebug) {
			echo '<pre style="background-color:grey; color:white;">'.$query.'</pre>';
		}

		self::$dump[] = $query;
	}

	/**
	 * Load a fullobject using it's identifier
	 */
	public function load($identifier) {

		$identifier = (int)$identifier;
		if($identifier <= 0) {
			throw new Exception("Malformed identifier");
		}

		$list = $this
			->whereById($identifier)
			->select(0, 1);

		if(empty($list)) {
			return FALSE;
		} else {
			return array_pop($list);
		}

	}

}

class DbStatement {

	protected $statement = NULL;

	public function __construct($statement) {
		$this->statement = $statement;
	}

	public function getStatement() {
		return $this->statement;
	}

}
?>
