<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2012 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

/**
 * Think 基础函数库
 * @category   Think
 * @package  Common
 * @author   liu21st <liu21st@gmail.com>
 */

/**
 * 获取模版文件 格式 项目://分组@主题/模块/操作
 * @param string $name 模版资源地址
 * @param string $layer 视图层（目录）名称
 * @return string
 */
function T($template='',$layer=''){
        if(is_file($template)) {
            return $template;
        }
        // 解析模版资源地址
        if(false === strpos($template,'://')){
            $template   =   APP_NAME.'://'.str_replace(':', '/',$template);
        }        
        $info   =   parse_url($template);
        $file   =   $info['host'].(isset($info['path'])?$info['path']:'');
        $group  =   isset($info['user'])?$info['user'].'/':(defined('GROUP_NAME')?GROUP_NAME.'/':'');
        $app    =   $info['scheme'];
        $layer  =   $layer?$layer:C('DEFAULT_V_LAYER');

        // 获取当前主题的模版路径
        if(($list = C('EXTEND_GROUP_LIST')) && isset($list[$app])){ // 扩展分组
            $baseUrl    =   $list[$app].'/'.$group.$layer.'/';
        }elseif(1==C('APP_GROUP_MODE')){ // 独立分组模式
            $baseUrl    =   dirname(BASE_LIB_PATH).'/'.$group.$layer.'/';
        }else{ 
            $baseUrl    =   TMPL_PATH.$group;
        }

        // 分析模板文件规则
        if('' == $file) {
            // 如果模板文件名为空 按照默认规则定位
            $file = MODULE_NAME . C('TMPL_FILE_DEPR') . ACTION_NAME;
        }elseif(false === strpos($file, '/')){
            $file = MODULE_NAME . C('TMPL_FILE_DEPR') . $file;
        }
        return $baseUrl.$file.C('TMPL_TEMPLATE_SUFFIX');
}

/**
 * 获取输入参数 支持过滤和默认值
 * 使用方法:
 * <code>
 * I('id',0); 获取id参数 自动判断get或者post
 * I('post.name','','htmlspecialchars'); 获取$_POST['name']
 * I('get.'); 获取$_GET
 * </code> 
 * @param string $name 变量的名称 支持指定类型
 * @param mixed $default 不存在的时候默认值
 * @param mixed $filter 参数过滤方法
 * @return mixed
 */
function I($name,$default='',$filter=null) {
    if(strpos($name,'.')) { // 指定参数来源
        list($method,$name) =   explode('.',$name,2);
    }else{ // 默认为自动判断
        $method =   'param';
    }
    switch(strtolower($method)) {
        case 'get'     :   $input =& $_GET;break;
        case 'post'    :   $input =& $_POST;break;
        case 'put'     :   parse_str(file_get_contents('php://input'), $input);break;
        case 'param'   :  
            switch($_SERVER['REQUEST_METHOD']) {
                case 'POST':
                    $input  =  $_POST;
                    break;
                case 'PUT':
                    parse_str(file_get_contents('php://input'), $input);
                    break;
                default:
                    $input  =  $_GET;
            }
            if(C('VAR_URL_PARAMS') && isset($_GET[C('VAR_URL_PARAMS')])){
                $input  =   array_merge($input,$_GET[C('VAR_URL_PARAMS')]);
            }
            break;
        case 'request' :   $input =& $_REQUEST;   break;
        case 'session' :   $input =& $_SESSION;   break;
        case 'cookie'  :   $input =& $_COOKIE;    break;
        case 'server'  :   $input =& $_SERVER;    break;
        case 'globals' :   $input =& $GLOBALS;    break;
        default:
            return NULL;
    }
    // 全局过滤
    if(C('VAR_FILTERS')) {
        $_filters    =   explode(',',C('VAR_FILTERS'));
        foreach($_filters as $_filter){
            // 全局参数过滤
            array_walk_recursive($input,$_filter);
        }
    }
    if(empty($name)) { // 获取全部变量
        $data       =   $input; 
        $filters    =   isset($filter)?$filter:C('DEFAULT_FILTER');
        if($filters) {
            $filters    =   explode(',',$filters);
            foreach($filters as $filter){
                $data   =   array_map($filter,$data); // 参数过滤
            }
        }        
    }elseif(isset($input[$name])) { // 取值操作
        $data       =	$input[$name];
        $filters    =   isset($filter)?$filter:C('DEFAULT_FILTER');
        if($filters) {
            $filters    =   explode(',',$filters);
            foreach($filters as $filter){
                if(function_exists($filter)) {
                    $data   =   is_array($data)?array_map($filter,$data):$filter($data); // 参数过滤
                }else{
                    $data   =   filter_var($data,is_int($filter)?$filter:filter_id($filter));
                    if(false === $data) {
                        return	 isset($default)?$default:NULL;
                    }
                }
            }
        }
    }else{ // 变量默认值
        $data       =	 isset($default)?$default:NULL;
    }
    is_array($data) && array_walk_recursive($data,'think_filter');
    return $data;
}

