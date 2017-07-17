<?php


namespace CKylin;

//COMMON Uses
use pocketmine\command\Command;
use pocketmine\plugin\Plugin;
use pocketmine\utils\TextFormat;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\Server;
use pocketmine\Player;
use pocketmine\command\CommandSender;

use pocketmine\event\player\PlayerJoinEvent;

class CkMsgBox extends PluginBase implements Listener
{
    public function getAPI(){
        return $this;
    }

    public function onEnable() {
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->path = $this->getDataFolder();
		@mkdir($this->path);
		$this->cfg = new Config($this->path."options.yml", Config::YAML,array(
            'cmds'=>array(
                'check'=>array(
                    'list',
                ),
                'read'=>array(
                    'get',
                ),
                'send'=>array(
                    'new',
                    'to',
                ),
                'bc'=>array(
                    'broadcast',
                    'all',
                    'sendall',
                    'toall',
                ),
            ),
            'translates'=>array(
                'you-have-unread-msgs'=>'You have %c unread messages!',
                'you-have-unread-bcs'=>'You have %c unread boradcasts!',
                'type-cmd-to-check-it'=>'Type "/msgbox list" to check them.',
                'type-cmd-to-read-it'=>'Type "/msgbox read <id>" to read it.',
                'no-msg'=>'No unread messages here.',
                'type-id'=>'Please type the msg id.',
                'type-msg'=>'Please type the msg.',
                'type-username-msg'=>'Please type the target player and your msg.',
                'E-getmsg-failed'=>'Error:Get message failed.',
                'msg-sended'=>'Message successfully sended.',
                'msg-saved'=>'Message have been sended to mailbox.',
                'msg-failed'=>'Oops, there is something error when sending the message.',
                'msg-unknow'=>'Oops, the message is out ou control, hope it can be sended.',
                'listname'=>'  > Message List <',
                'SYSTEM'=>'SYSTEM',
                'Broadcast'=>'Broadcast',
                'id'=>'Msg ID: ',
                'from'=>'From: ',
                'msg'=>'Message: ',
                'header'=>'========[Msg Box]========',
                'footer'=>'=========================',
            ),
        ));
		$this->msgbox = new Config($this->path."msgbox.yml", Config::YAML,array());
		$this->bcs = new Config($this->path."bcs.yml", Config::YAML,array());
		$this->getLogger()->info(TextFormat::GREEN . 'Enabled');
	}

	public function onDisable() {
		$this->saveall();
		$this->getLogger()->info(TextFormat::BLUE . 'Disabled.');
	}

	public function onCommand(CommandSender $s, Command $cmd, $label, array $args) {
        if($cmd=='msgbox'){
            if(empty($args[0])) return false;
            $s->sendMessage($this->getlang('header'));
            $keys = $this->cfg->get('cmd');
            $name = $s->getName();
            $opt = $args[0];
            if($opt=='check'||$opt=='c'||in_array($opt,$keys['check'])){
                $s->sendMessage($this->genMsgList($name));
                $s->sendMessage($this->getlang('footer'));
                return true;
            }
            if($opt=='read'||$opt=='r'||in_array($opt,$keys['read'])){
                if(empty($args[1])){
                    $s->sendMessage($this->getlang('type-id'));
                    $s->sendMessage($this->getlang('footer'));
                    return true;
                }
                $s->sendMessage($this->getMsg($args[1],$name));
                $s->sendMessage($this->getlang('footer'));
                return true;
            }
            if($opt=="send"||$opt=='s'||in_array($opt,$keys['send'])){
                if(empty($args[1])||empty($args[2])){
                    $s->sendMessage($this->getlang('type-username-msg'));
                    $s->sendMessage($this->getlang('footer'));
                    return true;
                }
                $code = $this->msg($args[2],$args[1],$name,true);
                switch($code){
                    case 0:
                        $status = $this->getlang('msg-sended');
                        break;
                    case 1:
                        $status = $this->getlang('msg-saved');
                        break;
                    case 2:
                        $status = $this->getlang('msg-failed');
                        break;
                    default:
                        $status = $this->getlang('msg-unknow');
                }
                $s->sendMessage($status);
                $s->sendMessage($this->getlang('footer'));
                return true;
            }
            if($s->isOp()){
                if($opt=="bc"||in_array($opt,$keys['bc'])){
                    if(empty($args[1])){
                        $s->sendMessage($this->getlang('type-msg'));
                        $s->sendMessage($this->getlang('footer'));
                        return true;
                    }
                    $this->broadcast($args[1]);
                    $s->sendMessage($this->getlang('msg-sended'));
                    $s->sendMessage($this->getlang('footer'));
                    return true;
                }
            }
        }
	}

