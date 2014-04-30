<?php
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Subscriber\Oauth\Oauth1;
use GuzzleHttp\Stream as Stream;

//autoloading and config
require_once '../vendor/autoload.php';
$config = include '../config.php';

//http client
$client = new HttpClient();

//oauth setup
$oauth = new Oauth1($config['oauth']);
$client->getEmitter()->attach($oauth);

//tracked keywords
$track = [
    'fail',
    'lvphp',
    'lvtech',
    'tjlytle'
];

//request for twitter's stream api
$request = $client->createRequest(
    'POST',
    'https://stream.twitter.com/1.1/statuses/filter.json',
    ['stream' => true, 'auth' => 'oauth']);

//set the track keywords
$request->getBody()->setField('track', implode(',', $track));

//get the streamed response
$response = $client->send($request);
$stream = $response->getBody();

//read lines from the response
while(!$stream->eof()){
    $tweet = Stream\read_line($stream);
    $tweet = json_decode($tweet, true);
    if(isset($tweet['text'])){
        error_log($tweet['text']);
        file_put_contents('tweets.txt', $tweet['text'] . PHP_EOL, FILE_APPEND);
    }
}
