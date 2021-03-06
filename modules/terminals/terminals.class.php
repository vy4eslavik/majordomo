<?php
/**
 * Terminals
 *
 * Terminals
 *
 * @package MajorDoMo
 * @author Serge Dzheigalo <jey@tut.by> http://smartliving.ru/
 * @version 0.3
 */
//
//
class terminals extends module
{
    /**
     * terminals
     *
     * Module class constructor
     *
     * @access private
     */
    function __construct()
    {
        $this->name = "terminals";
        $this->title = "<#LANG_MODULE_TERMINALS#>";
        $this->module_category = "<#LANG_SECTION_SETTINGS#>";
        $this->checkInstalled();
        $this->serverip = getLocalIp();
    }

    /**
     * saveParams
     *
     * Saving module parameters
     *
     * @access public
     */
    function saveParams($data = 1)
    {
        $data = array();
        if (IsSet($this->id)) {
            $data["id"] = $this->id;
        }
        if (IsSet($this->view_mode)) {
            $data["view_mode"] = $this->view_mode;
        }
        if (IsSet($this->edit_mode)) {
            $data["edit_mode"] = $this->edit_mode;
        }
        if (IsSet($this->tab)) {
            $data["tab"] = $this->tab;
        }
        return parent::saveParams($data);
    }

    /**
     * getParams
     *
     * Getting module parameters from query string
     *
     * @access public
     */
    function getParams($data = 1)
    {
        global $id;
        global $mode;
        global $view_mode;
        global $edit_mode;
        global $tab;
        if (isset($id)) {
            $this->id = $id;
        }
        if (isset($mode)) {
            $this->mode = $mode;
        }
        if (isset($view_mode)) {
            $this->view_mode = $view_mode;
        }
        if (isset($edit_mode)) {
            $this->edit_mode = $edit_mode;
        }
        if (isset($tab)) {
            $this->tab = $tab;
        }
    }

    /**
     * Run
     *
     * Description
     *
     * @access public
     */
    function run()
    {
        global $session;
        $out = array();
        if ($this->action == 'admin') {
            $this->admin($out);
        } else {
            $this->usual($out);
        }
        if (IsSet($this->owner->action)) {
            $out['PARENT_ACTION'] = $this->owner->action;
        }
        if (IsSet($this->owner->name)) {
            $out['PARENT_NAME'] = $this->owner->name;
        }
        $out['VIEW_MODE'] = $this->view_mode;
        $out['EDIT_MODE'] = $this->edit_mode;
        $out['MODE'] = $this->mode;
        $out['ACTION'] = $this->action;
        $out['TAB'] = $this->tab;
        if ($this->single_rec) {
            $out['SINGLE_REC'] = 1;
        }
        $this->data = $out;
        $p = new parser(DIR_TEMPLATES . $this->name . "/" . $this->name . ".html", $this->data, $this);
        $this->result = $p->result;
    }

    /**
     * BackEnd
     *
     * Module backend
     *
     * @access public
     */
    function admin(&$out)
    {
        if (isset($this->data_source) && !$_GET['data_source'] && !$_POST['data_source']) {
            $out['SET_DATASOURCE'] = 1;
        }
        if ($this->data_source == 'terminals' || $this->data_source == '') {
            if ($this->view_mode == '' || $this->view_mode == 'search_terminals') {
                $this->search_terminals($out);
            }
            if ($this->view_mode == 'edit_terminals') {
                $this->edit_terminals($out, $this->id);
            }
            if ($this->view_mode == 'delete_terminals') {
                $this->delete_terminals($this->id);
                $this->redirect("?");
            }
        }
    }

    /**
     * FrontEnd
     *
     * Module frontend
     *
     * @access public
     */
    function usual(&$out)
    {
        $this->admin($out);
    }

    /**
     * terminals search
     *
     * @access public
     */
    function search_terminals(&$out)
    {
        require(DIR_MODULES . $this->name . '/terminals_search.inc.php');
    }

    /**
     * terminals edit/add
     *
     * @access public
     */
    function edit_terminals(&$out, $id)
    {
        require(DIR_MODULES . $this->name . '/terminals_edit.inc.php');
    }

    /**
     * terminals delete record
     *
     * @access public
     */
    function delete_terminals($id)
    {
        if ($rec = getTerminalByID($id)) {
            SQLExec('DELETE FROM `terminals` WHERE `ID` = ' . $rec['ID']);
        }
    }


