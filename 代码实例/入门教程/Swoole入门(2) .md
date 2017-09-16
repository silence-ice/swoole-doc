#Swoole入门(2)
****

###编码前的准备
>在编码之前需要**简单了解**下网络协议，下面主要介绍一下协议中的TCP、UDP协议，何两者区别是？

###何为网络通信协议？
>从计算机时代的开始，由一个个相互独立的计算机，到计算机和计算机互联，最后形成一个计算机互联网；而这其中的互联是就是通过互联网规定的通信协议来实现的，它规定了如何在两机之间传输，规定了传输数据的大小，方式。

###TCP协议
>
>TCP最主要的一个特点是**可靠**、**可靠**、**可靠**；即提供可靠的数据传输服务。
>
>那么TCP要保证可靠性，就需要在源和目的方建立一个**连接**来维持可靠性，而这个连接就叫**三次握手**。下面简单接收三次握手过程：
>
>>i. 主机A向主机B发出连接请求数据包：“我想给你发数据，可以吗？”，**这是第一次对话**；
>
>>ii. 主机B向主机A发送同意连接和要求同步的数据包：“可以，你什么时候发？”，**这是第二次对话**；
>
>
>>iv. 三次“对话”的目的是使数据包的发送和接收同步，经过三次“对话”之后，主机A才向主机B正式发送数据，这样就保证了两者通信的可靠和稳定。

###UDP协议
>UDP最主要的一个特点，就是**不可靠**；这个特点引发出下面几个特性：
>
>>所以在传输数据时不需要建立连接。
>
>>UDP无法保证数据能够准确的交付到目的主机。
>
>>UDP的数据包结构比TCP简单的多。

###QQ使用案例
>【QQ登陆过程】：在登陆过程，客户端client采用TCP协议向服务器server发送信息，HTTP协议下载信息。登陆之后，会有一个TCP连接来保持在线状态。
>
>【QQ发消息】：当和好友发消息，客户端client采用UDP协议，但是需要通过服务器转发。腾讯为了确保传输消息的可靠，采用上层协议来保证可靠传输。如果消息发送失败，客户端会提示消息发送失败，并可重新发送。
>
>【内网传输文件】：如果是在内网里面的两个客户端传文件，QQ采用的是P2P技术，不需要服务器中转。


###创建TCP服务器
>在简单介绍了TCP和UDP协议之后，我们来创建一个见到那的TCP服务器。

	$serv = new swoole_server("127.0.0.1", 9501); 
	
	$serv->set(array(
	    'reactor_num' => 2, //reactor thread num
	    'worker_num' => 4,    //worker process num
	    'backlog' => 128,   //listen backlog
	    'max_request' => 50,
	    'dispatch_mode' => 1,
	));

	$serv->on('connect', function ($serv, $fd) {  
	    echo "Client: Connect.\n";
	});
	
	$serv->on('receive', function ($serv, $fd, $from_id, $data) {
	    $serv->send($fd, "Server: ".$data);
	});
	
	$serv->on('close', function ($serv, $fd) {
	    echo "Client: Close.\n";
	});
	
	$serv->start(); 
>
####下面简单介绍一下每一行的代码：
>`new swoole_server(string $host, int $port, int $mode = SWOOLE_PROCESS,
    int $sock_type = SWOOLE_SOCK_TCP);`
>>1.指定监听的ip地址，可以是内网地址、外网地址、IPv6地址；其中0.0.0.0监听全部地址。

>>2.指定监听的端口；需要注意的是小于1024端口是需要root权限；若端口已被占用会报错。

>>3.运行的模式，swoole提供了3种运行模式，默认为多进程模式。

>>4.指定socket的类型，支持TCP/UDP、TCP6/UDP6、UnixSock Stream/Dgram 6种

>`swoole_server->set`

>>该函数用于设置swoole_server运行时的各项参数。
>
>>`reactor_num => 2`，此参数是用户来调节poll线程的数量，以充分利用多核。

>>`worker_num => 4`，此参数用来设置启动的worker进程数量。swoole采用固定worker进程的模式。
>>>如果PHP代码中是全异步非阻塞，worker_num配置为CPU核数的1-4倍即可。
>>
>>>如果是同步阻塞，worker_num配置为100或者更高，具体要看每次请求处理的耗时和操作系统负载状况。
>
>>>当设定的worker进程数小于reactor线程数时，会自动调低reactor线程的数量。

>>`backlog => 128`
>>>此参数将决定最多同时有多少个待accept的连接，swoole本身accept效率是很高的，基本上不会出现大量排队情况。

>>max_request => 2000
>>>此参数表示worker进程在处理完n次请求后结束运行。manager会重新创建一个worker进程。此选项用来防止worker进程内存溢出。

>>>设置为0表示不自动重启。

>`$serv->on`表示注册事件回调函数。
>>1.回调的名称,但是大小写不敏感。
>
>>2.回调的PHP函数，可以是函数名的字符串，类静态方法，对象方法数组，匿名函数。
>
>>connect表示监听连接进入事件、receive表示监听数据接收事件、close表示监听连接关闭事件。

>`swoole_server->send(int $fd, string $data, int $reactorThreadId = 0);`表示向客户端发送数据。
>
>>$fd就是客户端连接的唯一标识符
>
>>$data，发送的数据。TCP协议最大不得超过2M，UDP协议不得超过64K
>
>>发送成功会返回true。发送失败会返回false。

>`swoole_server->start()`
>>启动swoole_server，并监听所有TCP/UDP端口，函数原型：
>
>>启动成功后会创建worker进程(可设置)+2个进程。这两个主要进程为**主进程**+**Manager进程**。

###运行代码
>再运行的代码之前，需要知道Swoole的绝大部分功能只能用于cli命令行环境。所以再命令行中使用`php server.php`即可。
>
>ok，没有报错，再查看使用正常运行`netstat -tulnp | grep 9501`，如果端口存在表示TCP服务端正在运行且正在监听中。
>
	root@silence-host:/home/silence# netstat -tulnp | grep 9501
	tcp        0      0 0.0.0.0:9501            0.0.0.0:*               LISTEN      2061/php

>此时打开另一个终端，使用telnet和TCP服务端进行通信：`telnet 127.0.0.1 9501`
>
>输入hello，结果如下，其中`Server: hello`是服务器端返回的消息；就这样成功创建一个简单的TCP服务器了。
	telnet 127.0.0.1 9501
	hello
	Server: hello

###创建UDP服务器
>创建UDP服务器和创建TCP服务器大致原理相同，不同的是UDP协议不可靠，没有建立连接的概念，只负责接收发送。

	$serv = new swoole_server("127.0.0.1", 9502, SWOOLE_PROCESS, SWOOLE_SOCK_UDP); 
	
	$serv->on('Packet', function ($serv, $data, $clientInfo) {
	    $serv->sendto($clientInfo['address'], $clientInfo['port'], "Server ".$data);
	    var_dump($clientInfo);
	});
	
	$serv->start();
>
>1.其中再创建Server对象需要注意的是，创建类型为SWOOLE_SOCK_UDP，表示UDP协议。
>
>2.UDP注册的事件是Packet。
>
>3.`woole_server->sendto(string $ip, int $port, string $data, int $server_socket = -1);`向任意的客户端IP:PORT发送UDP数据包。
>
>>$ip为IPv4字符串，如192.168.1.102。如果IP不合法会返回错误
>
>>$port为 1-65535的网络端口号，如果端口错误发送会失败
>
>>$data要发送的数据内容，可以是文本或者二进制内容
>

###结语
>接下来继续深入了解swoole的task模块把！