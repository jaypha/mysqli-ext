<?php
//----------------------------------------------------------------------------
//  Unit tests for MySQLiChunkedResult class
//----------------------------------------------------------------------------

use PHPUnit\Framework\TestCase;
use Jaypha\MySQLiExt;
use Jaypha\MySQLiChunkedResult;

class MySQLiChunkedResultTest extends TestCase
{
  const tableName = "__TestTable__";
  const tableDef = "CREATE TABLE `".self::tableName."` (`id` int(11) NOT NULL AUTO_INCREMENT,  `name` varchar(255) NOT NULL DEFAULT '',  `age` int(11) DEFAULT NULL,  PRIMARY KEY (`id`)) ENGINE=MEMORY DEFAULT CHARSET=utf8mb4;";
  const tableData = [
        ['mandy',15],
        ['pandy',14],
        ['andy',16],
        ['randy',18],
        ['quandy',19],
        ['dandy',20],
        ['sandy',21],
        ['bandy',22],
        ['tandy',23],
        ['fandy',24],
      ];

  protected static $mysqli;

  public static function setUpBeforeClass()
  {
    self::$mysqli = new MySQLiExt
    (
      $GLOBALS["DB_HOST"],
      $GLOBALS["DB_USER"],
      $GLOBALS["DB_PASSWD"],
      $GLOBALS["DB_DBNAME"]
    );
  }

  function setUp()
  {
    self::$mysqli->query("drop table  if exists `".self::tableName."`");
    self::$mysqli->query(self::tableDef);
    self::$mysqli->insert(self::tableName, [ 'name', 'age' ], self::tableData);
  }

  function testForeach()
  {
    $result = new MySQLiChunkedResult(self::$mysqli, "select name,age from ".self::tableName, 4);
    $this->assertEquals(count($result), count(self::tableData));
    $i = 0;
    foreach ($result as $row)
    {
      $expected = [ "name" => self::tableData[$i][0], "age" => self::tableData[$i++][1] ];
      $this->assertEquals($row, $expected);
    }
  }

  function testManual()
  {
    $result = self::$mysqli->queryChunkedData("select name,age from ".self::tableName, 3);
    $this->assertInstanceOf(MySQLiChunkedResult::class, $result);
    $this->assertEquals(count($result), count(self::tableData));
    $result->rewind();
    $this->assertTrue($result->valid());
    $this->assertEquals($result->key(), 0);
    $this->assertEquals($result->current(), ["name" => "mandy", "age" => 15]);
    $result->next();
    $this->assertTrue($result->valid());
    $this->assertEquals($result->key(), 1);
    $this->assertEquals($result->current(), ["name" => "pandy", "age" => 14]);
    $result->next();
    $this->assertTrue($result->valid());
    $this->assertEquals($result->key(), 2);
    $this->assertEquals($result->current(), ["name" => "andy", "age" => 16]);
    $result->next();
    $this->assertTrue($result->valid());
    $this->assertEquals($result->key(), 3);
    $this->assertEquals($result->current(), ["name" => "randy", "age" => 18]);
    $result->next();
    $this->assertTrue($result->valid());
    $this->assertEquals($result->key(), 4);
    $this->assertEquals($result->current(), ["name" => "quandy", "age" => 19]);
    $result->rewind();
    $this->assertTrue($result->valid());
    $this->assertEquals($result->key(), 0);
    $this->assertEquals($result->current(), ["name" => "mandy", "age" => 15]);
    $result->next(); // 1
    $this->assertTrue($result->valid());
    $result->next(); // 2
    $this->assertTrue($result->valid());
    $result->next(); // 3
    $this->assertTrue($result->valid());
    $result->next(); // 4
    $this->assertTrue($result->valid());
    $result->next(); // 5
    $this->assertTrue($result->valid());
    $result->next(); // 6
    $this->assertTrue($result->valid());
    $result->next(); // 7
    $this->assertTrue($result->valid());
    $result->next(); // 8
    $this->assertTrue($result->valid());
    $result->next(); // 9
    $this->assertTrue($result->valid());
    $this->assertEquals($result->key(), 9);
    $this->assertEquals($result->current(), ["name" => "fandy", "age" => 24]);
    $result->next(); // 9
    $this->assertFalse($result->valid());
  }

  function testChunkedColumn()
  {
    $result = self::$mysqli->queryChunkedColumn("select name from ".self::tableName, 3);
    $this->assertEquals(count($result), count(self::tableData));
    $i = 0;
    foreach ($result as $row)
    {
      $expected = self::tableData[$i++][0];
      $this->assertEquals($row, $expected);
    }
  }

  public static function tearDownAfterClass()
  {
    self::$mysqli->query("drop table  if exists `".self::tableName."`");
    self::$mysqli->close();
  }
}

//----------------------------------------------------------------------------
// Copyright (C) 2018 Jaypha.
// License: BSL-1.0
// Author: Jason den Dulk
//