    function terminalSayByCache($terminal_rec, $cached_filename, $level) {
        $min_level = getGlobal('ThisComputer.minMsgLevel');
        if ($terminal_rec['MIN_MSG_LEVEL']) {
            $min_level = (int)processTitle($terminal_rec['MIN_MSG_LEVEL']);
        }
        if ($level < $min_level) {
            return false;
        }
        if ($terminal_rec['MAJORDROID_API'] || $terminal_rec['PLAYER_TYPE'] == 'ghn') {
            return;
        }
        if ($terminal_rec['CANPLAY'] && $terminal_rec['PLAYER_TYPE']!='') {
            if (preg_match('/\/cms\/cached.+/',$cached_filename,$m)) {
                $server_ip = getLocalIp();
                if (!$server_ip) {
                    DebMes("Server IP not found", 'terminals');
                    return false;
                } else {
                    $cached_filename='http://'.$server_ip.$m[0];
                }
            } else {
                DebMes("Unknown file path format: " . $cached_filename, 'terminals');
                return false;
            }
            DebMes("Playing cached to " . $terminal_rec['TITLE'] . ' (level ' . $level . '): ' . $cached_filename, 'terminals');
            playMedia($cached_filename,$terminal_rec['TITLE']);
        }
    }

    function terminalSay($terminal_rec, $message, $level)
    {
        $asking=0;
        if ($level==='ask') {
            $level=9999;
            $asking=1;
        }
        $min_level = getGlobal('ThisComputer.minMsgLevel');
        if ($terminal_rec['MIN_MSG_LEVEL']) {
            $min_level = (int)processTitle($terminal_rec['MIN_MSG_LEVEL']);
        }
        if ($level < $min_level) {
            return false;
        }
        DebMes("Saying to " . $terminal_rec['TITLE'] . ' (level ' . $level . '): ' . $message, 'terminals');
        //if (!$terminal_rec['IS_ONLINE']) return false;
        if ($terminal_rec['MAJORDROID_API'] && $terminal_rec['HOST']) {
            $service_port = '7999';
            if ($asking) {
                $in = 'ask:' . $message;
            } else {
                $in = 'tts:' . $message;
            }
            $address = $terminal_rec['HOST'];
            if (!preg_match('/^\d/', $address)) return 0;
            $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            if ($socket === false) {
                return 0;
            }
            $result = socket_connect($socket, $address, $service_port);
            if ($result === false) {
                return 0;
            }
            socket_write($socket, $in, strlen($in));
            socket_close($socket);
            return 1;
        } elseif ($terminal_rec['PLAYER_TYPE'] == 'ghn') {
            $port = $terminal_rec['PLAYER_PORT'];
            $language = SETTINGS_SITE_LANGUAGE;
            if (!$port) {
                $port = '8091';
            }
            $host = $terminal_rec['HOST'];
            $url = 'http://' . $host . ':' . $port . '/google-home-notifier?language=' . $language . '&text=' . urlencode($ph);
            getURL($url, 0);
        }
    }

