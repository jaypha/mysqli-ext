<?php
//----------------------------------------------------------------------------
// Unit tests for MySQLiExt class
//----------------------------------------------------------------------------

use PHPUnit\Framework\TestCase;
use Jaypha\MySQLiExt;

class MySQLiExtTest extends TestCase
{
  const tableName = "__TestTable__";
  const tableDef = "CREATE TABLE `".self::tableName."` (`id` int(11) NOT NULL AUTO_INCREMENT,  `name` varchar(255) NOT NULL DEFAULT '',  `age` int(11) DEFAULT NULL,  PRIMARY KEY (`id`)) ENGINE=MEMORY DEFAULT CHARSET=utf8mb4;";

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
    self::$mysqli->q("drop table  if exists `".self::tableName."`");
    self::$mysqli->q(self::tableDef);
  }

  function testQuoting()
  {
    $this->assertEquals(self::$mysqli->quote(true), "1");
    $this->assertEquals(self::$mysqli->quote(23), "23");
    $this->assertEquals(self::$mysqli->quote(null), "null");
    $this->assertEquals(self::$mysqli->quote("redd"), "'redd'");
    $this->assertEquals(self::$mysqli->quote("103"), "103");
    $this->assertEquals(self::$mysqli->quote("0419675374"), "'0419675374'");
    $this->assertEquals(self::$mysqli->quote([23,16,"rty",null]),"23,16,'rty',null");
    $this->assertEquals(self::$mysqli->quote([[56,"lkj"],["qweet","oplds"],15]),"(56,'lkj'),('qweet','oplds'),15");
  }

  function testTableExist()
  {
    $this->assertFalse(self::$mysqli->tableExists("xrtcgyth"));
    $this->assertTrue(self::$mysqli->tableExists(self::tableName));
    $this->assertTrue(self::$mysqli->tableExists($GLOBALS["DB_DBNAME"].".".self::tableName));
    $data = self::$mysqli->queryValue("show tables like '".self::tableName."'");
    $this->assertEquals($data,self::tableName);
  }

  function testTableEmpty()
  {
    $data = self::$mysqli->queryData("select * from ".self::tableName);
    $this->assertInternalType("array", $data);
    $this->assertCount(0,$data);
  }

  function test1DInsert()
  {
    $id = self::$mysqli->insert(self::tableName, ['name', 'age'], ['blue', 36 ]);
    $this->assertEquals($id, 1);
    $row = self::$mysqli->get(self::tableName, $id);
    $this->assertInternalType("array", $row);
    $this->assertEquals($row, [ "id" => "1", "name" => "blue", "age" => "36"]);
  }

  function testAssocInsert()
  {
    $id = self::$mysqli->insert(self::tableName, [ "name" => 'john', "age" => 13 ]);
    $row = self::$mysqli->get(self::tableName, $id);
    $this->assertInternalType("array", $row);
    $this->assertEquals($row, [ "id" => "1", "name" => "john", "age" => "13"]);
  }

  function test2DInsert()
  {
    self::$mysqli->insert(self::tableName, [ 'name', 'age' ], [['mandy',15],['andy',16],['randy',17]]);
    $rows = self::$mysqli->queryData("select * from ".self::tableName);
    $this->assertInternalType("array", $rows);
    $this->assertContainsOnly("array", $rows);
    $this->assertCount(3,$rows);
    $this->assertEquals($rows, [
      [ "id" => "1", "name" => "mandy", "age" => "15"],
      [ "id" => "2", "name" => "andy",  "age" => "16"],
      [ "id" => "3", "name" => "randy", "age" => "17"],
    ]);
  }

  function testQueryValue()
  {
    self::$mysqli->insert(self::tableName, [ 'name', 'age' ], [
      ['mandy',15],
      ['andy',16],
      ['randy',17],
      ['john',27],
      ["xy", 44],
      ["z", 0]
    ]);
    $this->assertEquals(self::$mysqli->queryValue("select id from ".self::tableName." where name='john'"),4);
    $this->assertEquals(self::$mysqli->queryValue("select age from ".self::tableName." where name='xy'"), 44);
    $v = self::$mysqli->queryValue("select age from ".self::tableName." where name='non'");
    $this->assertFalse($v);
    $this->assertInternalType('boolean',$v);
    $this->assertNotFalse(self::$mysqli->queryValue("select age from ".self::tableName." where name='z'"));
    $this->assertEquals(self::$mysqli->queryValue("select name from ".self::tableName." where id=1"), "mandy");
  }

  function testQueryRow()
  {
    self::$mysqli->insert(self::tableName, [ 'name', 'age' ], [
      ['mandy',15],
      ['andy',16],
      ['randy',17],
      ['john',27],
      ["john", 44],
      ["z", 0]
    ]);

    $row = self::$mysqli->queryRow("select name, age from ".self::tableName." where name='john'");
    $this->assertInternalType("array", $row);
    $this->assertEquals($row, [ "name" => "john", "age" => "27" ]);
  }

  function testQueryData()
  {
    self::$mysqli->insert(self::tableName, [ 'name', 'age' ], [
      ['mandy',15],
      ['andy',15],
      ['randy',16],
      ['john',16],
      ["john", 17],
    ]);
    $rows = self::$mysqli->queryData("select * from ".self::tableName);
    $this->assertInternalType("array", $rows);
    $this->assertContainsOnly("array", $rows);
    $this->assertEquals($rows, [
      [ "id" => "1", "name" => "mandy", "age" => "15"],
      [ "id" => "2", "name" => "andy",  "age" => "15"],
      [ "id" => "3", "name" => "randy", "age" => "16"],
      [ "id" => "4", "name" => "john", "age" => "16"],
      [ "id" => "5", "name" => "john", "age" => "17"],
    ]);
    $rows = self::$mysqli->queryData("select name,age from ".self::tableName." where age=16");
    $this->assertInternalType("array", $rows);
    $this->assertEquals($rows, [
      [ "name" => "randy", "age" => "16"],
      [ "name" => "john", "age" => "16"],
    ]);
    $rows = self::$mysqli->queryData("select * from ".self::tableName." where name='bob'");
    $this->assertInternalType("array", $rows);
    $this->assertCount(0,$rows);
  }    

  function testQueryColumn()
  {
    self::$mysqli->insert(self::tableName, [ 'name', 'age' ], [
      ['mandy',15],
      ['andy',16],
      ['randy',17],
      ['john',27],
      ["z", 0],
      ["john", 44]
    ]);
    $row = self::$mysqli->queryColumn("select name from ".self::tableName);
    $this->assertEquals($row, [ "mandy", "andy", "randy", "john", "z", "john"]);
    $row = self::$mysqli->queryColumn("select id,age from ".self::tableName." where name='john'");
    $this->assertEquals($row, [ "4" => 27, "6" => 44]);
  }

  function testUpdate()
  {
    self::$mysqli->insert(self::tableName, [ 'name', 'age' ], [
      ['mandy',15],
      ['andy',16],
      ['randy',17],
      ['john',27],
      ["z", 0],
      ["john", 44]
    ]);
    $rows = self::$mysqli->queryData("select * from ".self::tableName);
    $this->assertEquals($rows, [
      [ "id" => "1", "name" => "mandy", "age" => "15"],
      [ "id" => "2", "name" => "andy",  "age" => "16"],
      [ "id" => "3", "name" => "randy", "age" => "17"],
      [ "id" => "4", "name" => "john", "age" => "27"],
      [ "id" => "5", "name" => "z", "age" => "0"],
      [ "id" => "6", "name" => "john", "age" => "44"],
    ]);
    self::$mysqli->update(self::tableName, [ "name" => "bob" ], [ "age" => 15, "id" => 14 ]);
    $this->assertEquals(self::$mysqli->affected_rows, 0);
    self::$mysqli->update(self::tableName, [ "name" => "bob" ], [ "age" => 0 ]);
    $this->assertEquals(self::$mysqli->affected_rows, 1);
    self::$mysqli->update(self::tableName, [ "age" => "12", "name" => "jill" ], [ "name" => "john" ]);
    $this->assertEquals(self::$mysqli->affected_rows, 2);
    $rows = self::$mysqli->queryData("select * from ".self::tableName);
    $this->assertEquals($rows, [
      [ "id" => "1", "name" => "mandy", "age" => "15"],
      [ "id" => "2", "name" => "andy",  "age" => "16"],
      [ "id" => "3", "name" => "randy", "age" => "17"],
      [ "id" => "4", "name" => "jill", "age" => "12"],
      [ "id" => "5", "name" => "bob", "age" => "0"],
      [ "id" => "6", "name" => "jill", "age" => "12"],
    ]);
  }

  function testInsertUpdate()
  {
    self::$mysqli->insert(self::tableName, [ 'name', 'age' ], [
      ['mandy',15],
      ['andy',16],
      ['randy',17],
      ['john',27],
      ["z", 0],
      ["john", 44]
    ]);
    self::$mysqli->insertUpdate(self::tableName, [ "name" => "bob" ], [ "age" => 22 ]);
    $this->assertEquals(self::$mysqli->affected_rows, 1);
    $rows = self::$mysqli->queryData("select * from ".self::tableName);
    $this->assertEquals($rows, [
      [ "id" => "1", "name" => "mandy", "age" => "15"],
      [ "id" => "2", "name" => "andy",  "age" => "16"],
      [ "id" => "3", "name" => "randy", "age" => "17"],
      [ "id" => "4", "name" => "john", "age" => "27"],
      [ "id" => "5", "name" => "z", "age" => "0"],
      [ "id" => "6", "name" => "john", "age" => "44"],
      [ "id" => "7", "name" => "bob", "age" => "22"],
    ]);
    self::$mysqli->insertUpdate(self::tableName, [ "name" => "bob" ], [ "age" => 16 ]);
    $this->assertEquals(self::$mysqli->affected_rows, 1);
    $rows = self::$mysqli->queryData("select * from ".self::tableName);
    $this->assertEquals($rows, [
      [ "id" => "1", "name" => "mandy", "age" => "15"],
      [ "id" => "2", "name" => "bob",  "age" => "16"],
      [ "id" => "3", "name" => "randy", "age" => "17"],
      [ "id" => "4", "name" => "john", "age" => "27"],
      [ "id" => "5", "name" => "z", "age" => "0"],
      [ "id" => "6", "name" => "john", "age" => "44"],
      [ "id" => "7", "name" => "bob", "age" => "22"],
    ]);
  }

  function testReplace()
  {
    self::$mysqli->insert(self::tableName, [ 'name', 'age' ], [
      ['mandy',15],
      ['andy',16],
      ['randy',17],
      ['john',27],
      ["z", 0],
      ["john", 44]
    ]);
    self::$mysqli->replace(self::tableName, [ "name" => "bob", "id"=>3 ]);
    // 2 rows are affected because one is removed and another is inserted.
    $this->assertEquals(self::$mysqli->affected_rows, 2);
    $rows = self::$mysqli->queryData("select * from ".self::tableName);
    $this->assertEquals($rows, [
      [ "id" => "1", "name" => "mandy", "age" => "15"],
      [ "id" => "2", "name" => "andy",  "age" => "16"],
      [ "id" => "3", "name" => "bob", "age" => null],
      [ "id" => "4", "name" => "john", "age" => "27"],
      [ "id" => "5", "name" => "z", "age" => "0"],
      [ "id" => "6", "name" => "john", "age" => "44"],
    ]);
  }

  function testGetAndSet()
  {
    $id = self::$mysqli->set(self::tableName, [ "name" => 'will' ], 4);
    $this->assertEquals($id, 4);
    $row = self::$mysqli->get(self::tableName, $id);
    $this->assertEquals($row, [ "id" => $id, "name" => "will", "age" => null ]);
    $id = self::$mysqli->set(self::tableName, [ "name" => 'mill', "age" => 12 ]);
    $this->assertEquals($id, 5);
    $row = self::$mysqli->get(self::tableName, $id);
    $this->assertEquals($row, [ "id" => $id, "name" => "mill", "age" => 12 ]);
    $id = self::$mysqli->set(self::tableName, [ "age" => '14' ], 4);
    $this->assertEquals($id, 4);
    $row = self::$mysqli->get(self::tableName, $id);
    $this->assertEquals($row, [ "id" => $id, "name" => "will", "age" => 14 ]);
  }

  function testDelete()
  {
    self::$mysqli->insert(self::tableName, [ 'name', 'age' ], [
      ['mandy',15],
      ['mandy',14],
      ['andy',16],
      ['randy',18],
      ['randy',19],
      ['randy',20],
    ]);
    $rows = self::$mysqli->queryData("select * from ".self::tableName);
    $this->assertEquals($rows, [
      [ "id" => "1", "name" => "mandy", "age" => "15"],
      [ "id" => "2", "name" => "mandy", "age" => "14"],
      [ "id" => "3", "name" => "andy",  "age" => "16"],
      [ "id" => "4", "name" => "randy", "age" => "18"],
      [ "id" => "5", "name" => "randy", "age" => "19"],
      [ "id" => "6", "name" => "randy", "age" => "20"],
    ]);
    self::$mysqli->delete(self::tableName, 3);
    $rows = self::$mysqli->queryData("select * from ".self::tableName);
    $this->assertEquals($rows, [
      [ "id" => "1", "name" => "mandy", "age" => "15"],
      [ "id" => "2", "name" => "mandy", "age" => "14"],
      [ "id" => "4", "name" => "randy", "age" => "18"],
      [ "id" => "5", "name" => "randy", "age" => "19"],
      [ "id" => "6", "name" => "randy", "age" => "20"],
    ]);
    self::$mysqli->delete(self::tableName, ["name" => "bob", "age" => 20 ]);
    $rows = self::$mysqli->queryData("select * from ".self::tableName);
    $this->assertEquals($rows, [
      [ "id" => "1", "name" => "mandy", "age" => "15"],
      [ "id" => "2", "name" => "mandy", "age" => "14"],
      [ "id" => "4", "name" => "randy", "age" => "18"],
      [ "id" => "5", "name" => "randy", "age" => "19"],
      [ "id" => "6", "name" => "randy", "age" => "20"],
    ]);
    self::$mysqli->delete(self::tableName, ["name" => "randy", "age" => 20 ]);
    $rows = self::$mysqli->queryData("select * from ".self::tableName);
    $this->assertEquals($rows, [
      [ "id" => "1", "name" => "mandy", "age" => "15"],
      [ "id" => "2", "name" => "mandy", "age" => "14"],
      [ "id" => "4", "name" => "randy", "age" => "18"],
      [ "id" => "5", "name" => "randy", "age" => "19"],
    ]);
    self::$mysqli->delete(self::tableName, ["name" => "mandy" ]);
    $rows = self::$mysqli->queryData("select * from ".self::tableName);
    $this->assertEquals($rows, [
      [ "id" => "4", "name" => "randy", "age" => "18"],
      [ "id" => "5", "name" => "randy", "age" => "19"],
    ]);
  }

  function testBadQuery()
  {
    $this->expectException(\LogicException::class);
    self::$mysqli->q("select class from ".self::tableName);
  }

  public static function tearDownAfterClass()
  {
    self::$mysqli->q("drop table  if exists `".self::tableName."`");
    self::$mysqli->close();
  }
}

//----------------------------------------------------------------------------
// Copyright (C) 2018 Jaypha.
// License: BSL-1.0
// Author: Jason den Dulk
//
