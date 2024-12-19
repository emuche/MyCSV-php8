<?php

if (!defined('SORT_LOCALE_STRING')) {
    define('SORT_LOCALE_STRING', 5);
}

if (!defined('SORT_NAT')) {
    define('SORT_NAT', 16);
}

if (!defined('SORT_TIME')) {
    define('SORT_TIME', 17);
}

if (!defined('SORT_NULL')) {
    define('SORT_NULL', 32);
}

class MyCSV
{
    public $fields = ["id"];
    public $data = [];
    public $delimiter = ",";
    public $insert_id = null;
    public $filename = "";
    private $_fp = false;
    private $_limitRows = null;
    private array $_cmpFields = [];   // Sorting fields

    public function __construct($tablename = "", $length = 10000)
    {
        if ($tablename) {
            $this->read($tablename, $length);
        }
    }

    public function read($tablename, $length = 10000)
    {
        $this->filename = $tablename;
        if (!preg_match('/\.\w+$/', $this->filename)) {
            $this->filename .= ".csv";
        }

        if (!file_exists($this->filename) && strpos($this->filename, "://") === false) {
            return false;
        }

        if (!empty($GLOBALS['_MyCSV_locked'][$this->filename])) {
            throw new Exception("MyCSV::read() failed, file {$this->filename} is open already");
        }

        $GLOBALS['_MyCSV_locked'][$this->filename] = true;

        $this->_fp = fopen($this->filename, "r+b");
        if (!$this->_fp) {
            $this->_fp = fopen($this->filename, "rb");
        }

        if (!$this->_fp) {
            return false;
        }

        if (strpos($this->filename, "://") === false) {
            flock($this->_fp, LOCK_EX);
        }

        $this->fields = fgetcsv($this->_fp, $length, $this->delimiter);
        $delimiters = str_replace($this->delimiter, "", ",;\t\0|&: ") . $this->delimiter;

        while (count($this->fields) < 2) {
            $this->delimiter = $delimiters[0];
            if (!$delimiters = substr($delimiters, 1)) break;
            rewind($this->_fp);
            $this->fields = fgetcsv($this->_fp, $length, $this->delimiter);
        }

        $lastId = 0;
        $fieldsCount = count($this->fields);
        $i = array_search("id", $this->fields);

        while ($row = fgetcsv($this->_fp, $length, $this->delimiter)) {
            $id = isset($row[$i]) ? $row[$i] : $lastId + 1;
            $lastId = max($id, $lastId);
            $count = min($fieldsCount, count($row));

            for ($c = 0; $c < $count; ++$c) {
                $row[$c] = strtr($row[$c], [
                    "\\\x7F" => "\x00",
                    "\\\x93" => '"',
                    '\\\\'   => '\\'
                ]);
                $this->data[$id][$this->fields[$c]] = $row[$c];
            }
        }

        array_unshift($this->fields, "id");
        return true;
    }

    public function add_field($field, $afterField = null): bool
    {
        if (!preg_match('/^[\w\x7F-\xFF]+$/is', $field) || in_array($field, $this->fields)) {
            return false;
        }

        if (isset($afterField) && in_array($afterField, $this->fields)) {
            $newFields = [];
            foreach ($this->fields as $oldField) {
                $newFields[] = $oldField;
                if (strcasecmp($oldField, $afterField) == 0) {
                    $newFields[] = $field;
                }
            }
            $this->fields = $newFields;
        } else {
            $this->fields[] = $field;
        }

        return true;
    }

    public function data_seek($row_number): bool
    {
        return $this->seek($row_number, SEEK_SET);
    }

    public function delete($id = null): void
    {
        if (is_array($id) && isset($id['id'])) {
            $id = $id['id'];
        }
        if (isset($id)) {
            if (!is_array($id)) unset($this->data[$id]);
        } else {
            $this->data = [];
            ++$this->insert_id;
        }
    }

    public function drop_field($field): bool
    {
        if (is_array($field) || strcasecmp($field, "id") == 0) return false;

        $offset = array_search($field, $this->fields);
        if ($offset === false || $offset === null) return false;

        array_splice($this->fields, $offset, 1);
        foreach ($this->data as $id => $row) {
            unset($this->data[$id][$field]);
        }

        return true;
    }

    public function drop_table(): void
    {
        $this->fields = ["id"];
        $this->data = [];
        $this->insert_id = null;
    }