/**
 * 记录和统计时间（微秒）和内存使用情况
 * 使用方法:
 * <code>
 * G('begin'); // 记录开始标记位
 * // ... 区间运行代码
 * G('end'); // 记录结束标签位
 * echo G('begin','end',6); // 统计区间运行时间 精确到小数后6位
 * echo G('begin','end','m'); // 统计区间内存使用情况
 * 如果end标记位没有定义，则会自动以当前作为标记位
 * 其中统计内存使用需要 MEMORY_LIMIT_ON 常量为true才有效
 * </code>
 * @param string $start 开始标签
 * @param string $end 结束标签
 * @param integer|string $dec 小数位或者m 
 * @return mixed
 */
function G($start,$end='',$dec=4) {
    static $_info       =   array();
    static $_mem        =   array();
    if(is_float($end)) { // 记录时间
        $_info[$start]  =   $end;
    }elseif(!empty($end)){ // 统计时间和内存使用
        if(!isset($_info[$end])) $_info[$end]       =  microtime(TRUE);
        if(MEMORY_LIMIT_ON && $dec=='m'){
            if(!isset($_mem[$end])) $_mem[$end]     =  memory_get_usage();
            return number_format(($_mem[$end]-$_mem[$start])/1024);          
        }else{
            return number_format(($_info[$end]-$_info[$start]),$dec);
        }       
            
    }else{ // 记录时间和内存使用
        $_info[$start]  =  microtime(TRUE);
        if(MEMORY_LIMIT_ON) $_mem[$start]           =  memory_get_usage();
    }
}

/**
 * 设置和获取统计数据
 * 使用方法:
 * <code>
 * N('db',1); // 记录数据库操作次数
 * N('read',1); // 记录读取次数
 * echo N('db'); // 获取当前页面数据库的所有操作次数
 * echo N('read'); // 获取当前页面读取次数
 * </code> 
 * @param string $key 标识位置
 * @param integer $step 步进值
 * @return mixed
 */
function N($key, $step=0,$save=false) {
    static $_num    = array();
    if (!isset($_num[$key])) {
        $_num[$key] = (false !== $save)? S('N_'.$key) :  0;
    }
    if (empty($step))
        return $_num[$key];
    else
        $_num[$key] = $_num[$key] + (int) $step;
    if(false !== $save){ // 保存结果
        S('N_'.$key,$_num[$key],$save);
    }
}

/**
 * 字符串命名风格转换
 * type 0 将Java风格转换为C的风格 1 将C风格转换为Java的风格
 * @param string $name 字符串
 * @param integer $type 转换类型
 * @return string
 */
function parse_name($name, $type=0) {
    if ($type) {
        return ucfirst(preg_replace("/_([a-zA-Z])/e", "strtoupper('\\1')", $name));
    } else {
        return strtolower(trim(preg_replace("/[A-Z]/", "_\\0", $name), "_"));
    }
}

/**
 * 优化的require_once
 * @param string $filename 文件地址
 * @return boolean
 */
function require_cache($filename) {
    static $_importFiles = array();
    if (!isset($_importFiles[$filename])) {
        if (file_exists_case($filename)) {
            require $filename;
            $_importFiles[$filename] = true;
        } else {
            $_importFiles[$filename] = false;
        }
    }
    return $_importFiles[$filename];
}

/**
 * 批量导入文件 成功则返回
 * @param array $array 文件数组
 * @param boolean $return 加载成功后是否返回
 * @return boolean
 */
function require_array($array,$return=false){
    foreach ($array as $file){
        if (require_cache($file) && $return) return true;
    }
    if($return) return false;
}

/**
 * 区分大小写的文件存在判断
 * @param string $filename 文件地址
 * @return boolean
 */
function file_exists_case($filename) {
    if (is_file($filename)) {
        if (IS_WIN && C('APP_FILE_CASE')) {
            if (basename(realpath($filename)) != basename($filename))
                return false;
        }
        return true;
    }
    return false;
}

/**
 * 导入所需的类库 同java的Import 本函数有缓存功能
 * @param string $class 类库命名空间字符串
 * @param string $baseUrl 起始路径
 * @param string $ext 导入的文件扩展名
 * @return boolean
 */
