<?php

class BaseProcess
{

    private $process;

    private $process_list = [];//子进程列表
    private $process_use = [];//已经使用的进程列表
    private $min_worker_num = 3;
    private $max_worker_num = 6;

    private $current_num;

    public function __construct()
    {
        //创建一个子进程，子进程调用run()方法
        $this->process = new swoole_process(array($this, 'run') , false , 2);
        $this->process->start();
		
		swoole_process::wait();
    }

    //子进程任务回调再创建数目子进程
    public function run()
    {
    	$this->current_num = $this->min_worker_num;

        //创建子进程
        for($i=0;$i<$this->current_num ; $i++){
            $process = new swoole_process(array($this, 'task_run') , false , 2);//创建子进程
            $pid = $process->start();//启动进程
            $this->process_list[$pid] = $process;//将进程对象存入进程列表
            $this->process_use[$pid] = 0;
        }

        //将新创建的子进程加入到swoole的reactor事件监听中，并作标记。
        foreach ($this->process_list as $process) {
        	swoole_event_add($process->pipe, function ($pipe) use($process) {
	            $data = $process->read();//子进程从管道中读取数据。
	            var_dump($data);
	            $this->process_use[$data] = 0;//子进程读完，将标记设置为0      
	        });
        }
		
        //每秒发放任务
        swoole_timer_tick(1000, function($timer_id) {
            static $index = 0;
            $index = $index + 1;
            $flag = true;
            foreach ($this->process_use as $pid => $used) {
            	if($used == 0)
            	{
                    //找到空闲子进程
            		$flag = false;
            		$this->process_use[$pid] = 1;//标记子进程为繁忙状态
					$this->process_list[$pid]->write($index . "Hello");//向管道中写数据
            		break;
            	}
            }
            //如果所有子进程都在繁忙状态，且进程小于最大进程，将会创建子进程
            if( $flag && $this->current_num < $this->max_worker_num )
            {
            	$process = new swoole_process(array($this, 'task_run') , false , 2);
	            $pid = $process->start();
	            $this->process_list[$pid] = $process;
	            $this->process_use[$pid] = 1;
				$this->process_list[$pid]->write($index . "Hello");
				$this->current_num ++;
            }
            var_dump($index);
            //执行完10次任务后，关闭子进程，清楚定时器
            if( $index == 10 )
            {
            	foreach ($this->process_list as $process) {
            		$process->write("exit");
            	}
                swoole_timer_clear($timer_id);
                $this->process->exit();
            }
        });
    }

    public function task_run($worker)
    {
    	swoole_event_add($worker->pipe, function ($pipe) use ($worker){
            $data = $worker->read();
            var_dump($worker->pid . ": " . $data);
            if($data == 'exit')
            {
                $worker->exit();
                exit;
            }
            //模拟5秒钟后处理完任务
            sleep(5);
            
            $worker->write("" . $worker->pid);
        });
    }
}

new BaseProcess();
