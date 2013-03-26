<?php
/**
 * @copyright	Copyright 2006-2012, Miles Johnson - http://milesj.me
 * @license		http://opensource.org/licenses/mit-license.php - Licensed under the MIT License
 * @link		http://milesj.me/code/php/databasic
 */

namespace mjohnson\databasic;

use \Exception;
use \mysqli;

/**
 * A wrapper class for accessing, abstracting and manipulating a MySQL database.
 *
 * @version	3.0.0
 * @package	mjohnson.databasic
 */
class Databasic {

	/**
	 * The MySQLi instance.
	 *
	 * @access public
	 * @var object
	 */
	public $sql;

	/**
	 * If you want data returned as an object instead of an array.
	 *
	 * @access protected
	 * @var boolean
	 */
	protected $_asObject = false;

	/**
	 * Holds data for SQLs and binds.
	 *
	 * @access protected
	 * @var array
	 */
	protected $_data = array();

	/**
	 * Contains all database connection information.
	 *
	 * @access protected
	 * @var array
	 * @static
	 */
	protected static $_dbConfigs = array();

	/**
	 * The current DB config for the instance.
	 *
	 * @access protected
	 * @var array
	 */
	protected $_db = array();

	/**
	 * If enabled, logs all queries and executions.
	 *
	 * @access protected
	 * @var boolean
	 */
	protected $_debug = true;

	/**
	 * Number of successful queries executed.
	 *
	 * @access protected
	 * @var int
	 */
	protected $_executed = 0;

	/**
	 * Contains the database instance.
	 *
	 * @access protected
	 * @var array
	 * @static
	 */
	protected static $_instances = array();

	/**
	 * A list of all queries being processed on a page.
	 *
	 * @access protected
	 * @var array
	 */
	protected $_queries = array();

	/**
	 * Connects to the database on class initialize; use getInstance().
	 *
	 * @access private
	 * @param array $db
	 */
	private function __construct($db) {
		$this->_db = $db;
		$this->_connect();
	}

	/**
	 * Add a binded value column pair to the respective call.
	 *
	 * @access public
	 * @param int $dataBit
	 * @param string $key
	 * @param mixed $value
	 * @return string
	 */
	public function addBind($dataBit, $key, $value) {
		$key = trim($key, ':');

		if (isset($this->_data[$dataBit]['binds'][$key])) {
			$key .= count($this->_data[$dataBit]['binds']);
		}

		$this->_data[$dataBit]['binds'][$key] = $value;

		return $key;
	}

	/**
	 * Set the fetch type.
	 *
	 * @access public
	 * @param boolean $status
	 * @return void
	 */
	public function asObject($status = false) {
		$this->_asObject = (boolean) $status;
	}

	/**
	 * Backticks columns, tables, etc.
	 *
	 * @access public
	 * @param string $var
	 * @param boolean $tick
	 * @return string
	 */
	public function backtick($var, $tick = true) {
		$var = trim((string) $var);

		if (mb_strpos($var, '.') !== false) {
			$v = explode('.', $var);
			$var  = "`". $v[0] ."`.";
			$var .= ($v[1] == '*') ? '*' : "`". $v[1] ."`";
		} else {
			$var = ($tick) ? "`$var`" : $var;
		}

		return $var;
	}

	/**
	 * Binds a paramater to the sql string.
	 *
	 * @access public
	 * @param string $sql
	 * @param array $params
	 * @param boolean $clean
	 * @return string
	 */
	public function bind($sql, array $params, $clean = true) {
		if ($params) {
			foreach ($params as $param => $value) {
				if (is_string($param)) {
					$param = ':'. trim($param, ':') .':';

					if (!preg_match('/^[_A-Z0-9]+\((.*)\)/', $value) && $clean) {
						$value = $this->clean($value);
					}

					$sql = str_replace($param, trim($value), $sql);
				}
			}
		}

		return $sql;
	}

