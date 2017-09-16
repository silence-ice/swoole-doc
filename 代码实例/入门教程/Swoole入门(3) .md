#Swoole入门(3)
****

###task模块的用途
>task模块可以用来一些异步的慢速任务，比如广播消息，发送群邮件等等；同时还支持PHP的数据库连接池，异步队列等，功能很强大。
>
>当swoole接收到任务时，worker进程将任务丢给task进程之后，worker进程可以继续处理新的数据请求。任务完成后会异步地通知worker进程告诉它此任务已经完成。

###再次深入了解Reactor、Worker、Task的关系 
>Reactor线程
>>Reactor线程以**多线程、异步非阻塞模式**接收**客户端机器**的TCP连接、处理网络IO、收发数据；
>
>>Reactor层面的代码全部为C代码，除Start/Shudown事件回调外，不执行任何PHP代码
>
>>若连接为TCP连接，Reactor将发来的数据缓冲、拼接、拆分成完整的一个请求数据包。

>Worker进程
>>Worker进程以**多进程模式**接受由Reactor线程投递的请求数据包，并执行PHP回调函数处理数据；并生成响应数据并发给Reactor线程，由Reactor线程发送给TCP客户端。

>>可以是异步非阻塞模式，也可以是同步阻塞模式

>Task进程
>>Task进程以**多进程模式**接受由Worker进程通过swoole_server->task/taskwait方法投递的任务，处理任务后，并将结果数据返回给Worker进程。
>
>>完全是同步阻塞模式

>三者关系：
>>假设Server就是一个工厂，那reactor就是销售，帮你接项目订单。而worker就是工人，当销售接到订单后，worker去工作生产出客户要的东西。而task_worker可以理解为行政人员，可以帮助worker干些杂事，让worker专心工作。


###示例代码1
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

####示例一代码结果
>server.php

	root:/var/www/html/silence/swoole/course/task# php Server.php 
	Start
	Client 1 connect
	Get Message From Client 1:huangbin
	
	This Task 0 from Worker 2
	string(18) "{"index":0,"fd":1}"
	Task 0 finish
	Result: Finished

>client.php

	root:/var/www/html# telnet 127.0.0.1 9501
	Trying 127.0.0.1...
	Connected to 127.0.0.1.
	Escape character is '^]'.
	huangbin

####示例1工作流程
![](http://i.imgur.com/lt6rOPc.jpg)
>
>worker进程触发task()方法，将任务投递到task进程，task调用onTask()方法处理任务，处理完成后在该方法return消息，并触发worker进程的onFinsh()方法。

####示例1代码流程
>1.构造函数处注册Task事件回调函数为Task、Finish，分别指向onTask()、onFinish()方法

>2.onTask()方法
>>onTask()方法在task_worker进程内被调用。当worker进程向task_worker进程投递新的任务时。当前的Task进程在调用onTask回调函数时会将进程状态切换为忙碌，这时将不再接收新的Task，当onTask函数返回时会将进程状态切换为空闲然后继续接收新的Task。
>
>>`function onTask(swoole_server $serv, int $task_id, int $src_worker_id, mixed $data);`
>>>$task_id是任务ID，由swoole扩展内自动生成，用于区分不同的任务。
>>
>>>$src_worker_id来自于哪个worker进程
>>
>>>$data 是任务的内容
>
>>>【注意】：$task_id和$src_worker_id组合起来才是全局唯一的，不同的worker进程投递的任务ID可能会有相同
>

>>当任务完成后，可在onTask函数中使用**return**字符串，表示将此内容返回给worker进程。worker进程中会触发onFinish函数，表示投递的task已完成，也就是说return的数据是返回给onFinish函数。

>>【注意】：onTask函数执行时遇到致命错误退出，或者被外部进程强制kill，当前的任务会被丢弃，但不会影响其他正在排队的Task
    
    
>2.onFinish()方法
>>当worker进程投递的任务在task_worker中完成时，task进程会调用swoole_server->finish()方法将任务处理的结果发送给worker进程。
>
>>`void onFinish(swoole_server $serv, int $task_id, string $data)`
>>>$task_id是任务的ID
>
>>>$data是任务处理的结果内容

>>【注意】
>>>执行onFinish方法的worker进程与下发task任务的worker进程是同一个进程

>>>如果task进程的onTask事件中没有调用finish方法或者return结果，worker进程不会触发onFinish

####示例1注意事项
>1.worker进程传递给worker进程的数据，也就是task($data)中的data数据小于8K是通过管道传输，大于8K是通过临时文件的写入进行传递。同时data数据只能是一个字符串，所以需要用json进行打包。

>2.若在task进程需要发送数据给client，这时候需要在worker进程中传递client的fd，来能保证传递到正确的client。

>3.worker进程和task进程的对象是不会共享的，毕竟两者的内存是不共享的。下面使用一个实例证明一下：
>>onReceive()和onFinish()方法同属worker进程，test对象共享；而onTask()方法是task进程test对象独立，不和worker进程共享。

	class Test
	{
	    public $index = 0;
	}

	class Server
	{
	    public function onReceive( swoole_server $serv, $fd, $from_id, $data ) {
	        $this->test = new Test();
	        var_dump($this->test);
	      
	        $serv->task( serialize($this->test) );
	    }
	
	    public function onTask($serv,$task_id,$from_id, $data) {
		
	        $data = unserialize($data);
	        $data->index = 2;
	
	        $this->test = new Test();
	        $this->test->index = 2;
	
	        return "Finished";
	    }
	    public function onFinish($serv,$task_id, $data) {      
	        var_dump($this->test);
	    }
	}


###mysql连接池的介入

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


>上面主要展示了如何在worker进程和task进程进行mysql数据交互。

>`function onWorkerStart(swoole_server $server, int $worker_id);`
>>此事件在worker进程/task进程启动时发生。这里可以创建如mysql对象，可以保证在进程生命周期内正常使用;

>`swoole_server::$taskworker `表示是否为task进程，是为true。

>worker_id表示当前worker进程的id

###结语
>接下来继续深入了解swoole的**毫秒级**定时器模块把！