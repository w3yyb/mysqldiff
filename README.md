phpmysqldiff - A PHP Console Tool
=========================

A mysql table diff tool written in php.

Requirements
------------

* MySQL
* PHP
* Pdo extension

Installation
------------
```
git clone https://github.com/w3yyb/mysqldiff.git
cd mysqldiff
vi config.php # config mysql connection info
```

Usage
-------------

```bash
	php mysqldiff.php
```
```open in browser
        http://localhost/mysqldiff/mysqldiff.php
````

Custom
--------
you would like to adjust some params to make it suited for your server.

* Config mysql connection
    ```php
    vi config.php

	return [
		"master" => [
			"host" => "127.0.0.1",
			"user" => "root",
			"pwd" => "root",
			"db" => "test",
		],
		"slave" => [
			"host" => "127.0.0.1",
			"user" => "root",
			"pwd" => "root",
			"db" => "test2",
		],
                "onlycheck" =>1 //只对比，不修复 
	];
    ```

Contributors
----------
- [exinnet](https://github.com/exinnet)
- [koiding](https://github.com/koiding)
- [liheng666](https://github.com/liheng666)
- [w3yyb](https://github.com/w3yyb)

Todo List
----------

- 自主选择需要新增的表
- 自主选择需要新增的列
- 调研master上是否可能有删除表/列的情况
- Web操作界面
- 执行结果生成报告(本地文件/邮件通知)
- 代码优化，错误，异常的处理，超时处理，单元测试

Discussing
----------
- [submit issue](https://github.com/exinnet/mysqldiff/issues/new)