    public function fetch_assoc()
    {
        return $this->each();
    }

    public function insert(array $data): bool
    {
        if (isset($data['id']) && strlen($data['id'])) {
            $this->insert_id = $data['id'];
        } elseif (!isset($this->insert_id) && empty($this->data)) {
            $this->insert_id = 1;
        }

        if (isset($this->data[$this->insert_id])) {
            $this->insert_id += 1;
        }

        if (!isset($this->insert_id) || isset($this->data[$this->insert_id])) {
            $this->insert_id = max(array_keys($this->data)) + 1;
        }

        $this->data[$this->insert_id] = $data;
        if (empty($this->fields) || count($this->fields) < 2) {
            unset($data['id']);
            $this->fields = array_merge(["id"], array_keys($data));
        }

        return true;
    }

    public function insert_id()
    {
        return isset($this->insert_id) ? $this->insert_id : false;
    }

    public function join(&$rightTable, $foreignKey): void
    {
        if (is_array($rightTable)) {
            $rightData = $rightTable;
        } else {
            $rightData = $rightTable->data;
            $prefix = preg_replace('/\.\w+$/', '', basename($rightTable->filename));
        }

        foreach ($this->data as $id => $row) {
            $fid = (strcasecmp($foreignKey, "id") == 0) ? $id : $row[$foreignKey];
            if (isset($rightData[$fid])) {
                if (!empty($prefix) && !isset($rightData[$fid][$prefix . ".id"])) {
                    foreach ($rightData[$fid] as $field => $value) {
                        $rightData[$fid][$prefix . "." . $field] = &$rightData[$fid][$field];
                    }
                }
                $this->data[$id] += $rightData[$fid];
            }
        }
    }

    public function limit($rows = null, $id = null, $whence = null)
    {
        $this->_limitRows = $rows > 0 ? $rows : null;
        return isset($id) ? $this->seek($id, $whence) : $this->reset();
    }

      // Add a new field (column)
      public function addField(string $field, ?string $afterField = null): bool
      {
          if (!preg_match('/^[\w\x7F-\xFF]+$/is', $field) || in_array($field, $this->fields)) {
              return false;
          }
  
          if ($afterField !== null && in_array($afterField, $this->fields)) {
              $newFields = [];
              foreach ($this->fields as $oldField) {
                  $newFields[] = $oldField;
                  if (strcasecmp($oldField, $afterField) === 0) {
                      $newFields[] = $field;
                  }
              }
              $this->fields = $newFields;
          } else {
              $this->fields[] = $field;
          }
  
          return true;
      }
  
      // Get the number of rows
      public function numRows(): int
      {
          return count($this->data);
      }
  
      // Get the table name (file name without path and extension)
      public function tablename(): string
      {
          return preg_replace('{^\./|\.csv$}', '', $this->filename);
      }
  
      // Update a row with new data
      public function update(array $data, ?int $id = null): bool
      {
          if (!isset($data['id']) && !$id) {
              return false;
          }
  
          $data['id'] = $data['id'] ?? $id;
          if ($data['id'] !== $id && isset($this->data[$data['id']])) {
              return false;
          }
  
          $this->data[$data['id']] = $data + $this->data[$id];
          if ($data['id'] !== $id) {
              unset($this->data[$id]);
          }
  
          return true;
      }
  
      // Count the rows in the data
      public function count(): int
      {
          return count($this->data);
      }
  
      // Iterate over the rows
      public function each(): ?array
      {
          if ($this->_limitRows !== null && --$this->_limitRows < 0) {
              return null;
          }
  
          $row = current($this->data);
          if ($row === false) {
              return null;
          }
  
          $id = key($this->data);
          next($this->data);
  
          return ['id' => $id] + $row;
      }

      public function end(): ?array
      {
          return end($this->data) ?: null; // Return the last element or null if empty
      }
  
      public function idExists(int $id): bool
      {
          return isset($this->data[$id]);
      }
  
      public function ids(): array
      {
          return array_keys($this->data); // Return all IDs
      }
  
      public function ksort(int $sort_flags = 0): bool
      {
          return ksort($this->data, $sort_flags); // Sort by keys
      }
  
      public function krsort(int $sort_flags = 0): bool
      {
          return krsort($this->data, $sort_flags); // Reverse sort by keys
      }
  
