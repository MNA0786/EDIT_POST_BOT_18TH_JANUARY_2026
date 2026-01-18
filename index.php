<?php
/**
 * TADKA MOVIE BOT - COMPLETE TELEGRAM ADMIN PANEL
 * Version: 3.0
 * Features:
 * 1. Video-based workflow (forward → select → post)
 * 2. Multi-channel selector with checkboxes
 * 3. Auto-edit sync (edit once, update everywhere)
 * 4. Scheduled posts with 12-hour format
 * 5. Channel stats and analytics
 * 6. Admin panel with inline buttons
 * 7. Emergency panic mode
 * 8. Rate limiting and security
 * 
 * Developer: Mahatab Ansari
 * Bot: @TadkaMovieBot
 * Channels: 6 channels
 * Owner ID: 1080317415
 */

// ==================== CONFIGURATION ====================
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/data/error.log');

// Bot Token (can be set via environment variable)
$BOT_TOKEN = getenv('BOT_TOKEN') ?: '7928919721:AAEM-62e16367cP9HPMFCpqhSc00f3YjDkQ';
$API_URL = "https://api.telegram.org/bot" . $BOT_TOKEN;

// Channel Configuration (ID => Name)
$CHANNELS = [
    -1003181705395 => '🎬 EntertainmentTadka786',
    -1002964109368 => '📼 ETBackup',
    -1002831605258 => '🎥 Threater_Print_Movies',
    -1002337293281 => '💾 Backup Channel 2',
    -1003251791991 => '🔒 Private Channel',
    -1003614546520 => '🔄 Forwarded From Any Channel'
];

$GROUP_ID = -1003083386043;  // @EntertainmentTadka7860
$OWNER_ID = 1080317415;
$BOT_USERNAME = '@TadkaMovieBot';
$BOT_ID = 7928919721;

// Data Directory Setup
$DATA_DIR = __DIR__ . '/data';
if (!is_dir($DATA_DIR)) {
    mkdir($DATA_DIR, 0777, true);
    chmod($DATA_DIR, 0777);
}

// File Paths
$POSTS_FILE = $DATA_DIR . '/posts.json';
$SETTINGS_FILE = $DATA_DIR . '/settings.json';
$SYNC_FILE = $DATA_DIR . '/sync_map.json';
$ANALYTICS_FILE = $DATA_DIR . '/analytics.json';
$PANIC_FILE = $DATA_DIR . '/panic.flag';
$USERS_FILE = $DATA_DIR . '/users.json';
$REPLAY_LOG = $DATA_DIR . '/replay.log';
$RATE_LIMIT_FILE = $DATA_DIR . '/rate_limit.json';

// Initialize JSON files if they don't exist
$json_files = [
    $POSTS_FILE, $SETTINGS_FILE, $SYNC_FILE, 
    $ANALYTICS_FILE, $USERS_FILE, $RATE_LIMIT_FILE
];

foreach ($json_files as $file) {
    if (!file_exists($file)) {
        file_put_contents($file, '{}');
        chmod($file, 0777);
    }
}

// Default Settings
if (filesize($SETTINGS_FILE) == 0) {
    $default_settings = [
        'typing_delay' => 2,
        'auto_sync' => true,
        'auto_delete_admin_reply' => true,
        'default_channels' => [],
        'timezone' => 'Asia/Kolkata',
        'schedule_check_interval' => 60,
        'max_posts_per_day' => 50,
        'button_template' => "⬇️ Download|https://t.me/TadkaMovieBot\n🎬 Trailer|https://youtube.com"
    ];
    file_put_contents($SETTINGS_FILE, json_encode($default_settings, JSON_PRETTY_PRINT));
}

// Set timezone
$settings = json_decode(file_get_contents($SETTINGS_FILE), true);
date_default_timezone_set($settings['timezone'] ?? 'Asia/Kolkata');

// ==================== HELPER FUNCTIONS ====================

/**
 * Call Telegram API
 */
function api($method, $data = []) {
    global $API_URL;
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $API_URL . '/' . $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => ['Content-Type: multipart/form-data']
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        error_log("cURL Error: " . curl_error($ch));
        curl_close($ch);
        return ['ok' => false, 'error' => curl_error($ch)];
    }
    
    curl_close($ch);
    
    if ($http_code != 200) {
        error_log("HTTP Error: " . $http_code . " - " . $response);
    }
    
    return json_decode($response, true);
}

/**
 * Send message with HTML parsing
 */
function sendMessage($chat_id, $text, $extra = []) {
    $params = array_merge([
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true
    ], $extra);
    
    return api('sendMessage', $params);
}

/**
 * Delete message
 */
function deleteMessage($chat_id, $message_id) {
    return api('deleteMessage', [
        'chat_id' => $chat_id,
        'message_id' => $message_id
    ]);
}

/**
 * Edit message text
 */
function editMessageText($chat_id, $message_id, $text, $extra = []) {
    $params = array_merge([
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $text,
        'parse_mode' => 'HTML'
    ], $extra);
    
    return api('editMessageText', $params);
}

/**
 * Edit message caption
 */
function editMessageCaption($chat_id, $message_id, $caption, $extra = []) {
    $params = array_merge([
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'caption' => $caption,
        'parse_mode' => 'HTML'
    ], $extra);
    
    return api('editMessageCaption', $params);
}

/**
 * Send typing action
 */
function sendTypingAction($chat_id) {
    global $SETTINGS_FILE;
    $settings = json_decode(file_get_contents($SETTINGS_FILE), true);
    $delay = $settings['typing_delay'] ?? 2;
    
    api('sendChatAction', [
        'chat_id' => $chat_id,
        'action' => 'typing'
    ]);
    
    if ($delay > 0) {
        sleep($delay);
    }
}

/**
 * Check if user is owner
 */
