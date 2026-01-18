<?php
/* ================================
   TADKA MOVIE BOT – CONFIG FILE
   Sab important settings yahin se control hongi
   ================================ */

// ================= BOT DETAILS =================
define("BOT_TOKEN", "7928919721:AAEM-62e16367cP9HPMFCpqhSc00f3YjDkQ");
define("BOT_USERNAME", "@TadkaMovieBot");
define("BOT_ID", 7928919721);

define("OWNER_ID", 1080317415);

// ================= TIMEZONE =================
// India ke liye perfect
date_default_timezone_set("Asia/Kolkata");

// ================= CHANNEL LIST =================
// Tum yahin se decide karoge kaunsa channel use hoga
$CHANNELS = [
    "-1003181705395" => "@EntertainmentTadka786",
    "-1002964109368" => "@ETBackup",
    "-1002831605258" => "@threater_print_movies",
    "-1002337293281" => "Backup Channel 2",
    "-1003251791991" => "Private Channel",
    "-1003614546520" => "Forwarded From Any Channel"
];

// ================= GROUP (OPTIONAL) =================
define("ADMIN_GROUP_ID", "-1003083386043");

// ================= FEATURE TOGGLES =================
// true = ON | false = OFF

define("ENABLE_TYPING_DELAY", true);     // typing... delay realism
define("ENABLE_STATS", true);            // analytics enable
define("ENABLE_AUTO_EDIT", true);         // original post edit → sab channels sync
define("ENABLE_SCHEDULER", true);         // scheduled posts
define("ENABLE_LOGGING", true);           // logs save karega

// ================= RATE LIMIT =================
// seconds me (spam protection)
define("ACTION_COOLDOWN", 2);

// ================= DATA PATH =================
define("DATA_DIR", __DIR__ . "/data");

define("STATE_FILE", DATA_DIR . "/state.json");
define("POST_MAP_FILE", DATA_DIR . "/post_map.json");
define("STATS_FILE", DATA_DIR . "/stats.json");
define("SCHEDULE_FILE", DATA_DIR . "/schedule.json");
define("LOG_FILE", DATA_DIR . "/bot.log");

// ================= SECURITY =================
// Agar future me aur admins add karne ho
$ADMINS = [
    1080317415, // Mahatab Ansari
];

// ================= HELPER =================
function isAdmin($user_id) {
    global $ADMINS;
    return in_array($user_id, $ADMINS);
}
