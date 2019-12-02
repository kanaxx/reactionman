<?php 

namespace App\Console\Commands\ReactionMan;

//リアクション１個分のクラス
class SlackReaction{
    public function __construct($from, $to, $icon, $ts ){
        $this->from = $from;
        $this->to = $to;
        $this->icon = $icon;
        $this->ts = $ts;
    }

    //fromとtoはusers.list の戻り値の連想配列
    //https://api.slack.com/methods/users.list
    private $from;
    private $to;

    //リアクションの文字列 :new: のような形
    private $icon;
    //タイムスタンプ
    private $ts;

    public function getIcon(){
        return $this->icon;
    }

    public function getFrom($col='id'){
        return $this->from[$col];
    }
    public function getTo($col='id'){
        return $this->to[$col];
    }

    public function isFrom($user){
        return $this->from['id'] == $user  || $this->from['name'] == $user;
    }
    public function isTo($user){
        return $this->to['id'] == $user  || $this->to['name'] == $user;
    }
}