    /**
     * terminals subscription events
     *
     * @access public
     */
    function processSubscription($event, $details = '')
    {
        $this->getConfig();
        DebMes("Processing $event: ".json_encode($details),'terminals');
        if ($event == 'SAY') {
            $terminals = SQLSelect("SELECT * FROM terminals WHERE CANTTS=1");
            foreach ($terminals as $terminal_rec) {
                $this->terminalSay($terminal_rec, $details['message'], $details['level']);
            }
        } elseif ($event == 'SAYTO' || $event == 'ASK') {
            $terminal_rec = array();
            if ($details['destination']) {
                if (!$terminal_rec = getTerminalsByName($details['destination'], 1)[0]) {
                    $terminal_rec = getTerminalsByHost($details['destination'], 1)[0];
                }
            }
            if (!$terminal_rec['ID']) {
                return false;
            }
            if ($event == 'ASK') {
                $details['level']='ask';
            }
            $this->terminalSay($terminal_rec, $details['message'], $details['level']);
        } elseif ($event == 'SAY_CACHED_READY') {
            $filename = $details['filename'];
            if (!file_exists($filename)) return false;
            if ($details['event']) {
                $source_event = $details['event'];
            } else {
                $source_event = 'SAY';
            }
            if ($source_event == 'SAY') {
                $terminals = SQLSelect("SELECT * FROM terminals WHERE CANTTS=1");
                foreach ($terminals as $terminal_rec) {
                    //$this->terminalSayByCache($terminal_rec, $filename, $details['level']);
                    $this->terminalSayByCacheQueue($terminal_rec,$details['level'],$filename,$details['message']);
                }
            } elseif ($source_event == 'SAYTO' || $source_event == 'ASK') {
                $terminal_rec = array();
                if ($details['destination']) {
                    if (!$terminal_rec = getTerminalsByName($details['destination'], 1)[0]) {
                        $terminal_rec = getTerminalsByHost($details['destination'], 1)[0];
                    }
                }
                if (!$terminal_rec['ID']) {
                    return false;
                }
                if ($source_event == 'ASK') {
                    $details['level']=9999;
                }
                //$this->terminalSayByCache($terminal_rec, $filename, $details['level']);
                $this->terminalSayByCacheQueue($terminal_rec,$details['level'],$filename,$details['message']);
            }

        } elseif ($event == 'HOURLY') {
            // check terminals
            $terminals=SQLSelect("SELECT * FROM terminals WHERE IS_ONLINE=0 AND HOST!=''");
            foreach($terminals as $terminal) {
                if (ping($terminal['HOST'])) {
                    $terminal['LATEST_ACTIVITY']=date('Y-m-d H:i:s');
                    $terminal['IS_ONLINE']=1;
                    SQLUpdate('terminals',$terminal);
                }
            }
            SQLExec('UPDATE terminals SET IS_ONLINE=0 WHERE LATEST_ACTIVITY < (NOW() - INTERVAL 90 MINUTE)'); //
        } elseif ($event == 'SAYREPLY') {
        }
    }

/**
* очередь сообщений 
*
* @access public
*/
function terminalSayByCacheQueue($target, $levelMes, $cached_filename, $ph) { 
    
    // исключаем все сообщения что ниже нужного уровня
    $min_level = getGlobal('ThisComputer.minMsgLevel');
    if ($target['MIN_MSG_LEVEL']) {
        $min_level = (int)$target['MIN_MSG_LEVEL'];
    }
    if ($levelMes < $min_level) {
        return false;
    }

    // если скеширован файл а терминал не может воспроизводить сообщение  то возвращаемся без воспроизведения...
    if (!$target['CANTTS'] or !$target['PLAYER_TYPE'] or $target['MAJORDROID_API'] or $target['PLAYER_TYPE'] == 'ghn') { 
        return;
    }
   
    // poluchaem adress cashed files dlya zapuska ego na vosproizvedeniye
    if (preg_match('/\/cms\/cached.+/',$cached_filename,$m)) {
        $server_ip = getLocalIp();
        if (!$server_ip) {
            //DebMes("Server IP not found", 'terminals');
            return false;
        } else {
            $cached_filename='http://'.$server_ip.$m[0];
        }
    } else {
        //DebMes("Unknown file path format: " . $cached_filename, 'terminals');
        return false;
    }

   // berem vse soobsheniya iz shoots dlya poiska soobsheniya s takoy frazoy
   $messages = SQLSelect("SELECT * FROM shouts ORDER BY ID DESC LIMIT 0 , 100");
   foreach ( $messages as $message ) {
     if ($ph==$message['MESSAGE']) { 
         $number_message = $message['ID'];
         break;
     }
   }
   
   // получаем данные оплеере для восстановления проигрываемого контента
    $chek_restore = SQLSelectOne("SELECT * FROM jobs WHERE TITLE LIKE'".'allsay-target-'.$target['TITLE'].'-number-'."99999999998'");
    if (!$chek_restore ) {
        $played = getPlayerStatus($target['NAME']);
        if (($played['state']=='playing') and (stristr($played['file'], 'cms\cached\voice') === FALSE)) {
	        addScheduledJob('allsay-target-'.$target['TITLE'].'-number-99999999998', "playMedia('".$played['file']."', '".$target['TITLE']."',1);", time()+100, 4);
	        addScheduledJob('allsay-target-'.$target['TITLE'].'-number-99999999999', "seekPlayerPosition('".$target['TITLE']."',".$played['time'].");", time()+110, 4);
	    }
     }
	
    // dobavlyaem soobshenie v konec potom otsortituem
    $time_shift = 2 + getMediaDurationSeconds($cached_filename); // необходимая задержка для перезапуска проигрівателя на факте 2 секундЫ
    //DebMes("Add new message".$last_mesage,'terminals');
    addScheduledJob('allsay-target-'.$target['TITLE'].'-number-'.$number_message, "playMedia('".$cached_filename."', '".$target['TITLE']."');", time()+1, $time_shift);

    // vibiraem vse soobsheniya dla terminala s sortirovkoy po nazvaniyu
    $all_messages = SQLSelect("SELECT * FROM jobs WHERE TITLE LIKE'".'allsay-target-'.$target['TITLE'].'-number-'."%' ORDER BY `TITLE` ASC");
    $first_fields = reset($all_messages);
    $runtime = (strtotime($first_fields['RUNTIME']));
    foreach ($all_messages as $message) {
      $expire = (strtotime($message['EXPIRE']))-(strtotime($message['RUNTIME']));
      $rec['ID']       = $message['ID'];
      $rec['TITLE']    = $message['TITLE'];
      $rec['COMMANDS'] = $message['COMMANDS'];
      $rec['RUNTIME']  = date('Y-m-d H:i:s', $runtime);
      $rec['EXPIRE']   = date('Y-m-d H:i:s', $runtime+$expire);
      // proverka i udaleniye odinakovih soobsheniy
      if ($prev_message['TITLE'] == $message['TITLE']) {
         SQLExec("DELETE FROM jobs WHERE ID='".$rec['ID']."'"); 
      } else {
         SQLUpdate('jobs', $rec);
      }
      $runtime = $runtime + $expire;
      $prev_message = $message;
     }
     //DebMes("Timers sorted",'terminals');
   }