function import($class, $baseUrl = '', $ext='.class.php') {
    static $_file = array();
    $class = str_replace(array('.', '#'), array('/', '.'), $class);
    if ('' === $baseUrl && false === strpos($class, '/')) {
        // 检查别名导入
        return alias_import($class);
    }
    if (isset($_file[$class . $baseUrl]))
        return true;
    else
        $_file[$class . $baseUrl] = true;
    $class_strut     = explode('/', $class);
    if (empty($baseUrl)) {
        $libPath    =   defined('BASE_LIB_PATH')?BASE_LIB_PATH:LIB_PATH;
        if ('@' == $class_strut[0] || APP_NAME == $class_strut[0]) {
            //加载当前项目应用类库
            $baseUrl = dirname($libPath);
            $class   = substr_replace($class, basename($libPath).'/', 0, strlen($class_strut[0]) + 1);
        }elseif ('think' == strtolower($class_strut[0])){ // think 官方基类库
            $baseUrl = CORE_PATH;
            $class   = substr($class,6);
        }elseif (in_array(strtolower($class_strut[0]), array('org', 'com'))) {
            // org 第三方公共类库 com 企业公共类库
            $baseUrl = LIBRARY_PATH;
        }else { // 加载其他项目应用类库
            $class   = substr_replace($class, '', 0, strlen($class_strut[0]) + 1);
            $baseUrl = APP_PATH . '../' . $class_strut[0] . '/'.basename($libPath).'/';
        }
    }
    if (substr($baseUrl, -1) != '/')
        $baseUrl    .= '/';
    $classfile       = $baseUrl . $class . $ext;
    if (!class_exists(basename($class),false)) {
        // 如果类不存在 则导入类库文件
        return require_cache($classfile);
    }
}

/**
 * 基于命名空间方式导入函数库
 * load('@.Util.Array')
 * @param string $name 函数库命名空间字符串
 * @param string $baseUrl 起始路径
 * @param string $ext 导入的文件扩展名
 * @return void
 */
function load($name, $baseUrl='', $ext='.php') {
    $name = str_replace(array('.', '#'), array('/', '.'), $name);
    if (empty($baseUrl)) {
        if (0 === strpos($name, '@/')) {
            //加载当前项目函数库
            $baseUrl    = COMMON_PATH;
            $name       = substr($name, 2);
        } else {
            //加载ThinkPHP 系统函数库
            $baseUrl    = EXTEND_PATH . 'Function/';
        }
    }
    if (substr($baseUrl, -1) != '/')
        $baseUrl       .= '/';
    require_cache($baseUrl . $name . $ext);
}

/**
 * 快速导入第三方框架类库 所有第三方框架的类库文件统一放到 系统的Vendor目录下面
 * @param string $class 类库
 * @param string $baseUrl 基础目录
 * @param string $ext 类库后缀 
 * @return boolean
 */
function vendor($class, $baseUrl = '', $ext='.php') {
    if (empty($baseUrl))
        $baseUrl = VENDOR_PATH;
    return import($class, $baseUrl, $ext);
}

/**
 * 快速定义和导入别名 支持批量定义
 * @param string|array $alias 类库别名
 * @param string $classfile 对应类库
 * @return boolean
 */
function alias_import($alias, $classfile='') {
    static $_alias = array();
    if (is_string($alias)) {
        if(isset($_alias[$alias])) {
            return require_cache($_alias[$alias]);
        }elseif ('' !== $classfile) {
            // 定义别名导入
            $_alias[$alias] = $classfile;
            return;
        }
    }elseif (is_array($alias)) {
        $_alias   =  array_merge($_alias,$alias);
        return;
    }
    return false;
}

/**
 * D函数用于实例化Model 格式 项目://分组/模块
 * @param string $name Model资源地址
 * @param string $layer 业务层名称
 * @return Model
 */
function D($name='',$layer='') {
    if(empty($name)) return new Model;
    static $_model  =   array();
    $layer          =   $layer?$layer:C('DEFAULT_M_LAYER');
    if(strpos($name,'://')) {// 指定项目
        list($app)  =   explode('://',$name);
        $name       =   str_replace('://','/'.$layer.'/',$name);
    }else{
        $app        =   C('DEFAULT_APP');
        $name       =   $app.'/'.$layer.'/'.$name;
    }
    if(isset($_model[$name]))   return $_model[$name];
    $path           =   explode('/',$name);
    if($list = C('EXTEND_GROUP_LIST') && isset($list[$app])){ // 扩展分组
        $baseUrl    =   $list[$app];
        import($path[2].'/'.$path[1].'/'.$path[3].$layer,$baseUrl);
    }elseif(count($path)>3 && 1 == C('APP_GROUP_MODE')) { // 独立分组
        $baseUrl    =   $path[0]== '@' ? dirname(BASE_LIB_PATH) : APP_PATH.'../'.$path[0].'/'.C('APP_GROUP_PATH').'/';
        import($path[2].'/'.$path[1].'/'.$path[3].$layer,$baseUrl);
    }else{
        import($name.$layer);
    } 
    $class          =   basename($name.$layer);
    if(class_exists($class)) {
        $model      =   new $class(basename($name));
    }else {
        $model      =   new Model(basename($name));
    }
    $_model[$name]  =  $model;
    return $model;
}

/**
 * M函数用于实例化一个没有模型文件的Model
 * @param string $name Model名称 支持指定基础模型 例如 MongoModel:User
 * @param string $tablePrefix 表前缀
 * @param mixed $connection 数据库连接信息
 * @return Model
 */