	/**
	 * Return all binds for hte defined databit. If it is not set, create it.
	 *
	 * @access public
	 * @param int $dataBit
	 * @return array
	 */
	public function binds($dataBit) {
		if (!isset($this->_data[$dataBit]['binds'])) {
			$this->_data[$dataBit]['binds'] = array();
		}

		return $this->_data[$dataBit]['binds'];
	}

	/**
	 * Cleans an sql string to prevent unwanted injection.
	 *
	 * @access public
	 * @param string $value
	 * @return string
	 */
	public function clean($value) {
		if (get_magic_quotes_gpc()) {
			$value = stripslashes($value);
		}

		return $this->sql->real_escape_string($value);
	}

	/**
	 * Get all column information for a table.
	 * Is formatted based on the create() syntax.
	 *
	 * @access public
	 * @param string $tableName
	 * @param boolean $explicit
	 * @return array
	 */
	public function columns($tableName, $explicit = true) {
		$query = $this->execute(sprintf('SHOW FULL COLUMNS FROM %s', $this->backtick($tableName)), $this->_startLoadTime());
		$columns = array();

		if (!$query) {
			return $columns;
		}

		while ($row = $this->fetchAll($query)) {
			if ($explicit) {
				$type = $row['Type'];
				$length = "";

				if (($pos = strpos($row['Type'], '(')) !== false) {
					$type = substr($row['Type'], 0, $pos);

					preg_match('/\((.*?)\)/is', $row['Type'], $matches);

					if (strpos($matches[1], ',') !== false) {
						$enum = $matches[1];
						$length = array_map(
							create_function('$enum', 'return trim($enum, "\'");'),
							explode(',', $enum)
						);
					} else {
						$length = $matches[1];
					}
				}

				$column = array(
					'type' => $type,
					'length' => $length,
					'options' => array(
						'null' => (strtolower($row['Null']) == 'yes'),
						'unsigned' => (strpos($row['Type'], 'unsigned') !== false),
						'zerofill' => (strpos($row['Type'], 'zerofill') !== false),
						'default' => $row['Default'],
						'comment' => $row['Comment'],
						'auto_increment' => ($row['Extra'] == 'auto_increment')
					)
				);

				if (!empty($row['Key'])) {
					switch ($row['Key']) {
						case 'PRI': $column['key'] = 'primary'; break;
						case 'UNI': $column['key'] = 'unique'; break;
						case 'MUL': $column['key'] = 'index'; break;
					}
				}

				$columns[$row['Field']] = $column;
			} else {
				$columns[] = $row['Field'];
			}
		}

		return $columns;
	}

	/**
	 * Count the number of returned rows from the query result.
	 *
	 * @access public
	 * @return int
	 */
	public function countRows() {
		return (int) $this->sql->field_count;
	}

