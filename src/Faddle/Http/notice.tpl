<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">
<title><?php echo isset($page_title) ? $page_title : $title ;?></title>
<style type="text/css" media="screen">
body { background-color: #f1f1f1; margin: 0; font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; }
.container { margin: 60px auto 40px auto; width: 600px; text-align: center; }
a { color: #4183c4; text-decoration: none; }
a:hover { text-decoration: underline; }
h1 { position:relative; letter-spacing: -1px; line-height: 60px; font-size: 60px; font-weight: 100; margin: 0px 0 50px 0; text-shadow: 0 1px 0 #fff; }
p { color: #888; margin: 20px 0; font-size: 16px; line-height: 1.6; }
ul { list-style: none; margin: 25px 0; padding: 0; }
li { display: table-cell; font-weight: bold; width: 1%; }
.logo { display: inline-block; margin-top: 35px; }
#suggestions { margin-top: 35px; color: #ccc; }
#suggestions a { color: #666; font-weight: 200; font-size: 14px; margin: 0 10px; }
#copyright { position: fixed; width: 100%; left: 0; bottom: 50px; font-size: 13px; color: #777; text-shadow: 0 1px 0 #fff; }
</style>
</head>
<body>
<div class="container">

<h1><?php echo $title ;?></h1>
<p><strong><?php echo $subtitle ;?></strong></p>

<p>
<?php echo $content ;?>

</p>

<div id="suggestions">
<?php if (is_string($suggestions)) echo $suggestions ;?>
<?php if (is_array($suggestions)) foreach($suggestions as $sub) { ?>
<a href="<?php echo $sub['link'] ;?>"><?php echo $sub['name'] ;?></a>
<?php } ?>

</div>

<div id="copyright">
<?php echo $copyright ;?>

</div>

</div>
</body>
</html>