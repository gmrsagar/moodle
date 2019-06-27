<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * DML read/read-write database handle use tests
 *
 * @package    core
 * @category   dml
 * @copyright  2018 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__.'/fixtures/test_moodle_database.php');
/**
 * Database driver test class
 *
 * @package    core
 * @category   dml
 * @copyright  2018 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class base_test_moodle_database extends test_moodle_database {
    /** @var string */
    protected $handle;
    public $txn_handle;

    /**
     * Does not connect to the database. Sets handle property to $dbhost
     * @param string $dbhost
     * @param string $dbuser
     * @param string $dbpass
     * @param string $dbname
     * @param mixed $prefix
     * @param array $dboptions
     * @return bool true
     */
    public function connect($dbhost, $dbuser, $dbpass, $dbname, $prefix, array $dboptions=null) {
        $this->handle = $dbhost;
        $this->prefix = $prefix;
    }

    /**
     * Begin database transaction
     * @return void
     */
    protected function begin_transaction() {
        $this->txn_handle = $this->handle;
    }

    /**
     * Commit database transaction
     * @return void
     */
    protected function commit_transaction() {
        $this->txn_handle = $this->handle;
    }

    /**
     * Abort database transaction
     * @return void
     */
    protected function rollback_transaction() {
        $this->txn_handle = $this->handle;
    }

    /**
     * Query wrapper that calls query_start() and query_end()
     * @param string $sql
     * @param array $params
     * @param int $querytype
     * @return string $handle handle property
     */
    private function with_query_start_end($sql, array $params=null, $querytype) {
        $this->query_start($sql, $params, $querytype);
        $ret = $this->handle;
        $this->query_end(null);
        return $ret;
    }

    /**
     * get_records_sql() override, calls with_query_start_end()
     * @param string $sql the SQL select query to execute.
     * @param array $params array of sql parameters
     * @param int $limitfrom return a subset of records, starting at this point (optional).
     * @param int $limitnum return a subset comprising this many records (optional, required if $limitfrom is set).
     * @return string $handle handle property
     */
    public function get_records_sql($sql, array $params=null, $limitfrom=0, $limitnum=0) {
        return $this->with_query_start_end($sql, $params, SQL_QUERY_SELECT);
    }

    /**
     * Calls with_query_start_end()
     * Default implementation, throws Exception
     * @param string $table
     * @param array $params
     * @param bool $returnid
     * @param bool $bulk
     * @param bool $customsequence
     * @return string $handle handle property
     */
    public function insert_record_raw($table, $params, $returnid=true, $bulk=false, $customsequence=false) {
        return $this->with_query_start_end($table, $params, SQL_QUERY_INSERT);
    }

    /**
     * Calls with_query_start_end()
     * Default implementation, throws Exception
     * @param string $table
     * @param array $params
     * @param bool $bulk
     * @return string $handle handle property
     */
    public function update_record_raw($table, $params, $bulk=false) {
        return $this->with_query_start_end($table, $params, SQL_QUERY_UPDATE);
    }
}

require_once(__DIR__.'/../moodle_read_slave_trait.php');
/**
 * Database driver test class with moodle_read_slave_trait
 *
 * @package    core
 * @category   dml
 * @copyright  2018 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class read_slave_moodle_database extends base_test_moodle_database {
    use moodle_read_slave_trait;

    /**
     * Gets handle property
     * @return string $handle handle property
     */
    protected function db_handle() {
        return $this->handle;
    }

    /**
     * Sets handle property
     * @param string $dbh
     * @return void
     */
    protected function set_db_handle($dbh) {
        $this->handle = $dbh;
    }
}

