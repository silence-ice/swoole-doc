<?php

$serv = new swoole_http_server("127.0.0.1", 9501);

//方便测试
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