	/**
	 * Creates a database table based on the schema array.
	 *
	 * @access public
	 * @param string $tableName
	 * @param array $schema
	 * @param array $settings - Merges with defaults
	 * @return boolean
	 * @throws Exception
	 */
	public function create($tableName, array $schema, array $settings = array()) {
		if (!$schema) {
			throw new Exception('Schema is required to create tables.');
		}

		$dataBit = $this->_startLoadTime();
		$sql = array();
		$keys = array();
		$settings = $settings + array(
			'engine' => 'InnoDB',
			'charset' => 'utf8',
			'collate' => 'utf8_general_ci',
			'comment' => '',
			'increment'	=> 1
		);

		foreach ($schema as $field => $data) {
			$column = "\t". $this->backtick($field);

			$data['options'] = (isset($data['options']) ? $data['options'] : array()) + array(
				'null' => false,
				'unsigned' => false,
				'zerofill' => false,
				'comment' => '',
				'default' => '',
				'auto_increment' => false
			);

			if (empty($data['type'])) {
				$data['type'] = 'text';
			}

			if (empty($data['length'])) {
				$data['length'] = ($data['type'] === 'string' || $data['type'] === 'text') ? 255 : 10;
			}

			// Integers
			if ($data['type'] === 'integer' || $data['type'] === 'int') {
				if (empty($data['length'])) {
					$column .= ' INT(10)';
				} else {
					if ($data['length'] <= 3) {
						$column .= ' TINYINT(3)';
					} else if ($data['length'] <= 5) {
						$column .= ' SMALLINT(5)';
					} else if ($data['length'] <= 7) {
						$column .= ' MEDIUMINT(7)';
					} else if ($data['length'] <= 10) {
						$column .= ' INT(10)';
					} else {
						$column .= ' BIGINT(25)';
					}
				}

				// Unsigned, Zerofill
				if ($data['options']['unsigned']) {
					$column .= ' UNSIGNED';

					if ($data['options']['zerofill']) {
						$column .= ' ZEROFILL';
					}
				}

				// Auto increment
				if ($data['options']['auto_increment']) {
					$column .= ' AUTO_INCREMENT';
				}

			// Strings
			} else if ($data['type'] === 'string' || $data['type'] === 'text' || $data['type'] === 'char' || $data['type'] === 'varchar') {
				if ($data['length'] <= 255) {
					$column .= ' VARCHAR('. $data['length'] . ')';
				} else {
					$column .= ' TEXT';
				}

			// Enum
			} else if ($data['type'] === 'enum') {
				if (is_array($data['length']) && empty($data['enum'])) {
					$data['enum'] = $data['length'];
					unset($data['length']);
				}

				if (is_array($data['enum'])) {
					$opts = array();

					foreach ($data['enum'] as $opt) {
						$opts[] = "'". $opt ."'";
					}

					$column .= ' ENUM('. implode(', ', $opts) . ')';
				}

			// Datetime
			} else if ($data['type'] === 'datetime' || $data['type'] === 'timestamp' || $data['type'] === 'date' || $data['type'] === 'time' || $data['type'] === 'float' || $data['type'] === 'blob') {
				$column .= ' '. strtoupper($data['type']);

			// Year
			} else if ($data['type'] === 'year') {
				$column .= ' YEAR(4)';
			}

			if (isset($data['type'])) {
				// Null
				if (!$data['options']['auto_increment'] && $data['options']['null']) {
					$column .= ' NULL';
				} else {
					$column .= ' NOT NULL';
				}

				// Default
				if ($data['type'] === 'enum') {
					if ($data['options']['default'] !== '' && in_array($data['options']['default'], $data['enum'])) {
						$default = $data['options']['default'];
					} else {
						$default = $data['enum'][0];
					}

					$column .= " DEFAULT '". $default ."'";

				} else if ($data['options']['default'] !== '' && !$data['options']['auto_increment']) {
					$column .= " DEFAULT '". $this->_encode($data['options']['default']) ."'";
				}

				// Comment
				if (!empty($data['options']['comment'])) {
					$column .= " COMMENT '". $this->_encode($data['options']['comment']) ."'";
				}

				// Keys
				if (isset($data['key'])) {
					if ($data['key'] === 'index') {
						$keys['index'][] = $field;

					} else if ($data['key'] === 'primary') {
						$keys['primary'] = $field;

					} else if ($data['key'] === 'unique') {
						$keys['unique'] = $field;
					}
				}

				$sql[] = $column . ',';
			}
		}

		// Add keys
		if ($keys) {
			foreach ($keys as $key => $field) {
				$keySql = '';

				if ($key === 'index') {
					foreach ($field as $i => $index) {
						$keySql .= "\tKEY ". $this->backtick($index) ." (". $this->backtick($index) .")";

						if (count($field) != ($i + 1)) {
							$keySql .= ',';
						}
					}

				} else if ($key === 'primary') {
					$keySql = "\tPRIMARY KEY (". $this->backtick($field) .")";

				} else if ($key === 'uniqe') {
					$keySql = "\tUNIQUE KEY (". $this->backtick($field) .")";
				}

				if ($key !== 'index' && count($keys) != 1) {
					$keySql .= ',';
				}

				$sql[] = $keySql;
			}
		}

		$sql = sprintf("CREATE TABLE IF NOT EXISTS %s (\n%s\n) ENGINE=%s DEFAULT CHARSET=%s COLLATE=%s COMMENT='%s' AUTO_INCREMENT=%d;",
			$this->backtick($tableName),
			implode("\n", $sql),
			$settings['engine'],
			$settings['charset'],
			$this->_encode($settings['collate']),
			$this->_encode($settings['comment']),
			(int) $settings['increment']
		);

		return $this->execute($sql, $dataBit);
	}