function M($name='', $tablePrefix='',$connection='') {
    static $_model  = array();
    if(strpos($name,':')) {
        list($class,$name)    =  explode(':',$name);
    }else{
        $class      =   'Model';
    }
    $guid           =   $tablePrefix . $name . '_' . $class;
    if (!isset($_model[$guid]))
        $_model[$guid] = new $class($name,$tablePrefix,$connection);
    return $_model[$guid];
}

/**
 * A函数用于实例化Action 格式：[项目://][分组/]模块
 * @param string $name Action资源地址
 * @param string $layer 控制层名称
 * @param boolean $common 是否公共目录
 * @return Action|false
 */
function A($name,$layer='',$common=false) {
    static $_action = array();
    $layer      =   $layer?$layer:C('DEFAULT_C_LAYER');
    if(strpos($name,'://')) {// 指定项目
        list($app)  =   explode('://',$name);
        $name   =  str_replace('://','/'.$layer.'/',$name);
    }else{
        $app    =   '@';
        $name   =  '@/'.$layer.'/'.$name;
    }
    if(isset($_action[$name]))  return $_action[$name];
    $path           =   explode('/',$name);
    if($list = C('EXTEND_GROUP_LIST') && isset($list[$app])){ // 扩展分组
        $baseUrl    =   $list[$app];
        import($path[2].'/'.$path[1].'/'.$path[3].$layer,$baseUrl);
    }elseif(count($path)>3 && 1 == C('APP_GROUP_MODE')) { // 独立分组
        $baseUrl    =   $path[0]== '@' ? dirname(BASE_LIB_PATH) : APP_PATH.'../'.$path[0].'/'.C('APP_GROUP_PATH').'/';
        import($path[2].'/'.$path[1].'/'.$path[3].$layer,$baseUrl);
    }elseif($common) { // 加载公共类库目录
        import(str_replace('@/','',$name).$layer,LIB_PATH);
    }else{
        import($name.$layer);
    }
    $class      =   basename($name.$layer);
    if(class_exists($class,false)) {
        $action             =   new $class();
        $_action[$name]     =   $action;
        return $action;
    }else {
        return false;
    }
}

/**
 * 远程调用模块的操作方法 URL 参数格式 [项目://][分组/]模块/操作
 * @param string $url 调用地址
 * @param string|array $vars 调用参数 支持字符串和数组 
 * @param string $layer 要调用的控制层名称
 * @return mixed
 */
function R($url,$vars=array(),$layer='') {
    $info   =   pathinfo($url);
    $action =   $info['basename'];
    $module =   $info['dirname'];
    $class  =   A($module,$layer);
    if($class){
        if(is_string($vars)) {
            parse_str($vars,$vars);
        }
        return call_user_func_array(array(&$class,$action.C('ACTION_SUFFIX')),$vars);
    }else{
        return false;
    }
}

/**
 * 获取和设置语言定义(不区分大小写)
 * @param string|array $name 语言变量
 * @param string $value 语言值
 * @return mixed
 */
function L($name=null, $value=null) {
    static $_lang = array();
    // 空参数返回所有定义
    if (empty($name))
        return $_lang;
    // 判断语言获取(或设置)
    // 若不存在,直接返回全大写$name
    if (is_string($name)) {
        $name = strtoupper($name);
        if (is_null($value))
            return isset($_lang[$name]) ? $_lang[$name] : $name;
        $_lang[$name] = $value; // 语言定义
        return;
    }
    // 批量定义
    if (is_array($name))
        $_lang = array_merge($_lang, array_change_key_case($name, CASE_UPPER));
    return;
}

/**
 * 获取和设置配置参数 支持批量定义
 * @param string|array $name 配置变量
 * @param mixed $value 配置值
 * @return mixed
 */
function C($name=null, $value=null) {
    static $_config = array();
    // 无参数时获取所有
    if (empty($name)) {
        if(!empty($value) && $array = S('c_'.$value)) {
            $_config = array_merge($_config, array_change_key_case($array));
        }
        return $_config;
    }
    // 优先执行设置获取或赋值
    if (is_string($name)) {
        if (!strpos($name, '.')) {
            $name = strtolower($name);
            if (is_null($value))
                return isset($_config[$name]) ? $_config[$name] : null;
            $_config[$name] = $value;
            return;
        }
        // 二维数组设置和获取支持
        $name = explode('.', $name);
        $name[0]   =  strtolower($name[0]);
        if (is_null($value))
            return isset($_config[$name[0]][$name[1]]) ? $_config[$name[0]][$name[1]] : null;
        $_config[$name[0]][$name[1]] = $value;
        return;
    }
    // 批量设置
    if (is_array($name)){
        $_config = array_merge($_config, array_change_key_case($name));
        if(!empty($value)) {// 保存配置值
            S('c_'.$value,$_config);
        }
        return;
    }
    return null; // 避免非法参数
}

/**
 * 处理标签扩展
 * @param string $tag 标签名称
 * @param mixed $params 传入参数
 * @return mixed
 */
