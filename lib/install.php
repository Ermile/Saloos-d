<!DOCTYPE html>
<html>
<head>
 <meta charset="UTF-8"/>
 <title>(>_<)</title>
 <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1, user-scalable=no"/>
 <link rel="shortcut icon"    href="/static/images/favicon-error.png"/>
 <style type="text/css">*{font-family:sans-serif;-webkit-box-sizing: border-box;-moz-box-sizing: border-box;box-sizing: border-box;}body{width:90%;height:94%;margin:0;padding:3% 5%;background-color:rgba(61, 174, 255, 0.1);;color:#3DAEFF}a{color:#0091D6;}.slash{padding: 0 2px;}ol{direction:ltr;font-size: 14px;}li{padding-bottom:5px}.addr{font-size: 11px; font-weight: normal;}#no{z-index:-1;position:absolute;bottom:5%;right:5%;opacity:0.9;width: 250px}#smile{font-size:7em}img{max-width: 100%;}.btn{position:relative;width:300px;margin:100px auto 0;font-size:1em}.btn a{text-decoration:none;background-color:#0091D6;color:#fff;font-size:1.5em;text-align:center;padding:5px 10%;border-radius:10px;position:relative;display:block;width:100%;z-index:1}.btn span{background-color:#222;color:#fff;font-size:.8em;text-align:center;padding:5px 10%;position:absolute;width:90%;right:0;left:0;margin:0 auto;max-width:100%;overflow:hidden;transition:.5s}.btn .top{top:0;border-top-right-radius:10px;border-top-left-radius:10px}.btn:hover .top{top:-60%}.btn .bottom{bottom:0;border-bottom-right-radius:10px;border-bottom-left-radius:10px}.btn:hover .bottom{bottom:-60%}.btn span:hover{overflow:visible}
 </style>
</head>
<body>
 <h1>Saloos Installation</h1>
 <p>Welcome to saloos installation process.</p>
 <p>First of all set database connection detail on <b>config.php</b> then we do others!</p>
<?php
	if(isset($_GET['install']) && $_GET['install'] === 'go')
	{
		$result = \lib\db::install();
		if($result)
		{
?>
 <div class="btn">
  <span class="top">Install Successfully:)</span>
  <a href="/">Lets GO!</a>
 </div>
<?php
		}
	}
	else
	{
?>
 <div class="btn">
  <a href="?install=go">Install</a>
  <span class="bottom">Just a seconds!</span>
 </div>
<?php
	}
?>

 <div id="no"><img src="/static/images/logo.png" alt="Logo" id='logo'></div>
</body>
</html>