    /**
     * Install
     *
     * Module installation routine
     *
     * @access private
     */
    function install($parent_name = "")
    {
        subscribeToEvent($this->name, 'SAY', '', 0);
        subscribeToEvent($this->name, 'SAYREPLY', '', 0);
        subscribeToEvent($this->name, 'SAYTO', '', 0);
        subscribeToEvent($this->name, 'ASK', '', 0);
        subscribeToEvent($this->name, 'SAY_CACHED_READY',0);
        subscribeToEvent($this->name, 'HOURLY');
        parent::install($parent_name);

    }

    /**
     * Uninstall
     *
     * Module uninstall routine
     *
     * @access public
     */
    function uninstall()
    {
        SQLDropTable('terminals');
        unsubscribeFromEvent($this->name, 'SAY');
        unsubscribeFromEvent($this->name, 'SAYTO');
        unsubscribeFromEvent($this->name, 'ASK');
        unsubscribeFromEvent($this->name, 'SAYREPLY');
        unsubscribeFromEvent($this->name, 'SAY_CACHED_READY');
        unsubscribeFromEvent($this->name, 'HOURLY');
        parent::uninstall();
    }

    /**
     * dbInstall
     *
     * Database installation routine
     *
     * @access private
     */
    function dbInstall($data)
    {
        /*
        terminals - Terminals
        */
        $data = <<<EOD
 terminals: ID int(10) unsigned NOT NULL auto_increment
 terminals: NAME varchar(255) NOT NULL DEFAULT ''
 terminals: HOST varchar(255) NOT NULL DEFAULT ''
 terminals: TITLE varchar(255) NOT NULL DEFAULT ''
 terminals: CANPLAY int(3) NOT NULL DEFAULT '0'
 terminals: CANTTS int(3) NOT NULL DEFAULT '0'
 terminals: MIN_MSG_LEVEL varchar(255) NOT NULL DEFAULT ''
 terminals: PLAYER_TYPE char(10) NOT NULL DEFAULT ''
 terminals: PLAYER_PORT varchar(255) NOT NULL DEFAULT ''
 terminals: PLAYER_USERNAME varchar(255) NOT NULL DEFAULT ''
 terminals: PLAYER_PASSWORD varchar(255) NOT NULL DEFAULT ''
 terminals: PLAYER_CONTROL_ADDRESS varchar(255) NOT NULL DEFAULT ''
 terminals: IS_ONLINE int(3) NOT NULL DEFAULT '0'
 terminals: MAJORDROID_API int(3) NOT NULL DEFAULT '0'
 terminals: LATEST_REQUEST varchar(255) NOT NULL DEFAULT ''
 terminals: LATEST_REQUEST_TIME datetime
 terminals: LATEST_ACTIVITY datetime
 terminals: LINKED_OBJECT varchar(255) NOT NULL DEFAULT ''
 terminals: LEVEL_LINKED_PROPERTY varchar(255) NOT NULL DEFAULT ''
EOD;
        parent::dbInstall($data);
    }
// --------------------------------------------------------------------
}

/*
*
* TW9kdWxlIGNyZWF0ZWQgTWFyIDI3LCAyMDA5IHVzaW5nIFNlcmdlIEouIHdpemFyZCAoQWN0aXZlVW5pdCBJbmMgd3d3LmFjdGl2ZXVuaXQuY29tKQ==
*
*/
?>
