#Swoole入门(4)
****

###crontab服务 VS 基于swoole的定时器
>一般开发者都是使用linux自带的crontab定时处理服务，crontab服务够稳定，有日志，基于服务器，非常好用；但是有个缺点就是只支持**分钟**时间级别的定时器。
>
>而swoole的**timer定时器**完全可以弥补这个缺点，支持**毫秒级**的定时器，同时**支持异步操作**！

###timer定时器
>timer定时器基于Reactor线程，在Worker进程和Task进程使用。

>timer定时器基于epoll的timeout机制实现。
>
>timer定时器使用最小堆数据结构存放定时任务，触发时间越短越靠近堆顶，大大提高了检索效率。


###示例代码

	<?php
	
	class Test
	{
	    public $index = 0;
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
	        ));
	        $this->serv->on('Start', array($this, 'onStart'));
	        $this->serv->on('Connect', array($this, 'onConnect'));
	        $this->serv->on('Receive', array($this, 'onReceive'));
	        $this->serv->on('Close', array($this, 'onClose'));
	        $this->serv->on('WorkerStart', array($this, 'onWorkerStart'));
	
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
	
	    public function onWorkerStart( $serv , $worker_id) {
	        if( $worker_id == 0 )
	        {
	            $this->test=new Test();
	            $this->test->index = 1;
	            swoole_timer_tick(1000, array($this, 'onTick'), "Hello");
	        }
	    }
	
	    public function onReceive( swoole_server $serv, $fd, $from_id, $data ) {
	        echo "Get Message From Client {$fd}:{$data}\n";
	      
	        echo "Continue Handle Worker\n";
	    }
	
	    public function onTick($timer_id,  $params = null) {
	        echo "Timer {$timer_id} running\n";
	        echo "Params: {$params}\n";
	        
	        echo "Timer running\n";
	        echo "recv: {$params}\n";
	
	        var_dump($this->test);
	    }
	}
	
	$server = new Server();

####代码输出
	root:/var/www/html/silence/swoole/timer# php timer.php 
	Start
	Timer 1 running
	Params: Hello
	Timer running
	recv: Hello
	object(Test)#5 (1) {
	  ["index"]=>
	  int(1)
	}
	Timer 1 running
	Params: Hello
	Timer running
	recv: Hello
	object(Test)#5 (1) {
	  ["index"]=>
	  int(1)
	}

####代码流程
>定时器在worker进程启动时就启动，因为不希望每个worker进程都启动该定时器，所以用了workerid进行判断，只在第一个worker进程中启动。

>swoole_timer_tick(int $ms, callable $callback, mixed $user_param);
>>设置一个间隔时钟定时器，该定时器会一直持续触发，直到调用swoole_timer_clear清除该定时器。

>>$ms 指定时间，单位为毫秒
>
>>$callback_function 时间到期后所执行的函数，必须是可以调用的。
>
>>$user_param 可以传一些业务参数, 该参数会被传递到$callback_function中. 如果有多个参数可以使用数组形式。

>>注意：
>>>定时器仅在当前进程空间内有效
>
>>>定时器是纯异步实现的，不能与阻塞IO的函数(如sleep()函数)一起使用，否则定时器的执行时间会发生错乱

>>>与其相对立的是swoole_timer_after()函数，还函数只会调用一次。

>onTick($timer_id,  $params = null)；上文中的定时器异步回调函数是该函数。
>>$timer_id 定时器的ID，可用于swoole_timer_clear清除此定时器
>
>>$params 由swoole_timer_tick传入的第三个参数


>swoole_timer_clear(int $timer_id)是使用定时器ID来删除定时器。
>
>>$timer_id，定时器ID，调用swoole_timer_tick、swoole_timer_after后会返回一个整数的ID
>
>>swoole_timer_clear不能用于清除其他进程的定时器，只作用于当前进程

###结语
>接下来继续给大家讲讲进程相关知识，同时学习对swoole**进程**的使用。