function tag($tag, &$params=NULL) {
    // 系统标签扩展
    $extends    = C('extends.' . $tag);
    // 应用标签扩展
    $tags       = C('tags.' . $tag);
    if (!empty($tags)) {
        if(empty($tags['_overlay']) && !empty($extends)) { // 合并扩展
            $tags = array_unique(array_merge($extends,$tags));
        }elseif(isset($tags['_overlay'])){ // 通过设置 '_overlay'=>1 覆盖系统标签
            unset($tags['_overlay']);
        }
    }elseif(!empty($extends)) {
        $tags = $extends;
    }
    if($tags) {
        if(APP_DEBUG) {
            G($tag.'Start');
            trace('[ '.$tag.' ] --START--','','INFO');
        }
        // 执行扩展
        foreach ($tags as $key=>$name) {
            if(!is_int($key)) { // 指定行为类的完整路径 用于模式扩展
                $name   = $key;
            }
            B($name, $params);
        }
        if(APP_DEBUG) { // 记录行为的执行日志
            trace('[ '.$tag.' ] --END-- [ RunTime:'.G($tag.'Start',$tag.'End',6).'s ]','','INFO');
        }
    }else{ // 未执行任何行为 返回false
        return false;
    }
}

/**
 * 动态添加行为扩展到某个标签
 * @param string $tag 标签名称
 * @param string $behavior 行为名称
 * @param string $path 行为路径 
 * @return void
 */
function add_tag_behavior($tag,$behavior,$path='') {
    $array      =  C('tags.'.$tag);
    if(!$array) {
        $array  =  array();
    }
    if($path) {
        $array[$behavior] = $path;
    }else{
        $array[] =  $behavior;
    }
    C('tags.'.$tag,$array);
}

/**
 * 执行某个行为
 * @param string $name 行为名称
 * @param Mixed $params 传入的参数
 * @return void
 */
function B($name, &$params=NULL) {
    if(strpos($name,'/')){
        list($name,$method) = explode('/',$name);
    }else{
        $method     =   'run';
    }
    $class      = $name.'Behavior';
    if(APP_DEBUG) {
        G('behaviorStart');
    }
    $behavior   = new $class();
    $behavior->$method($params);
    if(APP_DEBUG) { // 记录行为的执行日志
        G('behaviorEnd');
        trace($name.' Behavior ::'.$method.' [ RunTime:'.G('behaviorStart','behaviorEnd',6).'s ]','','INFO');
    }
}

/**
 * 去除代码中的空白和注释
 * @param string $content 代码内容
 * @return string
 */
function strip_whitespace($content) {
    $stripStr   = '';
    //分析php源码
    $tokens     = token_get_all($content);
    $last_space = false;
    for ($i = 0, $j = count($tokens); $i < $j; $i++) {
        if (is_string($tokens[$i])) {
            $last_space = false;
            $stripStr  .= $tokens[$i];
        } else {
            switch ($tokens[$i][0]) {
                //过滤各种PHP注释
                case T_COMMENT:
                case T_DOC_COMMENT:
                    break;
                //过滤空格
                case T_WHITESPACE:
                    if (!$last_space) {
                        $stripStr  .= ' ';
                        $last_space = true;
                    }
                    break;
                case T_START_HEREDOC:
                    $stripStr .= "<<<THINK\n";
                    break;
                case T_END_HEREDOC:
                    $stripStr .= "THINK;\n";
                    for($k = $i+1; $k < $j; $k++) {
                        if(is_string($tokens[$k]) && $tokens[$k] == ';') {
                            $i = $k;
                            break;
                        } else if($tokens[$k][0] == T_CLOSE_TAG) {
                            break;
                        }
                    }
                    break;
                default:
                    $last_space = false;
                    $stripStr  .= $tokens[$i][1];
            }
        }
    }
    return $stripStr;
}

//[RUNTIME]
// 编译文件
function compile($filename) {
    $content        = file_get_contents($filename);
    // 替换预编译指令
    $content        = preg_replace('/\/\/\[RUNTIME\](.*?)\/\/\[\/RUNTIME\]/s', '', $content);
    $content        = substr(trim($content), 5);
    if ('?>' == substr($content, -2))
        $content    = substr($content, 0, -2);
    return $content;
}

// 根据数组生成常量定义
function array_define($array,$check=true) {
    $content = "\n";
    foreach ($array as $key => $val) {
        $key = strtoupper($key);
        if($check)   $content .= 'defined(\'' . $key . '\') or ';
        if (is_int($val) || is_float($val)) {
            $content .= "define('" . $key . "'," . $val . ');';
        } elseif (is_bool($val)) {
            $val = ($val) ? 'true' : 'false';
            $content .= "define('" . $key . "'," . $val . ');';
        } elseif (is_string($val)) {
            $content .= "define('" . $key . "','" . addslashes($val) . "');";
        }
        $content    .= "\n";
    }
    return $content;
}
//[/RUNTIME]

