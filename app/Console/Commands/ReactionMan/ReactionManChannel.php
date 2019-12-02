<?php 

namespace App\Console\Commands\ReactionMan;

class ReactionManChannel{

    //channnel id and name
    public $id;
    public $name;

    //instance of SlackReaction class as list;
    public $reactionList = [];

    public function __construct($id, $name){
        $this->id= $id;
        $this->name=$name;
    }

    public function id(){
        return $this->id;
    }
    public function name(){
        return $this->name;
    }

    public function addReaction($reaction){
        $this->reactionList[] = $reaction;
    }

    public function countReaction(){
        return count($this->reactionList);
    }

    public function filterFrom($user){
        $s = new ReactionManChannel($this->id, $this->name);
        $s->reactionList = array_filter($this->reactionList, 
            function($v,$k)use ($user){
                return $v->isFrom($user);
            } , ARRAY_FILTER_USE_BOTH );
        return $s;
    }
    public function filterTo($user){
        $s = new ReactionManChannel($this->id, $this->name);
        $s->reactionList =  array_filter($this->reactionList, 
            function($v,$k)use ($user){
                return $v->isTo($user);
            } , ARRAY_FILTER_USE_BOTH );
        return $s;
    }
    public function filterIcon($icon){
        return array_filter($this->reactionList, function($v,$k)use ($icon){return $v->icon() == $icon;} , ARRAY_FILTER_USE_BOTH );
    }

    public function uniqueIcon(){
        $ans = [];
        foreach($this->reactionList as $n=>$re){
            $ans[] = $re->getIcon();
        }
        return array_unique($ans);
    }
    
    public function rankIcon(){
        return $this->makeRank('getIcon');
    }
    public function rankFrom($col='id'){
        return $this->makeRank('getFrom', $col);
    }
    public function rankTo($col='id'){
        return $this->makeRank('getTo', $col);
    }

    protected function makeRank($func, $arg=null){
        $ans = [];
        
        foreach($this->reactionList as $n=>$re){
            $key = call_user_func(array($re, $func),$arg);
            if( isset($ans[$key] )) {
                $ans[$key]++;
            }else{
                $ans[$key]=1;
            }
        }
        arsort($ans);
        return $ans;
    }
    public function reactions(){
        return $this->reactionList;
    }

}