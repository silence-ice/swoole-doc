<?php
class MySQLPool
{
    private $serv;
    private $pdo;
    public function __construct() {
        $this->serv = new swoole_server("0.0.0.0", 9501);
        $this->serv->set(array(
            'worker_num' => 8,
            'daemonize' => false,
            'max_request' => 10000,
            'dispatch_mode' => 3,
            'debug_mode'=> 1 ,
            'task_worker_num' => 8
        ));
        $this->serv->on('WorkerStart', array($this, 'onWorkerStart'));
        $this->serv->on('Connect', array($this, 'onConnect'));
        $this->serv->on('Receive', array($this, 'onReceive'));
        $this->serv->on('Close', array($this, 'onClose'));
        // bind callback
        $this->serv->on('Task', array($this, 'onTask'));
        $this->serv->on('Finish', array($this, 'onFinish'));
        $this->serv->start();
    }
    public function onConnect( $serv, $fd, $from_id ) {
        echo "Client {$fd} connect\n";
    }
    public function onClose( $serv, $fd, $from_id ) {
        echo "Client {$fd} close connection\n";
    }

    public function onWorkerStart( $serv , $worker_id) {
        //echo "onWorkerStart\n";
        if($serv->taskworker){
            $this->pdo = mysqli_connect("127.0.0.1", "root", "123456", "silence");;
            echo "Task Worker\n";
        } else {
            echo "Worker Process\n";
        }
    }

    public function onReceive( swoole_server $serv, $fd, $from_id, $data ) {
        
        $task = [
            'sql' => 'insert into user(username) values (?)',
            'user_name' => 'silence',
            'fd' => $fd
        ];
        $serv->task(json_encode($task));
    }

    public function onTask($serv,$task_id,$from_id, $data) {
        try{
            $data = json_decode($data,true);
            $statement = $this->pdo->prepare($data['sql']);
            $statement->bind_param('s',  $data['user_name']);
            $statement->execute();

            $serv->send($data['fd'] , "Insert succeed");
            return "true";
        } catch( PDOException $e ) {
            var_dump( $e );
            return "false";
        }
    }
    public function onFinish($serv,$task_id, $data) {
        var_dump("result: " + $data);
    }
}
new MySQLPool();
