<?php
/* ================================
   TADKA MOVIE BOT – ADMIN PANEL
   Video-based | Multi-channel | Auto Edit | Schedule
   ================================ */

// ---------- CONFIG ----------
$BOT_TOKEN = "7928919721:AAEM-62e16367cP9HPMFCpqhSc00f3YjDkQ";
$API_URL = "https://api.telegram.org/bot$BOT_TOKEN/";

$OWNER_ID = 1080317415;

$CHANNELS = [
    "-1003181705395" => "@EntertainmentTadka786",
    "-1002964109368" => "@ETBackup",
    "-1002831605258" => "@threater_print_movies",
    "-1002337293281" => "Backup Channel 2",
    "-1003251791991" => "Private Channel",
    "-1003614546520" => "Forwarded From Any Channel"
];

$DATA_DIR = __DIR__ . "/data";
if (!file_exists($DATA_DIR)) mkdir($DATA_DIR, 0777, true);

$STATE_FILE = "$DATA_DIR/state.json";
$POST_MAP_FILE = "$DATA_DIR/post_map.json";
$STATS_FILE = "$DATA_DIR/stats.json";

// ---------- BASIC FUNCTIONS ----------
function tg($method, $data = []) {
    global $API_URL;
    $ch = curl_init($API_URL . $method);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $data
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

function sendMessage($chat_id, $text, $keyboard = null) {
    $data = [
        "chat_id" => $chat_id,
        "text" => $text,
        "parse_mode" => "HTML"
    ];
    if ($keyboard) $data["reply_markup"] = json_encode($keyboard);
    tg("sendMessage", $data);
}

function editMessage($chat_id, $msg_id, $text) {
    tg("editMessageCaption", [
        "chat_id" => $chat_id,
        "message_id" => $msg_id,
        "caption" => $text,
        "parse_mode" => "HTML"
    ]);
}

function typingDelay($chat_id) {
    tg("sendChatAction", ["chat_id"=>$chat_id,"action"=>"typing"]);
    usleep(rand(500000,1200000));
}

// ---------- LOAD FILES ----------
$state = file_exists($STATE_FILE) ? json_decode(file_get_contents($STATE_FILE), true) : [];
$postMap = file_exists($POST_MAP_FILE) ? json_decode(file_get_contents($POST_MAP_FILE), true) : [];
$stats = file_exists($STATS_FILE) ? json_decode(file_get_contents($STATS_FILE), true) : [
    "today_posts"=>0,"today_edits"=>0,"total_posts"=>0,"total_edits"=>0
];

// ---------- UPDATE ----------
$update = json_decode(file_get_contents("php://input"), true);
if (!$update) exit;

$message = $update["message"] ?? null;
$callback = $update["callback_query"] ?? null;

// =================================================
// =============== START COMMAND ===================
// =================================================
if ($message) {
    $chat_id = $message["chat"]["id"];
    $user_id = $message["from"]["id"];
    $text = $message["text"] ?? "";

    if ($user_id != $OWNER_ID) exit;

    // /start
    if ($text == "/start") {
        $keyboard = [
            "inline_keyboard" => [
                [["text"=>"➕ Create Post","callback_data"=>"create"]],
                [["text"=>"⏰ Scheduled Posts","callback_data"=>"schedule"],["text"=>"✏️ Edit Post","callback_data"=>"edit"]],
                [["text"=>"📊 Channel Stats","callback_data"=>"stats"],["text"=>"⚙️ Settings","callback_data"=>"settings"]]
            ]
        ];
        sendMessage($chat_id,
            "🔥 <b>Tadka Movie Bot Admin Panel</b>\n\nHere you can create rich posts, view stats and accomplish other tasks.",
            $keyboard
        );
        exit;
    }

    // ---------- MEDIA HANDLING ----------
    if (isset($state[$user_id]) && $state[$user_id]["step"] == "WAIT_VIDEO") {
        if (!isset($message["video"]) && !isset($message["document"])) {
            sendMessage($chat_id, "❌ Sirf video/document forward karo");
            exit;
        }

        $state[$user_id]["file"] = $message["video"] ?? $message["document"];
        $state[$user_id]["step"] = "WAIT_CAPTION";
        file_put_contents($STATE_FILE, json_encode($state));

        sendMessage($chat_id, "✍️ Ab caption + buttons bhejo");
        exit;
    }

    // ---------- CAPTION ----------
    if (isset($state[$user_id]) && $state[$user_id]["step"] == "WAIT_CAPTION") {
        $state[$user_id]["caption"] = $text;
        $state[$user_id]["step"] = "SELECT_CHANNELS";
        file_put_contents($STATE_FILE, json_encode($state));

        $kb = ["inline_keyboard"=>[]];
        foreach ($CHANNELS as $cid=>$name) {
            $kb["inline_keyboard"][] = [
                ["text"=>"☑️ $name","callback_data"=>"ch_$cid"]
            ];
        }
        $kb["inline_keyboard"][] = [["text"=>"✅ POST","callback_data"=>"post_now"]];

        sendMessage($chat_id, "📢 Channels select karo:", $kb);
        exit;
    }
}

// =================================================
// =============== CALLBACK HANDLER =================
// =================================================
if ($callback) {
    $data = $callback["data"];
    $chat_id = $callback["message"]["chat"]["id"];
    $user_id = $callback["from"]["id"];

    if ($user_id != $OWNER_ID) exit;

    // CREATE POST
    if ($data == "create") {
        typingDelay($chat_id);
        sendMessage($chat_id, "🎬 Video forward karo (Telegram me jo post karni hai)");
        $state[$user_id] = ["step"=>"WAIT_VIDEO","channels"=>[]];
        file_put_contents($STATE_FILE, json_encode($state));
        exit;
    }

    // CHANNEL SELECT
    if (str_starts_with($data,"ch_")) {
        $cid = str_replace("ch_","",$data);
        if (!in_array($cid,$state[$user_id]["channels"]))
            $state[$user_id]["channels"][] = $cid;
        file_put_contents($STATE_FILE, json_encode($state));
        exit;
    }

    // POST NOW
    if ($data == "post_now") {
        $info = $state[$user_id];
        foreach ($info["channels"] as $cid) {
            $res = tg("copyMessage",[
                "chat_id"=>$cid,
                "from_chat_id"=>$callback["message"]["chat"]["id"],
                "message_id"=>$info["file"]["file_id"],
                "caption"=>$info["caption"],
                "parse_mode"=>"HTML"
            ]);
            if ($res["ok"]) {
                $postMap[$info["file"]["file_id"]][] = [
                    "chat"=>$cid,
                    "msg"=>$res["result"]["message_id"]
                ];
            }
        }

        $stats["today_posts"]++;
        $stats["total_posts"]++;

        file_put_contents($POST_MAP_FILE, json_encode($postMap));
        file_put_contents($STATS_FILE, json_encode($stats));

        unset($state[$user_id]);
        file_put_contents($STATE_FILE, json_encode($state));

        sendMessage($chat_id, "✅ Post created & synced in selected channels");
        exit;
    }

    // STATS
    if ($data == "stats") {
        $txt = "📊 <b>Analytics</b>\n\n".
               "Today Posts: {$stats["today_posts"]}\n".
               "Today Edits: {$stats["today_edits"]}\n".
               "Total Posts: {$stats["total_posts"]}\n".
               "Total Edits: {$stats["total_edits"]}";
        sendMessage($chat_id, $txt);
        exit;
    }
}
