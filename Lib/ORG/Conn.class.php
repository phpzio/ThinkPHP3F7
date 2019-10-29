<?php
class conn{
	private $host;
	private $name;
	private $pass;
	private $table;
	private $ut;
	public function __construct($host,$name,$pass,$table,$ut){
		$this->host=$host;
		$this->name=$name;
		$this->pass=$pass;
		$this->table=$table;
		$this->ut=$ut;
	}
	
	public function connect(){
      $link=mysql_connect($this->host,$this->name,$this->pass) or die ($this->error());
      mysql_select_db($this->table,$link) or die("没该数据库：".$this->table);
      mysql_query("SET NAMES '$this->ut'");
	}
	public function new_database()
	{
		$link=mysql_connect($this->host,$this->name,$this->pass) or die ($this->error());
		if (mysql_query('CREATE DATABASE '.$this->table,$link))
		{
			echo "数据库创建成功 ";
		}
		else
		{
			echo "数据库创建失败: " . mysql_error();
			exit;
		}
		mysql_close($link);
	}
	public function import_database($filename)
	{
		$templine = '';
		$lines = file($filename);
		foreach ($lines as $line)
		{
			if (substr($line, 0, 2) == '--' || $line == '')
			continue;
			$templine .= $line;
			if (substr(trim($line), -1, 1) == ';')
			{
				mysql_query($templine) or print('Error performing query \'<strong>' . $templine . '\': ' . mysql_error() . '<br /><br />');
				$templine = '';
			}
		}
		echo "表导入成功";
	}
}
?>