function isOwner($user_id) {
    global $OWNER_ID;
    return $user_id == $OWNER_ID;
}

/**
 * Rate limiting
 */
function checkRateLimit($user_id) {
    global $RATE_LIMIT_FILE;
    
    $data = file_exists($RATE_LIMIT_FILE) ? 
        json_decode(file_get_contents($RATE_LIMIT_FILE), true) : [];
    
    $current_time = time();
    $user_key = (string)$user_id;
    
    if (!isset($data[$user_key])) {
        $data[$user_key] = [
            'count' => 1,
            'timestamp' => $current_time
        ];
    } else {
        $time_diff = $current_time - $data[$user_key]['timestamp'];
        
        if ($time_diff > 60) { // Reset after 1 minute
            $data[$user_key] = [
                'count' => 1,
                'timestamp' => $current_time
            ];
        } else {
            $data[$user_key]['count']++;
            
            if ($data[$user_key]['count'] > 30) { // 30 requests per minute
                return false;
            }
        }
    }
    
    file_put_contents($RATE_LIMIT_FILE, json_encode($data));
    return true;
}

/**
 * Log action for replay
 */
function logAction($action, $user_id = null, $details = '') {
    global $REPLAY_LOG;
    
    $log_entry = date('Y-m-d H:i:s') . " | ";
    $log_entry .= $user_id ? "User: $user_id | " : "";
    $log_entry .= "Action: $action";
    $log_entry .= $details ? " | Details: $details" : "";
    $log_entry .= "\n";
    
    file_put_contents($REPLAY_LOG, $log_entry, FILE_APPEND);
}

/**
 * Increment analytics counter
 */
function incrementAnalytics($key) {
    global $ANALYTICS_FILE;
    
    $analytics = file_exists($ANALYTICS_FILE) ? 
        json_decode(file_get_contents($ANALYTICS_FILE), true) : [];
    
    $today = date('Y-m-d');
    
    // Initialize daily stats
    if (!isset($analytics['daily'][$today])) {
        $analytics['daily'][$today] = [
            'posts' => 0,
            'edits' => 0,
            'scheduled' => 0,
            'errors' => 0
        ];
    }
    
    // Increment total
    if (!isset($analytics[$key])) {
        $analytics[$key] = 0;
    }
    $analytics[$key]++;
    
    // Increment daily
    if (in_array($key, ['posts', 'edits', 'scheduled', 'errors'])) {
        $analytics['daily'][$today][$key]++;
    }
    
    file_put_contents($ANALYTICS_FILE, json_encode($analytics, JSON_PRETTY_PRINT));
}

/**
 * Show main admin panel
 */
function showAdminPanel($chat_id, $text = null) {
    $default_text = "🔥 <b>Tadka Movie Bot Admin Panel</b>\n\n";
    $default_text .= "Here you can create rich posts, view stats and accomplish other tasks.\n\n";
    $default_text .= "📊 <b>Quick Stats:</b>\n";
    
    // Get analytics
    global $ANALYTICS_FILE;
    $analytics = file_exists($ANALYTICS_FILE) ? 
        json_decode(file_get_contents($ANALYTICS_FILE), true) : [];
    
    $today = date('Y-m-d');
    $today_posts = $analytics['daily'][$today]['posts'] ?? 0;
    $today_edits = $analytics['daily'][$today]['edits'] ?? 0;
    $total_posts = $analytics['posts'] ?? 0;
    $total_edits = $analytics['edits'] ?? 0;
    
    $default_text .= "• Today Posts: <b>$today_posts</b>\n";
    $default_text .= "• Today Edits: <b>$today_edits</b>\n";
    $default_text .= "• Total Posts: <b>$total_posts</b>\n";
    $default_text .= "• Total Edits: <b>$total_edits</b>\n";
    
    // Panel buttons
    $keyboard = [
        [
            ['text' => '➕ Create Post', 'callback_data' => 'create_post'],
            ['text' => '⏰ Schedule Post', 'callback_data' => 'schedule_post']
        ],
        [
            ['text' => '✏️ Edit Post', 'callback_data' => 'edit_post'],
            ['text' => '📊 Channel Stats', 'callback_data' => 'channel_stats']
        ],
        [
            ['text' => '📈 Analytics', 'callback_data' => 'analytics'],
            ['text' => '⚙️ Settings', 'callback_data' => 'settings']
        ],
        [
            ['text' => '🚨 Emergency Panic', 'callback_data' => 'emergency_panic']
        ]
    ];
    
    sendMessage($chat_id, $text ?: $default_text, [
        'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
    ]);
}

/**
 * Show channel selector
 */