/**
 * 添加和获取页面Trace记录
 * @param string $value 变量
 * @param string $label 标签
 * @param string $level 日志级别 
 * @param boolean $record 是否记录日志
 * @return void
 */
function trace($value='[think]',$label='',$level='DEBUG',$record=false) {
    static $_trace =  array();
    if('[think]' === $value){ // 获取trace信息
        return $_trace;
    }else{
        $info   =   ($label?$label.':':'').print_r($value,true);
        if('ERR' == $level && C('TRACE_EXCEPTION')) {// 抛出异常
            throw_exception($info);
        }
        $level  =   strtoupper($level);
        if(!isset($_trace[$level])) {
                $_trace[$level] =   array();
            }
        $_trace[$level][]   = $info;
        if((defined('IS_AJAX') && IS_AJAX) || !C('SHOW_PAGE_TRACE')  || $record) {
            Log::record($info,$level,$record);
        }
    }
}

/*自定义方法*/
//获取数据
function pg($var) {
	$value = '';
	if(isset($_POST[$var])) {
		$value = $_POST[$var];
	} else if (isset($_GET[$var])) {
		$value = $_GET[$var];
	}
	
	if (!get_magic_quotes_gpc()) {//防止重复转义
		//$value=saddslashes($value);
	}
	return $value;
}
//转义
function saddslashes($string) {
	if(is_array($string)) {
		foreach($string as $key => $val) {
			$string[$key] = saddslashes($val);
		}
	} else {
		$string = addslashes($string);
	}
	return $string;
}

//时间戳转日期格式
function cover_time($cov_times, $dateformat='')
{
	if($cov_times!='') {
		if($dateformat=='') {
			$dateformat = 'Y-m-d';
		}
		$cover_tiem = date($dateformat, $cov_times);
		
		return $cover_tiem;
	}
}
//md5加密
function md5_code($data){
	$str=md5($data);
	return $str;
}
function fckeditor($name,$height,$value=''){
	include_once 'Admin/Common/fckeditor/fckeditor.php';//引用FCKeditor.php
	$fck = new FCKeditor($name);
	$fck->BasePath= 'Admin/Common/fckeditor/';
	$fck->Width='100%';	//设置它的宽度
	$fck->Height= $height;
	//$fck->ToolbarSet= 'Basic';
	$fck->Value=$value ;
	return $fck->CreateHtml();
}

function showmsg($msg,$gourl,$limittime=0,$is=1)
{
	$htmlhead  = "<html>\r\n<head>\r\n<title>百家骏网络-网站管理系统</title>\r\n<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" />\r\n";
	if(strstr($gourl,"m=1"))
	{
		$htmlhead .= "<meta name='viewport' content='width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0'>";
	}
	$htmlhead .= "<base target='_self'/>\r\n<style>div{line-height:160%;}</style></head>\r\n<body leftmargin=\"0\" topmargin=\"0\">\r\n<center>\r\n<script>\r\n";
	$htmlfoot  = "</script>\r\n</center>\r\n</body>\r\n</html>\r\n";

	if($limittime==0)
	{
		$litime = 1000;
	}
	else
	{
		$litime = $limittime;
	}

	if($gourl=='-1')
	{
		if($limittime==0)
		{
			$litime = 1000;//跳转等待时间
		}
		$gourl = 'javascript:history.go(-1);';
	}

	if($gourl==''||$onlymsg==1)
	{
		$msg = "<script>alert(\"".str_replace("\"","",$msg)."\");</script>";
	}
	else
	{
		$func = "      var pgo=0;
      function JumpUrl(){
        if(pgo==0){ location='$gourl'; pgo=1; }
      }\r\n";
		$rmsg = $func;
		
		if(strstr($gourl,"m=1"))
		{
			$rmsg .= "document.write(\"<div style='width:80%;padding:3px;margin-top:30px;background:#e82363;color:#fff;'><div>";
		}
		else
		{
			$rmsg .= "document.write(\"<div style='width:400px;padding:3px;margin-top:30px;background:#e82363;color:#fff;'><div>";
		}

		$rmsg .= "<div style='padding:6px;font-size:14px;font-weight:300;'>提示信息</div>\");\r\n";
		$rmsg .= "document.write(\"<div style='height:130px;font-size:14px;font-weight:300;background:#fff;color:#e82363;'><br />\");\r\n";
		if($is==1)
		{
			$rmsg .= "document.write(\"".str_replace("\"","","<img src='Admin/Tpl/images/dui.png' height='16'> ".$msg)."\");\r\n";
		}
		else
		{
			$rmsg .= "document.write(\"".str_replace("\"","","<img src='Admin/Tpl/images/cuo.png' height='16'> ".$msg)."\");\r\n";			
		}
		$rmsg .= "document.write(\"";
		if($onlymsg==0)
		{
			if($gourl!='javascript:;' && $gourl!='')
			{
				$rmsg .= "<br /><br /><a href='{$gourl}' style='color:#204faf;font-weight:0;'>如果你的浏览器没反应，请点击这里...</a>";
			}
			$rmsg .= "<br/></div>\");\r\n";
			if($gourl!='javascript:;' && $gourl!='')
			{
				$rmsg .= "setTimeout('JumpUrl()',$litime);";
			}
		}
		else
		{
			$rmsg .= "<br/><br/></div></div>\");\r\n";
		}
		$msg  = $htmlhead.$rmsg.$htmlfoot;
	}
	echo $msg;
}

