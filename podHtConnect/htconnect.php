<?php

/**
 * @author Jo�o Lagarto / Nuno Moreno
 * @version Datumo 2.0
 * @copyright EUPL
 * @abstract Class to handle DB connections
 */

class dbConnection extends PDO{
	
	private $engine;
	private $host;
	private $database;
	private $username;
	private $password;
	private $dsn;
	private $schema;
		
	public function __construct(){
		$this->engine = "pgsql"; //"mysql" OR "pgsql"
		$this->host = "localhost";
		$this->database = "requisitions";
		$this->username = "postgres"; //"root" OR "postgres"
		$this->password = "nasaki"; // "" OR "nasaki"
		$this->dsn = $this->engine.":dbname=".$this->database.";host=".$this->host;
		try {
			//database connection
			parent::__construct($this->dsn, $this->username, $this->password);
			//PDO error handling
			parent::setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		} catch (PDOException $e) {
			echo $e->getMessage();
			exit();
		}
	}
	
	public function getEngine(){ return $this->engine;}
	public function getDatabase(){ return $this->database;}
	public function getSchema(){ return $this->schema;}
	
/**
 * @author Jo�o Lagarto / Nuno Moreno
 * @version Datumo 2.0
 * @copyright EUPL
 * @abstract method to return original database
 */
	
	public function dbConn(){
		$this->schemaSelect($this->engine, $this->getDatabase());
		$sql = parent::prepare($this->schema);
		$sql->execute();
	}
	
/**
 * @author Jo�o Lagarto / Nuno Moreno
 * @version Datumo 2.0
 * @copyright EUPL
 * @abstract method to select information schema
 */
	
	public function dbInfo(){
		$this->schemaSelect($this->engine, "information_schema");
		$sql = parent::prepare($this->schema);
		$sql->execute();
}
	
/**
 * @author Jo�o Lagarto / Nuno Moreno
 * @version Datumo 2.0
 * @copyright EUPL
 * @abstract method to handle different database engines
 */
	
	public function schemaSelect($engine, $db){ 
		switch($engine){
			case "mysql": //query to change database in mysql
				$this->schema = "use ".$db;
				break;
			case "pgsql"; //query to change database in postgresql
				$this->schema = "set search_path to ".$db.",public";
				break;
		}
	}

  	
}

?>