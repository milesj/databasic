<?php
/**
 * DataBasic - Basic Database Access
 *
 * A wrapper class for accessing, abstracting and manipulating a MySQL database.
 * 
 * @author      Miles Johnson - http://milesj.me
 * @copyright   Copyright 2006-2010, Miles Johnson, Inc.
 * @license     http://opensource.org/licenses/mit-license.php - Licensed under The MIT License
 * @link        http://milesj.me/code/php/databasic
 */
 
class Databasic {

    /**
     * Current version.
     *
     * @access public
     * @var int
     */
    public $version = '2.5';

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
    protected static $_instance = array();

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
     * @return void
     */
    private function __construct($db) {
        $this->_db = $db;
        $this->_connect();
    }
	
    /**
     * Add a binded value column pair to the respective call.
     *
     * @access public
     * @param $dataBit
     * @param $key
     * @param $value
     * @return string
     */
    public function addBind($dataBit, $key, $value) {
        if (isset($this->_data[$dataBit]['binds'][':'. $key .':'])) {
            $key .= count($this->_data[$dataBit]['binds']);
        }

        $this->_data[$dataBit]['binds'][':'. trim($key, ':') .':'] = $value;
		
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
    public function bind($sql, $params, $clean = true) {
        if (!empty($params) && !empty($sql)) {
            if (is_array($params)) {
                foreach ($params as $param => $value) {
                    if (is_string($param) && isset($value)) {
                        $param = ':'. trim($param, ':') .':';

                        if (!preg_match('/^[_A-Z0-9]+\((.*)\)/', $value) && $clean) {
                            $value = $this->clean($value);
                        }

                        $sql = str_replace($param, trim($value), $sql);
                    }
                }
            } else {
                trigger_error('Database::bind(): Params given are not an array', E_USER_WARNING);
            }
        }

        return $sql;
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
        if (empty($tableName)) {
			return;
		}

		$dataBit = microtime();
		$this->_startLoadTime($dataBit);

		$query = $this->execute("SHOW FULL COLUMNS FROM ". $this->backtick($tableName), $dataBit);
		$columns = array();

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
     */
    public function create($tableName, $schema, $settings = array()) {
        $keys = $sql = array();
        $settings = $settings + array(
            'engine' 	=> 'InnoDB',
            'charset'	=> 'utf8',
            'collate'	=> 'utf8_general_ci',
            'comment'	=> '',
            'increment'	=> 1
        );

        // Build schema
        if (empty($schema)) {
			return;
		}
		
		$dataBit = microtime();
		$this->_startLoadTime($dataBit);

		$sql[] = "CREATE TABLE IF NOT EXISTS ". $this->backtick($tableName) ." (";

		foreach ($schema as $field => $data) {
			$column = "\t". $this->backtick($field);

			$data['options'] = (isset($data['options']) ? $data['options'] : array())  + array(
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
				$data['length'] = ($data['type'] == 'string' || $data['type'] == 'text') ? 255 : 10;
			}

			// Integers
			if ($data['type'] == 'integer' || $data['type'] == 'int') {
				if (empty($data['length'])) {
					$column .= " INT(10)";
				} else {
					if ($data['length'] <= 3) {
						$column .= " TINYINT(3)";
					} else if ($data['length'] <= 5) {
						$column .= " SMALLINT(5)";
					} else if ($data['length'] <= 7) {
						$column .= " MEDIUMINT(7)";
					} else if ($data['length'] <= 10) {
						$column .= " INT(10)";
					} else {
						$column .= " BIGINT(25)";
					}
				}

				// Unsigned, Zerofill
				if ($data['options']['unsigned'] === true) {
					$column .= " UNSIGNED";

					if ($data['options']['zerofill'] === true) {
						$column .= " ZEROFILL";
					}
				}

				// Auto increment
				if ($data['options']['auto_increment'] === true) {
					$column .= " AUTO_INCREMENT";
				}

			// Strings
			} else if ($data['type'] == 'string' || $data['type'] == 'text') {
				if ($data['length'] <= 255) {
					$column .= " VARCHAR(". $data['length'] .")";
				} else {
					$column .= " TEXT";
				}

			// Enum
			} else if ($data['type'] == 'enum') {
				if (is_array($data['length']) && empty($data['enum'])) {
					$data['enum'] = $data['length'];
					unset($data['length']);
				}

				if (is_array($data['enum'])) {
					$opts = array();

					foreach ($data['enum'] as $opt) {
						$opts[] = "'". $opt ."'";
					}

					$column .= " ENUM(". implode(', ', $opts) .")";
				}

			// Datetime
			} else if ($data['type'] == 'datetime' || $data['type'] == 'timestamp' || $data['type'] == 'date' || $data['type'] == 'time' || $data['type'] == 'float' || $data['type'] == 'blob') {
				$column .= " ". strtoupper($data['type']);

			// Year
			} else if ($data['type'] == 'year') {
				$column .= " YEAR(4)";
			}

			if (isset($data['type'])) {
				// Null
				if (!$data['options']['auto_increment'] && $data['options']['null'] === true) {
					$column .= " NULL";
				} else {
					$column .= " NOT NULL";
				}

				// Default
				if ($data['type'] == 'enum') {
					if (!empty($data['options']['default']) && in_array($data['options']['default'], $data['enum'])) {
						$default = $data['options']['default'];
					} else {
						$default = $data['enum'][0];
					}
					$column .= " DEFAULT '". $default ."'";

				} else if (!empty($data['options']['default']) && !$data['options']['auto_increment']) {
					$column .= " DEFAULT '". $this->_encode($data['options']['default']) ."'";
				}

				// Comment
				if (!empty($data['options']['comment'])) {
					$column .= " COMMENT '". $this->_encode($data['options']['comment']) ."'";
				}

				// Keys
				if (isset($data['key'])) {
					if ($data['key'] == 'index') {
						$keys['index'][] = $field;
					} else if ($data['key'] == 'primary') {
						$keys['primary'] = $field;
					} else if ($data['key'] == 'unique') {
						$keys['unique'] = $field;
					}
				}

				$sql[] = $column .',';
			}
		}

		// Add keys
		if (!empty($keys)) {
			foreach ($keys as $key => $field) {
				if ($key == 'index') {
					$keySql = "";
					foreach ($field as $i => $index) {
						$keySql .= "\tKEY ". $this->backtick($index) ." (". $this->backtick($index) .")";

						if (count($field) != ($i + 1)) {
							$keySql .= ',';
						}
					}

				} else if ($key == 'primary') {
					$keySql = "\tPRIMARY KEY (". $this->backtick($field) .")";

				} else if ($key == 'uniqe') {
					$keySql = "\tUNIQUE KEY (". $this->backtick($field) .")";
				}

				if ($key != 'index' && count($keys) != 1) {
					$keySql .= ',';
				}

				$sql[] = $keySql;
			}
		}

		// Add settings
		$closer = ")";
		foreach ($settings as $field => $setting) {
			if ($field == 'engine') {
				$closer .= " ENGINE=". $setting;
			} else if ($field == 'charset') {
				$closer .= " DEFAULT CHARSET=". $setting;
			} else if ($field == 'comment') {
				$closer .= " COMMENT='". $this->_encode($setting) ."'";
			} else if ($field == 'increment') {
				$closer .= " AUTO_INCREMENT=". intval($setting);
			} else if ($field == 'collate') {
				$closer .= " COLLATE=". $this->_encode($setting);
			}
		}
		$closer .= ";";

		$sql[] = $closer;
		$sql = implode("\n", $sql);

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
        if (empty($tableName) || empty($conditions)) {
			return false;
		}

		$dataBit = microtime();
		$this->_startLoadTime($dataBit);
		$this->_buildConditions($dataBit, $conditions);

		$sql = "DELETE FROM ". $this->backtick($tableName) ." WHERE ". $this->_formatConditions($this->_data[$dataBit]['conditions']);

		// Limit, offset
		if (isset($limit) && is_int($limit)) {
			$sql .= " LIMIT :limit:";
			$this->addBind($dataBit, 'limit', $limit);
		}

		return $this->execute($this->bind($sql, $this->_data[$dataBit]['binds']), $dataBit);
    }
	
    /**
     * Describes a table (gets column information).
     *
     * @access public
     * @param string $tableName
     * @return boolean
     */
    public function describe($tableName) {
        if (empty($tableName)) {
			return;
		}

		$dataBit = microtime();
		$this->_startLoadTime($dataBit);
		$rows = array();

		if ($query = $this->execute("DESCRIBE ". $this->backtick($tableName), $dataBit)) {
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
        if (empty($tableName)) {
			return;
		}

		$dataBit = microtime();
		$this->_startLoadTime($dataBit);

		return $this->execute("DROP TABLE ". $this->backtick($tableName), $dataBit);
    }

    /**
     * Executes the sql statement after being prepared and binded.
     *
     * @access public
     * @param string $sql
     * @param int $dataBit
     * @return mixed
     */
    public function execute($sql, $dataBit = null) {
        if (empty($dataBit)) {
            $dataBit = microtime();
            $this->_startLoadTime($dataBit);
        }

        if (!$this->sql->ping()) {
            trigger_error('Database::execute(): Your database connection has been lost!', E_USER_ERROR);
        }

        $result = $this->sql->query($sql);

        if ($result === false) {
            $failure = $this->sql->error .'. ('. $this->sql->errno .')';
            trigger_error('Database::execute(): '. $failure, E_USER_ERROR);
        } else {
            ++$this->_executed;
        }

        if ($this->_debug) {
            $this->_queries[] = array(
                'statement' => $sql,
                'executed'  => isset($failure) ? $failure : 'true',
                'took'		=> $this->_endLoadTime($dataBit) .' seconds',
                'affected'	=> $this->getAffected() .' rows'
            );
        }

        unset($this->_data[$dataBit]);
        return $result;
    }
	
    /**
     * Fetches the first row from the query.
     *
     * @access public
     * @param result $query
     * @return array
     */
    public function fetch($query) {
        while ($row = $this->fetchAll($query)) {
            return $row;
        }
    }
	
    /**
     * Fetches all rows from the query.
     *
     * @access public
     * @param result $query
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
     * @return instance
     * @static
     */
    public static function getInstance($db = 'default') {
        if (!isset(self::$_instance[$db])){
            self::$_instance[$db] = new Databasic(self::$_dbConfigs[$db]);
        }

        return self::$_instance[$db];
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
        if (empty($tableName) || empty($columns)) {
			return false;
		}

		$dataBit = microtime();
		$this->_startLoadTime($dataBit);
		$this->_buildFields($dataBit, $columns, 'insert');

		$sql = "INSERT INTO ". $this->backtick($tableName) ." (". implode(', ', $this->_data[$dataBit]['fields']) .") VALUES (". implode(', ', $this->_data[$dataBit]['values']) .")";
		$sql = $this->bind($sql, $this->_data[$dataBit]['binds']);

		if ($query = $this->execute($sql, $dataBit)) {
			return $this->getLastInsertId();
		}

        return false;
    }
	
    /**
     * Optimizes and cleans all the overhead in the database.
     *
     * @access public
     * @param string $useDb
     * @return mixed
     */
    public function optimize($tableName = null) {
        $dataBit = microtime();
        $this->_startLoadTime($dataBit);

        if (empty($tableName)) {
            $tables = $this->tables();

            foreach ($tables as $table) {
                $this->execute('OPTIMIZE TABLE '. $this->backtick($table), $dataBit);
            }

            return true;
        }

		return $this->execute("OPTIMIZE TABLE ". $this->backtick($tableName), $dataBit);
    }
	
    /**
     * A basic method to select data from a database; can either return many rows, one row, or a count.
     *
     * @access public
     * @param string $finder
     * @param string $tableName
     * @param array $options - fields, conditions, order, group, limit, offset
     * @return mixed
     */
    public function select($finder, $tableName, array $options = array()) {
        if (empty($tableName)) {
			return false;
		}

		$execute = true;
		$dataBit = microtime();
		$this->_startLoadTime($dataBit);
		$tableNames = array();

		if (empty($finder)) {
			$finder = 'all';
		}

		// Table
		if (is_array($tableName)) {
			$tables = array();
			foreach ($tableName as $as => $table) {
				if (is_int($as)) {
					$as = $table;
				}

				$tables[] = $this->backtick($table) ." AS ". $this->backtick(ucfirst($as));
				$tableNames[] = $this->backtick(ucfirst($as));
			}

			$table = implode(', ', $tables);
		} else {
			$table = $tableNames[] = $this->backtick($tableName);
		}

		// Fields
		if ($finder == 'count') {
			$fields = "COUNT(*) AS `count`";
		} else {
			if (!empty($options['fields']) && is_array($options['fields'])) {
				$this->_buildFields($dataBit, $options['fields'], 'select');
				$fields = implode(', ', $this->_data[$dataBit]['fields']);

			} else {
				if (count($tableNames) > 1) {
					$fields = array();
					foreach ($tableNames as $t) {
						$fields[] = $t .".*";
					}

					$fields = implode(', ', $fields);
				} else {
					$fields = '*';
				}
			}
		}

		$sql = "SELECT ". $fields ." FROM ". $table;

		// Conditions
		if (!empty($options['conditions'])) {
			if (is_array($options['conditions'])) {
				$this->_buildConditions($dataBit, $options['conditions']);
			} else {
				$execute = false;
				trigger_error('Database::select(): Conditions/Where clause supplied must be an array', E_USER_WARNING);
			}

			$sql .= " WHERE ". $this->_formatConditions($this->_data[$dataBit]['conditions']);
		}

		// Order
		if (!empty($options['order'])) {
			if (is_array($options['order'])) {
				$orders = array();
				foreach ($options['order'] as $column => $dir) {
					$orders[] = $this->backtick($column) ." ". mb_strtoupper($dir);
				}

				$order = implode(', ', $orders);
				$sql .= " ORDER BY ". $order;

			} else if ($options['order'] == 'RAND()') {
				$sql .= " ORDER BY RAND()";
			}
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

			$sql .= " GROUP BY ". $group;
		}

		// Limit, offset
		if ($finder == 'first') {
			$options['limit'] = 1;
			$options['offset'] = null;
		}

		if (isset($options['limit']) && is_int($options['limit'])) {
			$sql .= " LIMIT ";

			if (isset($options['offset']) && is_int($options['offset'])) {
				$sql .= ":offset:,";
				$this->addBind($dataBit, 'offset', $options['offset']);
			}

			$sql .= ":limit:";
			$this->addBind($dataBit, 'limit', $options['limit']);
		}

		// Execute query and return results
		if ($execute === true) {
			if (!isset($this->_data[$dataBit]['binds'])) {
				$this->_data[$dataBit]['binds'] = array();
			}

			$sql = $this->bind($sql, $this->_data[$dataBit]['binds']);
			$query = $this->execute($sql, $dataBit);

			if ($finder == 'count') {
				if ($fetch = $this->fetch($query)) {
					if ($this->_asObject === true) {
						return $fetch->count;
					} else {
						return $fetch['count'];
					}
				}

			} else if ($finder == 'first') {
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
     * @return boolean
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
        return true;
    }

    /**
     * Return an array of table names.
     *
     * @access public
     * @return array
     */
    public function tables() {
        $dataBit = microtime();
        $this->_startLoadTime($dataBit);
        
        $query = $this->execute('SHOW TABLES', $dataBit);
        $tables = array();

        while ($table = $this->fetchAll($query)) {
            $tables[] = $table['Tables_in_'. $this->_db['database']];
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
        if (empty($tableName)) {
			return false;
		}

		$dataBit = microtime();
		$this->_startLoadTime($dataBit);

		return $this->execute("TRUNCATE TABLE ". $this->backtick($tableName), $dataBit);
    }

    /**
     * Builds a suitable SQL UPDATE query and executes.
     *
     * @access public
     * @param string $tableName
     * @param array $columns
     * @param array $conditions
     * @param int $limit
     * @return result
     */
    public function update($tableName, array $columns, array $conditions, $limit = 1) {
        if (empty($tableName) || empty($columns) || empty($conditions)) {
			return false;
		}

		$dataBit = microtime();
		$this->addBind($dataBit, 'limit', (int) $limit);
		$this->_startLoadTime($dataBit);
		$this->_buildFields($dataBit, $columns, 'update');
		$this->_buildConditions($dataBit, $conditions);

		$sql = "UPDATE ". $this->backtick($tableName) ." SET ". implode(', ', $this->_data[$dataBit]['fields']) ." WHERE ". $this->_formatConditions($this->_data[$dataBit]['conditions']) ." LIMIT :limit:";
		$sql = $this->bind($sql, $this->_data[$dataBit]['binds']);

		return $this->execute($sql, $dataBit);
    }
	
    /**
     * Builds the data array for the specific SQL.
     *
     * @access protected
     * @param int $dataBit
     * @param array $columns
     * @param string $type
     * @return void
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
                        foreach ($column as $ci => $col) {
                            $column[$ci] = $tableAlias .'.'. $col;
                        }

                        $this->_buildFields($dataBit, $column, 'select');
                    } else {
                        if (strpos(strtoupper($column), ' AS ') !== false) {
                            $parts = explode('AS', str_replace(' as ', ' AS ', $column));
                            $this->_data[$dataBit]['fields'][] = $this->backtick(trim($parts[0])) .' AS '. $this->backtick(trim($parts[1]));
                        } else {
                            $this->_data[$dataBit]['fields'][] = $this->backtick($column);
                        }
                    }
                }
            break;
        }
    }
	
    /**
     * Builds the data array conditions for the SQL.
     *
     * @access protected
     * @param int $dataBit
     * @param array $conditions
     * @param string $join
     * @return void
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
                    foreach ($value as $x => $val) {
                        $key = $this->addBind($dataBit, 'where_'. $x . $column, $val);
                        $values[] = $this->_formatType($val, trim($key, ':'));
                    }

                    $valueClean = '('. implode(', ', $values) .')';

                    if ($operator == '=') {
                        $operator = 'IN';
                    }

                // NULL, NOT NULL
                } else if (in_array($operator, array('IS NULL', 'IS NOT NULL'))) {
                    $valueClean = '';

                } else {
                    $key = $this->addBind($dataBit, 'where_'. $column, $value);
                    $valueClean = $this->_formatType($value, trim($key, ':'));
                }

                $data[] = $this->backtick($column) ." ". $operator ." ". $valueClean;
            }
        }

        $this->_data[$dataBit]['conditions'] = $data;
        return $data;
    }

    /**
     * Attempts to connect to the MySQL database.
     *
     * @access public
     * @param string $useDb
     * @return void
     */
    protected function _connect() {
        $this->_queries = array();
        $this->_executed = 0;
        $this->sql = new mysqli($this->_db['server'], $this->_db['username'], $this->_db['password'], $this->_db['database']);

        if (mysqli_connect_error()) {
            trigger_error('Database::connect(): '. mysqli_connect_errno() .'. ('. mysqli_connect_error() .')', E_USER_ERROR);
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
        return (string) htmlentities(strip_tags($value));
    }
	
    /**
     * If a mysql function is used, encode it!
     *
     * @access protected
     * @param string $value
     * @return string
     */
    protected function _encodeMethod($value) {
        if (mb_strtoupper($value) == $value && mb_substr($value, -2) == '()') {
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
			if (mb_substr($param, 0, 1) == "'" && mb_substr($param, -1) == "'") {
				$param = trim($param, "'");

				if (empty($param)) {
					$param = "''";
				} else {
					$param = "'". $this->clean($param) ."'";
				}

			} else {
				if (is_int($param)) {
					$param = intval($this->clean($param));
				} else {
					$param = $this->backtick($param);
				}
			}

			$cleaned[] = $param;
		}

		return (string) $function ."(". implode(', ', $cleaned) .")";
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
        // NULL
        if (($value === NULL || $value == 'NULL') && $value !== 0) {
            $cleanValue = 'NULL';

        // Empty
        } else if ((empty($value) || !isset($value)) && $value !== 0) {
            $cleanValue = "''";

        // NOW(), etc
        } else if (preg_match('/^[_A-Z0-9]+\((.*)\)/', $value)) {
            $cleanValue = $this->_encodeMethod($value);

        // Boolean
        } else if (is_bool($value)) {
            $cleanValue = (bool)$value;

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
                $clean[] = '('. $this->_formatConditions($clause, $op) .')';
            } else {
                $clean[] = $clause;
            }
        }

        return implode(' '. $operator .' ', $clean);
    }
	
    /**
     * Starts the timer for the query execution time.
     *
     * @access protected
     * @param int $dataBit
     * @return void
     */
    protected function _startLoadTime($dataBit) {
        if ($this->_debug) {
            $time = explode(' ', $dataBit);
            $time = $time[1] + $time[0];
            $this->_data[$dataBit]['start'] = $time;
        }
    }
	
    /**
     * Gets the final time in how long the query took.
     *
     * @access protected
     * @param int $dataBit
     * @return int
     */
    protected function _endLoadTime($dataBit) {
        if ($this->_debug) {
            $time = explode(' ', microtime());
            $time = $time[1] + $time[0];

            return ($time - $this->_data[$dataBit]['start']);
        }
    }

    /**
     * Disable clone from being used.
     */
    protected function __clone() { }
	
}
