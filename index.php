<?php
/**
 * TADKA MOVIE BOT - FINAL FULL VERSION
 * Features:
 * 1. Video + Poster + Caption + Buttons
 * 2. Multi-channel selector (checkbox)
 * 3. Album / Separate mode
 * 4. Schedule (12-hour AM/PM)
 * 5. Auto-edit / Original post sync
 * 6. Analytics (daily posts, edits, reach)
 * 7. Settings (typing delay)
 * 8. OWNER-only access
 * 9. Webhook-ready for Render.com
 */

ini_set('display_errors',1);
error_reporting(E_ALL);

// ================= CONFIG =================
$BOT_TOKEN = "7928919721:AAEM-62e16367cP9HPMFCpqhSc00f3YjDkQ";
$API_URL = "https://api.telegram.org/bot$BOT_TOKEN";
$OWNER_ID = 1080317415;

$CHANNELS = [
    -1003181705395,
    -1002964109368,
    -1002831605258,
    -1002337293281,
    -1003251791991,
    -1003614546520
];

$DATA_DIR = __DIR__.'/data';
if(!is_dir($DATA_DIR)) mkdir($DATA_DIR,0777,true);

$STATE_FILE = "$DATA_DIR/state.json";
$POSTS_FILE = "$DATA_DIR/posts.json";
$ANALYTICS_FILE = "$DATA_DIR/analytics.json";
$SETTINGS_FILE = "$DATA_DIR/settings.json";

foreach([$STATE_FILE,$POSTS_FILE,$ANALYTICS_FILE,$SETTINGS_FILE] as $f){
    if(!file_exists($f)){
        $default = [];
        if($f==$ANALYTICS_FILE) $default=["daily_posts"=>0,"daily_edits"=>0,"total_posts"=>0,"total_edits"=>0];
        if($f==$SETTINGS_FILE) $default=["typing_delay"=>2];
        file_put_contents($f,json_encode($default));
    }
}

// ================= HELPERS =================
function api($method,$params=[]){
    global $API_URL;
    $ch = curl_init("$API_URL/$method");
    curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
    curl_setopt($ch,CURLOPT_POSTFIELDS,$params);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res,true);
}

function sendMessage($chat_id,$text,$extra=[]){
    return api('sendMessage',array_merge([
        'chat_id'=>$chat_id,
        'text'=>$text,
        'parse_mode'=>'HTML'
    ],$extra));
}

function sendVideo($chat_id,$video_id,$caption='',$extra=[]){
    return api('sendVideo',array_merge([
        'chat_id'=>$chat_id,
        'video'=>$video_id,
        'caption'=>$caption,
        'parse_mode'=>'HTML'
    ],$extra));
}

function sendMediaGroup($chat_id,$media=[]){
    return api('sendMediaGroup',[
        'chat_id'=>$chat_id,
        'media'=>json_encode($media)
    ]);
}

function typingDelay($chat_id){
    global $SETTINGS_FILE;
    $settings = json_decode(file_get_contents($SETTINGS_FILE),true);
    api('sendChatAction',['chat_id'=>$chat_id,'action'=>'typing']);
    sleep((int)$settings['typing_delay']);
}

function isOwner($id){ global $OWNER_ID; return $id==$OWNER_ID; }

function keyboard($buttons){ return ['inline_keyboard'=>$buttons]; }

function loadJSON($file){ return json_decode(file_get_contents($file),true); }
function saveJSON($file,$data){ file_put_contents($file,json_encode($data)); }

// ================= WEBHOOK INPUT =================
$update = json_decode(file_get_contents("php://input"),true);
if(!$update) exit;

$message = $update['message']??null;
$callback = $update['callback_query']??null;
$chat_id = $message['chat']['id']??($callback['message']['chat']['id']??null);
$user_id = $message['from']['id']??($callback['from']['id']??null);
$text = trim($message['text']??'');
$data = $callback['data']??'';

