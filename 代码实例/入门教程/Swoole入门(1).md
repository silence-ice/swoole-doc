#Swoole入门(1)
****

###什么是swoole？
>Swoole允许通过PHP构造一个新的Server，提供跟Apache类似的功能，监听请求，作出响应。
>
>这个时候PHP不再是写Web业务逻辑的PHP了，它参与了Server的构建，成为一个Network Server，也不需要依赖与apache/nginx，因此访问该server是通过cli模式，不能再通过浏览器访问了。

###PHP和swoole到底是什么关系呢？
>很多编码人都认为PHP语言大部分都用来做Web开发，更有人认为只能做Web；把PHP黑的可惨，此次我必须得替天行道，匡扶正义，还PHP一个清白！！
>
>作为一个PHPer，我必须承认PHP很难实现如：网络通信编程、异步IO、异步文件读写，异步DNS查询等牛逼哄哄的功能，而这时候swoole诞生了，它是PHP的一个标准扩展；是一个基于网络通信和异步IO的引擎，有了它就能更加方便实现如上说的各种功能。

###使用案例
>一开再质疑swoole的质量和广度，也不清楚到底学不学是好，万一辛苦学下了，发现没有人维护了，淘汰了，那才杯具呢，之后百度一通，发现虎牙直播、YY语音、战旗TV这些大户都在使用swoole，那我有何担心呢？

>再看看swoole的社区，发觉社区论坛寥寥无几，毫无生气，版本迭代有点慢；又开始担心起来了，不过再仔细想想，学习swoole，可以学习网络协议、阻塞、IO复用、多进程、websocket、异步mysql/redis各种模糊的东西，足以足以。

###开发环境
>系统环境：Ubuntu 16.04
>
>开发环境：PHP7.0+Nginx+FPM
>
>鄙人再安装swoole扩展的时候遇到个问题，phpinfo是有swoole信息了，但是每次实例化都显示无法识别swoole对象，找了很久发现是在配置php.ini添加swoole模块的时候需要对：`/etc/php/7.0/cli/php.ini`，`/etc/php/7.0/fpm/php.ini`两个文件添加。
>
>更简单的方法是使用pecl一键安装，毕竟swoole已经被官方PHP收录了。省了很多麻烦事情，命令如下：`pecl install swoole`


###swoole结构
>在编码之前最好熟悉下swoole大体结构，不然编码下去也是一头雾水，云里雾里的：
>
![](http://i.imgur.com/EC2Krlw.jpg)

>Master主进程：
>>swoole启动后主线程，主进程由多个Reactor线程，基于epoll/kqueue进行网络事件轮询。
>
>>主进程负责监听server socket，当有请求过来时，Master会评估每个Reactor线程的连接数量。将此连接分配给连接数最少的reactor线程，再转发到worker进程进行处理。

>Manager进程：
>>swoole启动后会创建一个单独的Manager进程，用来管理worker进程的生命周期并监视进程的异常和回收。
>
>>当Reactor线程通过管道传给Manager进程的时候，Manager进程会fork出一个Worker进程或者Task进程
>
>>同时当Worker/Task进程发生致命错误或者运行生命周期结束时，管理进程会回收此进程，并创建新的进程。

>Worker进程：
>>Worker进程用来发送/接收数据，处理一些业务逻辑等任务。

>Task进程：
>>Task进程目的是为了解决在业务代码中，有些逻辑部分不需要马上执行。利用task进程池，可以方便的投递一个异步任务去执行，在Worker进程空闲时再去捕获任务执行的结果。

###结语
>各位对swoole有了大概的了解把，下一节就开始实现一个简单的实例！