function substrs($value){
	$value=str_replace("\n","<br>",$value);
	$value=str_replace(" ","&nbsp;",$value);
	return $value;
}
//分类地址
function classify_url($type_id,$classify_id){
	$list=M('classify_type')->where(array('type_id'=>$type_id))->select();
	
	$classify=M('classify')->where(array('classify_id'=>$classify_id))->select();
	/*
	global $rewrite;
	if($rewrite==1)
	{
		$url=$list[0]['table_name'].'_'.$list[0]['page_name'].'_'.$menu_id.'.html';
		if($list[0]['table_name']=='')$url='index.html';
	}
	else
	{
		$url='index.php?f='.$list[0]['table_name'].'&w='.$list[0]['page_name'].'&menu_id='.$menu_id;
		if($list[0]['table_name']=='')$url='index.php';
	}
	*/
	$url=__APP__.'?m='.$list[0]['table_name'].'&a='.$list[0]['page_name'].'&classify_id='.$classify_id;
	if($list[0]['table_name']=='')$url=__APP__;
	if(trim($classify[0]['classify_url'])!='')$url=$classify[0]['classify_url'];
	return $url;

}
//内容地址
function content_url($type_id,$content_id){
	$list=M('classify_type')->where(array('type_id'=>$type_id))->select();
	/*
	global $rewrite;
	if($rewrite==1)
	{
		$url=$list[0]['table_name'].'_end_'.$content_id.'.html';
	}
	else
	{
		$url='index.php?f='.$list[0]['table_name'].'&w=end&content_id='.$content_id;
	}
	*/
	$url=__APP__.'?m='.$list[0]['table_name'].'&a=details&content_id='.$content_id;
	return $url;
}
/**
 * 获取当前页面完整URL地址
 */
function get_url(){
    $sys_protocal = isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443' ? 'https://' : 'http://';
    $php_self = $_SERVER['PHP_SELF'] ? $_SERVER['PHP_SELF'] : $_SERVER['SCRIPT_NAME'];
    $path_info = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
    $relate_url = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : $php_self.(isset($_SERVER['QUERY_STRING']) ? '?'.$_SERVER['QUERY_STRING'] : $path_info);
    return $sys_protocal.(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '').$relate_url;
}

function unique_arr($array2D,$stkeep=false,$ndformat=true)
{
	// 判断是否保留一级数组键 (一级数组键可以为非数字)
	if($stkeep) $stArr = array_keys($array2D);

	// 判断是否保留二级数组键 (所有二级数组键必须相同)
	if($ndformat) $ndArr = array_keys(end($array2D));

	//降维,也可以用implode,将一维数组转换为用逗号连接的字符串
	foreach ($array2D as $v){
		$v = join(",",$v); 
		$temp[] = $v;
	}

	//去掉重复的字符串,也就是重复的一维数组
	$temp = array_unique($temp); 

	//再将拆开的数组重新组装
	foreach ($temp as $k => $v)
	{
		if($stkeep) $k = $stArr[$k];
		if($ndformat)
		{
			$tempArr = explode(",",$v); 
			foreach($tempArr as $ndkey => $ndval) $output[$k][$ndArr[$ndkey]] = $ndval;
		}
		else $output[$k] = explode(",",$v); 
	}

	return $output;
}

/**
	获取类型id
*/
function get_type_id(){
	$type_id=M('classify_type')->where(array('table_name'=>MODULE_NAME))->getField('type_id');
	return $type_id;
}

/**
	分类递归
*/
function recursive_classify_id($classify_id,$level_id)
{
	$classify=M('classify')->field('level_id,classify_id,classify_pid')->where(array('classify_id'=>$classify_id))->find();
	if($classify['level_id']>$level_id)
	{
		return recursive_classify_id($classify['classify_pid'],$level_id);
	}
	else
	{
		return $classify['classify_id'];
	}
}

/**
	获取分类id
*/
function get_classify_id()
{
	if(pg('classify_id')=='')
	{		
		$classify_id=M('relevance')->where(array('content_id'=>pg('content_id'),'main_id'=>1,'type_id'=>get_type_id()))->getField('classify_id');
	}
	else
	{
		$classify_id=pg('classify_id');
	}
	return $classify_id;
}

function strlens($str,$length,$charset="utf-8",$suffix='..')
{
	return mb_strlen($str,$charset) > $length ? mb_substr($str, 0, $length,$charset).$suffix : $str;
}