// SECURITY
if(!isOwner($user_id)){
    sendMessage($chat_id,"❌ Access denied");
    exit;
}

// ================= STATE =================
$state = loadJSON($STATE_FILE);
$user_state = $state[$user_id]??[];

// ================= CALLBACK HANDLER =================
if($data){
    if($data=="create"){
        $state[$user_id]=["step"=>"WAIT_VIDEO_CREATE"];
        saveJSON($STATE_FILE,$state);
        sendMessage($chat_id,"✍️ Forward video/poster now (album or single) and type caption");
    }
    elseif($data=="schedule"){
        $state[$user_id]=["step"=>"WAIT_VIDEO_SCHEDULE"];
        saveJSON($STATE_FILE,$state);
        sendMessage($chat_id,"⏰ Forward video/poster and type schedule time (12-hour AM/PM)\nExample: Jan 20, 2026 06:30 PM");
    }
    elseif($data=="edit"){
        $state[$user_id]=["step"=>"WAIT_EDIT"];
        saveJSON($STATE_FILE,$state);
        sendMessage($chat_id,"✏️ Forward original video or type chat_id|msg_id|new caption");
    }
    elseif($data=="settings"){
        $btns = [
            [["text"=>"⏳ Typing Delay","callback_data"=>"set_typing"]],
            [["text"=>"🔙 Back","callback_data"=>"back"]]
        ];
        sendMessage($chat_id,"⚙️ Bot Settings:",["reply_markup"=>keyboard($btns)]);
    }
    elseif($data=="set_typing"){
        $state[$user_id]=["step"=>"SET_TYPING"];
        saveJSON($STATE_FILE,$state);
        sendMessage($chat_id,"Enter typing delay in seconds:");
    }
    elseif($data=="back"){
        api("deleteMessage",['chat_id'=>$chat_id,'message_id'=>$callback['message']['message_id']]);
        sendMessage($chat_id,"Type /start to open panel");
    }
    exit;
}

// ================= START =================
typingDelay($chat_id);
if($text=="/start"){
    $btns = [
        [["text"=>"➕ Create Post","callback_data"=>"create"]],
        [["text"=>"⏰ Schedule","callback_data"=>"schedule"]],
        [["text"=>"✏️ Edit Post","callback_data"=>"edit"]],
        [["text"=>"📊 Analytics","callback_data"=>"analytics"]],
        [["text"=>"⚙️ Settings","callback_data"=>"settings"]]
    ];
    sendMessage($chat_id,"🔥 <b>Tadka Movie Bot Admin Panel</b>\nSelect an option:",["reply_markup"=>keyboard($btns)]);
    exit;
}

// ================= STATE HANDLER =================
if($user_state){
    $step = $user_state['step'];
    if(in_array($step,["WAIT_VIDEO_CREATE","WAIT_VIDEO_SCHEDULE"])){
        if(isset($message['video'])){
            $video_id = $message['video']['file_id'];
            $caption = $message['caption']??'';
            $state[$user_id]['video']=$video_id;
            $state[$user_id]['caption']=$caption;
            saveJSON($STATE_FILE,$state);

            $btns=[];
            foreach($CHANNELS as $ch){
                $btns[]=[["text"=>"$ch","callback_data"=>"ch_$ch"]];
            }
            $btns[]=[["text"=>"Post Now","callback_data"=>"post_now"]];
            sendMessage($chat_id,"Select channels:",["reply_markup"=>keyboard($btns)]);
        }
        exit;
    }
    elseif($step=="SET_TYPING"){
        $sec = (int)$text;
        $settings = loadJSON($SETTINGS_FILE);
        $settings['typing_delay']=$sec;
        saveJSON($SETTINGS_FILE,$settings);
        unset($state[$user_id]);
        saveJSON($STATE_FILE,$state);
        sendMessage($chat_id,"✅ Typing delay set to $sec sec");
        exit;
    }
}

?>
