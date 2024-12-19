<?php

require_once("MyCSV.class.php");

class MyCSV_MySQL extends MyCSV
{
    private $mysqli;  // Database connection

    // Constructor to establish MySQL connection
    public function __construct(mysqli $mysqli, string $tablename = "", int $length = 10000)
    {
        parent::__construct($tablename, $length);
        $this->mysqli = $mysqli;
    }

    // Method to fetch data from MySQL and populate the CSV
    public function fromMySQL(string $source = ""): bool
    {
        if (empty($source)) {
            $source = pathinfo($this->filename, PATHINFO_FILENAME);  // Get table name from file name
        }

        $query = "SELECT * FROM $source";
        $result = $this->mysqli->query($query);

        if (!$result) {
            trigger_error("MySQL query failed: " . $this->mysqli->error, E_USER_WARNING);
            return false;
        }

        $this->fields = ["id"];
        $this->data = [];
        while ($row = $result->fetch_assoc()) {
            $this->insert($row);
        }
        return true;
    }

    // Method to insert data into MySQL
    public function toMySQL(string $tablename = ""): bool
    {
        $sqlArray = $this->toSQL($tablename);
        foreach ($sqlArray as $sql) {
            if (!$this->mysqli->query($sql)) {
                trigger_error("MySQL query failed: " . $this->mysqli->error, E_USER_WARNING);
                return false;
            }
        }
        return true;
    }

    // Method to dump SQL queries to create and insert data into a table
    public function dumpSQL(string $tablename = ""): void
    {
        echo implode("\n", $this->toSQL($tablename));
    }

    // Convert CSV data to SQL statements
    public function toSQL(string $tablename = ""): array
    {
        if (empty($tablename)) {
            $tablename = preg_replace('/\.[^.]*$/', '', basename($this->filename));
        }

        $types = [
            0 => "TINYINT",  // -128 to 127
            1 => "SMALLINT", // -32768 to 32767
            2 => "INT",      // -2147483648 to 2147483647
            3 => "DOUBLE",
            4 => "VARCHAR(255)",
            5 => "LONGTEXT",
            6 => "LONGBLOB"
        ];

        $fieldTypes = [];
        reset($this->data);
        while ($row = $this->each()) {
            foreach ($row as $field => $value) {
                $fieldTypes[$field] = $this->determineFieldType($value, $fieldTypes[$field] ?? 0);
            }
        }

        $sqlArray = ["DROP TABLE IF EXISTS " . $this->_backquote($tablename) . ";"];
        $sql = "CREATE TABLE " . $this->_backquote($tablename) . " (\n";
        foreach ($this->fields as $field) {
            $sql .= "  " . $this->_backquote($field) . " " . $types[$fieldTypes[$field]] . " NOT NULL";
            if (strtolower($field) === "id") $sql .= " AUTO_INCREMENT";
            $sql .= ",\n";
        }
        $sql .= "  PRIMARY KEY (" . $this->_backquote("id") . ")\n);";
        $sqlArray[] = $sql;

        reset($this->data);
        while ($row = $this->each()) {
            $rowSql = [];
            foreach ($row as $field => $value) {
                $rowSql[$this->_backquote($field)] = $this->mysqli->real_escape_string($value);
            }

            $sql = "INSERT INTO " . $this->_backquote($tablename) . " (" . implode(", ", array_keys($rowSql)) . ")";
            $sql .= " VALUES (" . implode(", ", array_map(fn($val) => "'$val'", $rowSql)) . ");";
            $sqlArray[] = $sql;
        }

        return $sqlArray;
    }

    // Determine the type of a field based on its value
    private function determineFieldType($value, int $currentType): int
    {
        if (preg_match('/[\x00-\x06\x08\x0B-\x0C\x0E-\x13\x16-\x1F]/', $value)) {
            return 6;  // LONGBLOB
        } elseif (strlen($value) > 255 || strpos($value, "\n") !== false) {
            return max($currentType, 5);  // LONGTEXT
        } elseif (!empty($value) && !is_numeric($value)) {
            return max($currentType, 4);  // VARCHAR
        } elseif (!preg_match('/^[+-]?\d{0,9}$/s', $value)) {
            return max($currentType, 3);  // DOUBLE
        } elseif (!preg_match('/^[+-]?\d{0,4}$/s', $value)) {
            return max($currentType, 2);  // INT
        } elseif (!preg_match('/^[+-]?\d{0,2}$/s', $value)) {
            return max($currentType, 1);  // SMALLINT
        }

        return $currentType;
    }

    // Backquote table/column names for safety
    private function _backquote(string $name): string
    {
        $name = strtr($name, chr(0) . "./`" . chr(255), " _-'y");
        return "`" . substr($name, 0, 64) . "`";
    }
}
?>