	/**
	 * Turn debug on or off.
	 *
	 * @access public
	 * @param boolean $status
	 * @return void
	 */
	public function debug($status = true) {
		$this->_debug = (boolean) $status;
	}

	/**
	 * Builds a suitable SQL DELETE query and executes.
	 *
	 * @access public
	 * @param string $tableName
	 * @param array $conditions
	 * @param int $limit
	 * @return mixed
	 */
	public function delete($tableName, array $conditions, $limit = 1) {
		$dataBit = $this->_startLoadTime();

		$sql = sprintf('DELETE FROM %s WHERE %s', $this->backtick($tableName), $this->_formatConditions($this->_buildConditions($dataBit, $conditions)));

		if (is_numeric($limit)) {
			$sql .= ' LIMIT :limit:';
			$this->addBind($dataBit, 'limit', (int) $limit);
		}

		$sql = $this->bind($sql, $this->binds($dataBit));

		return $this->execute($sql, $dataBit);
	}

	/**
	 * Describes a table (gets column information).
	 *
	 * @access public
	 * @param string $tableName
	 * @return boolean
	 */
	public function describe($tableName) {
		$rows = array();

		if ($query = $this->execute(sprintf('DESCRIBE %s', $this->backtick($tableName)), $this->_startLoadTime())) {
			while ($row = $this->fetchAll($query)) {
				$rows[] = $row;
			}
		}

		return $rows;
	}

	/**
	 * Drops a table (completely removes a table and all its rows).
	 *
	 * @access public
	 * @param string $tableName
	 * @return boolean
	 */
	public function drop($tableName) {
		return $this->execute(sprintf('DROP TABLE %s', $this->backtick($tableName)), $this->_startLoadTime());
	}

	/**
	 * Executes the sql statement after being prepared and binded.
	 *
	 * @access public
	 * @param string $sql
	 * @param int $dataBit
	 * @return mixed
	 * @throws Exception
	 */
	public function execute($sql, $dataBit = null) {
		if (empty($dataBit)) {
			$dataBit = $this->_startLoadTime();
		}

		if (!$this->sql->ping()) {
			throw new Exception('Your database connection has been lost!');
		}

		$result = $this->sql->query($sql);

		if ($result === false) {
			$failure = $this->sql->error . ' (' . $this->sql->errno . ')';
		} else {
			++$this->_executed;
		}

		$seconds = $this->_endLoadTime($dataBit);

		if ($this->_debug) {
			$this->_queries[] = array(
				'statement' => $sql,
				'executed'  => isset($failure) ? $failure : 'true',
				'took'		=> $seconds . ' seconds',
				'affected'	=> $this->getAffected() . ' rows'
			);
		}

		return $result;
	}

	/**
	 * Fetches the first row from the query.
	 *
	 * @access public
	 * @param resource $query
	 * @return array
	 */
	public function fetch($query) {
		while ($row = $this->fetchAll($query)) {
			return $row;
		}

		return null;
	}

	/**
	 * Fetches all rows from the query.
	 *
	 * @access public
	 * @param resource $query
	 * @return array
	 */
	public function fetchAll($query) {
		if ($this->_asObject) {
			return $query->fetch_object();
		}

		return $query->fetch_assoc();
	}

	/**
	 * Gets the previously affected rows.
	 *
	 * @access public
	 * @return int
	 */
	public function getAffected() {
		return (int) $this->sql->affected_rows;
	}

	/**
	 * Returns the total successful queries executed.
	 *
	 * @access public
	 * @return int
	 */
	public function getExecuted() {
		return (int) $this->_executed;
	}

