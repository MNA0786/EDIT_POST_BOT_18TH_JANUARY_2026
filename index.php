<?php
/* =====================================================
   TADKA MOVIE BOT – SINGLE FILE ENGINE
   Platform: Render.com (Webhook)
   Authorised Admin Panel
   ===================================================== */

require_once __DIR__ . "/config.php";

/* ================= BASIC SETUP ================= */
$API_URL = "https://api.telegram.org/bot" . BOT_TOKEN;
$update = json_decode(file_get_contents("php://input"), true);
if (!$update) exit;

/* ================= HELPERS ================= */
function api($method, $data = []) {
    global $API_URL;
    $ch = curl_init($API_URL . "/" . $method);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => $data
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

function send($chat, $text, $extra = []) {
    api("sendMessage", array_merge([
        "chat_id" => $chat,
        "text" => $text,
        "parse_mode" => "HTML"
    ], $extra));
}

function keyboard($buttons) {
    return json_encode(["inline_keyboard" => $buttons]);
}

function logEvent($text) {
    if (!ENABLE_LOGGING) return;
    file_put_contents(LOG_FILE, "[".date("d-m-Y H:i:s")."] ".$text."\n", FILE_APPEND);
}

/* ================= STORAGE ================= */
function readJson($file, $default = []) {
    if (!file_exists($file)) return $default;
    return json_decode(file_get_contents($file), true);
}

function writeJson($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

/* ================= UPDATE TYPES ================= */
$message = $update["message"] ?? null;
$callback = $update["callback_query"] ?? null;

/* ================= MESSAGE HANDLER ================= */
if ($message) {

    $chat_id = $message["chat"]["id"];
    $user_id = $message["from"]["id"];
    $text = trim($message["text"] ?? "");

    if (!isAdmin($user_id)) {
        send($chat_id, "❌ Access Denied");
        exit;
    }

    /* ===== START ===== */
    if ($text == "/start") {

        $btns = [
            [
                ["text"=>"➕ Create Post","callback_data"=>"create"],
                ["text"=>"⏰ Schedule","callback_data"=>"schedule"]
            ],
            [
                ["text"=>"✏️ Edit Post","callback_data"=>"edit"],
                ["text"=>"📊 Stats","callback_data"=>"stats"]
            ],
            [
                ["text"=>"⚙️ Settings","callback_data"=>"settings"]
            ]
        ];

        send($chat_id,
        "🔥 <b>Tadka Movie Bot Admin Panel</b>\n\nSelect an option:",
        ["reply_markup"=>keyboard($btns)]
        );
    }

    /* ===== VIDEO FORWARD DETECT ===== */
    if (isset($message["video"])) {

        $state = readJson(STATE_FILE);
        $state[$user_id] = [
            "step" => "CHANNEL_SELECT",
            "file_id" => $message["video"]["file_id"],
            "caption" => $message["caption"] ?? ""
        ];
        writeJson(STATE_FILE, $state);

        $btns = [];
        foreach ($GLOBALS["CHANNELS"] as $cid => $name) {
            $btns[] = [
                ["text"=>"⬜ ".$name,"callback_data"=>"ch_".$cid]
            ];
        }
        $btns[] = [
            ["text"=>"✅ Post Now","callback_data"=>"post_now"]
        ];

        send($chat_id,
        "📌 <b>Channels select karo</b>\nVideo ready hai:",
        ["reply_markup"=>keyboard($btns)]
        );
    }
}

/* ================= CALLBACK HANDLER ================= */
if ($callback) {

    $chat_id = $callback["message"]["chat"]["id"];
    $user_id = $callback["from"]["id"];
    $data = $callback["data"];

    if (!isAdmin($user_id)) exit;

    $state = readJson(STATE_FILE);

    /* ===== CREATE ===== */
    if ($data == "create") {
        send($chat_id, "📹 Video forward karo (caption + buttons ke saath)");
    }

    /* ===== CHANNEL TOGGLE ===== */
    if (strpos($data,"ch_") === 0) {
        $cid = str_replace("ch_","",$data);

        $state[$user_id]["channels"][$cid] =
            !($state[$user_id]["channels"][$cid] ?? false);

        writeJson(STATE_FILE,$state);

        $btns=[];
        foreach ($GLOBALS["CHANNELS"] as $id=>$name){
            $mark = !empty($state[$user_id]["channels"][$id]) ? "✅" : "⬜";
            $btns[]=[[ "text"=>"$mark $name","callback_data"=>"ch_$id" ]];
        }
        $btns[]=[[ "text"=>"🚀 Post Now","callback_data"=>"post_now" ]];

        api("editMessageReplyMarkup",[
            "chat_id"=>$chat_id,
            "message_id"=>$callback["message"]["message_id"],
            "reply_markup"=>keyboard($btns)
        ]);
    }

    /* ===== POST NOW ===== */
    if ($data=="post_now") {

        $info = $state[$user_id];
        $map = readJson(POST_MAP_FILE);

        foreach ($info["channels"] as $cid=>$ok) {
            if (!$ok) continue;

            $res = api("sendVideo",[
                "chat_id"=>$cid,
                "video"=>$info["file_id"],
                "caption"=>$info["caption"],
                "parse_mode"=>"HTML"
            ]);

            $mid = $res["result"]["message_id"];
            $map[$info["file_id"]][$cid] = $mid;
        }

        writeJson(POST_MAP_FILE,$map);

        // stats
        $stats = readJson(STATS_FILE);
        $today = date("Y-m-d");
        $stats["total_posts"]++;
        $stats["daily"][$today]["posts"] =
            ($stats["daily"][$today]["posts"] ?? 0) + 1;
        writeJson(STATS_FILE,$stats);

        unset($state[$user_id]);
        writeJson(STATE_FILE,$state);

        send($chat_id,"✅ <b>Video posted successfully</b>");
    }

    /* ===== STATS ===== */
    if ($data=="stats") {
        $stats = readJson(STATS_FILE);
        $today = date("Y-m-d");

        send($chat_id,
        "📊 <b>Analytics</b>\n\n".
        "Today Posts: ".($stats["daily"][$today]["posts"] ?? 0)."\n".
        "Today Edits: ".($stats["daily"][$today]["edits"] ?? 0)."\n\n".
        "Total Posts: ".($stats["total_posts"] ?? 0)."\n".
        "Total Edits: ".($stats["total_edits"] ?? 0)
        );
    }
}

/* ================= AUTO EDIT SYNC ================= */
// Agar tum original channel me caption edit karoge,
// to yahan se sab channels me auto edit ho jayega

if (isset($update["edited_message"]["video"])) {

    $file_id = $update["edited_message"]["video"]["file_id"];
    $new_caption = $update["edited_message"]["caption"] ?? "";

    $map = readJson(POST_MAP_FILE);
    if (!isset($map[$file_id])) exit;

    foreach ($map[$file_id] as $cid=>$mid){
        api("editMessageCaption",[
            "chat_id"=>$cid,
            "message_id"=>$mid,
            "caption"=>$new_caption,
            "parse_mode"=>"HTML"
        ]);
    }

    $stats = readJson(STATS_FILE);
    $today=date("Y-m-d");
    $stats["total_edits"]++;
    $stats["daily"][$today]["edits"] =
        ($stats["daily"][$today]["edits"] ?? 0)+1;
    writeJson(STATS_FILE,$stats);

    logEvent("Auto-edit sync done");
}
