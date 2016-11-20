<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="author" content="faddle">
	<title><?php echo (isset($page_title) and $page_title) ? $page_title : $title ;?></title>
	
	<style type="text/css">
	
	::selection { background-color: #E13300; color: white; }
	
	body {
		background-color: #fff;
		margin: 40px;
		font: 14px/22px normal Helvetica, Microsoft YaHei, Arial, sans-serif;
		color: #4F5155;
	}
	
	a {
		color: #003399;
		background-color: transparent;
		font-weight: normal;
	}
	a:hover {
		color: #002266;
	}
	
	h1 {
		color: #333;
		background-color: transparent;
		border-bottom: 1px solid #D0D0D0;
		font-size: 19px;
		font-weight: normal;
		margin: 0 0 14px 0;
		padding: 14px 15px 10px 15px;
	}
	
	p {
		margin: 0 0 10px;
	}
	
	code, pre {
		font-family: Consolas, Monaco, Courier New, Courier, monospace;
		font-size: 13px;
		background-color: #f9fafa;
	}
	pre {
		border: 1px solid #D0D0D0;
		color: #002166;
		margin: 14px 0 14px 0;
		padding: 12px 10px 12px 10px;
	}
	code {
		padding: 1px 2px;
		margin: 0 5px;
		color: #858080;
		border-radius: 3px;
		border: 1px solid #e4e4e4;
		font-size: 12px;
	}
	pre code {
		display: block;
		padding: 0;
		margin: 0;
		border: 0;
		white-space: pre;
		background-color: transparent;
	}
	
	#container {
		margin: 10px;
		border: 1px solid #D0D0D0;
		box-shadow: 0 0 8px #D0D0D0;
	}
	
	#body {
		margin: 0 15px 0 15px;
	}
	
	#footer {
		text-align: right;
		font-size: 12px;
		border-top: 1px solid #D0D0D0;
		line-height: 32px;
		padding: 0 10px 0 10px;
		margin: 20px 0 0 0;
	}
	
	.alert {
		padding: 15px;
		margin-bottom: 20px;
		border: 1px solid #d9caab;
		border-radius: 4px;
		background-color: #fcf8e3;
		color: #8a6d3b;
		line-height: 26px;
	}
	
	</style>
</head>
<body>

<div id="container">
	<h1><?php echo $title ;?></h1>
	
	<div id="body">
<?php echo $content ;?>
	</div>
	
	<div id="footer">Page rendered in <strong><?php echo sprintf('%.4f', floatval(microtime(true) - FADDLE_AT)) ;?></strong> seconds. 
		Faddle Version <strong><?php echo \Faddle\App::VERSION ;?></strong></div>
</div>

</body>
</html>