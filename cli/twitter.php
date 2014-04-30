<?php
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Subscriber\Oauth\Oauth1;
use GuzzleHttp\Stream as Stream;

//autoloading and config
require_once '../vendor/autoload.php';

$getStream = function(){
    $config = include '../config.php';
    error_log('loaded config');

    //http client
    $client = new HttpClient();

    //oauth setup
    $oauth = new Oauth1($config['oauth']);
    $client->getEmitter()->attach($oauth);

    error_log('setup oauth');

    //tracked keywords
    $track = $config['track'];
    error_log('tracking: ' . implode(',', $track));

    //request for twitter's stream api
    $request = $client->createRequest(
        'POST',
        'https://stream.twitter.com/1.1/statuses/filter.json',
        ['stream' => true, 'auth' => 'oauth']);

    //set the track keywords
    $request->getBody()->setField('track', implode(',', $track));

    error_log('created stream request');

    //get the streamed response
    $response = $client->send($request);
    $stream = $response->getBody();

    return $stream;
};

//read lines from the response
$count = 0;
$start = $time = time();
$run = true;

//stats call
$stats = function($count, $start){
    error_log('tweets collected: ' . $count);
    $elapsed = time() - $start;
    error_log('tweets / minute: ' . $count/($elapsed/60));
    return time();
};

//shutdown
$shutdown = function($signal) use (&$run){
    error_log('caught signal: ' . $signal);
    $run = false;
};

//reload
$reload = function($signal) use ($getStream, &$stream){
    error_log('caught signal: ' . $signal);
    $stream = $getStream();
};

//register the handler
pcntl_signal(SIGINT, $shutdown);
pcntl_signal(SIGHUP, $reload);

$stream = $getStream();
error_log('connected to stream');
while(!$stream->eof() AND $run){
    $tweet = Stream\read_line($stream);
    $tweet = json_decode($tweet, true);
    if(isset($tweet['text'])){
        $count++;
        file_put_contents('tweets.txt', $tweet['text'] . PHP_EOL, FILE_APPEND);
    }

    if(time() > ($time + 30)){
        $time = $stats($count, $start);
    }

    //only process the signals here
    pcntl_signal_dispatch();
}

//do some shutdown like things
error_log('shutting down');
error_log('closing stream connection');
$stream->close();
error_log('marking archive');
file_put_contents('tweets.txt', '--paused--' . PHP_EOL, FILE_APPEND);
error_log('final stats');
$stats($count, $start);