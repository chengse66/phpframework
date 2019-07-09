<?php if(!defined("ALLOW_ACCESS")) exit("not access");?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Title</title>
</head>
<body>
<?php  include bootstrap::renderer('/header',null,1);  ?>
    Hello <?php  echo $name;  ?> 你好!
</body>
</html>