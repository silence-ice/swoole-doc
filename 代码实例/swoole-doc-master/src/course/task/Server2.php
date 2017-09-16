<?php

class Test
{
    public $index = 0;
    public $fd = 0;
}

class Server
{
    private $serv;
    private $test;

    public function __construct() {
        $this->serv = new swoole_server("0.0.0.0", 9501);
        $this->serv->set(array(
            'worker_num' => 8,
            'daemonize' => false,
            'max_request' => 10000,
            'dispatch_mode' => 2,
            'task_worker_num' => 8
        ));
        $this->serv->on('Start', array($this, 'onStart'));
        $this->serv->on('Connect', array($this, 'onConnect'));
        $this->serv->on('Receive', array($this, 'onReceive'));
        $this->serv->on('Close', array($this, 'onClose'));
        // bind callback
        $this->serv->on('Task', array($this, 'onTask'));
        $this->serv->on('Finish', array($this, 'onFinish'));
        $this->serv->start();
    }
    public function onStart( $serv ) {
        echo "Start\n";
    }
    public function onConnect( $serv, $fd, $from_id ) {
        echo "Client {$fd} connect\n";
    }
    public function onClose( $serv, $fd, $from_id ) {
        echo "Client {$fd} close connection\n";
    }

    public function onReceive( swoole_server $serv, $fd, $from_id, $data ) {
        //此处data是client传送过来的
        echo "Get Message From Client {$fd}:{$data}\n";
        $this->test = new Test();
        $this->test->fd = $fd;
        // var_dump($this->test);
        
        //task方法传递只能传递一个字符串，所以需要用json打包
        $serv->task( json_encode($this->test) );
    }

    public function onTask($serv,$task_id,$from_id, $data) {
        //from_id表示worker进程号
        echo "This Task {$task_id} from Worker {$from_id}\n";

        var_dump($data);

        $data = json_decode($data, true);

        //发送给客户端
        $serv->send($data['fd'], 'Task is over!');
        //返回给worker进程表示完成任务
        return "Finished";
    }
    public function onFinish($serv,$task_id, $data) {
        echo "Task {$task_id} finish\n";
        //此处data就是onTask()中return的数据
        echo "Result: {$data}\n";
        
    }
}
$server = new Server();