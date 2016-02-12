<?php
namespace CGI\Orm;

use PDO;
use CGI\Connection\PdoConnection;
use CGI\Logger\DatabaseLogger;
use CGI\Logger\FileLogger;
/**
 * Created by PhpStorm.
 * User: aleksandr
 * Date: 09.02.16
 * Time: 12:37
 */

abstract class EntityAbstract implements OrmInterface
{
    protected $data = [];
    protected $isLoaded = false;
    protected $error = false;

    protected static $dbh = null;
    protected static $logger = null;

    private $connection = null;

    protected $table;
    protected $primaryKey;

    public function __construct($data = [])
    {
        if(self::$logger == null) {
            self::$logger = new FileLogger('log.txt');
        }

        if (self::$dbh == null) {
            try{
                $this->connection = new PdoConnection('file');
                self::$dbh = $this->connection->establish();
            }
            catch (\PDOException $ex) {
                self::$logger->error($ex);
            }

        }

        $this->data = $data;
        $this->table = $this->getTableName();

        $statement = self::$dbh->query(
            'SHOW COLUMNS FROM ' . $this->getTableName()
        );
        if($statement) {
            $this->primaryKey = $statement->fetch(PDO::FETCH_NUM)[0];
        }
        else {
            self::$logger->error("Ошибка при попытке получить PRIMARY KEY из
            таблицы $this->getTableName(): $statement->errorInfo()");
        }
    }

    public function set($field, $value)
    {
        $this->data[$field] = $value;
    }

    public function get($field)
    {
        if (array_key_exists($field, $this->data)) {
            return $this->data[$field];
        } else {
            return false;
        }
    }

    public function load($id)
    {
        $sqlQuery = "SELECT * FROM `" . $this->table
            . "` WHERE $this->primaryKey = ?";

        $statement = self::$dbh->prepare($sqlQuery);
        $statement->execute([$id]);
        $values = $statement->fetch();
        if ($values == null) {
            self::$logger->notice("Запись с ID: $id отсутствует");
        } else {
            $this->data = $values;
            $this->isLoaded = true;
        }

        return $this->data;
    }

    public function save()
    {
        try {
            $this->saveEntry();
        } catch (\PDOException $ex) {
            self::$logger->error($ex);

        } catch (\Exception $ex) {
            self::$logger->error(
                "An error has occurred while saving the data: $ex "
            );
        }
    }
    public function delete()
    {
        if ($this->isLoaded) {
            $sqlQuery = "DELETE FROM `" . $this->table
                . "` WHERE $this->primaryKey = ?";

            $statement = self::$dbh->prepare($sqlQuery);
            $inserted = $statement->execute([$this->data[$this->primaryKey]]);
            echo "$inserted entry was deleted <br />";
            $this->isLoaded = false;
            $this->data[$this->primaryKey] = null;
        } else {
            self::$logger->notice("You must load an entry before deleting");
        }
    }

    abstract protected function saveEntry();

    abstract protected function getTableName();
}