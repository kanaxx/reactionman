<?php

namespace App\Console\Commands\ReactionMan;

use Illuminate\Console\Command;

class ReactionManStart extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reactionman:start 
        {--days= : 集計日数}
        {--start= : 集計の開始日 2019-11-11形式}
        {--end= : 集計の終了日 2019-11-11形式}
        {--channel= : 集計対象チャネル(publicのみ)}
        {--sendto= : 通知先チャンネル名(publicのみ).指定がないときは結果をPostしないで終わる}
        {--token= : 結果をpostMessage可能なユーザトークン}
        ' ;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Slackのリアクションを集計してレポートするかわいいやつ';

    const API_USER_URL = 'https://slack.com/api/users.list';
    const API_CHANNELLIST_URL= 'https://slack.com/api/conversations.list';
    const API_HISTORY_URL= 'https://slack.com/api/conversations.history';
    const API_CHATPOST_URL = 'https://slack.com/api/chat.postMessage';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        ini_set('xdebug.var_display_max_children', -1);
        ini_set('xdebug.var_display_max_data', -1);
        ini_set('xdebug.var_display_max_depth', -1);

        $token = $this->option('token');
        if( blank($token) ){
            $this->error('parameter [token] is mandatory.');
            return 1;
        }
        
        $days = $this->option('days');
        if( !blank($days) ){
            $latest = strtotime( date("Y/m/d 00:00:00") );
            $latest_date = date('Y/m/d H:i:s', $latest);
            $oldest = strtotime( "-$days day" , $latest );
            $oldest_date = date('Y/m/d H:i:s', $oldest);
        }else{
            $latest_date = $this->option('end');
            $oldest_date = $this->option('start');
            if( blank($latest_date) || blank($oldest_date)){
                $this->error("parameter [start/end] or [days] is mandatory.");
                return 1;
            }
            $latest_date = $latest_date . ' 23:59:59';
            $oldest_date = $oldest_date . ' 00:00.00';
            $latest = strtotime($latest_date);
            $oldest = strtotime($oldest_date);
        }

        
        $this->info("from: $oldest_date to: $latest_date");
        $this->info("from: $oldest to: $latest");

        //httpの設定
        $httpOption = ['verify' => false, 'debug'=>false, 'http_errors'=>false, ];
        $httpOption['headers'] = ['Authorization'=>"Bearer " . $token];
        $guzzle = new \GuzzleHttp\Client($httpOption);

        //slackのチャンネル一覧(public channel)
        $slackChannelList = $this->getSlackChannels($guzzle, $token);
        $this->info("All Channel in Slack: {$slackChannelList->count()}");

        //指定したチャンネルがないときはエラー
        $optChannel = $this->option('channel');
        $slackChannel = $slackChannelList->firstWhere('name', $optChannel);

        if( blank($slackChannel) ){
            $this->error("parameter [channel] {$optChannel} is not found in your slack workspace." );
            return 1;
        }

        //送り先がないときは、エラーにせず先に進む。postはしない
        $optSendTo = $this->option('sendto');
        $sendTo = $slackChannelList->firstWhere('name', $optSendTo);
        if( blank($sendTo) ) {
            $this->info("parameter [sendto] is not found. ReactionMan will not post message to slack.");
        }

        //slackのユーザ一覧
        $slackUserList = $this->getSlackUsers($guzzle, $token);
        $this->info("All User in Slack: " . count($slackUserList));

        //メッセージ取得
        $slackMessageList = $this->getSlackMassages($guzzle,$token,$slackChannel['id'],$latest,$oldest);
        $this->info( " " . count($slackMessageList) . " msgs in {$slackChannel['name']}.");

        //チャンネルの結果をサマリーするクラス
        $reactionManChannel = new ReactionManChannel($slackChannel['id'], $slackChannel['name']);

        foreach($slackMessageList as $m=>$message){
            if( !isset($message['user']) || !isset($message['reactions']) ){
                continue;
            }
            
            foreach($message['reactions'] as $r=>$reaction ){
                foreach( $reaction['users'] as $reactedUserId){
                    $toId = $message['user']??'';
                    $to = $slackUserList[$toId];

                    $from = $slackUserList[$reactedUserId]??'';
                    if( blank($to) || blank($from) ){
                        $this->line('skip empty:' . $reactedUserId . '-' . $toId);
                        continue;
                    }

                    if( $from == $to){
                        //自分から自分はノーカウント
                        $this->line(' skip self reaction:' . $reactedUserId . '-' . $toId);
                        continue;
                    }
                    //reactionの日付は取れないので、元メッセージの日付にする
                    $re = new SlackReaction($from, $to, $reaction['name'], $message['ts']);
                    $reactionManChannel->addReaction($re);
                }
            }
        }

        $this->info( " " . count($slackMessageList) . " msgs, {$reactionManChannel->countReaction()} reactions in {$slackChannel['name']}.");


        //全部終わったらSlackのメッセージ作る
        $slackMessageBuilder = new SlackMessageBuilder();
        $slackMessageBuilder->oldest = $oldest;
        $slackMessageBuilder->latest = $latest;
        $slackMessageBuilder->messageCount = count($slackMessageList);
        $slackMessageBuilder->reactionManChannel = $reactionManChannel;
        
        $slackPayload = $slackMessageBuilder->getSlackMessage();

        //sendtoが正しいときだけSlackに送る。それ以外はコンソールにダンプして終わる
        if( !blank($sendTo) ) {
            $this->info('Post message to slack');

            //チャンネルはIDで指定（チャンネル名ではないので注意）
            $slackPayload['channel']=$sendTo['id'];
            $slackPayload['as_user']=false;
            $response = $guzzle->post(self::API_CHATPOST_URL, ['json'=>$slackPayload]);
            $body = $response->getBody()->getContents();
            $this->line('post api :' . $body);
        }else{
            $this->info('Only dump slack message payload');
            $json = json_encode($slackPayload, JSON_PRETTY_PRINT |JSON_UNESCAPED_SLASHES |JSON_UNESCAPED_UNICODE);
            $this->line($json);
        }
        $this->info( "end of batch.");
        return 0;
    }

    private function getSlackUsers($http, $token){
        $this->info('■User');
        $param =['token'=>$token,'limit'=>200];
        $list = $this->callSlackAPI($http, self::API_USER_URL, $param, 'members');
        //id=>{member}に変形
        $map = $list->mapWithKeys(function ($item) {
            return [$item['id'] => $item];
        });
        //var_dump($map); 
        return $map;
    }

    private function getSlackChannels($http, $token){
        $this->info('■Channel');
        $param =['token'=>$token,'limit'=>200];
        return $this->callSlackAPI($http, self::API_CHANNELLIST_URL, $param, 'channels');
    }

    private function getSlackMassages($http, $token, $channelId, $latestTs, $oldestTs){
        $this->info('■Message');
        $param =['token'=>$token,'limit'=>50, 'channel'=>$channelId, 'latest'=>$latestTs, 'oldest'=>$oldestTs];
        return $this->callSlackAPI($http,  self::API_HISTORY_URL, $param, 'messages');
    }

    private function callSlackAPI($http, $url, $parameter, $listName){
        $nextCursor='';

        $apiResult = collect([]);
        do{
            $parameter['cursor']=$nextCursor;

            $this->info('  API call with ' . $nextCursor );
            $response = $http->get($url, ['query' => $parameter]);
            $response_code = $response->getStatusCode();
            if( $response_code == '429' ){
                $sec = $response->getHeader('Retry-After')[0];
                $this->line( ' waiting ' . $sec . ' seconds.');
                sleep($sec);
                continue;
            }

            $body = $response->getBody()->getContents();
            $json = json_decode($body, true);
            if( !$json['ok'] ){
                $this->error('slack returns error');
                $this->info($body);
                return null;
            }

            $list = collect($json[$listName]);
            $this->info('  '.$list->count() . ' 件取得');

            $apiResult = $apiResult->concat($list);
            $nextCursor = $json['response_metadata']['next_cursor']??'';

            $this->info('  next cursor is ' . $nextCursor);
        }while(!blank($nextCursor));

        return $apiResult;
    }

}