      public function min(): ?int
      {
          return $this->data ? min(array_keys($this->data)) : null; // Return the minimum key
      }
  
      public function max(): ?int
      {
          return $this->data ? max(array_keys($this->data)) : null; // Return the maximum key
      }
  
       // Function to get the first id
    public function first(): ?int
    {
        return $this->data ? (int)array_key_first($this->data) : null; // Return the first ID or null if no data
    }

    // Function to get the last id
    public function last(): ?int
    {
        return $this->data ? (int)array_key_last($this->data) : null; // Return the last ID or null if no data
    }

    // Function to get the previous ID based on current ID
    public function prev(int $id, int $offset = 1): ?int
    {
        return $this->next($id, -$offset); // Negative offset for previous ID
    }

    // Function to get the next ID based on current ID
    public function next(int $id, int $offset = 1): ?int
    {
        $ids = array_keys($this->data);
        $i = array_search($id, $ids) + $offset;
        return isset($ids[$i]) ? (int)$ids[$i] : null; // Return the next ID or null if out of bounds
    }
     // Function to get a random row(s) from the data
     public function rand(int $num_req = 1): mixed
     {
         return empty($this->data) ? false : array_rand($this->data, $num_req);
     }
 
     // Function to reset and get the first row in the data
     public function reset(): mixed
     {
         return reset($this->data);
     }
 
     // Function to check if a row exists based on search criteria
     public function row_exists(array $search): bool
     {
         foreach ($this->data as $id => $row) {
             foreach ($search as $key => $value) {
                 if (!isset($row[$key]) || $row[$key] !== $value) {
                     continue 2;  // Skip to next row if criteria do not match
                 }
             }
             return true; // Row found
         }
 
         return false; // Row not found
     }

      // Function to sort data based on the provided sort flags
    public function sort($sort_flags): void
    {
        // Parse sort flags
        if (func_num_args() > 1) {
            $sort_flags = func_get_args();
        } else {
            $sort_flags = preg_split('/[,\s]+/s', trim($sort_flags));
        }

        // Prepare sorting fields
        $this->_cmpFields = [];
        $p = -1;

        foreach ($sort_flags as $f) {
            // Normalize sort flags
            $f = preg_replace('/^(A|DE)SC$/i', 'SORT_\0', $f);
            if (defined(strtoupper($f))) {
                $f = constant(strtoupper($f));
            }

            // Configure sorting order and type
            if ($f == SORT_ASC) {
                continue;
            } elseif ($f == SORT_DESC) {
                $this->_cmpFields[$p]['order'] = -1;
            } elseif (is_int($f)) {
                $this->_cmpFields[$p]['type'] |= $f;
            } else {
                ++$p;
                $this->_cmpFields[] = ['field' => $f, 'order' => 1, 'type' => 0];
            }
        }

        // Sort the data based on the 'id' or other fields
        if (strcasecmp($this->_cmpFields[0]['field'], 'id') == 0) {
            if ($this->_cmpFields[0]['order'] > 0) {
                ksort($this->data);
            } else {
                krsort($this->data);
            }
        } else {
            uasort($this->data, [$this, '_cmp']);
        }

        // Reset pointer after sorting
        reset($this->data);
    }

    // Comparison function used for uasort
    private function _cmp(array $a, array $b): int
    {
        foreach ($this->_cmpFields as $f) {
            if ($f['type'] & SORT_NULL) {
                if (strlen($a[$f['field']]) <= 0 || strlen($b[$f['field']]) <= 0) {
                    $f['order'] = -1;
                }
            }

            switch ($f['type'] & ~SORT_NULL) {
                case SORT_NUMERIC:
                    $result = ($a[$f['field']] - $b[$f['field']]) * $f['order'];
                    break;
                case SORT_STRING:
                    $result = strcasecmp($a[$f['field']], $b[$f['field']]) * $f['order'];
                    break;
                case SORT_LOCALE_STRING:
                    $result = strcoll(strtolower($a[$f['field']]), strtolower($b[$f['field']])) * $f['order'];
                    break;
                case SORT_NAT:
                    $result = strnatcasecmp($a[$f['field']], $b[$f['field']]) * $f['order'];
                    break;
                case SORT_TIME:
                    $result = (strtotime($a[$f['field']]) - strtotime($b[$f['field']])) * $f['order'];
                    break;
                default:
                    $result = ($a[$f['field']] == $b[$f['field']]) ? 0 :
                        ($a[$f['field']] > $b[$f['field']] ? $f['order'] : -$f['order']);
                    break;
            }

            if ($result != 0) {
                return $result;
            }
        }

        return 0; // If all comparisons are equal, return 0
    }

    
    // Retrieve data for a given ID
    public function data(int $id): array|false
    {
        return isset($this->data[$id]) ? ['id' => $id] + $this->data[$id] : false;
    }