    public function getMsg($id,$target){
        //$id = (int) $id;
        if(empty($id)||empty($target)) return $this->getlang('E-getmsg-failed').'(E-1 Missing parameters)';
        $type = 0;
        if(substr($id,0,2)=="bc") $type = 1;
        if($type==0){
            if(!$this->msgbox->exists($target)) return $this->getlang('E-getmsg-failed').'(E-2 Unknown target)';
            $msglist = $this->msgbox->get($target);
            $notfound = true;
            $return = '';
            foreach($msglist as $mid=>$data){
                if($mid==$id){
                    $notfound = false;
                    $from = $data['from'];
                    if($from=="SYSTEM") $from = $this->getlang('SYSTEM');
                    $return = $this->getlang('id').$mid."\n".$this->getlang('from').$from."\n".$this->getlang('msg').$data['msg'];
                    unset($msglist[$mid]);
                    array_merge($msglist);
                    $this->msgbox->set($target,$msglist);
                    $this->saveall();
                    break;
                }
            }
            if($notfound) return $this->getlang('E-getmsg-failed').'(E-3 Message not found with ID '.$id.')';
            return $return;
        }else{
            if(!$this->bcs->exists($id)) return $this->getlang('E-getmsg-failed').'(E-4 Unknown broadcast with ID '.$id.')';
            $bc = $this->bcs->get($id);
            $return = $this->getlang('id').$id."\n".$this->getlang('from').$this->getlang('Broadcast')."\n".$this->getlang('msg').$bc['msg'];
            if(!in_array($target,$bc['readlist'])){
                array_push($bc['readlist'],$target);
                $this->bcs->set($id,$bc);
                $this->saveall();
            }
            return $return;
        }
    }

    public function genMsgList($target){
        $list = $this->getlang('listname');
        $havebcs = false;
        $havemsg = false;
        $msgs = $this->countUnreadMsg($target);
        $bcs = $this->countUnreadBCMsg($target);
        if($bcs>0){
            $havebcs = true;
            $allbcs = $this->bcs->getAll();
            foreach($allbcs as $id=>$data){
                if(!in_array($target,$data['readlist'])){
                    $msgdata = $this->msubstr($data['msg'],0,10);
                    $list.= "\n$id | ".$this->getlang('SYSTEM')." | $msgdata";
                }
            }
        }
        if($msgs>0){
            $havemsg = true;
            $pmdatas = $this->msgbox->get($target);
            foreach($pmdatas as $id=>$data){
                $msgdata = $this->msubstr($data['msg'],0,10);
                $list.= "\n$id | ".$data['from']." | $msgdata";
            }
        }
        if($havebcs===false&&$havemsg===false){
            $list = $this->getlang('no-msg');
        }
        return $list;
    }

    public function onJoin(PlayerJoinEvent $e){
        $this->joinMsger($e->getPlayer());
    }

    public function joinMsger(Player $p){
        $name = $p->getName();
        $msgs = $this->countUnreadMsg($name);
        $bcs = $this->countUnreadBCMsg($name);
        $msg = '';
        $havebcs = false;
        $havemsg = false;
        if($msgs>0){
            $str = $this->getlang('you-have-unread-msgs');
            $str = str_replace('%c',$msgs,$str);
            $msg.= "[*] ".$str;
            $havemsg = true;
        }
        if($bcs>0){
            $str = $this->getlang('you-have-unread-bcs');
            $str = str_replace('%c',$bcs,$str);
            if($havemsg===true) $msg.= "\n";
            $msg.= "[*] ".$str;
            $havebcs = true;
        }
        if($havemsg===true||$havebcs===true){
            $msg.= "\n".$this->getlang('type-cmd-to-check-it');
            $this->getLogger()->info('sended');
        }
        $p->sendMessage($msg);
    }

    public function msgnow($msg,$name){
        if(empty($msg)||empty($target)) return;
        foreach($this->getServer()->getOnlinePlayers() as $p){
            if($p->getName()==$target){
                $p->sendMessage($msg);
                break;
            }
        }
    }