/*打印目录下的文件*/
function listdir($dir)
{
	if(is_dir($dir))
   	{
     	if ($dh = opendir($dir)) 
		{
        	while (($file = readdir($dh)) !== false)
			{
				if($file!="." && $file!="..")
				{
					$array[]=$file;
				}
        	}
        	closedir($dh);
     	}
   	}
	return $array;	
}

/*打印目录下的文件-修改时间、创建时间返回二维数组*/
function each_dir($dir)
{
	$i=0;
	if(is_dir($dir))
   	{
     	if ($dh = opendir($dir))
		{
        	while (($file = readdir($dh)) !== false)
			{
				if($file!="." && $file!="..")
				{
					$array[$i]['filename']=$file;//文件名
					$array[$i]['filemtime']=filemtime($dir.'/'.$file);//文件最后一次修改时间
					$array[$i]['filectime']=filectime($dir.'/'.$file);//文件创建时间
					$i++;
				}
        	}
        	closedir($dh);
     	}
   	}
	return $array;
}


function creat_file($path)//建立文件
{
	if (!file_exists($path))
	{
		if(touch($path))
		{
			return true;
		}
		else
		{
			return false;
		}
	}
}
//写入文件-生成静态文件
function write_file($path,$data)
{
	creat_file($path);
	$fp=fopen($path,"w+");
	if(!fwrite($fp,$data))
	{
		return false;
	}
	else
	{
		fclose($fp);
		return true;
	}
}
//读取文件
function read_file($path)
{
	$filename = $path;
	$handle = fopen($filename, "r");//读取二进制文件时，需要将第二个参数设置成'rb'
	$filesize=filesize($filename);
	if($filesize>0)
	{
		$contents = fread($handle, $filesize);
	}
	fclose($handle);
	return $contents;
}
//删除文件
function delete_file($path)
{
	if(file_exists($path))
	{
		unlink($path);
	}
}
//复制文件
function copy_dir($sourceDir,$aimDir)
{
	$succeed = true;
	if(!file_exists($sourceDir)){
		return false;
	}
	if(!file_exists($aimDir)){
		if(!mkdir($aimDir,0777)){
			return false;
		}
	}
	$objDir = opendir($sourceDir);
	while(false !== ($fileName = readdir($objDir))){
			if(($fileName != ".") && ($fileName != "..")){
				if(!is_dir($sourceDir.'/'.$fileName)){
					if(!copy($sourceDir.'/'.$fileName,$aimDir.'/'.$fileName)){
						$succeed = false;
						break;
					}
				}
				else{
					copy_dir($sourceDir.'/'.$fileName,$aimDir.'/'.$fileName);
				}
		}
	}
	closedir($objDir);
	return $succeed;
}

//创建文件夹
function create_dir($dir)
{
	if(!is_dir($dir))
	{
	if(!create_dir(dirname($dir))){
	  return false;
	}
	if(!mkdir($dir,0777)){
	  return false;
	}
	}
	return true;
}
function del_dir($dir)
{
	if (!file_exists($dir))
	{
		return true;
	}
	else
	{
		@chmod($dir, 0777);
	}
	$dh=opendir($dir);
	while ($file=readdir($dh))
	{
		if($file!="." && $file!="..")
		{
			$fullpath=$dir."/".$file;
			if(!is_dir($fullpath))
			{
				unlink($fullpath);
			}
			else
			{
				del_dir($fullpath);
			}
		}
	}
	closedir($dh);
	
	if(rmdir($dir))
	{
		return true;
	}
	else
	{
		return false;
	}
}


function array_sort($arr,$keys,$type='asc'){ 
	$keysvalue= $new_array= array(); 
	foreach($arr as $k=>$v){ 
	$keysvalue[$k] = $v[$keys]; 
	} 
	if($type== 'asc'){ 
	asort($keysvalue); 
	}else{ 
	arsort($keysvalue); 
	} 
	reset($keysvalue); 
	foreach($keysvalue as $k=>$v){ 
	$new_array[$k] = $arr[$k]; 
	}
	return $new_array;
}

function input_csv($handle)//解析csv
{   
	$out = array ();   
	$n = 0;   
	while ($data = fgetcsv($handle, 10000))   
	{   
		$num = count($data);
		for ($i = 0; $i < $num; $i++)   
		{   
			$out[$n][$i] = $data[$i];
		}   
		$n++;   
	}   
	return $out;   
}
function export_csv($filename,$data)//导出CSV
{
	header("Content-type:text/csv");
	header("Content-Disposition:attachment;filename=".$filename);   
	header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
	header('Expires:0');
	header('Pragma:public');
	echo $data;
}

function default_classify_id($classify_id)
{
	if(get_classify_id()!='')$classify_id=get_classify_id();
	return $classify_id;
}
function default_content_id($content_id)
{
	if(pg('content_id')!='')$content_id=pg('content_id');
	return $content_id;
}