	/**
	 * Connects and returns a single instance of the database connection handle.
	 *
	 * @access public
	 * @param string $db
	 * @return Databasic
	 * @static
	 */
	public static function getInstance($db = 'default') {
		if (!isset(self::$_instances[$db])){
			self::$_instances[$db] = new Databasic(self::$_dbConfigs[$db]);
		}

		return self::$_instances[$db];
	}

	/**
	 * Gets the last inserted id from a query.
	 *
	 * @access public
	 * @return int
	 */
	public function getLastInsertId() {
		return (int) $this->sql->insert_id;
	}

	/**
	 * Returns an array of queries that have been executed; used with debugging.
	 *
	 * @access public
	 * @return array
	 */
	public function getQueries() {
		return $this->_queries;
	}

	/**
	 * Builds a suitable SQL INSERT query and executes.
	 *
	 * @access public
	 * @param string $tableName
	 * @param array $columns
	 * @return mixed
	 */
	public function insert($tableName, array $columns) {
		$dataBit = $this->_startLoadTime();
		$fields = $this->_buildFields($dataBit, $columns, 'insert');

		$sql = sprintf('INSERT INTO %s (%s) VALUES (%s)', $this->backtick($tableName), implode(', ', $fields['fields']), implode(', ', $fields['values']));
		$sql = $this->bind($sql, $this->binds($dataBit));

		if ($query = $this->execute($sql, $dataBit)) {
			return $this->getLastInsertId();
		}

		return false;
	}

	/**
	 * Optimizes and cleans all the overhead in the database.
	 *
	 * @access public
	 * @param string $tableName
	 * @return mixed
	 */
	public function optimize($tableName = null) {
		$dataBit = $this->_startLoadTime();

		if (empty($tableName)) {
			foreach ($this->tables() as $table) {
				$this->execute(sprintf('OPTIMIZE TABLE %s', $this->backtick($table)), $dataBit);
			}

			return true;
		}

		return $this->execute(sprintf('OPTIMIZE TABLE %s', $this->backtick($tableName)), $dataBit);
	}

	/**
	 * A basic method to select data from a database; can either return many rows, one row, or a count.
	 *
	 * @access public
	 * @param string $finder
	 * @param string|array $tableName
	 * @param array $options - fields, conditions, order, group, limit, offset
	 * @return mixed
	 */
	public function select($finder, $tableName, array $options = array()) {
		$dataBit = $this->_startLoadTime();
		$tableNames = array();

		if (!in_array($finder, array('all', 'first', 'count'))) {
			$finder = 'all';
		}

		// Table
		if (is_array($tableName)) {
			$tables = array();

			foreach ($tableName as $as => $table) {
				if (is_numeric($as)) {
					$as = $table;
				}

				$tables[] = $this->backtick($table) . ' AS ' . $this->backtick(ucfirst($as));
				$tableNames[] = $this->backtick(ucfirst($as));
			}

			$table = implode(', ', $tables);
		} else {
			$table = $tableNames[] = $this->backtick($tableName);
		}

		// Fields
		if ($finder == 'count') {
			$fields = 'COUNT(*) AS `count`';
		} else {
			if (!empty($options['fields'])) {
				$fields = $this->_buildFields($dataBit, $options['fields'], 'select');
				$fields = implode(', ', $fields['fields']);

			} else {
				if (count($tableNames) > 1) {
					$fields = array();

					foreach ($tableNames as $tableName) {
						$fields[] = $tableName . '.*';
					}

					$fields = implode(', ', $fields);
				} else {
					$fields = '*';
				}
			}
		}

		$sql = sprintf('SELECT %s FROM %s', $fields, $table);

		// Conditions
		if (!empty($options['conditions'])) {
			$sql .= ' WHERE ' . $this->_formatConditions($this->_buildConditions($dataBit, $options['conditions']));
		}

		// Order
		if (!empty($options['order'])) {
			$order = '';

			if (is_array($options['order'])) {
				$orders = array();

				foreach ($options['order'] as $column => $dir) {
					$orders[] = $this->backtick($column) ." ". mb_strtoupper($dir);
				}

				$order = implode(', ', $orders);

			} else if ($options['order'] === 'RAND()') {
				$order = $options['order'];
			}

			$sql .= ' ORDER BY ' . $order;
		}

		// Group
		if (!empty($options['group'])) {
			if (is_array($options['group'])) {
				$groups = array();

				foreach ($options['group'] as $group) {
					$groups[] = $this->backtick($group);
				}

				$group = implode(', ', $groups);

			} else {
				$group = $this->backtick($options['group']);
			}

			$sql .= ' GROUP BY ' . $group;
		}

		// Limit, offset
		if ($finder === 'first') {
			$options['limit'] = 1;
			$options['offset'] = null;
		}

		if (isset($options['limit']) && is_numeric($options['limit'])) {
			$sql .= ' LIMIT ';

			if (isset($options['offset']) && is_numeric($options['offset'])) {
				$sql .= ':offset:,';
				$this->addBind($dataBit, 'offset', (int) $options['offset']);
			}

			$sql .= ':limit:';
			$this->addBind($dataBit, 'limit', (int) $options['limit']);
		}

		// Execute query and return results
		$sql = $this->bind($sql, $this->binds($dataBit));

		if ($query = $this->execute($sql, $dataBit)) {
			if ($finder == 'count') {
				if ($fetch = $this->fetch($query)) {
					if ($this->_asObject) {
						return $fetch->count;
					} else {
						return $fetch['count'];
					}
				}

			} else if ($finder === 'first') {
				return $this->fetch($query);

			} else {
				$rows = array();

				while ($row = $this->fetchAll($query)) {
					$rows[] = $row;
				}

				return $rows;
			}
		}

		return false;
	}

