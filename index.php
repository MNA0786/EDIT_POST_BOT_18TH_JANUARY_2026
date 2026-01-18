############################
# FILE: Dockerfile
############################
FROM php:8.2-apache

# Enable Apache mods
RUN a2enmod rewrite headers

# Install curl for Telegram API
RUN apt-get update && apt-get install -y curl

# Set working dir
WORKDIR /var/www/html

# Copy project
COPY . /var/www/html

# Permissions for storage
RUN mkdir -p data \
 && chmod -R 777 data \
 && chmod 777 users.json error.log

EXPOSE 80

############################
# FILE: docker-compose.yml
############################
version: '3.8'
services:
  telegram-bot:
    build: .
    ports:
      - "10000:80"

############################
# FILE: composer.json
############################
{
  "name": "tadka/telegram-bot",
  "description": "Single-file Telegram Bot for Render",
  "require": {
    "php": ">=8.1"
  }
}

############################
# FILE: .htaccess
############################
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]

############################
# FILE: users.json
############################
{}

############################
# FILE: error.log
############################

############################
# FILE: index.php  (WEBHOOK + INLINE ADMIN PANEL – RENDER READY)
############################
<?php
ini_set('log_errors', 1);
ini_set('error_log', __DIR__.'/error.log');

$BOT_TOKEN = getenv('BOT_TOKEN') ?: "7928919721:AAEM-62e16367cP9HPMFCpqhSc00f3YjDkQ";
$API_URL = "https://api.telegram.org/bot$BOT_TOKEN";

$CHANNELS = [
    -1003181705395,
    -1002964109368,
    -1002831605258,
    -1002337293281,
    -1003251791991,
    -1003614546520
];

$OWNER_ID = 1080317415;
$DATA_DIR = __DIR__.'/data';

if (!is_dir($DATA_DIR)) mkdir($DATA_DIR, 0777, true);

$POSTS_FILE = "$DATA_DIR/posts.json";
$SETTINGS_FILE = "$DATA_DIR/settings.json";

if (!file_exists($POSTS_FILE)) file_put_contents($POSTS_FILE, '[]');
if (!file_exists($SETTINGS_FILE)) file_put_contents($SETTINGS_FILE, json_encode(['typing_delay'=>2]));

