<?php
require_once '../config.php';

if($_POST['is_success']=='true'){
$func =  new func();
$func->update(array('b'=>'index_order','t'=>array('order_hao'=>$_POST['order_no']),'data'=>array('order_pay_zt' =>382)));
}


