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