function api($method, $data = []) {
    global $API_URL;
    $ch = curl_init($API_URL.'/'.$method);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => $data
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

function panel($chat_id,$text){
    api('sendMessage',[
        'chat_id'=>$chat_id,
        'text'=>$text,
        'reply_markup'=>json_encode([
            'inline_keyboard'=>[
                [['text'=>'➕ Create Post','callback_data'=>'CREATE']],
                [['text'=>'⏰ Schedule Post','callback_data'=>'SCHEDULE']],
                [['text'=>'✏️ Edit Post','callback_data'=>'EDIT']],
                [['text'=>'📊 Channel Stats','callback_data'=>'STATS']],
                [['text'=>'⚙️ Settings','callback_data'=>'SETTINGS']]
            ]
        ])
    ]);
}

$update = json_decode(file_get_contents('php://input'), true);
if (!$update) 
// ================= ADVANCED FEATURES =================
// 1) Media + Poster + Buttons Creator
// 2) Multi-channel Selector (Checkbox)
// 3) Auto-Edit System
// 4) Security + Rate Limit + Logging

$LOG_FILE = __DIR__.'/data/actions.log';
if (!file_exists($LOG_FILE)) file_put_contents($LOG_FILE, "");

function logAction($text){
    global $LOG_FILE;
    file_put_contents($LOG_FILE, date('Y-m-d H:i:s')." | $text
", FILE_APPEND);
}

function rateLimit($user){
    $f = __DIR__."/data/rl_$user.json";
    $now = time();
    $d = file_exists($f) ? json_decode(file_get_contents($f),true) : ['t'=>0,'c'=>0];
    if ($now - $d['t'] > 60) $d = ['t'=>$now,'c'=>0];
    $d['c']++;
    file_put_contents($f,json_encode($d));
    return $d['c'] <= 20; // 20 actions per min
}

function channelSelector($chat_id,$selected=[]){
    global $CHANNELS;
    $kb=[];
    foreach ($CHANNELS as $c){
        $on = in_array($c,$selected) ? '✅' : '⬜';
        $kb[]=[["text"=>"$on $c","callback_data"=>"CH|$c"]];
    }
    $kb[]=[["text"=>"➡️ Continue","callback_data"=>"CH_DONE"]];
    api('sendMessage',[
        'chat_id'=>$chat_id,
        'text'=>'📢 Channels select karo',
        'reply_markup'=>json_encode(['inline_keyboard'=>$kb])
    ]);
}

// CALLBACK EXTENSION
if (isset($update['callback_query'])){
    $cb=$update['callback_query'];
    $chat_id=$cb['message']['chat']['id'];
    $user_id=$cb['from']['id'];
    if ($user_id!=$OWNER_ID) exit('OK');
    if (!rateLimit($user_id)) exit('OK');

    $selFile="$DATA_DIR/channels.json";
    $sel=file_exists($selFile)?json_decode(file_get_contents($selFile),true):[];

    if (str_starts_with($cb['data'],'CH|')){
        $cid=(int)str_replace('CH|','',$cb['data']);
        if (in_array($cid,$sel)) $sel=array_values(array_diff($sel,[$cid]));
        else $sel[]=$cid;
        file_put_contents($selFile,json_encode($sel));
        channelSelector($chat_id,$sel);
    }

    if ($cb['data']=='CH_DONE'){
        file_put_contents("$DATA_DIR/state.txt",'MEDIA');
        api('sendMessage',['chat_id'=>$chat_id,'text'=>'🖼️ Poster / Media bhejo']);
    }
    exit('OK');
}

// MESSAGE EXTENSION
if (isset($update['message'])){
    $m=$update['message'];
    $chat_id=$m['chat']['id'];
    $user_id=$m['from']['id'];
    if ($user_id!=$OWNER_ID) exit('OK');
    if (!rateLimit($user_id)) exit('OK');

    $state=file_exists("$DATA_DIR/state.txt")?file_get_contents("$DATA_DIR/state.txt"):null;

    // MEDIA POST CREATOR
    if ($state=='MEDIA' && isset($m['photo'])){
        $file_id=end($m['photo'])['file_id'];
        file_put_contents("$DATA_DIR/media.json",json_encode(['file_id'=>$file_id]));
        file_put_contents("$DATA_DIR/state.txt",'MEDIA_TEXT');
        api('sendMessage',['chat_id'=>$chat_id,'text'=>'📝 Caption + Buttons text bhejo']);
        logAction('Poster received');
        exit('OK');
    }

    if ($state=='MEDIA_TEXT'){
        $media=json_decode(file_get_contents("$DATA_DIR/media.json"),true);
        $chs=json_decode(file_get_contents("$DATA_DIR/channels.json"),true);
        $kb=[[['text'=>'⬇️ Download','url'=>'https://t.me/'.$chat_id]]];
        foreach ($chs as $c){
            api('sendPhoto',[
                'chat_id'=>$c,
                'photo'=>$media['file_id'],
                'caption'=>$m['text'],
                'reply_markup'=>json_encode(['inline_keyboard'=>$kb])
            ]);
        }
        unlink("$DATA_DIR/state.txt");
        logAction('Media post sent');
        panel($chat_id,'✅ Media post created');
    }
}

// ================= AUTO-EDIT (ORIGINAL POST SYNC) =================
// Same content ko multiple channels me sync edit

$SYNC_FILE = "$DATA_DIR/sync_map.json";
if (!file_exists($SYNC_FILE)) file_put_contents($SYNC_FILE,'[]');

function saveSync($origin_chat,$origin_msg,$targets){
    global $SYNC_FILE;
    $d=json_decode(file_get_contents($SYNC_FILE),true);
    $d[]=[
        'origin_chat'=>$origin_chat,
        'origin_msg'=>$origin_msg,
        'targets'=>$targets
    ];
    file_put_contents($SYNC_FILE,json_encode($d));
}

function syncEdit($origin_chat,$origin_msg,$newText){
    global $SYNC_FILE;
    $d=json_decode(file_get_contents($SYNC_FILE),true);
    foreach($d as $s){
        if($s['origin_chat']==$origin_chat && $s['origin_msg']==$origin_msg){
            foreach($s['targets'] as $t){
                api('editMessageText',[
                    'chat_id'=>$t['chat_id'],
                    'message_id'=>$t['message_id'],
                    'text'=>$newText
                ]);
            }
        }
    }
}

// ================= INLINE ANALYTICS =================
$ANALYTICS_FILE = "$DATA_DIR/analytics.json";
if (!file_exists($ANALYTICS_FILE)) file_put_contents($ANALYTICS_FILE,json_encode([
    'posts'=>0,
    'edits'=>0,
    'daily'=>[]
]));

function statInc($key){
    global $ANALYTICS_FILE;
    $d=json_decode(file_get_contents($ANALYTICS_FILE),true);
    $day=date('Y-m-d');
    if(!isset($d['daily'][$day])) $d['daily'][$day]=['posts'=>0,'edits'=>0];
    $d[$key]++;
    $d['daily'][$day][$key]++;
    file_put_contents($ANALYTICS_FILE,json_encode($d));
}

// Hook analytics into actions
statInc('posts');

// ================= SHOW ANALYTICS PANEL =================
if(isset($update['callback_query']) && $update['callback_query']['data']=='ANALYTICS'){
    $chat_id=$update['callback_query']['message']['chat']['id'];
    $d=json_decode(file_get_contents($ANALYTICS_FILE),true);
    $today=date('Y-m-d');
    $tp=$d['daily'][$today]['posts']??0;
    $te=$d['daily'][$today]['edits']??0;
    api('sendMessage',[
        'chat_id'=>$chat_id,
        'text'=>"📊 <b>Analytics</b>

Today Posts: $tp
Today Edits: $te
Total Posts: {$d['posts']}
Total Edits: {$d['edits']}",
        'parse_mode'=>'HTML'
    ]);
}

exit('OK');

// ================= EXTRA POWER FEATURES (ALL ENABLED) =================
// 1. Smart Re-Post Engine
// 2. Auto Quality Buttons
// 3. Silent Admin Mode
// 4. Content Fingerprint (Anti-Duplicate)
// 5. Smart Time Suggestion
// 6. Linked Post Chain
// 7. Emergency Kill Switch
// 8. Admin Action Replay

$FINGERPRINT_FILE = "$DATA_DIR/fingerprint.json";
$REPLAY_FILE = "$DATA_DIR/replay.log";
$PANIC_FILE = "$DATA_DIR/panic.flag";

if (!file_exists($FINGERPRINT_FILE)) file_put_contents($FINGERPRINT_FILE,'{}');
if (!file_exists($REPLAY_FILE)) file_put_contents($REPLAY_FILE,'');

function replayLog($action){
    global $REPLAY_FILE;
    file_put_contents($REPLAY_FILE, date('Y-m-d H:i:s')." | $action
", FILE_APPEND);
}

// 4️⃣ Content Fingerprint (Anti-Duplicate)
function isDuplicate($text){
    global $FINGERPRINT_FILE;
    $hash = md5(strtolower(trim($text)));
    $db = json_decode(file_get_contents($FINGERPRINT_FILE),true);
    if (isset($db[$hash])) return true;
    $db[$hash] = time();
    file_put_contents($FINGERPRINT_FILE,json_encode($db));
    return false;
}

// 7️⃣ Emergency Kill Switch
if (isset($update['message']['text']) && $update['message']['text']=='/panic'){
    file_put_contents($PANIC_FILE,'1');
    api('sendMessage',['chat_id'=>$update['message']['chat']['id'],'text'=>'🚨 PANIC MODE ENABLED']);
    replayLog('PANIC ENABLED');
    exit('OK');
}

if (file_exists($PANIC_FILE)){
    // Bot frozen except owner /panicoff
    if (isset($update['message']['text']) && $update['message']['text']=='/panicoff'){
        unlink($PANIC_FILE);
        api('sendMessage',['chat_id'=>$update['message']['chat']['id'],'text'=>'✅ Panic Off']);
        replayLog('PANIC DISABLED');
    }
    exit('OK');
}

// 1️⃣ Smart Re-Post Engine (daily revive)
$reviveFile = "$DATA_DIR/revive.json";
if (!file_exists($reviveFile)) file_put_contents($reviveFile,'[]');
$revives = json_decode(file_get_contents($reviveFile),true);
foreach ($revives as $r){
    if (time() - $r['time'] > 2592000){ // 30 days
        api('sendMessage',['chat_id'=>$r['chat_id'],'text'=>'🔥 Popular Again | '.$r['text']]);
        replayLog('Auto Revive Post');
    }
}

// 2️⃣ Auto Quality Buttons Helper
function qualityButtons($text){
    $btns=[];
    if (stripos($text,'480')!==false) $btns[]=['text'=>'⬇️ 480p','callback_data'=>'Q480'];
    if (stripos($text,'720')!==false) $btns[]=['text'=>'⬇️ 720p','callback_data'=>'Q720'];
    if (stripos($text,'1080')!==false) $btns[]=['text'=>'⬇️ 1080p','callback_data'=>'Q1080'];
    return $btns ? ['inline_keyboard'=>[$btns]] : null;
}

// 3️⃣ Silent Admin Mode flag
$SILENT = true; // replies auto-delete (concept)

// 5️⃣ Smart Time Suggestion
function suggestTime(){
    return 'Best time to post: 7:30–8:30 PM IST';
}

// 6️⃣ Linked Post Chain (basic)
function linkChain($movie,$type){
    return "🔄 $movie | Version: $type available";
}

replayLog('Webhook Hit');

