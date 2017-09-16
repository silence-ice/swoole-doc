#Swoole入门(9)
****
###一个完整的HTTP请求
>在实现HttpServer之前，先了解下HTTP请求的一个完整过程。
>
![](http://i.imgur.com/LP6Cke0.png)

>>1.客户输入`www.baidu.com`域名
>
>>2.DNS进行域名解析获得IP
>
>>3.发起TCP的3次握手
>
>>4.建立TCP连接后发起http请求
>
>>5.服务器响应http请求，浏览器得到html代码
>
>>6.浏览器解析html代码，并请求html代码中的资源（如js、css、图片等）
>
>>7.浏览器对页面进行渲染呈现给用户



###实现HttpServer，并支持pathinfo模式
	
	<?php
	$serv = new swoole_http_server("127.0.0.1", 9501);
	
	$serv->set([
	    'worker_num' => 1
	]);
	
	$serv->on('Start' , function(){
	    swoole_set_process_name('simple_route_master');//设置master进程名
	});
	
	$serv->on('ManagerStart' , function(){
	    swoole_set_process_name('simple_route_manager');//设置manager进程名
	});
	
	$serv->on('WorkerStart' , function(){
	    swoole_set_process_name('simple_route_worker');//设置worker进程名
	
	    spl_autoload_register(function($class){
	        $baseClasspath = \str_replace('\\', DIRECTORY_SEPARATOR , $class) . '.php';
	
	        $classpath = __DIR__ . '/' . $baseClasspath;
	        if (is_file($classpath)) {
	            require "{$classpath}";
	            return;
	        }
	    });
	
	});
	
	$serv->on('Request', function($request, $response) {
	
	    $path_info = explode('/',$request->server['path_info']);
	
	    if( isset($path_info[1]) && !empty($path_info[1])) {  // ctrl
	        $ctrl = 'ctrl\\' . $path_info[1];
	    } else {
	        $ctrl = 'ctrl\\Index';
	    }
	    if( isset($path_info[2] ) ) {  // method
	        $action = $path_info[2];
	    } else {
	        $action = 'index';
	    }
	
	    $result = "Ctrl not found";
	    if( class_exists($ctrl) )
	    {
	        $class = new $ctrl();
	
	        $result = "Action not found";
	
	        if( method_exists($class, $action) )
	        {
	            $result = $class->$action($request);
	        }
	    }
	
	    $response->end($result);
	});
	
	$serv->start();

###代码目录结构

	-webroot
	  -ctrl
		--Index.php（输出Hello World信息）
		--Login.php（输出login success信息）
	  -simple_route.php	


###代码结果输出
>访问URL：http://192.168.2.119:9501/Login/login
>>输出login success

>访问URL：http://192.168.2.119:9501/Index
>>输出Hello World

###代码流程
>1.`new swoole_http_server("127.0.0.1", 9501);`，实现一个HTTP服务器。
>
>2.设置worker进程数为1个，方便测试。同时为master、manager、worker进程设置别名。
>
>3.注册__autoload()函数 。

>4.设置Http的Request请求对象，使其支持pathinfo模式。并让其默认跳转至Index/index方法中。

>5.Http服务器发送Http响应，并结束请求处理。
  
###热加载问题
>1.当新版本功能上线，有代码改动的时候，就需要实现代码热加载保证请求最新的代码。
>
>2.先查看当前已设置别名的进程。
>
	root:/home/silence# ps -aux | grep simple_route_*
	root      2349  0.0  0.4 255204 28372 pts/9    Sl+  09:40   0:00 simple_route_master
	root      2350  0.0  0.1 180960  7944 pts/9    S+   09:40   0:00 simple_route_manager
	root      2352  0.0  0.1 183296 10668 pts/9    S+   09:40   0:00 simple_route_worker
>3.只需要实现实时的正常杀死**未接收请求的worker进程**，保证平滑过渡就能很好的实现热加载了。

###结语
>下一节讲解如何用swoole创建WebScoket服务器。