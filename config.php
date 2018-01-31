<?php
return [
	"master" => [
		"host" => "127.0.0.1",
		"port" => "3306",
		"user" => "root",
		"pwd" => "root",
		"db" => "test",
	],
	"slave" => [
		"host" => "127.0.0.1",
		"port" => "3306",
		"user" => "root",
		"pwd" => "root",
		"db" => "test2",
	],
	"onlycheck" =>1 //只对比，不修复	
];
