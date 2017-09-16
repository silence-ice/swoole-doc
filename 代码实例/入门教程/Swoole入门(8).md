#Swoole入门(8) 
****
>什么是消息队列?
>>消息队列是保存消息数据得容器，按照队列数据结构，先进先出得获取数据；
>
>>消息可以得结构很随意，可以是字符串、对象、集合。

>为什么使用消息队列？
>>消息队列不仅可以在进程之间通信，也成为系统之间通信交互得介质。

>>消息队列可以将业务逻辑解耦，比如发送邮件不需要等待整个流程结束才能执行下一个步骤。

>>消息队列可以保证数据按照队列数据结构处理。
>
>>消息队列再另一个角度来看就是一个异步通信机制。


###进程间消息队列通信

	<?php
	class BaseProcess
	{
	
	    private $process;
	
	    public function __construct()
	    {
	        $this->process = new swoole_process(array($this, 'run') , false , true);
	        if( !$this->process->useQueue( 123 ) )
	        {
	            var_dump(swoole_strerror(swoole_errno()));
	            exit;
	        }
	        $this->process->start();
	
	        while(true)
	        {
	            $data = $this->process->pop();
	            echo "RECV: " . $data.PHP_EOL;
	        }
	    }
	
	    public function run($worker)
	    {
	        swoole_timer_tick(1000, function($timer_id ) {
	            static $index = 0;
	            $index = $index + 1;
	            $this->process->push("Hello");
	            var_dump($index);
	            if( $index == 10 )
	            {
	                swoole_timer_clear($timer_id);
	            }
	        });
	    }
	}

###代码结果输出

	root:/var/www/html/silence/swoole/process# php queue.php 
	RECV: Hello
	int(1)
	int(2)
	RECV: Hello
	int(3)
	RECV: Hello
	int(4)
	RECV: Hello
	int(5)
	RECV: Hello
	int(6)
	RECV: Hello
	int(7)
	RECV: Hello
	int(8)
	RECV: Hello
	int(9)
	RECV: Hello
	int(10)
	RECV: Hello

###代码流程
>1.创建子进程
>
>2.启用消息队列作为进程间通信，且key为123；注意key结构必须是int型。
>
>3.子进程使用定时器每一秒向队列中推送`Hello`字符串，推送10个之后取消定时器。

>4.父进程不断监听消息队列，如果有就拿出来。

  
###结语
>下一节讲解如何用swoole创建HTTP SERVER。