    // Dump the entire CSV data to output
    public function dump(): void
    {
        echo $this->export();
    }

    // Check if the CSV file exists
    public function exists(): bool
    {
        return file_exists($this->filename);
    }

    // Export data to CSV format
    public function export(): string
    {
        $countFields = count($this->fields);
        $trFrom = ['"', "\x00"];
        $trTo = ['""', "\\\x7F"];

        $csv = implode($this->delimiter, $this->fields) . "\r\n";

        // Loop through each row in data
        foreach ($this->data as $id => $row) {
            $csv .= $this->sanitizeField($id);
            for ($c = 1; $c < $countFields; ++$c) {
                $csv .= $this->delimiter;
                $d = @$row[$this->fields[$c]];
                if (strlen($d)) {
                    $d = preg_replace('/\\\(?=\\\|\x00|"|\x7F|\x93|$)/s', '\\\\\0', $d);
                    $d = preg_replace('/(^"|"$)/s', "\\\x93", $d);
                    $csv .= '"' . str_replace($trFrom, $trTo, $d) . '"';
                }
            }
            $csv .= "\r\n";
        }

        return $csv;
    }


    // Helper function to sanitize a field (e.g., escaping quotes)
    private function sanitizeField(string $field): string
    {
        if (strpos($field, $this->delimiter) === false && strpos($field, '"') === false) {
            return $field;
        } else {
            return '"' . str_replace('"', '""', $field) . '"';
        }
    }

    public function id_exists(int $id): bool
    {
        return isset($this->data[$id]);
    }
        // Check if the file is writable
        public function isWritable(): bool
        {
            return is_writable($this->filename);
        }
    
        // Seek through the data (move the pointer)
        public function seek(int $id = 0, int $whence = SEEK_SET): bool
        {
            if ($whence === SEEK_SET) {
                $id = array_search($id, array_keys($this->data), true);
            } elseif ($whence === SEEK_END) {
                $id = count($this->data) - 1 - abs($id);
            }
    
            if ($whence !== SEEK_CUR) {
                reset($this->data);
            }
    
            // Move the pointer to the desired position
            for ($i = 0; $i < $id; ++$i) {
                if (!next($this->data)) {
                    return false; // Failed to move to the desired position
                }
            }
            return true; // Successfully moved to the desired position
        }
    
          // Write function with type hints and improved file handling
    public function write(string $tablename = "", string $delimiter = ""): bool
    {
        if ($tablename && !preg_match('/\.\w+$/', $tablename)) {
            $tablename .= ".csv";
        }

        if ($tablename && $tablename !== $this->filename) {
            $this->close();
            $this->filename = $tablename;
        }

        if (!$this->filename) {
            return false;
        }

        if (!$this->_fp) {
            $this->_fp = fopen($this->filename, "wb");
            if (!$this->_fp) {
                return false;
            }
            flock($this->_fp, LOCK_EX);
        }

        if ($delimiter) {
            $this->delimiter = $delimiter;
        }

        rewind($this->_fp);
        if (!fwrite($this->_fp, $this->export())) {
            user_error("MyCSV::write() failed, file $this->filename seems to be read only", E_USER_WARNING);
            return false;
        }

        ftruncate($this->_fp, ftell($this->_fp));
        $this->close();

        if (count($this->fields) <= 1 && empty($this->data)) {
            unlink($this->filename);
        }

        return true;
    }

    // Close file handle and release lock
    public function close(): void
    {
        if ($this->_fp) {
            fflush($this->_fp);
            flock($this->_fp, LOCK_UN);
            fclose($this->_fp);
            $this->_fp = false;
            if (isset($GLOBALS['_MyCSV_locked'][$this->filename])) {
                unset($GLOBALS['_MyCSV_locked'][$this->filename]);
            }
        }
    }
    
      

}