/**
 * Database driver test class that exposes table_names()
 *
 * @package    core
 * @category   dml
 * @copyright  2018 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class read_slave_moodle_database_table_names extends read_slave_moodle_database {
    protected $prefix = 't_';

    public function table_names($sql) {
        return parent::table_names($sql);
    }
}

/**
 * DML read/read-write database handle use tests
 *
 * @package    core
 * @category   dml
 * @copyright  2018 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_dml_read_slave_testcase extends base_testcase {
    /**
     * Constructs a test case with the given name.
     *
     * @param string $name
     * @param array  $data
     * @param string $dataname
     */
    final public function __construct($name = null, array $data = array(), $dataname = '') {
        parent::__construct($name, $data, $dataname);

        $this->setBackupGlobals(false);
        $this->setBackupStaticAttributes(false);
        $this->setRunTestInSeparateProcess(false);
    }

    /**
     * Instantiates a test database interface object
     *
     * @return read_slave_moodle_database $db
     */
    public function new_db() {
        $dbhost = 'test_rw';
        $dbname = 'test';
        $dbuser = 'test';
        $dbpass = 'test';
        $prefix = 'test_';
        $dboptions = array('dbhost_readonly' => ['test_ro1', 'test_ro2']);

        $db = new read_slave_moodle_database();
        $db->connect($dbhost, $dbuser, $dbpass, $dbname, $prefix, $dboptions);
        return $db;
    }

    public function test_table_names() {
        $t = array(
            "SELECT *
             FROM {user} u
             JOIN (
                 SELECT DISTINCT u.id FROM {user} u
                 JOIN {user_enrolments} ue1 ON ue1.userid = u.id
                 JOIN {enrol} e ON e.id = ue1.enrolid
                 WHERE u.id NOT IN (
                     SELECT DISTINCT ue.userid FROM {user_enrolments} ue
                     JOIN {enrol} e ON (e.id = ue.enrolid AND e.courseid = 1)
                     WHERE ue.status = 'active'
                       AND e.status = 'enabled'
                       AND ue.timestart < now()
                       AND (ue.timeend = 0 OR ue.timeend > now())
                 )
             ) je ON je.id = u.id
             JOIN (
                 SELECT DISTINCT ra.userid
                   FROM {role_assignments} ra
                  WHERE ra.roleid IN (1, 2, 3)
                    AND ra.contextid = 'ctx'
              ) rainner ON rainner.userid = u.id
              WHERE u.deleted = 0" => [
                'user',
                'user',
                'user_enrolments',
                'enrol',
                'user_enrolments',
                'enrol',
                'role_assignments',
            ],
        );

        $db = new read_slave_moodle_database_table_names();
        foreach ($t as $sql => $tables) {
            $this->assertEquals($tables, $db->table_names($db->fix_sql_params($sql)[0]));
        }
    }

    public function test_read_read_write_read() {
        $DB = $this->new_db();

        $this->assertEquals(0, $DB->perf_get_reads_slave());

        $handle = $DB->get_records('test_table');
        $this->assertStringStartsWith('test_ro', $handle);
        $readsslave = $DB->perf_get_reads_slave();
        $this->assertGreaterThan(0, $readsslave);

        $handle = $DB->get_records('test_table2');
        $this->assertStringStartsWith('test_ro', $handle);
        $readsslave = $DB->perf_get_reads_slave();
        $this->assertGreaterThan(1, $readsslave);

        $handle = $DB->insert_record_raw('test_table', array('name' => 'blah'));
        $this->assertEquals('test_rw', $handle);

        $DB->get_records('test_table');
        $this->assertEquals('test_rw', $handle);

        $this->assertEquals($readsslave, $DB->perf_get_reads_slave());
    }

    public function test_read_write_write() {
        $DB = $this->new_db();

        $this->assertEquals(0, $DB->perf_get_reads_slave());

        $handle = $DB->get_records('test_table');
        $this->assertStringStartsWith('test_ro', $handle);
        $readsslave = $DB->perf_get_reads_slave();
        $this->assertGreaterThan(0, $readsslave);

        $handle = $DB->insert_record_raw('test_table', array('name' => 'blah'));
        $this->assertEquals('test_rw', $handle);

        $handle = $DB->update_record_raw('test_table', array('name' => 'blah2'));
        $this->assertEquals('test_rw', $handle);

        $this->assertEquals($readsslave, $DB->perf_get_reads_slave());
    }

    public function test_write_read_read() {
        $DB = $this->new_db();

        $this->assertEquals(0, $DB->perf_get_reads_slave());

        $handle = $DB->insert_record_raw('test_table', array('name' => 'blah'));
        $this->assertEquals('test_rw', $handle);
        $this->assertEquals(0, $DB->perf_get_reads_slave());

        $handle = $DB->get_records('test_table');
        $this->assertEquals('test_rw', $handle);
        $this->assertEquals(0, $DB->perf_get_reads_slave());

        $handle = $DB->get_records('test_table2');
        $this->assertStringStartsWith('test_ro', $handle);
        $this->assertEquals(1, $DB->perf_get_reads_slave());

        $handle = $DB->get_records_sql("SELECT * FROM {test_table2} JOIN {test_table}");
        $this->assertEquals('test_rw', $handle);
        $this->assertEquals(1, $DB->perf_get_reads_slave());
    }

    public function test_transaction_commit() {
        $DB = $this->new_db();

        $DB->txn_handle = null;
        $transaction = $DB->start_delegated_transaction();
        $this->assertEquals('test_rw', $DB->txn_handle);

        $DB->txn_handle = null;
        $transaction->allow_commit();
        $this->assertEquals('test_rw', $DB->txn_handle);
    }

    public function test_transaction_rollback() {
        $DB = $this->new_db();

        $DB->txn_handle = null;
        $transaction = $DB->start_delegated_transaction();
        $this->assertEquals('test_rw', $DB->txn_handle);

        $DB->txn_handle = null;
        try {
            $transaction->rollback(new Exception("Dummy"));
        } catch (Exception $e) {
        }
        $this->assertEquals('test_rw', $DB->txn_handle);
    }
}