	/**
	 * Stores database connection and login info.
	 *
	 * @access public
	 * @param string $db
	 * @param string $server
	 * @param string $database
	 * @param string $username
	 * @param string $password
	 * @return void
	 * @static
	 */
	public static function store($db, $server, $database, $username, $password) {
		self::$_dbConfigs[$db] = array(
			'server'	=> $server,
			'database'	=> $database,
			'username'	=> $username,
			'password'	=> $password
		);

		unset($password);
	}

	/**
	 * Return an array of table names.
	 *
	 * @access public
	 * @return array
	 */
	public function tables() {
		$tables = array();

		if ($query = $this->execute('SHOW TABLES', $this->_startLoadTime())) {
			while ($table = $this->fetchAll($query)) {
				$tables[] = $table['Tables_in_' . $this->_db['database']];
			}
		}

		return $tables;
	}

	/**
	 * Truncates a table (empties all data and sets auto_increment to 0).
	 *
	 * @access public
	 * @param string $tableName
	 * @return boolean
	 */
	public function truncate($tableName) {
		return $this->execute(sprintf('TRUNCATE TABLE %s', $this->backtick($tableName)), $this->_startLoadTime());
	}

	/**
	 * Builds a suitable SQL UPDATE query and executes.
	 *
	 * @access public
	 * @param string $tableName
	 * @param array $columns
	 * @param array $conditions
	 * @param int $limit
	 * @return array|object
	 */
	public function update($tableName, array $columns, array $conditions, $limit = 1) {
		$dataBit = $this->_startLoadTime();
		$fields = $this->_buildFields($dataBit, $columns, 'update');
		$conditions = $this->_buildConditions($dataBit, $conditions);

		$sql = sprintf('UPDATE %s SET %s WHERE %s', $this->backtick($tableName), implode(', ', $fields['fields']), $this->_formatConditions($conditions));

		if (is_numeric($limit)) {
			$sql .= ' LIMIT :limit:';
			$this->addBind($dataBit, 'limit', (int) $limit);
		}

		$sql = $this->bind($sql, $this->binds($dataBit));

		return $this->execute($sql, $dataBit);
	}