    public function msg($msg = false,$target = false,$from = 'Unknow',$msgwithsender = false){
        // errcode:
        // 0 - Sended
        // 1 - Saved
        // 2 - Error
        if(empty($msg)||empty($target)) return 2;
        $notsended = true;
        foreach($this->getServer()->getOnlinePlayers() as $p){
            if($p->getName()==$target){
                $smsg = $msgwithsender ? $from.': '.$msg : $msg;
                $p->sendMessage($smsg);
                $notsended = false;
                break;
            }
        }
        if($notsended){
            $this->addNewMsg($msg,$target,$from);
            return 1;
        }else return 0;
    }

    public function broadcast($msg = false){
        if(empty($msg)) return false;
        $readlist = array();
        foreach($this->getServer()->getOnlinePlayers() as $p){
            $p->sendMessage($msg);
            array_push($readlist,$p->getName());
        }
        $allmsg = $this->bcs->getAll();
        $id = count($allmsg)+1;
        $this->bcs->set("bc".$id,array(
            'msg'=>$msg,
            'readlist'=>$readlist
        ));
        $this->saveall();
    }

    public function addNewMsg($msg = 'Empty Message.',$target,$from = 'SYSTEM'){
        if(empty($target)) return false;
        $target = (string) $target;
        if($this->msgbox->exists($target)){
            $list = $this->msgbox->get($target);
        }else{
            $list = array();
        }
        $msgdata = array(
            'msg'=>$msg,
            'from'=>$from
        );
        $id = count($list)+1;
        $id = (string) $id;
        //array_push($list,array($id=>$msgdata));
        $bool = true;
        while($bool){
            if(!empty($list[$id])){
                $id++;
            }else{
                $bool = false;
            }
        }
        $list[$id] = $msgdata;
        $this->msgbox->set($target,$list);
        $this->saveall();
    }

    public function countUnreadMsg($target){
        if(empty($target)) return -1;
        if($this->msgbox->exists($target)){
            return count($this->msgbox->get($target));
        }else return 0;
    }

    public function countUnreadBCMsg($target){
        if(empty($target)) return -1;
        $allbc = $this->bcs->getAll();
        $counter = 0;
        foreach($allbc as $id=>$data){
            if(!in_array($target,$data['readlist'])){
                $counter++;
            }
        }
        return $counter;
    }

    public function getlang($lang){
        $langs = $this->cfg->get('translates');
        if(empty($langs[$lang])){
            $langs[$lang] = '{{'.$lang.'}}';
            $this->cfg->set('translates',$langs);
            $this->saveall();
            $this->getLogger()->info('Found an undefined translation node ('.$langs[$lang].'), please go to options.yml for translation.');
        }
        return $langs[$lang];
    }

    public function saveall(){
        $this->cfg->save();
        $this->msgbox->save();
        $this->bcs->save();
    }

    /**
     +----------------------------------------------------------
     * 字符串截取，支持中文和其他编码
     +----------------------------------------------------------
     * @static
     * @access public
     +----------------------------------------------------------
     * @param string $str 需要转换的字符串
     * @param string $start 开始位置
     * @param string $length 截取长度
     * @param string $charset 编码格式
     * @param string $suffix 截断显示字符
     +----------------------------------------------------------
     * @return string
     +----------------------------------------------------------
     */
    public function msubstr($str, $start, $length, $charset="utf-8", $suffix=true)
    {
        if(function_exists("mb_substr")){
            $slice = mb_substr($str, $start, $length, $charset);
        }elseif(function_exists('iconv_substr')) {
            $slice = iconv_substr($str,$start,$length,$charset);
            if(false === $slice) {
                $slice = '';
            }
        }else{
            $re['utf-8']  = "/[\x01-\x7f]|[\xc2-\xdf][\x80-\xbf]|[\xe0-\xef][\x80-\xbf]{2}|[\xf0-\xff][\x80-\xbf]{3}/";
            $re['gb2312'] = "/[\x01-\x7f]|[\xb0-\xf7][\xa0-\xfe]/";
            $re['gbk']    = "/[\x01-\x7f]|[\x81-\xfe][\x40-\xfe]/";
            $re['big5']   = "/[\x01-\x7f]|[\x81-\xfe]([\x40-\x7e]|\xa1-\xfe])/";
            preg_match_all($re[$charset], $str, $match);
            $slice = join("",array_slice($match[0], $start, $length));
        }
        return $suffix ? $slice.'...' : $slice;
    }

}
