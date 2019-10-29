<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>跳转提示</title>
<style type="text/css">
* {
	padding: 0;
	margin: 0;
}
body {
	background: #fff;
	font-family: '微软雅黑';
	color: #333;
	font-size: 16px;
}
.system-message {
	width:400px;
	padding:3px;
	margin-top:30px;
	background:#e82363;
	color:#fff;
	margin-left:auto;
	margin-right:auto;
	text-align:center;
}
.system-message h1 {
	font-size: 100px;
	font-weight: normal;
	line-height: 120px;
	margin-bottom: 12px;
}
.system-message .jump {
	padding-top: 10px
}
.system-message .jump a {
	color: #333;
}
.success {
	display:none;
}
.error {
	display:none;
}
.system-message .success, .system-message .error {
	line-height: 1.8em;
	font-size: 36px
}
.system-message .detail {
	font-size: 12px;
	line-height: 20px;
	margin-top: 12px;
	display:none
}
</style>
</head>
<body>
<?php ?>
<div class="system-message">
  <present name="message">
    <div style='padding:6px;font-size:14px;font-weight:300;'>提示信息</div>
    <div style='height:130px;font-size:14px;font-weight:300;background:#fff;color:#e82363;'><br />
      <?php echo($message); ?> <br />
      <br />
      <a id="href" href="<?php echo($jumpUrl); ?>" style='color:#204faf;font-weight:0;'>如果你的浏览器没反应，请点击这里...</a><br/>
      等待时间： <b id="wait"><?php echo($waitSecond); ?></b> </div>
    <p class="success"><?php echo($message); ?></p>
    <else/>
    <div style='padding:6px;font-size:14px;font-weight:300;'>提示信息</div>
    <div style='height:130px;font-size:14px;font-weight:300;background:#fff;color:#e82363;'><br />
      <?php echo($error); ?> <br />
      <br />
      <a id="href" href="<?php echo($jumpUrl); ?>" style='color:#204faf;font-weight:0;'>如果你的浏览器没反应，请点击这里...</a><br/>
      等待时间： <b id="wait"><?php echo($waitSecond); ?></b> </div>
    <p class="error"><?php echo($error); ?></p>
  </present>
</div>
<script type="text/javascript">
(function(){
var wait = document.getElementById('wait'),href = document.getElementById('href').href;
var interval = setInterval(function(){
	var time = --wait.innerHTML;
	if(time <= 0) {
		location.href = href;
		clearInterval(interval);
	};
}, 1000);
})();
</script>
</body>
</html>