	/**
	 * Builds the data array for the specific SQL.
	 *
	 * @access protected
	 * @param int $dataBit
	 * @param array $columns
	 * @param string $type
	 * @return array
	 */
	protected function _buildFields($dataBit, $columns, $type = 'select') {
		switch ($type) {
			case 'update':
				foreach ($columns as $column => $value) {
					$this->_data[$dataBit]['fields'][] = $this->backtick($column) ." = ". $this->_formatType($value, $column);
					$this->addBind($dataBit, $column, $value);
				}
			break;

			case 'insert':
				foreach ($columns as $column => $value) {
					$this->_data[$dataBit]['fields'][] = $this->backtick($column);
					$this->_data[$dataBit]['values'][] = $this->_formatType($value, $column);
					$this->addBind($dataBit, $column, $value);
				}
			break;

			case 'select':
				foreach ($columns as $tableAlias => $column) {
					if (is_string($tableAlias) && is_array($column)) {
						foreach ($column as $i => $col) {
							$column[$i] = $tableAlias . '.' . $col;
						}

						$this->_buildFields($dataBit, $column, 'select');
					} else {
						if (strpos(strtoupper($column), ' AS ') !== false) {
							$parts = explode('AS', str_replace(' as ', ' AS ', $column));
							$this->_data[$dataBit]['fields'][] = $this->backtick(trim($parts[0])) . ' AS ' . $this->backtick(trim($parts[1]));
						} else {
							$this->_data[$dataBit]['fields'][] = $this->backtick($column);
						}
					}
				}
			break;
		}

		return $this->_data[$dataBit];
	}

	/**
	 * Builds the data array conditions for the SQL.
	 *
	 * @access protected
	 * @param int $dataBit
	 * @param array $conditions
	 * @param string $join
	 * @return array
	 */
	protected function _buildConditions($dataBit, $conditions, $join = '') {
		$data = array();

		foreach ($conditions as $column => $clause) {
			if (is_array($clause) && (in_array($column, array('OR', 'AND')) || !empty($join))) {
				$data[$column] = $this->_buildConditions($dataBit, $clause, $column);

			} else {
				$operators = array('=', '!=', '>', '>=', '<=', '<', '<>', 'LIKE', 'NOT LIKE', 'IS NULL', 'IS NOT NULL', 'IN', 'NOT IN');
				$operator = trim(mb_strstr($column, ' '));
				$value = $clause;

				if (in_array($operator, $operators)) {
					$operator = trim($operator);
					$length = (mb_strlen($column) - mb_strlen($operator));
					$column = trim(mb_substr($column, 0, $length));
				} else {
					$operator = '=';
				}

				// Is a column
				if (!is_array($value) && preg_match('/^[_a-zA-Z0-9]+\.[_a-zA-Z0-9]+$/i', $value)) {
					$valueClean = $this->backtick($value);

				// IN, array
				} else if (is_array($value)) {
					$values = array();

					foreach ($value as $i => $val) {
						$key = $this->addBind($dataBit, 'where_' . $i . $column, $val);
						$values[] = $this->_formatType($val, $key);
					}

					$valueClean = '(' . implode(', ', $values) . ')';

					if ($operator == '=') {
						$operator = 'IN';
					}

				// NULL, NOT NULL
				} else if (in_array($operator, array('IS NULL', 'IS NOT NULL'))) {
					$valueClean = '';

				} else {
					$key = $this->addBind($dataBit, 'where_' . $column, $value);
					$valueClean = $this->_formatType($value, trim($key, ':'));
				}

				$data[] = $this->backtick($column) . " " . $operator . " " . $valueClean;
			}
		}

		$this->_data[$dataBit]['conditions'] = $data;

		return $data;
	}

	/**
	 * Attempts to connect to the MySQL database.
	 *
	 * @access public
	 * @return void
	 * @throws Exception
	 */
	protected function _connect() {
		$this->_queries = array();
		$this->_executed = 0;
		$this->sql = new mysqli($this->_db['server'], $this->_db['username'], $this->_db['password'], $this->_db['database']);

		if (mysqli_connect_error()) {
			throw new Exception(sprintf('%s (%s)', mysqli_connect_errno(), mysqli_connect_error()));
		}

		unset($this->_db['password']);
	}

