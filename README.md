# Jaypha MySQL Ext

Written by Jason den Dulk

A trait containing convenience functions to extend the functionality of the
`mysqli` class. These functions reflect very common database related tasks, and
can help reduce code overhead.

## Requirements

PHP v5 or greater.


## Installation

```
composer require jaypha/mysqli-ext
```

## MySqliExt

### As Trait

Must be added to a child of `mysqli`.

```
use MySQLiExtTrait;
```

### As Class

```
$conn = new Jaypha\MySQLiExt($host, $user, $password = NULL, $database = NULL)
```
## API

### `q($query)`

Same as mysqli::query, but throws an exception upon error.

### `mq($query)`

Same as mysqli::multi_query, but throws an exception upon error.

### `queryValue($query)`

Calls `q` with `$query` and returns a single value. The first value of
the first row.

Returns a string if a  value is found and not null, NULL if the value was
null, or false if no row was found

```
$n = $db->queryValue("select name from sometable where id='2'");  
echo "name is $n";
```

### `queryRow($query, $resultType = MYSQLI_ASSOC)`

Calls `q` with `$query` and returns the first row.

Returns an array if a row was found. If result type is MYSQLI_ASSOC, it will be
an associative array. Returns false if no row was found

```
$row = $db->queryRow("select * from sometable where id='2'");  
print_r($row);
```

### `queryData($query, $keyField = NULL, $resultType = MYSQLI_ASSOC)`

Calls `q` with `$query` and returns the whole data set. If `$keyField` is set,
then the values from this column are used as array keys.

Returns an array of rows. Each row is an array. If resultType is MYSQLI_ASSOC, it will be
an associative array.

If now rows were found, returns an empty array.


```
$data = $db->queryData("select * from sometable");  
foreach ($data as $row) { ... }
```

If a key is repeated, it will overwrite any existing value.

### `queryChunkedData($query, $limit = 1000, $resultType = MYSQLI_ASSOC)`

Returns a `MySQLiChunkedResult` instance.

### `queryColumn($query)`

Calls `q` with `$query` and returns a single column. If the SQL query selects
one field, then `queryColumn` returns an array containing the values. If the
query selects two or more, then an associative array is returned with the
contents of the first column as the keys and the contents of the second
columns as the values.

If no rows were found, returns an empty array.

If a key is repeated, it will overwrite any existing value.

```
$column = $db->queryColumn("select name from sometable");  
print_r($column);  
  
$assoc = $db->queryColumn("select id,name from sometable");  
print_r($assoc);
```


### `insert($tableName, $columns, $values = NULL)`

A shortcut for insert statements. There are three cases

- If `$values` is NULL, then `$columns` is assumed to be an associative array
 and it is inserted to the database using key/value pairs.
- If `$columns` is an array and `$values` is an array, then a single row is
  inserted using `$columns` and `$values`.
- If `$columns` is an array and `$values` is an array of arrays, then multiple
  rows are inserted. Each element of the `$values` array is considered a row.

Returns the insert ID value.

```
// insert into sometable set id=1, name='john'  
$id = $db->insert("sometable", [ "id" => 1, "name" => "john" ]);  
echo "new row ID is $id";

// insert into sometable (id,name) values (1,'john')"  
$db->insert("sometable", [ "id", "name"], [1, "john"]);

// insert into sometable (id,name) values (1,'john'), (2,'jane')  
$db->insert("sometable", [ "id", "name"], [[1, "john"], [2, "jane"]]);
```

### `update($tableName, $values, $wheres)`

A shortcut for update statements.

```
// update into sometable set name='john' where id=1  
$db->update("sometable", [ "name" => "john" ], [ "id" => 1 ]);
```

### `replace($tableName, $values)`

A shortcut for replace statements.

```
// replace sometable set name='john', id=1  
$db->replace("sometable", [ "name" => "john", "id" => 1 ]);
```

### `insertUpdate($tableName, $values, $wheres)`

Will either insert or update. If a row mathcing the given `$wheres` exists, then
it will be updated with the new `$values`. Otherwise a new row is inserted with
the `$values` and `$wheres` combined.

Use this instead of replace when you do not want to destory existing rows.

```
// update into sometable set name='john' where id=1 (if exists)  
// insert into sometable set name='john', id=1 (if not exists)  
$db->insertUpdate("sometable", [ "name" => "john" ], [ "id" => 1 ]);
```

### `delete($tableName, $wheres = null)`

Shortcut for delete. If `$where` is an integer, then it is matched to an `id`
column.

If `$wheres` is null, then it will truncate the table.

```
// delete from sometable where name='john'
$db->delete("sometable", [ "name" => "john" ]);

// delete from sometable where id=1
$db->delete("sometable", 1);
```

### `get($tableName, int $id)`

Selects a row where column 'id' is of value `$id`. A column called 'id' must exist.

```
$row = $db->get("sometable", id);  
assert(is_array($row));
```

### `set($tableName, $values, int $id = 0)`

Updates a table row where column 'id' is of value `id`. If no id is provided
(i.e. a value of zero), performs an insert instead. A column called 'id' must
exist.

Returns the row's id.

```
$db->set("sometable", [ "name" => "john" ], 1);  
$id = $db->set("sometable", [ "name" => "jane" ]);
```

### `quote($value)`

Escapes and quotes a values according to the following rules:
- a null value returns the string "null"
- a DateTimeInterface value returns a quoted string formatted as "Y-m-d H:i:s",
  compatible with all date and time fields.
- numbers are returned as numbers.
- strings containing numbers are returned as numbers unless they start with '0'.
- other strings are escaped and quoted.
- arrays are returned as a comma separated string of quoted values.
- arrays within arrays are bracketed.

## MySQLiChunkedResult

If, for whetever reason, you cannot have an open query, but you also
cannot load the whole query result into memory, then this class works
as a compromise. The result is stored into a temporary table, and then
read in chunks as separate queries. Implements `Iterator` so it can be
used with `foreach`.

### Usage

```
$mysql = new MySQLiExt(...);  
$result = new MySQLiChunkedResult($mysql, $query, $limit);  
foreach ($result as $row) { ... }  

$mysql = new MySQLiExt(...);  
$result = $mysql->queryChunkedData($query, $limit);
foreach ($result as $row) { ... }
```

## License

Copyright (C) 2017 Jaypha.  
Distributed under the Boost Software License, Version 1.0.  
See http://www.boost.org/LICENSE_1_0.txt

