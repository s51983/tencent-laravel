<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));
define('TEXT_REG', '#\.html.*|\.js.*|\.css.*|\.html.*#');
define('BINARY_REG', '#\.ttf.*|\.woff.*|\.woff2.*|\.gif.*|\.jpg.*|\.png.*|\.jepg.*|\.swf.*|\.bmp.*|\.ico.*#');

// auto make dir /tmp/storage/framework/views
system("mkdir -p /tmp/storage/framework/views");

/**
 * handler static files
 */
function handlerStatic($path)
{
    $filename = __DIR__ . "/public" . $path;
    $handle   = fopen($filename, "r");
    $contents = fread($handle, filesize($filename));
    fclose($handle);

    $base64Encode = false;
    $headers = [
        'Content-Type'  => '',
        'Cache-Control' => "max-age=8640000",
        'Accept-Ranges' => 'bytes',
    ];
    $body = $contents;
    if (preg_match(BINARY_REG, $path)) {
        $base64Encode = true;
        $headers = [
            'Content-Type'  => '',
            'Cache-Control' => "max-age=86400",
        ];
        $body = base64_encode($contents);
    }
    return [
        "isBase64Encoded" => $base64Encode,
        "statusCode" => 200,
        "headers" => $headers,
        "body" => $body,
    ];
}

function handler($event, $context)
{
    require __DIR__ . '/vendor/autoload.php';

    $app = require __DIR__ . '/bootstrap/app.php';

    // init path
    $path = str_replace("//", "/", $event->path);

    if (preg_match(TEXT_REG, $path) || preg_match(BINARY_REG, $path)) {
        return handlerStatic($path);
    }

    // init body
    $req = $event->body ?? '';

    // init headers
    $headers = $event->headers ?? [];
    $headers = json_decode(json_encode($headers), true);

    // init data
    $data = !empty($req) ? json_decode($req, true) : [];
    // 实际运行时$event->path不包含url参数，在此处补全
    if($event->queryString){
        $data = array_merge($data, json_decode(json_encode($event->queryString),true));
    }
  
    // execute laravel app request, get response
    $kernel = $app->make(Kernel::class);
    $request = Request::create($path, $event->httpMethod, $data, [], [], $headers);
    $response = $kernel->handle(
        $request
    );
  
    // 支持teminate中间件
    $kernel->terminate($request, $response);

    $headers=[];
    foreach($response->headers->all() as $key=>$value){
        $headers[$key] = implode(';', $value);
    }
    if(!isset($headers['content-type'])){
        $headers['content-type'] = $request->ajax()?'application/json':'text/html; charset=UTF-8';
    }

    return [
        'isBase64Encoded' => false,
        'statusCode' => $response->getStatusCode(),
        'headers' => $headers,
        'body' => $response->getContent()
    ];
}
