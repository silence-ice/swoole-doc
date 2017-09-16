#Swoole入门(10) 
****
###什么是webscoket？
>在讲websocket之前先来说说ajax轮询和长连接。

>ajax轮询
>>ajax轮询就是前端通过setInterval()方法实现定时请求，向服务器拿信息，这时候不管服务器有没有信息都会一直发起请求。

>长连接
>>长连接和ajax轮询一样，一直不断得向服务器拿信息，只不过是当长连接没有向服务器拿到信息得时候会一直阻塞在那里。直到有消息才返回，返回完之后，客户端再次建立连接，周而复始。

>ajax轮询和长连接的缺点
>>两者都是在不断地建立HTTP连接，然后等待服务端处理，非常被动，非常消耗资源。
>>那为什么服务端不能主动联系客户端呢？这时候websocket就诞生了，这样服务端就可以主动推送信息给客户端了。

###实现WebScoket
>server.php

	<?php
	$server = new swoole_websocket_server("0.0.0.0", 9501);
	
	$server->on('open', function (swoole_websocket_server $server, $request) {
	    echo "server: handshake success with fd{$request->fd}\n";//$request->fd 是客户端id
	});
	
	$server->on('message', function (swoole_websocket_server $server, $frame) {
	    echo "receive from {$frame->fd}:{$frame->data},opcode:{$frame->opcode},fin:{$frame->finish}\n";
	    $server->push($frame->fd, "msg server to client");//$frame->fd 是客户端id，$frame->data是客户端发送的数据
	    //服务端向客户端发送数据是用 $server->push( '客户端id' ,  '内容')
	});
	
	$server->on('close', function ($ser, $fd) {
	    echo "client {$fd} closed\n";
	});
	
	$server->start();

>client.html

	<!DOCTYPE html>
	<html>
	<head>
	  <title></title>
	  <meta charset="UTF-8">
	  <script type="text/javascript">
	  var exampleSocket = new WebSocket("ws://0.0.0.0:9501");
	  exampleSocket.onopen = function (event) {
	    exampleSocket.send("亲爱的服务器！我连上你啦！"); 
	  };
	  exampleSocket.onmessage = function (event) {
	    console.log(event.data);
	  }
	  </script>
	</head>
	<body>
	<input  type="text" id="content">
	<button  onclick="exampleSocket.send( document.getElementById('content').value )">发送</button>
	</body>
	</html>

###代码结果输出
>【开启webSocket服务】：`root:/var/www/html/silence/swoole/websocket# php websocket.php`
>
>【浏览器输入URL】：`127.0.0.1/index.html`
>
>【server接收信息】：
>
	server: handshake success with fd1
	receive from 1:亲爱的服务器！我连上你啦！,opcode:1,fin:1
>>当开启另个浏览器，server接收的信息：
>>
	server: handshake success with fd2
	receive from 2:亲爱的服务器！我连上你啦！,opcode:1,fin:1
>
>【在两个浏览器页面input标签分别输入`client1`和`client2`信息】：
>
>>server收到的信息
	receive from 2:client2,opcode:1,fin:1
	receive from 1:client1,opcode:1,fin:1
>
>>浏览器console收到的信息
>>
	msg server to client
>
>【关闭浏览器`client1`】：
>>server收到的信息：
>>
	client 1 closed

###代码流程
>1.`new swoole_websocket_server("127.0.0.1", 9501);`，实现一个WebSocket服务器。
>
>2.客户端与服务器建立连接并完成握手后会回调此open()函数，其中$request参数是客户端请求对象信息。

>3.当服务器收到来自客户端的数据时会回调message()函数。

>4.收到信息收在message()函数中使用push()方法向websocket客户端连接推送数据。

>5.当客户端关闭连接会触发close()函数。
  
###聊天室实现原理
>在上面代码的基础上，当客户端建立起连接就保存该客户端的id，每当一个客户端发起消息的时候，服务器端就遍历存放的客户端id数据，为每一个客户端发送该消息数据，这样不就实现了聊天室功能了嘛~~~

###结语
>Swoole入门文章就到此结束来，这些文章知识简单的说了一下swoole皮毛，如果还需深入了解得靠自己啦。
>
>这里要再次吐槽一下，swoole得社区论坛真的很安静，要找问题goggle半天找不到，社区更不用说了，实在找不到问题答案只能自己看源码了，这多坑爹啊啊！！