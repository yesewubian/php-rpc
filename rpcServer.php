<?php
/**
 * Created by PhpStorm.
 * User: 28981
 * Date: 2017/6/12
 * Time: 16:32
 */
class RpcServer {
    protected $serv = null;

    public function __construct($host, $port, $path) {
        //创建一个tcp socket服务
        $this->serv = stream_socket_server("tcp://{$host}:{$port}", $errno, $errstr);
        if (!$this->serv) {
            exit("{$errno} : {$errstr} \n");
        }
        //判断我们的RPC服务目录是否存在
        $realPath = realpath(__DIR__ . $path);
        if ($realPath === false || !file_exists($realPath)) {
            echo __DIR__ . $path."\n";
            echo $realPath;echo "\n";
            exit("{$path} error \n");
        }

        while (true) {
            $client = stream_socket_accept($this->serv,30);

            if ($client) {
                //这里为了简单，我们一次性读取
                $buf = fread($client, 2048);
                //解析客户端发送过来的协议
                $classRet = preg_match('/Rpc-Class:\s(.*);(\r\n|\n|\r)/i', $buf, $class);
                $methodRet = preg_match('/Rpc-Method:\s(.*);(\r\n|\n|\r)/i', $buf, $method);
                $paramsRet = preg_match('/Rpc-Params:\s(.*);/i', $buf, $params);

                if($classRet && $methodRet) {
                    $class = ucfirst($class[1]);
                    $file = $realPath . '/' . $class . '.php';
                    //判断文件是否存在，如果有，则引入文件
                    if(file_exists($file)) {
                        require_once $file;
                        //实例化类，并调用客户端指定的方法
                        $obj = new $class();
                        //如果有参数，则传入指定参数
                        $method = $method[1];
                        if($params[1]=='') {
                            $data = $obj->$method();
                        } else {
                            $data = $obj->$method(json_decode($params[1], true));
                        }
                        //把运行后的结果返回给客户端
                        fwrite($client, $data);
                    }
                } else {
                    fwrite($client, 'class or method error');
                }
                //关闭客户端
                fclose($client);
            }
        }
    }

    public function __destruct() {
        fclose($this->serv);
    }
}

new RpcServer('127.0.0.1', 8888, '/service');
