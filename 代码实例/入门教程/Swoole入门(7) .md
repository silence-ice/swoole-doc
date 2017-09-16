#Swoole入门(7) 
****

###子进程创建
>这一篇主要实现子进程的创建和使用

	<?php
	class BaseProcess
	{
	    private $process;                   // process对象
	
	    public function __construct()
	    {
	        $this->process = new swoole_process(array($this, 'run') , false , true);
	        $this->process->start();
	    }
	
	    public function run($worker)
	    {
	        $worker->name("swoole_process");   // 设置进程名
	        for($i = 0; $i < 10; $i ++)
	        {
	            echo "process: {$i}\n";
	        }
	    }
	}
	
	new BaseProcess();
	//SIGCHLD，在一个进程终止或者停止时，将SIGCHLD信号发送给其父进程，按系统默认将忽略此信号，如果父进程希望被告知其子系统的这种状态，则应捕捉此信号。
	swoole_process::signal(SIGCHLD, function($sig) {
	  //1.信号发生时可能同时有多个子进程退出；必须循环执行wait直到返回false
	  //2.如果是异步信号回调必须为false，非阻塞模式
	  while($ret =  swoole_process::wait(false)) {
	      echo "PID={$ret['pid']}\n";
	  }
	});

###代码结果输出

	root:/var/www/html/silence/swoole/process# php process.php 
	process: 0
	process: 1
	process: 2
	process: 3
	process: 4
	process: 5
	process: 6
	process: 7
	process: 8
	process: 9
	PID=2996

###代码流程
>1.创建子进程
>
>2.子进程创建成功后要执行的run()函数
>
>3.异步信号监听到子进程结束,执行相关代码

###相关函数
>int swoole_process::__construct(mixed $function, $redirect_stdin_stdout = false, $create_pipe = true);

>>$function，子进程创建成功后要执行的函数。
>
>>$redirect_stdin_stdout，重定向子进程的标准输入和输出。启用此选项后，在进程内echo将不是打印屏幕，而是写入到管道。读取键盘输入将变为从管道中读取数据。默认为阻塞读取。

>>$create_pipe，是否创建管道，启用$redirect_stdin_stdout后，此选项将忽略用户参数，强制为true 如果子进程内没有进程间通信，可以设置为false。


>int swoole_process->start();
>>执行fork系统调用，启动进程。
>
>>创建成功返回子进程的PID，创建失败返回false。可使用swoole_errno和swoole_strerror得到错误码和错误信息。

>>执行后子进程会保持父进程的内存和资源，如父进程内创建了一个redis连接，那么在子进程会保留此对象，所有操作都是对同一个连接进行的。

>bool swoole_process::name(string $new_process_name);
>>修改进程名称。此函数是swoole_set_process_name的别名。
>
>>$process->name("php server.php: worker");

>array swoole_process::wait(bool $blocking = true);

>>1.回收结束运行的子进程。子进程结束必须要执行wait进行回收，否则子进程会变成僵尸进程

>>2.$blocking 参数可以指定是否阻塞等待，默认为阻塞，如果是异步信号回调必须为false，非阻塞模式

>>3.操作成功会返回返回一个数组包含子进程的PID、退出状态码、被哪种信号KILL，失败返回false。形如`$result = array('code' => 0, 'pid' => 15001, 'signal' => 15);`

 
>bool swoole_process::signal(int $signo, callable $callback);

>>设置异步信号监听。
>
>>此方法基于signalfd和eventloop是异步IO，不能用于同步程序中
>
>>同步阻塞的程序可以使用pcntl扩展提供的pcntl_signal
>
>>$callback如果为null，表示移除信号监听


  
###结语
>下一节用消息队列实现进程间通信。