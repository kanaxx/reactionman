<?php

namespace App\Console\Commands\ReactionMan;

class SlackMessageBuilder 
{

    public $from = "リアクションマン";
    public $image = 'https://emojipedia-us.s3.dualstack.us-west-1.amazonaws.com/thumbs/120/mozilla/36/wrapped-present_1f381.png';

    public $reactionManChannel;

    public $messageCount;
    public $oldest;
    public $latest;
    
    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(){
    }

    public function getSlackMessage()
    {
        $blocks = [];
        $contents = ':sunflower:' . $this->reactionManChannel->name() . 'のリアクション' . PHP_EOL;
        $contents .= '集計期間：' . date('Y/m/d', $this->oldest) . '～' . date('Y/m/d', $this->latest-1) .PHP_EOL;
        $contents .= 'メッセージ数：' .$this->messageCount . PHP_EOL;
        $contents .= 'リアクション数：' . $this->reactionManChannel->countReaction() . '回' . PHP_EOL;
        $contents .= 'アイコン種類：' . count($this->reactionManChannel->uniqueIcon()) . '種類' . PHP_EOL;

        $blocks[] = ['type'=>'section', 'text'=>['type'=>'mrkdwn', 'text'=>$contents]];

        $divider = ['type'=>'divider'];
        $blocks[] = $divider;

        $blocks = $this->makeIconRanking($blocks,3,'使った絵文字');
        $blocks = $this->makePersonalRanking($blocks,5,'いっぱい送った人', 'from');
        $blocks = $this->makePersonalRanking($blocks,5,'いっぱい受け取った人', 'to');
        $payload = ['blocks'=>$blocks, 'username'=>$this->from, 'icon_url'=>$this->image];
        
        return $payload;
    }

    private function makeIconRanking($blocks, $maxBlocks, $title){

        $texts=[];

        $text = '';
        $sum = 0;
        $rank = 1;
        $result = $this->reactionManChannel->rankIcon();
        foreach($result as $icon=>$cnt){
            $text .=  "{$rank}位 :$icon: {$cnt}" . PHP_EOL;
            $rank++;
            if(mb_strlen($text, 'UTF-8')>2500){
                $texts[]=$text;
                $text='';
            }
        }
        $texts[]=$text;
        
        foreach($texts as $blk=>$t){
            if($blk >= $maxBlocks){
                break;
            }
            $page = $blk+1;
            $blockTitle = "*{$title}* /{$page} page";
            $blockTxt = $blockTitle . PHP_EOL . $t;
            $blocks[] = ['type'=>'section', 'text'=>['type'=>'mrkdwn', 'text'=>$blockTxt]];
        }
        return $blocks;
    }
    private function makePersonalRanking($blocks, $maxBlocks, $title, $fromto){
        $title = '*' . $title . '*';
        $texts = [];
        $text = '';

        $rank = 1;
        $result = [];
        if($fromto =='from'){
            $result = $this->reactionManChannel->rankFrom('name');
        }else{
            $result = $this->reactionManChannel->rankTo('name');
        }
        foreach($result as $user=>$cnt){
            if($fromto =='from'){
                $iconlist = $this->reactionManChannel->filterFrom($user)->rankIcon();
            }else{
                $iconlist = $this->reactionManChannel->filterTo($user)->rankIcon();
            }
            $r = 0;
            $icondisp = "";
            foreach($iconlist as $icon=>$iconcnt){
                $icondisp .= ":{$icon}: {$iconcnt}  ";

                $r++;
                if( $r >= 5 ){
                    break;
                }
            }
            $text .=  "{$rank}位 {$user} {$cnt}回 {$icondisp}" . PHP_EOL;
            $rank++;

            if(mb_strlen($text, 'UTF-8')>2500){
                $texts[]=$text;
                $text='';
            }
        }
        $texts[]=$text;
        
        foreach($texts as $blk=>$t){
            if($blk >= $maxBlocks){
                break;
            }
            $page = $blk+1;
            $blockTxt = "{$title}  /{$page} page" . PHP_EOL . $t;
            $blocks[] = ['type'=>'section', 'text'=>['type'=>'mrkdwn', 'text'=>$blockTxt]];
        }
        return $blocks;
    }
}