	/**
	 * Strips html and encodes the string.
	 *
	 * @access protected
	 * @param string $value
	 * @return string
	 */
	protected function _encode($value) {
		return (string) htmlentities(strip_tags($value), ENT_COMPAT, 'UTF-8');
	}

	/**
	 * If a mysql function is used, encode it!
	 *
	 * @access protected
	 * @param string $value
	 * @return string
	 */
	protected function _encodeMethod($value) {
		if (mb_strtoupper($value) == $value && mb_substr($value, -2) === '()') {
			return (string) $value;
		}

		$function = mb_substr($value, 0, mb_strpos($value, '('));
		preg_match('/^[_A-Z0-9]+\((.*)\)/', $value, $matches);

		if (empty($matches)) {
			return (string) $function ."()";
		}

		$params = array_map('trim', explode(',', $matches[1]));
		$cleaned = array();

		foreach ($params as $param) {
			if (mb_substr($param, 0, 1) === "'" && mb_substr($param, -1) === "'") {
				$param = trim($param, "'");

				if (empty($param)) {
					$param = "''";
				} else {
					$param = "'". $this->clean($param) ."'";
				}

			} else {
				if (is_numeric($param)) {
					$param = (int) $this->clean($param);
				} else {
					$param = $this->backtick($param);
				}
			}

			$cleaned[] = $param;
		}

		return (string) $function . '(' . implode(', ', $cleaned) . ')';
	}

	/**
	 * Determines the column values type.
	 *
	 * @access protected
	 * @param mixed $value
	 * @param string $column
	 * @param string $prefix
	 * @return mixed
	 */
	protected function _formatType($value, $column, $prefix = '') {
		$cleanValue = null;

		// NULL
		if (($value === NULL || $value == 'NULL') && $value !== 0) {
			$cleanValue = 'NULL';

		// Empty
		} else if (empty($value) && $value !== 0) {
			$cleanValue = "''";

		// NOW(), etc
		} else if (preg_match('/^[_A-Z0-9]+\((.*)\)/', $value)) {
			$cleanValue = $this->_encodeMethod($value);

		// Boolean
		} else if (is_bool($value)) {
			$cleanValue = (bool) $value;

		// Integers, Numbers
		} else if (is_numeric($value) || is_int($value)) {
			$cleanValue = ":". $prefix . $column .":";

		// Strings
		} else if (is_string($value) && mb_strlen($value) > 0) {
			$cleanValue = "':". $prefix . $column .":'";
		}

		return $cleanValue;
	}

	/**
	 * Formats the joins for the conditions.
	 *
	 * @access protected
	 * @param array $conditions
	 * @param string $operator
	 * @return string
	 */
	protected function _formatConditions($conditions, $operator = 'AND') {
		$clean = array();

		foreach ($conditions as $op => $clause) {
			if (is_array($clause)) {
				$clean[] = '(' . $this->_formatConditions($clause, $op) . ')';
			} else {
				$clean[] = $clause;
			}
		}

		return implode(' ' . $operator . ' ', $clean);
	}

	/**
	 * Starts the timer for the query execution time.
	 *
	 * @access protected
	 * @return int
	 */
	protected function _startLoadTime() {
		$dataBit = microtime();

		$this->_data[$dataBit] = array();

		if ($this->_debug) {
			$time = explode(' ', $dataBit);
			$time = $time[1] + $time[0];
			$this->_data[$dataBit]['start'] = $time;
		}

		return $dataBit;
	}

	/**
	 * Gets the final time in how long the query took.
	 *
	 * @access protected
	 * @param int $dataBit
	 * @return int
	 */
	protected function _endLoadTime($dataBit) {
		$start = $this->_data[$dataBit]['start'];
		unset($this->_data[$dataBit]);

		if ($this->_debug) {
			$time = explode(' ', microtime());
			$time = $time[1] + $time[0];

			return ($time - $start);
		}

		return null;
	}

	/**
	 * Disable clone from being used.
	 */
	protected function __clone() { }

}