function showChannelSelector($chat_id, $selected_channels = [], $message_id = null) {
    global $CHANNELS;
    
    $keyboard = [];
    foreach ($CHANNELS as $channel_id => $channel_name) {
        $is_selected = in_array($channel_id, $selected_channels);
        $emoji = $is_selected ? '✅' : '⬜';
        
        $keyboard[] = [[
            'text' => "$emoji $channel_name",
            'callback_data' => "toggle_channel_$channel_id"
        ]];
    }
    
    // Add continue button
    $keyboard[] = [[
        'text' => '🚀 Continue to Next Step',
        'callback_data' => 'channels_selected_done'
    ]];
    
    $keyboard[] = [[
        'text' => '📋 Select All',
        'callback_data' => 'select_all_channels'
    ], [
        'text' => '🗑️ Clear All',
        'callback_data' => 'clear_all_channels'
    ]];
    
    $message = "📢 <b>Select Target Channels</b>\n\n";
    $message .= "Choose where you want to post this content:\n";
    $message .= "✅ = Selected\n";
    $message .= "⬜ = Not Selected\n\n";
    $message .= "Selected: " . count($selected_channels) . " of " . count($CHANNELS) . " channels";
    
    if ($message_id) {
        // Edit existing message
        editMessageText($chat_id, $message_id, $message, [
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    } else {
        // Send new message
        sendMessage($chat_id, $message, [
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    }
}

/**
 * Save user state
 */
function saveUserState($user_id, $state, $data = null) {
    $state_file = __DIR__ . "/data/state_$user_id.json";
    $state_data = ['state' => $state];
    
    if ($data) {
        $state_data['data'] = $data;
    }
    
    file_put_contents($state_file, json_encode($state_data));
}

/**
 * Get user state
 */
function getUserState($user_id) {
    $state_file = __DIR__ . "/data/state_$user_id.json";
    
    if (file_exists($state_file)) {
        return json_decode(file_get_contents($state_file), true);
    }
    
    return null;
}

/**
 * Clear user state
 */
function clearUserState($user_id) {
    $state_file = __DIR__ . "/data/state_$user_id.json";
    if (file_exists($state_file)) {
        unlink($state_file);
    }
}

/**
 * Process buttons from text
 */
function parseButtons($button_text) {
    if (empty($button_text) || strtolower($button_text) == 'skip') {
        return null;
    }
    
    $buttons = [];
    $lines = explode("\n", trim($button_text));
    
    foreach ($lines as $line) {
        $parts = explode('|', trim($line), 2);
        if (count($parts) == 2) {
            $buttons[] = [[
                'text' => trim($parts[0]),
                'url' => trim($parts[1])
            ]];
        }
    }
    
    if (empty($buttons)) {
        return null;
    }
    
    return ['inline_keyboard' => $buttons];
}

/**
 * Check if content is duplicate
 */
function isDuplicateContent($content_hash) {
    global $DATA_DIR;
    $duplicate_file = $DATA_DIR . '/content_hash.json';
    
    if (!file_exists($duplicate_file)) {
        $hashes = [];
    } else {
        $hashes = json_decode(file_get_contents($duplicate_file), true);
    }
    
    if (isset($hashes[$content_hash])) {
        $age = time() - $hashes[$content_hash];
        if ($age < 86400) { // 24 hours
            return true;
        }
    }
    
    $hashes[$content_hash] = time();
    file_put_contents($duplicate_file, json_encode($hashes));
    return false;
}

/**
 * Format time for display
 */
function formatTime12Hour($timestamp) {
    return date('M d, Y h:i A', $timestamp);
}

/**
 * Parse 12-hour time
 */
function parse12HourTime($time_string) {
    // Try multiple formats
    $formats = [
        'M d, Y h:i A',
        'F d, Y h:i A',
        'd M, Y h:i A',
        'Y-m-d h:i A',
        'h:i A'
    ];
    
    foreach ($formats as $format) {
        $timestamp = strtotime($time_string);
        if ($timestamp !== false) {
            return $timestamp;
        }
    }
    
    return false;
}

/**
 * Get channel member count
 */
function getChannelMemberCount($channel_id) {
    $result = api('getChatMemberCount', ['chat_id' => $channel_id]);
    return $result['ok'] ? $result['result'] : 'N/A';
}

// ==================== PANIC MODE CHECK ====================
if (file_exists($PANIC_FILE)) {
    $update = json_decode(file_get_contents('php://input'), true);
    
    if ($update && isset($update['message']['text'])) {
        $text = $update['message']['text'];
        $chat_id = $update['message']['chat']['id'];
        $user_id = $update['message']['from']['id'];
        
        if ($text == '/panicoff' && isOwner($user_id)) {
            unlink($PANIC_FILE);
            sendMessage($chat_id, "✅ <b>Panic Mode Disabled</b>\nBot is now operational.");
            logAction('PANIC_DISABLED', $user_id);
        } else {
            sendMessage($chat_id, "🚨 <b>PANIC MODE ACTIVE</b>\nAll bot functions are disabled.\nOnly owner can use /panicoff");
        }
    }
    
    exit('OK');
}

// ==================== MAIN WEBHOOK HANDLER ====================
$update = json_decode(file_get_contents('php://input'), true);

if (!$update) {
    // Health check for Render.com
    if ($_SERVER['REQUEST_METHOD'] == 'GET') {
        echo "Tadka Movie Bot is running!";
        echo "\nOwner: Mahatab Ansari";
        echo "\nBot: @TadkaMovieBot";
        echo "\nTime: " . date('Y-m-d H:i:s');
        echo "\nStatus: ✅ Operational";
    }
    exit;
}

// ==================== CALLBACK QUERY HANDLER ====================
if (isset($update['callback_query'])) {
    $callback = $update['callback_query'];
    $user_id = $callback['from']['id'];
    $chat_id = $callback['message']['chat']['id'];
    $message_id = $callback['message']['message_id'];
    $data = $callback['data'];
    
    // Check if owner
    if (!isOwner($user_id)) {
        api('answerCallbackQuery', [
            'callback_query_id' => $callback['id'],
            'text' => '❌ Access denied. Only owner can use this bot.'
        ]);
        exit;
    }
    
    // Check rate limit
    if (!checkRateLimit($user_id)) {
        api('answerCallbackQuery', [
            'callback_query_id' => $callback['id'],
            'text' => '⚠️ Too many requests. Please wait.'
        ]);
        exit;
    }
    
    sendTypingAction($chat_id);
    
    // Handle different callback actions
    switch (true) {
        case $data == 'create_post':
            sendMessage($chat_id, "🎬 <b>Create New Post</b>\n\nForward a video from any of your channels to begin.");
            saveUserState($user_id, 'awaiting_video');
            logAction('CREATE_POST_INITIATED', $user_id);
            break;
            
        case $data == 'schedule_post':
            sendMessage($chat_id, "⏰ <b>Schedule Post</b>\n\nForward a video, then I'll ask for scheduling time.\n\n<b>Time Format:</b> <code>Jan 20, 2026 06:30 PM</code>");
            saveUserState($user_id, 'awaiting_video_schedule');
            logAction('SCHEDULE_POST_INITIATED', $user_id);
            break;
            
        case $data == 'edit_post':
            sendMessage($chat_id, "✏️ <b>Edit Existing Post</b>\n\nForward the original video post that you want to edit.");
            saveUserState($user_id, 'awaiting_video_edit');
            logAction('EDIT_POST_INITIATED', $user_id);
            break;
            
        case $data == 'channel_stats':
            $stats_message = "📊 <b>Channel Statistics</b>\n\n";
            foreach ($CHANNELS as $channel_id => $channel_name) {
                $count = getChannelMemberCount($channel_id);
                $stats_message .= "• <b>$channel_name</b>: $count members\n";
            }
            sendMessage($chat_id, $stats_message);
            logAction('CHANNEL_STATS_VIEWED', $user_id);
            break;
            
        case $data == 'analytics':
            $analytics = file_exists($ANALYTICS_FILE) ? 
                json_decode(file_get_contents($ANALYTICS_FILE), true) : [];
            
            $today = date('Y-m-d');
            $today_posts = $analytics['daily'][$today]['posts'] ?? 0;
            $today_edits = $analytics['daily'][$today]['edits'] ?? 0;
            $total_posts = $analytics['posts'] ?? 0;
            $total_edits = $analytics['edits'] ?? 0;
            
            $analytics_message = "📈 <b>Bot Analytics</b>\n\n";
            $analytics_message .= "📅 <b>Today (" . date('M d, Y') . ")</b>\n";
            $analytics_message .= "• Posts Created: <b>$today_posts</b>\n";
            $analytics_message .= "• Posts Edited: <b>$today_edits</b>\n\n";
            
            $analytics_message .= "📊 <b>All Time</b>\n";
            $analytics_message .= "• Total Posts: <b>$total_posts</b>\n";
            $analytics_message .= "• Total Edits: <b>$total_edits</b>\n";
            
            // Weekly stats
            $weekly_posts = 0;
            $weekly_edits = 0;
            for ($i = 0; $i < 7; $i++) {
                $date = date('Y-m-d', strtotime("-$i days"));
                if (isset($analytics['daily'][$date])) {
                    $weekly_posts += $analytics['daily'][$date]['posts'];
                    $weekly_edits += $analytics['daily'][$date]['edits'];
                }
            }
            
            $analytics_message .= "\n📅 <b>Last 7 Days</b>\n";
            $analytics_message .= "• Posts: <b>$weekly_posts</b>\n";
            $analytics_message .= "• Edits: <b>$weekly_edits</b>";
            
            sendMessage($chat_id, $analytics_message);
            logAction('ANALYTICS_VIEWED', $user_id);
            break;
            
        case $data == 'settings':
            $settings = json_decode(file_get_contents($SETTINGS_FILE), true);
            
            $settings_message = "⚙️ <b>Bot Settings</b>\n\n";
            $settings_message .= "1. Typing Delay: <b>" . $settings['typing_delay'] . "s</b>\n";
            $settings_message .= "2. Auto Sync: <b>" . ($settings['auto_sync'] ? '✅ ON' : '❌ OFF') . "</b>\n";
            $settings_message .= "3. Auto Delete Replies: <b>" . ($settings['auto_delete_admin_reply'] ? '✅ ON' : '❌ OFF') . "</b>\n";
            $settings_message .= "4. Timezone: <b>" . $settings['timezone'] . "</b>\n";
            $settings_message .= "5. Max Posts/Day: <b>" . $settings['max_posts_per_day'] . "</b>\n\n";
            
            $settings_message .= "<b>Commands to Change:</b>\n";
            $settings_message .= "• <code>/setdelay 3</code> - Change typing delay\n";
            $settings_message .= "• <code>/setautosync on</code> - Enable auto sync\n";
            $settings_message .= "• <code>/settimezone Asia/Kolkata</code>\n";
            $settings_message .= "• <code>/setmaxposts 100</code>";
            
            sendMessage($chat_id, $settings_message);
            logAction('SETTINGS_VIEWED', $user_id);
            break;
            
        case $data == 'emergency_panic':
            file_put_contents($PANIC_FILE, '1');
            sendMessage($chat_id, "🚨 <b>EMERGENCY PANIC MODE ACTIVATED</b>\n\nAll bot functions are now frozen.\n\nUse <code>/panicoff</code> to disable.");
            logAction('PANIC_MODE_ACTIVATED', $user_id);
            break;
            
        case strpos($data, 'toggle_channel_') === 0:
            $channel_id = (int)str_replace('toggle_channel_', '', $data);
            $state = getUserState($user_id);
            
            if (!$state || !isset($state['data']['selected_channels'])) {
                $selected_channels = [];
            } else {
                $selected_channels = $state['data']['selected_channels'];
            }
            
            $key = array_search($channel_id, $selected_channels);
            if ($key !== false) {
                unset($selected_channels[$key]);
            } else {
                $selected_channels[] = $channel_id;
            }
            
            // Save updated selection
            if ($state) {
                $state['data']['selected_channels'] = array_values($selected_channels);
                saveUserState($user_id, $state['state'], $state['data']);
            }
            
            // Update the channel selector
            showChannelSelector($chat_id, $selected_channels, $message_id);
            break;
            
        case $data == 'select_all_channels':
            $state = getUserState($user_id);
            $selected_channels = array_keys($CHANNELS);
            
            if ($state) {
                $state['data']['selected_channels'] = $selected_channels;
                saveUserState($user_id, $state['state'], $state['data']);
            }
            
            showChannelSelector($chat_id, $selected_channels, $message_id);
            break;
            
        case $data == 'clear_all_channels':
            $state = getUserState($user_id);
            
            if ($state) {
                $state['data']['selected_channels'] = [];
                saveUserState($user_id, $state['state'], $state['data']);
            }
            
            showChannelSelector($chat_id, [], $message_id);
            break;
            
        case $data == 'channels_selected_done':
            $state = getUserState($user_id);
            
            if (!$state || !isset($state['data']['selected_channels'])) {
                sendMessage($chat_id, "❌ No channels selected. Please select at least one channel.");
                break;
            }
            
            $selected_channels = $state['data']['selected_channels'];
            
            if (empty($selected_channels)) {
                sendMessage($chat_id, "❌ No channels selected. Please select at least one channel.");
                break;
            }
            
            // Move to next step based on state
            switch ($state['state']) {
                case 'video_received':
                    sendMessage($chat_id, "✍️ <b>Step 3: Add Caption</b>\n\nSend the caption for this video.\n\nType <code>SAME</code> to use original caption.\nType <code>NONE</code> for no caption.");
                    saveUserState($user_id, 'awaiting_caption', $state['data']);
                    break;
                    
                case 'video_received_schedule':
                    sendMessage($chat_id, "⏰ <b>Step 3: Schedule Time</b>\n\nSend the time for scheduling.\n\n<b>Format:</b> <code>Jan 20, 2026 06:30 PM</code>\n\n<i>Current time: " . date('M d, Y h:i A') . "</i>");
                    saveUserState($user_id, 'awaiting_schedule_time', $state['data']);
                    break;
                    
                default:
                    sendMessage($chat_id, "⚠️ Something went wrong. Please start again with /start");
                    clearUserState($user_id);
            }
            
            // Delete the channel selector message
            deleteMessage($chat_id, $message_id);
            break;
    }
    
    // Always answer callback query
    api('answerCallbackQuery', ['callback_query_id' => $callback['id']]);
    exit;
}

// ==================== MESSAGE HANDLER ====================
if (isset($update['message'])) {
    $message = $update['message'];
    $chat_id = $message['chat']['id'];
    $user_id = $message['from']['id'];
    $message_id = $message['message_id'];
    $text = $message['text'] ?? '';
    
    // Check if owner
    if (!isOwner($user_id)) {
        sendMessage($chat_id, "❌ <b>Access Denied</b>\n\nThis bot is private. Only owner can use it.");
        exit;
    }
    
    // Check rate limit
    if (!checkRateLimit($user_id)) {
        sendMessage($chat_id, "⚠️ <b>Too Many Requests</b>\n\nPlease wait a minute before sending more commands.");
        exit;
    }
    
    sendTypingAction($chat_id);
    
    // Handle commands
    if (!empty($text)) {
        switch ($text) {
            case '/start':
                showAdminPanel($chat_id);
                logAction('START_COMMAND', $user_id);
                break;
                
            case '/panic':
                file_put_contents($PANIC_FILE, '1');
                sendMessage($chat_id, "🚨 <b>EMERGENCY PANIC MODE ACTIVATED</b>\n\nAll bot functions are now frozen.\n\nUse <code>/panicoff</code> to disable.");
                logAction('PANIC_COMMAND', $user_id);
                break;
                
            case strpos($text, '/setdelay ') === 0:
                $delay = (int)str_replace('/setdelay ', '', $text);
                if ($delay >= 0 && $delay <= 10) {
                    $settings = json_decode(file_get_contents($SETTINGS_FILE), true);
                    $settings['typing_delay'] = $delay;
                    file_put_contents($SETTINGS_FILE, json_encode($settings, JSON_PRETTY_PRINT));
                    sendMessage($chat_id, "✅ Typing delay set to <b>$delay seconds</b>");
                    logAction('SET_DELAY', $user_id, "Delay: {$delay}s");
                } else {
                    sendMessage($chat_id, "❌ Invalid delay. Use 0-10 seconds.");
                }
                break;
                
            case strpos($text, '/setautosync ') === 0:
                $value = str_replace('/setautosync ', '', $text);
                $enabled = in_array(strtolower($value), ['on', 'yes', 'true', '1']);
                
                $settings = json_decode(file_get_contents($SETTINGS_FILE), true);
                $settings['auto_sync'] = $enabled;
                file_put_contents($SETTINGS_FILE, json_encode($settings, JSON_PRETTY_PRINT));
                
                sendMessage($chat_id, "✅ Auto sync " . ($enabled ? "enabled" : "disabled"));
                logAction('SET_AUTO_SYNC', $user_id, "Value: $enabled");
                break;
                
            case strpos($text, '/settimezone ') === 0:
                $timezone = str_replace('/settimezone ', '', $text);
                
                if (in_array($timezone, timezone_identifiers_list())) {
                    $settings = json_decode(file_get_contents($SETTINGS_FILE), true);
                    $settings['timezone'] = $timezone;
                    file_put_contents($SETTINGS_FILE, json_encode($settings, JSON_PRETTY_PRINT));
                    
                    date_default_timezone_set($timezone);
                    sendMessage($chat_id, "✅ Timezone set to <b>$timezone</b>\nCurrent time: " . date('Y-m-d H:i:s'));
                    logAction('SET_TIMEZONE', $user_id, "Zone: $timezone");
                } else {
                    sendMessage($chat_id, "❌ Invalid timezone. Use valid PHP timezone like 'Asia/Kolkata'");
                }
                break;
                
            case strpos($text, '/setmaxposts ') === 0:
                $max = (int)str_replace('/setmaxposts ', '', $text);
                if ($max > 0 && $max <= 1000) {
                    $settings = json_decode(file_get_contents($SETTINGS_FILE), true);
                    $settings['max_posts_per_day'] = $max;
                    file_put_contents($SETTINGS_FILE, json_encode($settings, JSON_PRETTY_PRINT));
                    sendMessage($chat_id, "✅ Maximum posts per day set to <b>$max</b>");
                    logAction('SET_MAX_POSTS', $user_id, "Max: $max");
                } else {
                    sendMessage($chat_id, "❌ Invalid value. Use 1-1000.");
                }
                break;
                
            case '/help':
                $help_text = "🆘 <b>Tadka Movie Bot Help</b>\n\n";
                $help_text .= "<b>Main Commands:</b>\n";
                $help_text .= "• /start - Show admin panel\n";
                $help_text .= "• /panic - Emergency stop bot\n";
                $help_text .= "• /help - This message\n\n";
                
                $help_text .= "<b>Settings Commands:</b>\n";
                $help_text .= "• /setdelay 3 - Typing delay in seconds\n";
                $help_text .= "• /setautosync on - Auto edit sync\n";
                $help_text .= "• /settimezone Asia/Kolkata\n";
                $help_text .= "• /setmaxposts 50\n\n";
                
                $help_text .= "<b>Workflow:</b>\n";
                $help_text .= "1. Click 'Create Post'\n";
                $help_text .= "2. Forward video from channel\n";
                $help_text .= "3. Select target channels\n";
                $help_text .= "4. Add caption & buttons\n";
                $help_text .= "5. Done!";
                
                sendMessage($chat_id, $help_text);
                logAction('HELP_COMMAND', $user_id);
                break;
                
            default:
                // Check user state for workflow
                $state = getUserState($user_id);
                
                if ($state) {
                    switch ($state['state']) {
                        case 'awaiting_caption':
                            $video_data = $state['data'];
                            $caption = $text;
                            
                            if (strtoupper($caption) == 'SAME') {
                                $caption = $video_data['original_caption'] ?? '';
                            } elseif (strtoupper($caption) == 'NONE') {
                                $caption = '';
                            }
                            
                            // Save caption
                            $video_data['caption'] = $caption;
                            saveUserState($user_id, 'awaiting_buttons', $video_data);
                            
                            // Get button template
                            $settings = json_decode(file_get_contents($SETTINGS_FILE), true);
                            $template = $settings['button_template'] ?? "⬇️ Download|https://t.me/TadkaMovieBot";
                            
                            sendMessage($chat_id, "🔘 <b>Step 4: Add Buttons</b>\n\nSend inline buttons (one per line):\n<code>Text|URL</code>\n\n<b>Example:</b>\n<code>$template</code>\n\nType <code>skip</code> for no buttons.");
                            break;
                            
                        case 'awaiting_buttons':
                            $video_data = $state['data'];
                            $buttons = parseButtons($text);
                            
                            // Post to selected channels
                            $selected_channels = $video_data['selected_channels'] ?? [];
                            $posted_messages = [];
                            
                            foreach ($selected_channels as $channel_id) {
                                $post_result = api('sendVideo', array_filter([
                                    'chat_id' => $channel_id,
                                    'video' => $video_data['file_id'],
                                    'caption' => $video_data['caption'] ?? '',
                                    'parse_mode' => 'HTML',
                                    'reply_markup' => $buttons ? json_encode($buttons) : null
                                ]));
                                
                                if ($post_result['ok']) {
                                    $posted_messages[] = [
                                        'chat_id' => $channel_id,
                                        'message_id' => $post_result['result']['message_id']
                                    ];
                                }
                            }
                            
                            // Save sync mapping
                            $sync_data = file_exists($SYNC_FILE) ? 
                                json_decode(file_get_contents($SYNC_FILE), true) : [];
                            
                            $sync_entry = [
                                'source_chat' => $video_data['source_chat'],
                                'source_msg' => $video_data['source_message_id'],
                                'source_file_id' => $video_data['file_id'],
                                'targets' => $posted_messages,
                                'caption' => $video_data['caption'] ?? '',
                                'buttons' => $buttons,
                                'created_at' => time()
                            ];
                            
                            $sync_data[] = $sync_entry;
                            file_put_contents($SYNC_FILE, json_encode($sync_data, JSON_PRETTY_PRINT));
                            
                            // Increment analytics
                            incrementAnalytics('posts');
                            
                            // Send success message
                            $success_msg = "✅ <b>Post Created Successfully!</b>\n\n";
                            $success_msg .= "• Posted to: <b>" . count($posted_messages) . " channels</b>\n";
                            $success_msg .= "• Caption: " . (empty($video_data['caption']) ? 'None' : 'Added') . "\n";
                            $success_msg .= "• Buttons: " . ($buttons ? 'Added' : 'None') . "\n\n";
                            $success_msg .= "<i>You can now edit this post by forwarding the original video and using 'Edit Post'</i>";
                            
                            sendMessage($chat_id, $success_msg);
                            logAction('POST_CREATED', $user_id, "Channels: " . count($posted_messages));
                            
                            // Clear state
                            clearUserState($user_id);
                            
                            // Auto-delete admin reply if enabled
                            $settings = json_decode(file_get_contents($SETTINGS_FILE), true);
                            if ($settings['auto_delete_admin_reply'] ?? true) {
                                sleep(5);
                                deleteMessage($chat_id, $message_id);
                            }
                            break;
                            
                        case 'awaiting_schedule_time':
                            $video_data = $state['data'];
                            $schedule_time = parse12HourTime($text);
                            
                            if (!$schedule_time) {
                                sendMessage($chat_id, "❌ <b>Invalid Time Format</b>\n\nUse format: <code>Jan 20, 2026 06:30 PM</code>\n\nExamples:\n• Tomorrow 08:00 PM\n• Jan 25, 2026 10:30 AM\n• Next Monday 09:00 PM");
                                break;
                            }
                            
                            if ($schedule_time <= time()) {
                                sendMessage($chat_id, "❌ <b>Time must be in the future</b>\n\nCurrent time: " . date('M d, Y h:i A'));
                                break;
                            }
                            
                            // Ask for caption
                            saveUserState($user_id, 'awaiting_schedule_caption', array_merge(
                                $video_data,
                                ['schedule_time' => $schedule_time]
                            ));
                            
                            sendMessage($chat_id, "✍️ <b>Step 4: Add Caption</b>\n\nSend caption for scheduled post.\n\nScheduled for: <b>" . formatTime12Hour($schedule_time) . "</b>\n\nType <code>SAME</code> for original caption\nType <code>NONE</code> for no caption");
                            break;
                            
                        case 'awaiting_schedule_caption':
                            $video_data = $state['data'];
                            $caption = $text;
                            
                            if (strtoupper($caption) == 'SAME') {
                                $caption = $video_data['original_caption'] ?? '';
                            } elseif (strtoupper($caption) == 'NONE') {
                                $caption = '';
                            }
                            
                            // Ask for buttons
                            saveUserState($user_id, 'awaiting_schedule_buttons', array_merge(
                                $video_data,
                                ['caption' => $caption]
                            ));
                            
                            $settings = json_decode(file_get_contents($SETTINGS_FILE), true);
                            $template = $settings['button_template'] ?? "⬇️ Download|https://t.me/TadkaMovieBot";
                            
                            sendMessage($chat_id, "🔘 <b>Step 5: Add Buttons</b>\n\nSend inline buttons for scheduled post:\n<code>Text|URL</code>\n\n<b>Example:</b>\n<code>$template</code>\n\nType <code>skip</code> for no buttons.");
                            break;
                            
                        case 'awaiting_schedule_buttons':
                            $video_data = $state['data'];
                            $buttons = parseButtons($text);
                            
                            // Save scheduled post
                            $scheduled_post = [
                                'file_id' => $video_data['file_id'],
                                'caption' => $video_data['caption'] ?? '',
                                'buttons' => $buttons,
                                'channels' => $video_data['selected_channels'] ?? [],
                                'schedule_time' => $video_data['schedule_time'],
                                'created_at' => time(),
                                'user_id' => $user_id
                            ];
                            
                            $posts = file_exists($POSTS_FILE) ? 
                                json_decode(file_get_contents($POSTS_FILE), true) : [];
                            
                            $posts[] = $scheduled_post;
                            file_put_contents($POSTS_FILE, json_encode($posts, JSON_PRETTY_PRINT));
                            
                            // Increment analytics
                            incrementAnalytics('scheduled');
                            
                            $success_msg = "⏰ <b>Post Scheduled Successfully!</b>\n\n";
                            $success_msg .= "• Scheduled for: <b>" . formatTime12Hour($video_data['schedule_time']) . "</b>\n";
                            $success_msg .= "• Channels: <b>" . count($video_data['selected_channels']) . "</b>\n";
                            $success_msg .= "• Caption: " . (empty($video_data['caption']) ? 'None' : 'Added') . "\n";
                            $success_msg .= "• Buttons: " . ($buttons ? 'Added' : 'None') . "\n\n";
                            $success_msg .= "<i>Post will be automatically published at scheduled time.</i>";
                            
                            sendMessage($chat_id, $success_msg);
                            logAction('POST_SCHEDULED', $user_id, "Time: " . formatTime12Hour($video_data['schedule_time']));
                            
                            clearUserState($user_id);
                            break;
                            
                        case 'awaiting_edit_caption':
                            $edit_data = $state['data'];
                            $new_caption = $text;
                            
                            // Update caption in all linked channels
                            $sync_data = file_exists($SYNC_FILE) ? 
                                json_decode(file_get_contents($SYNC_FILE), true) : [];
                            
                            $updated = false;
                            foreach ($sync_data as &$entry) {
                                if ($entry['source_chat'] == $edit_data['source_chat'] && 
                                    $entry['source_msg'] == $edit_data['source_msg']) {
                                    
                                    // Update caption in entry
                                    $entry['caption'] = $new_caption;
                                    $entry['edited_at'] = time();
                                    $entry['edited_by'] = $user_id;
                                    
                                    // Update in all target channels
                                    foreach ($entry['targets'] as $target) {
                                        if (isset($target['chat_id']) && isset($target['message_id'])) {
                                            editMessageCaption(
                                                $target['chat_id'],
                                                $target['message_id'],
                                                $new_caption,
                                                $entry['buttons'] ? ['reply_markup' => json_encode($entry['buttons'])] : []
                                            );
                                        }
                                    }
                                    
                                    $updated = true;
                                    break;
                                }
                            }
                            
                            if ($updated) {
                                file_put_contents($SYNC_FILE, json_encode($sync_data, JSON_PRETTY_PRINT));
                                incrementAnalytics('edits');
                                
                                sendMessage($chat_id, "✅ <b>Caption Updated Successfully!</b>\n\nUpdated in all linked channels.");
                                logAction('POST_EDITED', $user_id, "Caption updated");
                            } else {
                                sendMessage($chat_id, "❌ <b>Could not update caption</b>\n\nPost not found in sync map.");
                            }
                            
                            clearUserState($user_id);
                            break;
                            
                        default:
                            showAdminPanel($chat_id);
                    }
                } else {
                    showAdminPanel($chat_id);
                }
        }
    } 
    // Handle forwarded video
    elseif (isset($message['video']) || isset($message['forward_from_chat'])) {
        $state = getUserState($user_id);
        
        if (!$state) {
            sendMessage($chat_id, "⚠️ Please start by clicking 'Create Post' or 'Edit Post' from the admin panel.");
            showAdminPanel($chat_id);
            exit;
        }
        
        // Get video info
        $video = $message['video'] ?? null;
        $forwarded_from = $message['forward_from_chat'] ?? null;
        $forwarded_message_id = $message['forward_from_message_id'] ?? null;
        
        if (!$video || !$forwarded_from) {
            sendMessage($chat_id, "❌ Please forward a video from your channel, not just send it directly.");
            exit;
        }
        
        // Check if source is one of our channels
        $source_chat_id = $forwarded_from['id'];
        $is_our_channel = isset($CHANNELS[$source_chat_id]) || $source_chat_id == $GROUP_ID;
        
        if (!$is_our_channel) {
            sendMessage($chat_id, "❌ Please forward video from one of your channels, not external channels.");
            exit;
        }
        
        // Prepare video data
        $video_data = [
            'file_id' => $video['file_id'],
            'source_chat' => $source_chat_id,
            'source_message_id' => $forwarded_message_id,
            'original_caption' => $message['caption'] ?? '',
            'file_size' => $video['file_size'] ?? 0,
            'duration' => $video['duration'] ?? 0,
            'width' => $video['width'] ?? 0,
            'height' => $video['height'] ?? 0
        ];
        
        // Handle based on state
        switch ($state['state']) {
            case 'awaiting_video':
                // Save video data and show channel selector
                $video_data['selected_channels'] = [];
                saveUserState($user_id, 'video_received', $video_data);
                showChannelSelector($chat_id);
                break;
                
            case 'awaiting_video_schedule':
                // Save video data and show channel selector
                $video_data['selected_channels'] = [];
                saveUserState($user_id, 'video_received_schedule', $video_data);
                showChannelSelector($chat_id);
                break;
                
            case 'awaiting_video_edit':
                // Check if video exists in sync map
                $sync_data = file_exists($SYNC_FILE) ? 
                    json_decode(file_get_contents($SYNC_FILE), true) : [];
                
                $found = false;
                foreach ($sync_data as $entry) {
                    if ($entry['source_chat'] == $source_chat_id && 
                        $entry['source_msg'] == $forwarded_message_id) {
                        $found = true;
                        
                        // Ask for new caption
                        saveUserState($user_id, 'awaiting_edit_caption', [
                            'source_chat' => $source_chat_id,
                            'source_msg' => $forwarded_message_id,
                            'current_caption' => $entry['caption'] ?? ''
                        ]);
                        
                        $current_caption = $entry['caption'] ?? 'No caption';
                        if (strlen($current_caption) > 100) {
                            $current_caption = substr($current_caption, 0, 100) . '...';
                        }
                        
                        sendMessage($chat_id, "✏️ <b>Edit Caption</b>\n\nCurrent caption:\n<code>" . htmlspecialchars($current_caption) . "</code>\n\nSend new caption:");
                        break;
                    }
                }
                
                if (!$found) {
                    sendMessage($chat_id, "❌ This video is not in the sync map. Please use 'Create Post' first.");
                    clearUserState($user_id);
                }
                break;
                
            default:
                sendMessage($chat_id, "⚠️ Unexpected state. Please start again with /start");
                clearUserState($user_id);
        }
    }
}

// ==================== SCHEDULED POSTS PROCESSOR ====================
// This runs on every webhook hit (Render.com compatible)
$posts = file_exists($POSTS_FILE) ? 
    json_decode(file_get_contents($POSTS_FILE), true) : [];

$current_time = time();
$remaining_posts = [];

foreach ($posts as $post) {
    if (isset($post['schedule_time']) && $post['schedule_time'] <= $current_time) {
        // Time to post!
        $buttons = $post['buttons'] ?? null;
        
        foreach ($post['channels'] as $channel_id) {
            $post_result = api('sendVideo', array_filter([
                'chat_id' => $channel_id,
                'video' => $post['file_id'],
                'caption' => $post['caption'] ?? '',
                'parse_mode' => 'HTML',
                'reply_markup' => $buttons ? json_encode($buttons) : null
            ]));
            
            if ($post_result['ok']) {
                // Save to sync map
                $sync_data = file_exists($SYNC_FILE) ? 
                    json_decode(file_get_contents($SYNC_FILE), true) : [];
                
                // Check if entry exists
                $entry_exists = false;
                foreach ($sync_data as &$entry) {
                    if ($entry['source_file_id'] == $post['file_id']) {
                        $entry['targets'][] = [
                            'chat_id' => $channel_id,
                            'message_id' => $post_result['result']['message_id']
                        ];
                        $entry_exists = true;
                        break;
                    }
                }
                
                if (!$entry_exists) {
                    $sync_data[] = [
                        'source_chat' => null, // Scheduled posts have no source
                        'source_msg' => null,
                        'source_file_id' => $post['file_id'],
                        'targets' => [[
                            'chat_id' => $channel_id,
                            'message_id' => $post_result['result']['message_id']
                        ]],
                        'caption' => $post['caption'] ?? '',
                        'buttons' => $buttons,
                        'created_at' => time(),
                        'scheduled' => true
                    ];
                }
                
                file_put_contents($SYNC_FILE, json_encode($sync_data, JSON_PRETTY_PRINT));
            }
        }
        
        // Increment analytics
        incrementAnalytics('posts');
        
        // Log scheduled post
        logAction('SCHEDULED_POST_EXECUTED', $post['user_id'] ?? null, 
                 "Channels: " . count($post['channels']));
        
        // Don't keep this post anymore
        continue;
    }
    
    // Keep post for future
    $remaining_posts[] = $post;
}

// Update posts file
if ($posts != $remaining_posts) {
    file_put_contents($POSTS_FILE, json_encode($remaining_posts, JSON_PRETTY_PRINT));
}

// ==================== AUTO-SYNC CHECK ====================
$settings = json_decode(file_get_contents($SETTINGS_FILE), true);
if ($settings['auto_sync'] ?? false) {
    // This would typically check for edits in source posts
    // For now, it's handled in the edit workflow
}

// Always return OK to Telegram
echo 'OK';
?>
