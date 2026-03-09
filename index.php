<?php
// ==============================
// 1. SECURITY HEADERS & BASIC SETUP
// ==============================
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// ==============================
// 2. ENVIRONMENT & CONSTANTS
// ==============================
define('BOT_TOKEN', '8315381064:AAGk0FGVGmB8j5SjpBvW3rD3_kQHe_hyOWU');
define('BOT_USERNAME', '@EntertainmentTadkaBot');
define('API_ID', '21944581');
define('API_HASH', '7b1c174a5cd3466e25a976c39a791737');
define('ADMIN_ID', 1080317415);

// ==============================
// 3. CHANNEL CONFIGURATION
// ==============================
define('MAIN_CHANNEL', '@EntertainmentTadka786');
define('MAIN_CHANNEL_ID', '-1003181705395');
define('SERIAL_CHANNEL', '@Entertainment_Tadka_Serial_786');
define('SERIAL_CHANNEL_ID', '-1003614546520');
define('THEATER_CHANNEL', '@threater_print_movies');
define('THEATER_CHANNEL_ID', '-1002831605258');
define('BACKUP_CHANNEL', '@ETBackup');
define('BACKUP_CHANNEL_ID', '-1002964109368');
define('PRIVATE_CHANNEL_1_ID', '-1003251791991');
define('PRIVATE_CHANNEL_2_ID', '-1002337293281');
define('REQUEST_CHANNEL', '@EntertainmentTadka7860');
define('REQUEST_GROUP_ID', '-1003083386043');

// ==============================
// 4. FILE PATHS & CONSTANTS
// ==============================
define('CSV_FILE', 'movies.csv');
define('USERS_FILE', 'users.json');
define('STATS_FILE', 'bot_stats.json');
define('REQUEST_FILE', 'movie_requests.json');
define('BACKUP_DIR', 'backups/');
define('LOG_FILE', 'bot_activity.log');
define('SETTINGS_FILE', 'user_settings.json');
define('DELETE_QUEUE_FILE', 'delete_queue.json');
define('FILTER_SESSION_FILE', 'filter_sessions.json');

define('CACHE_EXPIRY', 300);
define('ITEMS_PER_PAGE', 5);
define('MAX_SEARCH_RESULTS', 15);
define('DAILY_REQUEST_LIMIT', 5);
define('AUTO_BACKUP_HOUR', '03');

// ==============================
// 5. GLOBAL VARIABLES
// ==============================
$movie_messages = array();
$movie_cache = array();
$waiting_users = array();
$user_sessions = array();
$user_pagination_sessions = array();
$MAINTENANCE_MODE = false;
$MAINTENANCE_MESSAGE = "🛠️ <b>Bot Under Maintenance</b>\n\nWe'll be back soon!";

// ==============================
// 6. CHANNEL MAPPING FUNCTIONS
// ==============================
function get_channel_id_by_username($username) {
    $username = strtolower(trim($username));
    $username = ltrim($username, '@');
    
    $channel_map = [
        'entertainmenttadka786' => MAIN_CHANNEL_ID,
        'entertainment_tadka_serial_786' => SERIAL_CHANNEL_ID,
        'threater_print_movies' => THEATER_CHANNEL_ID,
        'etbackup' => BACKUP_CHANNEL_ID,
        'entertainmenttadka7860' => REQUEST_GROUP_ID,
        '@entertainmenttadka786' => MAIN_CHANNEL_ID,
        '@entertainment_tadka_serial_786' => SERIAL_CHANNEL_ID,
        '@threater_print_movies' => THEATER_CHANNEL_ID,
        '@etbackup' => BACKUP_CHANNEL_ID,
        '@entertainmenttadka7860' => REQUEST_GROUP_ID,
    ];
    
    return $channel_map[$username] ?? null;
}

function get_channel_type_by_id($channel_id) {
    $channel_id = strval($channel_id);
    
    if ($channel_id == MAIN_CHANNEL_ID) return 'main';
    if ($channel_id == SERIAL_CHANNEL_ID) return 'serial';
    if ($channel_id == THEATER_CHANNEL_ID) return 'theater';
    if ($channel_id == BACKUP_CHANNEL_ID) return 'backup';
    if ($channel_id == PRIVATE_CHANNEL_1_ID) return 'private1';
    if ($channel_id == PRIVATE_CHANNEL_2_ID) return 'private2';
    if ($channel_id == REQUEST_GROUP_ID) return 'request';
    
    return 'other';
}

function get_channel_display_name($channel_type) {
    $names = [
        'main' => '🍿 Main Channel',
        'serial' => '📺 Serial Channel',
        'theater' => '🎭 Theater Prints',
        'backup' => '🔒 Backup Channel',
        'private1' => '🔐 Private Channel 1',
        'private2' => '🔐 Private Channel 2',
        'request' => '📝 Request Group',
        'other' => '📢 Other Channel'
    ];
    
    return $names[$channel_type] ?? '📢 Unknown Channel';
}

function get_channel_username_link($channel_type) {
    switch ($channel_type) {
        case 'main':
            return "https://t.me/" . ltrim(MAIN_CHANNEL, '@');
        case 'serial':
            return "https://t.me/" . ltrim(SERIAL_CHANNEL, '@');
        case 'theater':
            return "https://t.me/" . ltrim(THEATER_CHANNEL, '@');
        case 'backup':
            return "https://t.me/" . ltrim(BACKUP_CHANNEL, '@');
        default:
            return "https://t.me/EntertainmentTadka786";
    }
}

function get_direct_channel_link($message_id, $channel_id) {
    if (empty($channel_id)) {
        return "Channel ID not available";
    }
    
    $channel_id_clean = str_replace('-100', '', $channel_id);
    return "https://t.me/c/" . $channel_id_clean . "/" . $message_id;
}

// ==============================
// 7. FILE INITIALIZATION
// ==============================
function initialize_files() {
    $files = [
        CSV_FILE => "movie_name,message_id,channel_id\n",
        USERS_FILE => json_encode([
            'users' => [],
            'total_requests' => 0,
            'message_logs' => [],
            'daily_stats' => []
        ], JSON_PRETTY_PRINT),
        STATS_FILE => json_encode([
            'total_movies' => 0,
            'total_users' => 0,
            'total_searches' => 0,
            'total_downloads' => 0,
            'successful_searches' => 0,
            'failed_searches' => 0,
            'daily_activity' => [],
            'last_updated' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT),
        REQUEST_FILE => json_encode([
            'requests' => [],
            'pending_approval' => [],
            'completed_requests' => [],
            'user_request_count' => []
        ], JSON_PRETTY_PRINT)
    ];
    
    foreach ($files as $file => $content) {
        if (!file_exists($file)) {
            file_put_contents($file, $content);
            @chmod($file, 0666);
        }
    }
    
    if (!file_exists(BACKUP_DIR)) {
        @mkdir(BACKUP_DIR, 0777, true);
    }
    
    if (!file_exists(LOG_FILE)) {
        file_put_contents(LOG_FILE, "[" . date('Y-m-d H:i:s') . "] SYSTEM: Files initialized\n");
    }
}

initialize_files();

// ==============================
// 8. LOGGING SYSTEM
// ==============================
function bot_log($message, $type = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $type: $message\n";
    file_put_contents(LOG_FILE, $log_entry, FILE_APPEND);
}

// ==============================
// 9. TELEGRAM API FUNCTIONS
// ==============================
function apiRequest($method, $params = array(), $is_multipart = false) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/" . $method;
    
    if ($is_multipart) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $res = curl_exec($ch);
        if ($res === false) {
            bot_log("CURL ERROR: " . curl_error($ch), 'ERROR');
        }
        curl_close($ch);
        return $res;
    } else {
        $options = array(
            'http' => array(
                'method' => 'POST',
                'content' => http_build_query($params),
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n"
            )
        );
        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);
        if ($result === false) {
            bot_log("API Request failed for method: $method", 'ERROR');
        }
        return $result;
    }
}

function sendMessage($chat_id, $text, $reply_markup = null, $parse_mode = null) {
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'disable_web_page_preview' => true
    ];
    if ($reply_markup) $data['reply_markup'] = json_encode($reply_markup);
    if ($parse_mode) $data['parse_mode'] = $parse_mode;
    
    $result = apiRequest('sendMessage', $data);
    bot_log("Message sent to $chat_id");
    return json_decode($result, true);
}

function editMessage($chat_id, $message_id, $new_text, $reply_markup = null) {
    $data = [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $new_text,
        'disable_web_page_preview' => true
    ];
    if ($reply_markup) $data['reply_markup'] = json_encode($reply_markup);
    apiRequest('editMessageText', $data);
}

function deleteMessage($chat_id, $message_id) {
    apiRequest('deleteMessage', [
        'chat_id' => $chat_id,
        'message_id' => $message_id
    ]);
}

function answerCallbackQuery($callback_query_id, $text = null, $show_alert = false) {
    $data = [
        'callback_query_id' => $callback_query_id,
        'show_alert' => $show_alert
    ];
    if ($text) $data['text'] = $text;
    apiRequest('answerCallbackQuery', $data);
}

function forwardMessage($chat_id, $from_chat_id, $message_id) {
    $result = apiRequest('forwardMessage', [
        'chat_id' => $chat_id,
        'from_chat_id' => $from_chat_id,
        'message_id' => $message_id
    ]);
    return $result;
}

function copyMessage($chat_id, $from_chat_id, $message_id) {
    return apiRequest('copyMessage', [
        'chat_id' => $chat_id,
        'from_chat_id' => $from_chat_id,
        'message_id' => $message_id
    ]);
}

// ==============================
// 10. TYPING INDICATORS
// ==============================
function sendMessageWithDelay($chat_id, $text, $reply_markup = null, $parse_mode = null, $delay_ms = 1000) {
    $text_length = strlen($text);
    $estimated_typing_time = ceil($text_length / 50) * 1000;
    $typing_duration = min(max($estimated_typing_time, 1000), 5000);
    
    $start_time = time();
    $elapsed = 0;
    
    while ($elapsed < $typing_duration) {
        $typing_data = [
            'chat_id' => $chat_id,
            'action' => 'typing'
        ];
        
        apiRequest('sendChatAction', $typing_data);
        sleep(2);
        
        $elapsed = (time() - $start_time) * 1000;
    }
    
    usleep(500000);
    
    return sendMessage($chat_id, $text, $reply_markup, $parse_mode);
}

function editMessageWithDelay($chat_id, $message_id, $new_text, $reply_markup = null, $delay_ms = 500) {
    $typing_data = [
        'chat_id' => $chat_id,
        'action' => 'typing'
    ];
    
    apiRequest('sendChatAction', $typing_data);
    usleep($delay_ms * 1000);
    
    return editMessage($chat_id, $message_id, $new_text, $reply_markup);
}

function sendActionWithDelay($chat_id, $action = 'typing', $duration_seconds = 3) {
    $valid_actions = ['typing', 'upload_photo', 'record_video', 'upload_video', 
                      'record_audio', 'upload_audio', 'upload_document', 'find_location'];
    
    if (!in_array($action, $valid_actions)) {
        $action = 'typing';
    }
    
    $start = time();
    $end = $start + $duration_seconds;
    
    while (time() < $end) {
        $data = [
            'chat_id' => $chat_id,
            'action' => $action
        ];
        
        apiRequest('sendChatAction', $data);
        
        $remaining = $end - time();
        $sleep_time = min(4.5, $remaining);
        
        if ($sleep_time > 0) {
            sleep($sleep_time);
        }
    }
}

// ==============================
// 11. DATABASE FUNCTIONS (CSV)
// ==============================
function load_and_clean_csv($filename = CSV_FILE) {
    global $movie_messages;
    
    if (!file_exists($filename)) {
        file_put_contents($filename, "movie_name,message_id,channel_id\n");
        return [];
    }

    $data = [];
    $handle = fopen($filename, "r");
    if ($handle !== FALSE) {
        $header = fgetcsv($handle);
        
        while (($row = fgetcsv($handle)) !== FALSE) {
            if (count($row) >= 3 && !empty(trim($row[0]))) {
                $movie_name = trim($row[0]);
                $message_id = trim($row[1]);
                $channel_id = trim($row[2]);
                
                $channel_type = get_channel_type_by_id($channel_id);
                
                $quality = 'Unknown';
                if (preg_match('/(2160p|1440p|1080p|720p|576p|480p|360p|4k|uhd|fhd|hd)/i', $movie_name, $matches)) {
                    $quality = $matches[0];
                }
                
                $language = 'Hindi';
                if (stripos($movie_name, 'english') !== false) $language = 'English';
                elseif (stripos($movie_name, 'tamil') !== false) $language = 'Tamil';
                elseif (stripos($movie_name, 'telugu') !== false) $language = 'Telugu';
                elseif (stripos($movie_name, 'malayalam') !== false) $language = 'Malayalam';
                elseif (stripos($movie_name, 'kannada') !== false) $language = 'Kannada';
                
                $size = 'Unknown';
                if (preg_match('/(\d+(?:\.\d+)?)\s*(GB|MB)/i', $movie_name, $matches)) {
                    $size = $matches[1] . ' ' . $matches[2];
                }
                
                $entry = [
                    'movie_name' => $movie_name,
                    'message_id' => intval($message_id),
                    'message_id_raw' => $message_id,
                    'channel_id' => $channel_id,
                    'channel_type' => $channel_type,
                    'quality' => $quality,
                    'language' => $language,
                    'size' => $size,
                    'date' => date('d-m-Y', filemtime($filename) ?: time()),
                    'source_channel' => $channel_id
                ];
                
                $data[] = $entry;
                
                $movie_key = strtolower($movie_name);
                if (!isset($movie_messages[$movie_key])) {
                    $movie_messages[$movie_key] = [];
                }
                $movie_messages[$movie_key][] = $entry;
            }
        }
        fclose($handle);
    }

    $stats = get_stats();
    $stats['total_movies'] = count($data);
    $stats['last_updated'] = date('Y-m-d H:i:s');
    file_put_contents(STATS_FILE, json_encode($stats, JSON_PRETTY_PRINT));

    bot_log("CSV loaded - " . count($data) . " movies");
    return $data;
}

function saveMovies($movies) {
    $handle = fopen(CSV_FILE, 'w');
    fputcsv($handle, ['movie_name', 'message_id', 'channel_id']);
    
    foreach ($movies as $movie) {
        fputcsv($handle, [
            $movie['title'] ?? $movie['movie_name'],
            $movie['message_id'],
            $movie['channel_id']
        ]);
    }
    fclose($handle);
    
    global $movie_cache;
    $movie_cache = [];
    
    bot_log("Movies saved: " . count($movies) . " entries");
}

function loadMovies() {
    return load_and_clean_csv();
}

function get_all_movies_list() {
    return get_cached_movies();
}

function get_cached_movies() {
    global $movie_cache;
    
    if (!empty($movie_cache) && (time() - $movie_cache['timestamp']) < CACHE_EXPIRY) {
        return $movie_cache['data'];
    }
    
    $movie_cache = [
        'data' => load_and_clean_csv(),
        'timestamp' => time()
    ];
    
    return $movie_cache['data'];
}

// ==============================
// 12. STATISTICS SYSTEM
// ==============================
function update_stats($field, $increment = 1) {
    if (!file_exists(STATS_FILE)) return;
    
    $stats = json_decode(file_get_contents(STATS_FILE), true);
    $stats[$field] = ($stats[$field] ?? 0) + $increment;
    $stats['last_updated'] = date('Y-m-d H:i:s');
    
    $today = date('Y-m-d');
    if (!isset($stats['daily_activity'][$today])) {
        $stats['daily_activity'][$today] = [
            'searches' => 0,
            'downloads' => 0,
            'users' => 0
        ];
    }
    
    if ($field == 'total_searches') $stats['daily_activity'][$today]['searches'] += $increment;
    if ($field == 'total_downloads') $stats['daily_activity'][$today]['downloads'] += $increment;
    
    file_put_contents(STATS_FILE, json_encode($stats, JSON_PRETTY_PRINT));
}

function get_stats() {
    if (!file_exists(STATS_FILE)) return [];
    return json_decode(file_get_contents(STATS_FILE), true);
}

// ==============================
// 13. USER MANAGEMENT
// ==============================
function update_user_data($user_id, $user_info = []) {
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    
    if (!isset($users_data['users'][$user_id])) {
        $users_data['users'][$user_id] = [
            'first_name' => $user_info['first_name'] ?? '',
            'last_name' => $user_info['last_name'] ?? '',
            'username' => $user_info['username'] ?? '',
            'joined' => date('Y-m-d H:i:s'),
            'last_active' => date('Y-m-d H:i:s'),
            'points' => 0,
            'total_searches' => 0,
            'total_downloads' => 0,
            'request_count' => 0,
            'last_request_date' => null
        ];
        $users_data['total_requests'] = ($users_data['total_requests'] ?? 0) + 1;
        update_stats('total_users', 1);
        bot_log("New user registered: $user_id");
    }
    
    $users_data['users'][$user_id]['last_active'] = date('Y-m-d H:i:s');
    file_put_contents(USERS_FILE, json_encode($users_data, JSON_PRETTY_PRINT));
    
    return $users_data['users'][$user_id];
}

function update_user_activity($user_id, $action) {
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    
    if (isset($users_data['users'][$user_id])) {
        $points_map = [
            'search' => 1,
            'found_movie' => 5,
            'daily_login' => 10,
            'movie_request' => 2,
            'download' => 3
        ];
        
        $users_data['users'][$user_id]['points'] += ($points_map[$action] ?? 0);
        
        if ($action == 'search') $users_data['users'][$user_id]['total_searches']++;
        if ($action == 'download') $users_data['users'][$user_id]['total_downloads']++;
        
        $users_data['users'][$user_id]['last_active'] = date('Y-m-d H:i:s');
        file_put_contents(USERS_FILE, json_encode($users_data, JSON_PRETTY_PRINT));
    }
}

function get_users_count() {
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    return count($users_data['users'] ?? []);
}

function get_active_users_count() {
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $active_count = 0;
    $one_week_ago = strtotime('-1 week');
    
    foreach ($users_data['users'] ?? [] as $user) {
        if (strtotime($user['last_active'] ?? '') >= $one_week_ago) {
            $active_count++;
        }
    }
    
    return $active_count;
}

// ==============================
// 14. MEDIA INFO CLASS
// ==============================
class MediaInfo {
    public static function detect_quality($filename) {
        $filename = strtolower($filename);
        
        $qualities = [
            '2160p' => ['4k', '2160p', '2160', 'uhd'],
            '1440p' => ['2k', '1440p', '1440', 'qhd'],
            '1080p' => ['1080p', '1080', 'fhd', 'full hd'],
            '720p' => ['720p', '720', 'hd'],
            '576p' => ['576p', '576', 'pal'],
            '480p' => ['480p', '480', 'sd', 'dvd'],
            '360p' => ['360p', '360']
        ];
        
        foreach ($qualities as $quality => $patterns) {
            foreach ($patterns as $pattern) {
                if (strpos($filename, $pattern) !== false) {
                    return $quality;
                }
            }
        }
        
        return 'Unknown';
    }
    
    public static function detect_video_codec($filename) {
        $filename = strtolower($filename);
        
        $codecs = [
            'H.265/HEVC' => ['x265', 'h265', 'hevc', '265'],
            'H.264/AVC' => ['x264', 'h264', 'avc', '264'],
            'VP9' => ['vp9'],
            'AV1' => ['av1'],
            'MPEG-4' => ['mpeg4', 'mpeg-4', 'divx', 'xvid']
        ];
        
        foreach ($codecs as $name => $patterns) {
            foreach ($patterns as $pattern) {
                if (strpos($filename, $pattern) !== false) {
                    return $name;
                }
            }
        }
        
        return 'Unknown';
    }
    
    public static function detect_audio_info($filename) {
        $filename = strtolower($filename);
        $info = [
            'codec' => 'Unknown',
            'channels' => 'Unknown',
            'is_dual' => false
        ];
        
        $audio_codecs = [
            'AAC' => ['aac'],
            'MP3' => ['mp3'],
            'AC3' => ['ac3', 'dd5.1', 'dolby'],
            'EAC3' => ['eac3', 'dd+'],
            'DTS' => ['dts'],
            'TrueHD' => ['truehd', 'atmos'],
            'FLAC' => ['flac']
        ];
        
        foreach ($audio_codecs as $name => $patterns) {
            foreach ($patterns as $pattern) {
                if (strpos($filename, $pattern) !== false) {
                    $info['codec'] = $name;
                    break 2;
                }
            }
        }
        
        if (preg_match('/(\d)\.(\d)/', $filename, $matches)) {
            $info['channels'] = $matches[0];
        }
        
        if (strpos($filename, 'dual') !== false || strpos($filename, 'dub') !== false) {
            $info['is_dual'] = true;
        }
        
        return $info;
    }
    
    public static function detect_subtitle_info($filename) {
        $filename = strtolower($filename);
        $info = [
            'has_subs' => false,
            'languages' => []
        ];
        
        if (strpos($filename, 'sub') !== false || strpos($filename, 'subtitle') !== false) {
            $info['has_subs'] = true;
            
            $langs = ['english', 'hindi', 'tamil', 'telugu'];
            foreach ($langs as $lang) {
                if (strpos($filename, $lang) !== false) {
                    $info['languages'][] = ucfirst($lang);
                }
            }
        }
        
        return $info;
    }
    
    public static function detect_hdr($filename) {
        $filename = strtolower($filename);
        $hdr_patterns = ['hdr', 'hdr10', 'dolby vision', 'dv'];
        
        foreach ($hdr_patterns as $pattern) {
            if (strpos($filename, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    public static function get_complete_info($movie) {
        $name = $movie['movie_name'];
        
        $info = [
            'title' => $name,
            'quality' => self::detect_quality($name),
            'video_codec' => self::detect_video_codec($name),
            'audio' => self::detect_audio_info($name),
            'subtitles' => self::detect_subtitle_info($name),
            'hdr' => self::detect_hdr($name),
            'size' => $movie['size'] ?? 'Unknown',
            'language' => $movie['language'] ?? 'Hindi'
        ];
        
        return $info;
    }
    
    public static function format_info($movie) {
        $info = self::get_complete_info($movie);
        
        $text = "🎬 <b>" . htmlspecialchars($info['title']) . "</b>\n\n";
        $text .= "📊 <b>Technical Details:</b>\n";
        $text .= "• Quality: " . $info['quality'] . "\n";
        $text .= "• Video: " . $info['video_codec'] . "\n";
        $text .= "• Audio: " . $info['audio']['codec'] . "\n";
        
        if ($info['audio']['channels'] != 'Unknown') {
            $text .= "• Channels: " . $info['audio']['channels'] . "\n";
        }
        
        if ($info['audio']['is_dual']) {
            $text .= "• Dual Audio: ✅ Yes\n";
        }
        
        if ($info['hdr']) {
            $text .= "• HDR: ✅ Yes\n";
        }
        
        if ($info['subtitles']['has_subs']) {
            $text .= "• Subtitles: ✅ Available\n";
        }
        
        $text .= "• Size: " . $info['size'] . "\n";
        $text .= "• Language: " . $info['language'] . "\n";
        
        return $text;
    }
}

// ==============================
// 15. MOVIE METADATA CLASS
// ==============================
class MovieMetadata {
    public $title;
    public $message_id;
    public $channel_id;
    public $channel_type;
    public $file_size;
    public $quality;
    public $video_codec;
    public $audio_codec;
    public $audio_channels;
    public $subtitle_languages = [];
    public $is_dual_audio = false;
    public $is_hdr = false;
    public $is_episode = false;
    public $season_number = null;
    public $episode_number = null;
    public $series_name = null;
    
    function __construct($movie) {
        $this->title = $movie['movie_name'];
        $this->message_id = $movie['message_id'];
        $this->channel_id = $movie['channel_id'];
        $this->channel_type = get_channel_type_by_id($this->channel_id);
        $this->file_size = $movie['size'] ?? 'Unknown';
        $this->extract_metadata();
    }
    
    private function extract_metadata() {
        $this->quality = MediaInfo::detect_quality($this->title);
        $this->video_codec = MediaInfo::detect_video_codec($this->title);
        
        $audio_info = MediaInfo::detect_audio_info($this->title);
        $this->audio_codec = $audio_info['codec'];
        $this->audio_channels = $audio_info['channels'];
        $this->is_dual_audio = $audio_info['is_dual'];
        
        $sub_info = MediaInfo::detect_subtitle_info($this->title);
        $this->subtitle_languages = $sub_info['languages'];
        
        $this->is_hdr = MediaInfo::detect_hdr($this->title);
        
        if (preg_match('/[Ss](\d+)[Ee](\d+)/', $this->title, $matches)) {
            $this->is_episode = true;
            $this->season_number = (int)$matches[1];
            $this->episode_number = (int)$matches[2];
            $this->series_name = preg_replace('/[Ss]\d+[Ee]\d+/', '', $this->title);
            $this->series_name = trim(preg_replace('/[.\-_]/', ' ', $this->series_name));
        }
    }
    
    public function get_detailed_info() {
        $info = "🎬 <b>" . htmlspecialchars($this->title) . "</b>\n\n";
        $info .= "📊 <b>Technical Details:</b>\n";
        $info .= "• Quality: {$this->quality}\n";
        $info .= "• Size: {$this->file_size}\n";
        $info .= "• Video Codec: {$this->video_codec}\n";
        $info .= "• Audio Codec: {$this->audio_codec}\n";
        
        if ($this->audio_channels != 'Unknown') {
            $info .= "• Audio Channels: {$this->audio_channels}\n";
        }
        
        if ($this->is_dual_audio) {
            $info .= "• Dual Audio: ✅ Yes\n";
        }
        
        if ($this->is_hdr) {
            $info .= "• HDR: ✅ Yes\n";
        }
        
        if (!empty($this->subtitle_languages)) {
            $info .= "• Subtitles: " . implode(', ', $this->subtitle_languages) . "\n";
        }
        
        if ($this->is_episode) {
            $info .= "\n📺 <b>Series Info:</b>\n";
            $info .= "• Series: {$this->series_name}\n";
            $info .= "• Season: {$this->season_number}\n";
            $info .= "• Episode: {$this->episode_number}\n";
        }
        
        return $info;
    }
}

// ==============================
// 16. USER SETTINGS CLASS
// ==============================
class UserSettings {
    private $user_id;
    private $settings_file = 'user_settings.json';
    private $default_settings = [
        'language' => 'hindi',
        'default_quality' => '1080p',
        'file_type' => 'any',
        'result_layout' => 'buttons',
        'priority_mode' => 'quality',
        'spoiler_mode' => false,
        'top_search' => true,
        'auto_scan' => false,
        'notifications' => true,
        'theme' => 'dark',
        'results_per_page' => 10
    ];
    
    function __construct($user_id) {
        $this->user_id = $user_id;
        $this->init_settings();
    }
    
    private function init_settings() {
        $settings = $this->load_all_settings();
        if (!isset($settings[$this->user_id])) {
            $settings[$this->user_id] = $this->default_settings;
            $this->save_all_settings($settings);
        }
    }
    
    private function load_all_settings() {
        if (!file_exists($this->settings_file)) {
            return [];
        }
        $content = file_get_contents($this->settings_file);
        return json_decode($content, true) ?: [];
    }
    
    private function save_all_settings($settings) {
        file_put_contents($this->settings_file, json_encode($settings, JSON_PRETTY_PRINT));
    }
    
    public function get($key) {
        $settings = $this->load_all_settings();
        return $settings[$this->user_id][$key] ?? $this->default_settings[$key];
    }
    
    public function set($key, $value) {
        $settings = $this->load_all_settings();
        $settings[$this->user_id][$key] = $value;
        $this->save_all_settings($settings);
    }
    
    public function reset_to_default() {
        $settings = $this->load_all_settings();
        $settings[$this->user_id] = $this->default_settings;
        $this->save_all_settings($settings);
    }
    
    public function show_main_panel($chat_id) {
        $lang = $this->get('language');
        $quality = $this->get('default_quality');
        $layout = $this->get('result_layout');
        $priority = $this->get('priority_mode');
        $spoiler = $this->get('spoiler_mode') ? '✅ ON' : '❌ OFF';
        $topsearch = $this->get('top_search') ? '✅ ON' : '❌ OFF';
        $autoscan = $this->get('auto_scan') ? '✅ ON' : '❌ OFF';
        $notify = $this->get('notifications') ? '✅ ON' : '❌ OFF';
        $theme = $this->get('theme');
        $per_page = $this->get('results_per_page');
        
        $message = "⚙️ <b>Settings Panel</b>\n\n";
        $message .= "🆔 <b>User ID:</b> <code>$this->user_id</code>\n\n";
        $message .= "📋 <b>Current Preferences:</b>\n";
        $message .= "• 🌐 Language: " . ucfirst($lang) . "\n";
        $message .= "• 📊 Default Quality: $quality\n";
        $message .= "• 📁 Result Layout: " . ($layout == 'buttons' ? '🔘 Buttons' : '📝 Text') . "\n";
        $message .= "• 🎯 Priority Mode: " . ($priority == 'quality' ? '📊 Quality' : '💾 Size') . "\n";
        $message .= "• 📄 Results Per Page: $per_page\n";
        $message .= "• 🎨 Theme: " . ($theme == 'dark' ? '🌙 Dark' : '☀️ Light') . "\n";
        $message .= "• 🔒 Spoiler Mode: $spoiler\n";
        $message .= "• 🔍 Top Search: $topsearch\n";
        $message .= "• 🔄 Auto Scan: $autoscan\n";
        $message .= "• 🔔 Notifications: $notify\n\n";
        
        $message .= "🛠️ <b>Select category:</b>";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🌐 Language', 'callback_data' => 'settings_language'],
                    ['text' => '📊 Quality', 'callback_data' => 'settings_quality']
                ],
                [
                    ['text' => '📁 Layout', 'callback_data' => 'settings_layout'],
                    ['text' => '🎯 Priority', 'callback_data' => 'settings_priority']
                ],
                [
                    ['text' => '📄 Results/Page', 'callback_data' => 'settings_perpage'],
                    ['text' => '🎨 Theme', 'callback_data' => 'settings_theme']
                ],
                [
                    ['text' => '🔒 Spoiler Mode', 'callback_data' => 'settings_spoiler'],
                    ['text' => '🔍 Top Search', 'callback_data' => 'settings_topsearch']
                ],
                [
                    ['text' => '🔄 Auto Scan', 'callback_data' => 'settings_autoscan'],
                    ['text' => '🔔 Notifications', 'callback_data' => 'settings_notify']
                ],
                [
                    ['text' => '🔄 Reset', 'callback_data' => 'settings_reset'],
                    ['text' => '❌ Close', 'callback_data' => 'settings_close']
                ]
            ]
        ];
        
        sendMessage($chat_id, $message, $keyboard, 'HTML');
    }
    
    public function show_language_settings($chat_id) {
        $current = $this->get('language');
        
        $message = "🌐 <b>Select Language</b>\n\nCurrent: <b>" . ucfirst($current) . "</b>\n\n";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🇮🇳 Hindi' . ($current == 'hindi' ? ' ✅' : ''), 'callback_data' => 'settings_set_language_hindi'],
                    ['text' => '🇬🇧 English' . ($current == 'english' ? ' ✅' : ''), 'callback_data' => 'settings_set_language_english']
                ],
                [
                    ['text' => '🇮🇳 Tamil' . ($current == 'tamil' ? ' ✅' : ''), 'callback_data' => 'settings_set_language_tamil'],
                    ['text' => '🇮🇳 Telugu' . ($current == 'telugu' ? ' ✅' : ''), 'callback_data' => 'settings_set_language_telugu']
                ],
                [
                    ['text' => '⬅️ Back', 'callback_data' => 'settings_back'],
                    ['text' => '❌ Close', 'callback_data' => 'settings_close']
                ]
            ]
        ];
        
        sendMessage($chat_id, $message, $keyboard, 'HTML');
    }
    
    public function show_quality_settings($chat_id) {
        $current = $this->get('default_quality');
        
        $message = "📊 <b>Select Default Quality</b>\n\nCurrent: <b>$current</b>\n\n";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '2160p (4K)' . ($current == '2160p' ? ' ✅' : ''), 'callback_data' => 'settings_set_quality_2160p'],
                    ['text' => '1440p (2K)' . ($current == '1440p' ? ' ✅' : ''), 'callback_data' => 'settings_set_quality_1440p']
                ],
                [
                    ['text' => '1080p (FHD)' . ($current == '1080p' ? ' ✅' : ''), 'callback_data' => 'settings_set_quality_1080p'],
                    ['text' => '720p (HD)' . ($current == '720p' ? ' ✅' : ''), 'callback_data' => 'settings_set_quality_720p']
                ],
                [
                    ['text' => '480p (SD)' . ($current == '480p' ? ' ✅' : ''), 'callback_data' => 'settings_set_quality_480p'],
                    ['text' => '360p' . ($current == '360p' ? ' ✅' : ''), 'callback_data' => 'settings_set_quality_360p']
                ],
                [
                    ['text' => '⬅️ Back', 'callback_data' => 'settings_back'],
                    ['text' => '❌ Close', 'callback_data' => 'settings_close']
                ]
            ]
        ];
        
        sendMessage($chat_id, $message, $keyboard, 'HTML');
    }
    
    public function show_layout_settings($chat_id) {
        $current = $this->get('result_layout');
        
        $message = "📁 <b>Select Layout</b>\n\nCurrent: <b>" . ($current == 'buttons' ? '🔘 Buttons' : '📝 Text') . "</b>\n\n";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🔘 Buttons' . ($current == 'buttons' ? ' ✅' : ''), 'callback_data' => 'settings_set_layout_buttons'],
                    ['text' => '📝 Text List' . ($current == 'text' ? ' ✅' : ''), 'callback_data' => 'settings_set_layout_text']
                ],
                [
                    ['text' => '⬅️ Back', 'callback_data' => 'settings_back'],
                    ['text' => '❌ Close', 'callback_data' => 'settings_close']
                ]
            ]
        ];
        
        sendMessage($chat_id, $message, $keyboard, 'HTML');
    }
    
    public function show_priority_settings($chat_id) {
        $current = $this->get('priority_mode');
        
        $message = "🎯 <b>Select Priority Mode</b>\n\nCurrent: <b>" . ($current == 'quality' ? '📊 Quality First' : '💾 Size First') . "</b>\n\n";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '📊 Quality First' . ($current == 'quality' ? ' ✅' : ''), 'callback_data' => 'settings_set_priority_quality'],
                    ['text' => '💾 Size First' . ($current == 'size' ? ' ✅' : ''), 'callback_data' => 'settings_set_priority_size']
                ],
                [
                    ['text' => '⬅️ Back', 'callback_data' => 'settings_back'],
                    ['text' => '❌ Close', 'callback_data' => 'settings_close']
                ]
            ]
        ];
        
        sendMessage($chat_id, $message, $keyboard, 'HTML');
    }
    
    public function show_perpage_settings($chat_id) {
        $current = $this->get('results_per_page');
        
        $message = "📄 <b>Results Per Page</b>\n\nCurrent: <b>$current</b>\n\n";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '5' . ($current == 5 ? ' ✅' : ''), 'callback_data' => 'settings_set_perpage_5'],
                    ['text' => '10' . ($current == 10 ? ' ✅' : ''), 'callback_data' => 'settings_set_perpage_10']
                ],
                [
                    ['text' => '15' . ($current == 15 ? ' ✅' : ''), 'callback_data' => 'settings_set_perpage_15'],
                    ['text' => '20' . ($current == 20 ? ' ✅' : ''), 'callback_data' => 'settings_set_perpage_20']
                ],
                [
                    ['text' => '⬅️ Back', 'callback_data' => 'settings_back'],
                    ['text' => '❌ Close', 'callback_data' => 'settings_close']
                ]
            ]
        ];
        
        sendMessage($chat_id, $message, $keyboard, 'HTML');
    }
    
    public function show_theme_settings($chat_id) {
        $current = $this->get('theme');
        
        $message = "🎨 <b>Select Theme</b>\n\nCurrent: <b>" . ($current == 'dark' ? '🌙 Dark' : '☀️ Light') . "</b>\n\n";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🌙 Dark' . ($current == 'dark' ? ' ✅' : ''), 'callback_data' => 'settings_set_theme_dark'],
                    ['text' => '☀️ Light' . ($current == 'light' ? ' ✅' : ''), 'callback_data' => 'settings_set_theme_light']
                ],
                [
                    ['text' => '⬅️ Back', 'callback_data' => 'settings_back'],
                    ['text' => '❌ Close', 'callback_data' => 'settings_close']
                ]
            ]
        ];
        
        sendMessage($chat_id, $message, $keyboard, 'HTML');
    }
    
    public function toggle_setting($chat_id, $setting) {
        $current = $this->get($setting);
        $this->set($setting, !$current);
        
        $setting_names = [
            'spoiler_mode' => 'Spoiler Mode',
            'top_search' => 'Top Search',
            'auto_scan' => 'Auto Scan',
            'notifications' => 'Notifications'
        ];
        
        $status = !$current ? '✅ ENABLED' : '❌ DISABLED';
        
        sendMessage($chat_id, "✅ <b>" . $setting_names[$setting] . "</b> $status");
        $this->show_main_panel($chat_id);
    }
}

// ==============================
// 17. FILTER SYSTEM CLASS (Continued)
// ==============================
class MovieFilter {
    private $filters = [];
    private $user_id;
    private $filter_session_file = 'filter_sessions.json';
    
    function __construct($user_id = null) {
        $this->user_id = $user_id;
    }
    
    public function apply_filters($movies, $filters) {
        $this->filters = $filters;
        $filtered = array_filter($movies, [$this, 'filter_movie']);
        return array_values($filtered);
    }
    
    private function filter_movie($movie) {
        $movie_name = $movie['movie_name'] ?? $movie['title'] ?? '';
        $movie_lower = strtolower($movie_name);
        
        foreach ($this->filters as $key => $value) {
            if (empty($value)) continue;
            
            switch ($key) {
                case 'language':
                    $lang_match = false;
                    if (stripos($movie_lower, $value) !== false) {
                        $lang_match = true;
                    }
                    if ($value == 'hindi' && !preg_match('/english|tamil|telugu/', $movie_lower)) {
                        $lang_match = true;
                    }
                    if (!$lang_match) return false;
                    break;
                
                case 'quality':
                    if (stripos($movie_lower, $value) === false) {
                        if (!($value == '2160p' && stripos($movie_lower, '4k') !== false) &&
                            !($value == '1080p' && stripos($movie_lower, 'fhd') !== false)) {
                            return false;
                        }
                    }
                    break;
                
                case 'season':
                    if (preg_match('/[Ss](\d+)/', $movie_lower, $matches)) {
                        if ($matches[1] != $value) return false;
                    } else {
                        if ($value != 'all') return false;
                    }
                    break;
                
                case 'dual_audio':
                    $is_dual = (stripos($movie_lower, 'dual') !== false || stripos($movie_lower, 'dub') !== false);
                    if ($value == 'yes' && !$is_dual) return false;
                    if ($value == 'no' && $is_dual) return false;
                    break;
                
                case 'hdr':
                    $is_hdr = (stripos($movie_lower, 'hdr') !== false || stripos($movie_lower, 'dv') !== false);
                    if ($value == 'yes' && !$is_hdr) return false;
                    if ($value == 'no' && $is_hdr) return false;
                    break;
                
                case 'subtitles':
                    $has_subs = (stripos($movie_lower, 'sub') !== false);
                    if ($value == 'yes' && !$has_subs) return false;
                    if ($value == 'no' && $has_subs) return false;
                    break;
            }
        }
        
        return true;
    }
    
    public function save_filter_session($filters) {
        if (!$this->user_id) return;
        
        $sessions = $this->load_filter_sessions();
        $sessions[$this->user_id] = [
            'filters' => $filters,
            'timestamp' => time()
        ];
        file_put_contents($this->filter_session_file, json_encode($sessions, JSON_PRETTY_PRINT));
    }
    
    public function load_filter_session() {
        if (!$this->user_id) return [];
        
        $sessions = $this->load_filter_sessions();
        if (isset($sessions[$this->user_id])) {
            if (time() - $sessions[$this->user_id]['timestamp'] < 3600) {
                return $sessions[$this->user_id]['filters'];
            }
        }
        return [];
    }
    
    private function load_filter_sessions() {
        if (!file_exists($this->filter_session_file)) {
            return [];
        }
        return json_decode(file_get_contents($this->filter_session_file), true) ?: [];
    }
    
    public function show_filter_panel($chat_id, $current_filters = []) {
        $active_count = count($current_filters);
        
        $message = "🔍 <b>Filter System</b>\n\n";
        
        if ($active_count > 0) {
            $message .= "🎯 <b>Active Filters ($active_count):</b>\n";
            foreach ($current_filters as $key => $value) {
                $message .= "• <b>" . ucfirst($key) . ":</b> $value\n";
            }
            $message .= "\n";
        }
        
        $message .= "📋 <b>Filter Categories:</b>\n\n";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🗣️ Language', 'callback_data' => 'filter_menu_language'],
                    ['text' => '📊 Quality', 'callback_data' => 'filter_menu_quality']
                ],
                [
                    ['text' => '📺 Season', 'callback_data' => 'filter_menu_season'],
                    ['text' => '🔊 Dual Audio', 'callback_data' => 'filter_menu_dual']
                ],
                [
                    ['text' => '✨ HDR', 'callback_data' => 'filter_menu_hdr'],
                    ['text' => '📝 Subtitles', 'callback_data' => 'filter_menu_subs']
                ]
            ]
        ];
        
        $action_row = [];
        if ($active_count > 0) {
            $action_row[] = ['text' => '🧹 Clear All', 'callback_data' => 'filter_clear_all'];
        }
        $action_row[] = ['text' => '❌ Close', 'callback_data' => 'filter_close'];
        
        $keyboard['inline_keyboard'][] = $action_row;
        
        sendMessage($chat_id, $message, $keyboard, 'HTML');
    }
    
    public function show_language_filter($chat_id, $current = null) {
        $message = "🗣️ <b>Language Filter</b>\n\nSelect language:\n\n";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🇮🇳 Hindi' . ($current == 'hindi' ? ' ✅' : ''), 'callback_data' => 'filter_set_language_hindi'],
                    ['text' => '🇬🇧 English' . ($current == 'english' ? ' ✅' : ''), 'callback_data' => 'filter_set_language_english']
                ],
                [
                    ['text' => '🇮🇳 Tamil' . ($current == 'tamil' ? ' ✅' : ''), 'callback_data' => 'filter_set_language_tamil'],
                    ['text' => '🇮🇳 Telugu' . ($current == 'telugu' ? ' ✅' : ''), 'callback_data' => 'filter_set_language_telugu']
                ],
                [
                    ['text' => '🔹 Any', 'callback_data' => 'filter_set_language_any'],
                    ['text' => '⬅️ Back', 'callback_data' => 'filter_back']
                ]
            ]
        ];
        
        sendMessage($chat_id, $message, $keyboard, 'HTML');
    }
    
    public function show_quality_filter($chat_id, $current = null) {
        $message = "📊 <b>Quality Filter</b>\n\nSelect quality:\n\n";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '2160p (4K)' . ($current == '2160p' ? ' ✅' : ''), 'callback_data' => 'filter_set_quality_2160p'],
                    ['text' => '1080p (FHD)' . ($current == '1080p' ? ' ✅' : ''), 'callback_data' => 'filter_set_quality_1080p']
                ],
                [
                    ['text' => '720p (HD)' . ($current == '720p' ? ' ✅' : ''), 'callback_data' => 'filter_set_quality_720p'],
                    ['text' => '480p (SD)' . ($current == '480p' ? ' ✅' : ''), 'callback_data' => 'filter_set_quality_480p']
                ],
                [
                    ['text' => '🔹 Any', 'callback_data' => 'filter_set_quality_any'],
                    ['text' => '⬅️ Back', 'callback_data' => 'filter_back']
                ]
            ]
        ];
        
        sendMessage($chat_id, $message, $keyboard, 'HTML');
    }
    
    public function show_season_filter($chat_id, $current = null) {
        $message = "📺 <b>Season Filter</b>\n\nSelect season:\n\n";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => 'Season 1', 'callback_data' => 'filter_set_season_1'],
                    ['text' => 'Season 2', 'callback_data' => 'filter_set_season_2'],
                    ['text' => 'Season 3', 'callback_data' => 'filter_set_season_3']
                ],
                [
                    ['text' => 'Season 4', 'callback_data' => 'filter_set_season_4'],
                    ['text' => 'Season 5', 'callback_data' => 'filter_set_season_5'],
                    ['text' => 'Season 6', 'callback_data' => 'filter_set_season_6']
                ],
                [
                    ['text' => '🔹 All Seasons', 'callback_data' => 'filter_set_season_all'],
                    ['text' => '⬅️ Back', 'callback_data' => 'filter_back']
                ]
            ]
        ];
        
        sendMessage($chat_id, $message, $keyboard, 'HTML');
    }
    
    public function show_dual_audio_filter($chat_id, $current = null) {
        $message = "🔊 <b>Dual Audio Filter</b>\n\nSelect option:\n\n";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '✅ Dual Audio Only' . ($current == 'yes' ? ' ✅' : ''), 'callback_data' => 'filter_set_dual_yes'],
                    ['text' => '❌ Single Audio Only' . ($current == 'no' ? ' ✅' : ''), 'callback_data' => 'filter_set_dual_no']
                ],
                [
                    ['text' => '🔹 Any', 'callback_data' => 'filter_set_dual_any'],
                    ['text' => '⬅️ Back', 'callback_data' => 'filter_back']
                ]
            ]
        ];
        
        sendMessage($chat_id, $message, $keyboard, 'HTML');
    }
    
    public function show_hdr_filter($chat_id, $current = null) {
        $message = "✨ <b>HDR Filter</b>\n\nSelect option:\n\n";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '✅ HDR Only' . ($current == 'yes' ? ' ✅' : ''), 'callback_data' => 'filter_set_hdr_yes'],
                    ['text' => '❌ SDR Only' . ($current == 'no' ? ' ✅' : ''), 'callback_data' => 'filter_set_hdr_no']
                ],
                [
                    ['text' => '🔹 Any', 'callback_data' => 'filter_set_hdr_any'],
                    ['text' => '⬅️ Back', 'callback_data' => 'filter_back']
                ]
            ]
        ];
        
        sendMessage($chat_id, $message, $keyboard, 'HTML');
    }
    
    public function show_subtitle_filter($chat_id, $current = null) {
        $message = "📝 <b>Subtitle Filter</b>\n\nSelect option:\n\n";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '✅ With Subtitles' . ($current == 'yes' ? ' ✅' : ''), 'callback_data' => 'filter_set_subs_yes'],
                    ['text' => '❌ Without Subtitles' . ($current == 'no' ? ' ✅' : ''), 'callback_data' => 'filter_set_subs_no']
                ],
                [
                    ['text' => '🔹 Any', 'callback_data' => 'filter_set_subs_any'],
                    ['text' => '⬅️ Back', 'callback_data' => 'filter_back']
                ]
            ]
        ];
        
        sendMessage($chat_id, $message, $keyboard, 'HTML');
    }
}

// ==============================
// 18. BULK MANAGER CLASS
// ==============================
class BulkManager {
    private $movies = [];
    
    function __construct() {
        $this->movies = get_all_movies_list();
    }
    
    public function extract_series() {
        $series_list = [];
        
        foreach ($this->movies as $movie) {
            $name = $movie['movie_name'];
            
            if (preg_match('/^(.*?)[.\s_]*[Ss](\d+)[Ee](\d+)/', $name, $matches)) {
                $series_name = trim(str_replace(['.', '_'], ' ', $matches[1]));
                $season = (int)$matches[2];
                $episode = (int)$matches[3];
                
                if (!isset($series_list[$series_name])) {
                    $series_list[$series_name] = [];
                }
                if (!isset($series_list[$series_name][$season])) {
                    $series_list[$series_name][$season] = [];
                }
                
                $series_list[$series_name][$season][$episode] = $movie;
            }
        }
        
        foreach ($series_list as $series => $seasons) {
            foreach ($seasons as $season => $episodes) {
                ksort($episodes);
                $series_list[$series][$season] = $episodes;
            }
            ksort($series_list[$series]);
        }
        
        return $series_list;
    }
    
    public function show_series_list($chat_id) {
        $series = $this->extract_series();
        
        if (empty($series)) {
            sendMessage($chat_id, "📭 No series found!");
            return;
        }
        
        $message = "📺 <b>Series List</b>\n\nTotal: " . count($series) . "\n\n";
        
        $keyboard = ['inline_keyboard' => []];
        
        foreach ($series as $name => $seasons) {
            $total_eps = 0;
            foreach ($seasons as $eps) {
                $total_eps += count($eps);
            }
            $keyboard['inline_keyboard'][] = [
                ['text' => "📺 $name ($total_eps eps)", 'callback_data' => 'bulk_series_' . base64_encode($name)]
            ];
        }
        
        $keyboard['inline_keyboard'][] = [
            ['text' => '❌ Close', 'callback_data' => 'bulk_close']
        ];
        
        sendMessage($chat_id, $message, $keyboard, 'HTML');
    }
    
    public function show_seasons($chat_id, $series_name) {
        $series = $this->extract_series();
        $name = base64_decode($series_name);
        
        if (!isset($series[$name])) {
            sendMessage($chat_id, "❌ Series not found!");
            return;
        }
        
        $seasons = $series[$name];
        
        $message = "📺 <b>$name</b>\n\nSeasons: " . count($seasons) . "\n\n";
        
        $keyboard = ['inline_keyboard' => []];
        
        foreach ($seasons as $season_num => $episodes) {
            $keyboard['inline_keyboard'][] = [
                ['text' => "Season $season_num (" . count($episodes) . " eps)", 'callback_data' => 'bulk_season_' . base64_encode($name . '||' . $season_num)]
            ];
        }
        
        $keyboard['inline_keyboard'][] = [
            ['text' => '⬅️ Back', 'callback_data' => 'bulk_back'],
            ['text' => '❌ Close', 'callback_data' => 'bulk_close']
        ];
        
        sendMessage($chat_id, $message, $keyboard, 'HTML');
    }
    
    public function show_episodes($chat_id, $data) {
        list($series_name, $season_num) = explode('||', base64_decode($data));
        
        $series = $this->extract_series();
        
        if (!isset($series[$series_name][$season_num])) {
            sendMessage($chat_id, "❌ Season not found!");
            return;
        }
        
        $episodes = $series[$series_name][$season_num];
        
        $message = "📺 <b>$series_name - Season $season_num</b>\n\nEpisodes: " . count($episodes) . "\n\n";
        
        $keyboard = ['inline_keyboard' => []];
        $row = [];
        
        foreach ($episodes as $ep_num => $movie) {
            $row[] = ['text' => "Ep $ep_num", 'callback_data' => 'play_' . base64_encode($movie['message_id'] . '||' . $movie['channel_id'])];
            
            if (count($row) == 5) {
                $keyboard['inline_keyboard'][] = $row;
                $row = [];
            }
        }
        
        if (!empty($row)) {
            $keyboard['inline_keyboard'][] = $row;
        }
        
        $keyboard['inline_keyboard'][] = [
            ['text' => '⬅️ Back', 'callback_data' => 'bulk_series_' . base64_encode($series_name)],
            ['text' => '❌ Close', 'callback_data' => 'bulk_close']
        ];
        
        sendMessage($chat_id, $message, $keyboard, 'HTML');
    }
    
    public function download_season($chat_id, $data) {
        list($series_name, $season_num) = explode('||', base64_decode($data));
        
        $series = $this->extract_series();
        
        if (!isset($series[$series_name][$season_num])) {
            sendMessage($chat_id, "❌ Season not found!");
            return;
        }
        
        $episodes = $series[$series_name][$season_num];
        $total = count($episodes);
        
        $progress_msg = sendMessage($chat_id, "📦 Downloading Season $season_num\n\n0/$total");
        $progress_id = $progress_msg['result']['message_id'];
        
        $success = 0;
        $failed = 0;
        $index = 0;
        
        foreach ($episodes as $ep_num => $movie) {
            $index++;
            $progress = round(($index / $total) * 100);
            
            editMessage($chat_id, $progress_id, "📦 Downloading... $progress%\n$index/$total");
            
            $result = deliver_item_to_chat($chat_id, $movie);
            if ($result) $success++; else $failed++;
            
            usleep(500000);
        }
        
        editMessage($chat_id, $progress_id, "✅ Complete!\nSuccess: $success\nFailed: $failed");
    }
}

// ==============================
// 19. FOLDER NAVIGATOR CLASS
// ==============================
class FolderNavigator {
    private $movies = [];
    private $structure = [];
    
    function __construct() {
        $this->movies = get_all_movies_list();
        $this->build_structure();
    }
    
    private function build_structure() {
        foreach ($this->movies as $movie) {
            $name = $movie['movie_name'];
            $quality = $movie['quality'] ?? 'Unknown';
            
            if (preg_match('/^(.*?)[.\s_]*[Ss](\d+)[Ee](\d+)/', $name, $matches)) {
                $series = trim(str_replace(['.', '_'], ' ', $matches[1]));
                $season = "Season " . (int)$matches[2];
                $episode = "Episode " . (int)$matches[3];
                
                $this->structure['Series'][$series][$season][$episode][] = $movie;
            } else {
                $year = '';
                if (preg_match('/\((\d{4})\)/', $name, $matches)) {
                    $year = $matches[1];
                }
                
                $category = $year ? "Movies $year" : "Movies";
                $this->structure[$category][$quality][] = $movie;
            }
        }
        
        ksort($this->structure);
    }
    
    public function show_root($chat_id) {
        $message = "📁 <b>Media Library</b>\n\nSelect a folder:\n\n";
        
        $keyboard = ['inline_keyboard' => []];
        
        foreach (array_keys($this->structure) as $folder) {
            $icon = $folder == 'Series' ? '📺' : '🎬';
            $keyboard['inline_keyboard'][] = [
                ['text' => "$icon $folder", 'callback_data' => 'folder_open_' . base64_encode($folder)]
            ];
        }
        
        $keyboard['inline_keyboard'][] = [
            ['text' => '❌ Close', 'callback_data' => 'folder_close']
        ];
        
        sendMessage($chat_id, $message, $keyboard, 'HTML');
    }
    
    public function open_folder($chat_id, $folder_name) {
        $folder = base64_decode($folder_name);
        
        if (!isset($this->structure[$folder])) {
            sendMessage($chat_id, "❌ Folder not found!");
            return;
        }
        
        if ($folder == 'Series') {
            $this->show_series($chat_id);
        } else {
            $this->show_quality_folders($chat_id, $folder);
        }
    }
    
    private function show_series($chat_id) {
        $series_list = $this->structure['Series'];
        
        $message = "📺 <b>Series</b>\n\nTotal: " . count($series_list) . "\n\n";
        
        $keyboard = ['inline_keyboard' => []];
        
        foreach ($series_list as $series => $seasons) {
            $total_eps = 0;
            foreach ($seasons as $eps) {
                foreach ($eps as $ep_movies) {
                    $total_eps += count($ep_movies);
                }
            }
            $keyboard['inline_keyboard'][] = [
                ['text' => "📺 $series ($total_eps eps)", 'callback_data' => 'folder_series_' . base64_encode($series)]
            ];
        }
        
        $keyboard['inline_keyboard'][] = [
            ['text' => '⬅️ Back', 'callback_data' => 'folder_back'],
            ['text' => '❌ Close', 'callback_data' => 'folder_close']
        ];
        
        sendMessage($chat_id, $message, $keyboard, 'HTML');
    }
    
    public function show_seasons($chat_id, $series_name) {
        $series = base64_decode($series_name);
        
        if (!isset($this->structure['Series'][$series])) {
            sendMessage($chat_id, "❌ Series not found!");
            return;
        }
        
        $seasons = $this->structure['Series'][$series];
        
        $message = "📺 <b>$series</b>\n\nSeasons: " . count($seasons) . "\n\n";
        
        $keyboard = ['inline_keyboard' => []];
        
        foreach ($seasons as $season => $episodes) {
            $ep_count = 0;
            foreach ($episodes as $eps) {
                $ep_count += count($eps);
            }
            $keyboard['inline_keyboard'][] = [
                ['text' => "$season ($ep_count eps)", 'callback_data' => 'folder_season_' . base64_encode($series . '||' . $season)]
            ];
        }
        
        $keyboard['inline_keyboard'][] = [
            ['text' => '⬅️ Back', 'callback_data' => 'folder_back'],
            ['text' => '❌ Close', 'callback_data' => 'folder_close']
        ];
        
        sendMessage($chat_id, $message, $keyboard, 'HTML');
    }
    
    public function show_episodes($chat_id, $data) {
        list($series, $season) = explode('||', base64_decode($data));
        
        if (!isset($this->structure['Series'][$series][$season])) {
            sendMessage($chat_id, "❌ Season not found!");
            return;
        }
        
        $episodes = $this->structure['Series'][$series][$season];
        
        $message = "📺 <b>$series - $season</b>\n\n";
        
        $keyboard = ['inline_keyboard' => []];
        $row = [];
        $ep_num = 1;
        
        foreach ($episodes as $ep_name => $movies) {
            foreach ($movies as $movie) {
                $row[] = ['text' => "Ep $ep_num", 'callback_data' => 'play_' . base64_encode($movie['message_id'] . '||' . $movie['channel_id'])];
                
                if (count($row) == 5) {
                    $keyboard['inline_keyboard'][] = $row;
                    $row = [];
                }
                $ep_num++;
            }
        }
        
        if (!empty($row)) {
            $keyboard['inline_keyboard'][] = $row;
        }
        
        $keyboard['inline_keyboard'][] = [
            ['text' => '⬅️ Back', 'callback_data' => 'folder_series_' . base64_encode($series)],
            ['text' => '❌ Close', 'callback_data' => 'folder_close']
        ];
        
        sendMessage($chat_id, $message, $keyboard, 'HTML');
    }
    
    private function show_quality_folders($chat_id, $category) {
        $qualities = $this->structure[$category];
        
        $message = "🎬 <b>$category</b>\n\nSelect quality:\n\n";
        
        $keyboard = ['inline_keyboard' => []];
        
        foreach ($qualities as $quality => $movies) {
            $keyboard['inline_keyboard'][] = [
                ['text' => "$quality (" . count($movies) . ")", 'callback_data' => 'folder_quality_' . base64_encode($category . '||' . $quality)]
            ];
        }
        
        $keyboard['inline_keyboard'][] = [
            ['text' => '⬅️ Back', 'callback_data' => 'folder_back'],
            ['text' => '❌ Close', 'callback_data' => 'folder_close']
        ];
        
        sendMessage($chat_id, $message, $keyboard, 'HTML');
    }
    
    public function show_movies_by_quality($chat_id, $data) {
        list($category, $quality) = explode('||', base64_decode($data));
        
        if (!isset($this->structure[$category][$quality])) {
            sendMessage($chat_id, "❌ Quality not found!");
            return;
        }
        
        $movies = $this->structure[$category][$quality];
        
        $message = "🎬 <b>$category - $quality</b>\n\nTotal: " . count($movies) . "\n\n";
        
        $keyboard = ['inline_keyboard' => []];
        $row = [];
        
        foreach ($movies as $index => $movie) {
            $name = strlen($movie['movie_name']) > 30 ? substr($movie['movie_name'], 0, 27) . '...' : $movie['movie_name'];
            $row[] = ['text' => ($index+1) . ". $name", 'callback_data' => 'play_' . base64_encode($movie['message_id'] . '||' . $movie['channel_id'])];
            
            if (count($row) == 2) {
                $keyboard['inline_keyboard'][] = $row;
                $row = [];
            }
        }
        
        if (!empty($row)) {
            $keyboard['inline_keyboard'][] = $row;
        }
        
        $keyboard['inline_keyboard'][] = [
            ['text' => '⬅️ Back', 'callback_data' => 'folder_open_' . base64_encode($category)],
            ['text' => '❌ Close', 'callback_data' => 'folder_close']
        ];
        
        sendMessage($chat_id, $message, $keyboard, 'HTML');
    }
}

// ==============================
// 20. AUTO DELETE MANAGER CLASS
// ==============================
class AutoDeleteManager {
    private $queue_file = 'delete_queue.json';
    private $default_time = 60;
    
    function __construct() {
        $this->init_queue();
    }
    
    private function init_queue() {
        if (!file_exists($this->queue_file)) {
            file_put_contents($this->queue_file, json_encode([]));
        }
    }
    
    private function load_queue() {
        return json_decode(file_get_contents($this->queue_file), true) ?: [];
    }
    
    private function save_queue($queue) {
        file_put_contents($this->queue_file, json_encode($queue, JSON_PRETTY_PRINT));
    }
    
    public function schedule_delete($chat_id, $message_id, $minutes = null) {
        $queue = $this->load_queue();
        $delete_time = time() + (($minutes ?? $this->default_time) * 60);
        
        $queue[] = [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'delete_time' => $delete_time,
            'warning_sent' => false,
            'created' => time()
        ];
        
        $this->save_queue($queue);
        
        $warning = "⚠️ Auto-Delete Warning\n\nThis file will be deleted in " . ($minutes ?? $this->default_time) . " minutes.";
        sendMessage($chat_id, $warning);
        
        return $delete_time;
    }
    
    public function schedule_with_options($chat_id, $message_id) {
        $message = "⏰ Auto-Delete Timer\n\nSelect delete time:\n\n";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '5 min', 'callback_data' => 'delete_set_5_' . $message_id],
                    ['text' => '15 min', 'callback_data' => 'delete_set_15_' . $message_id],
                    ['text' => '30 min', 'callback_data' => 'delete_set_30_' . $message_id]
                ],
                [
                    ['text' => '1 hour', 'callback_data' => 'delete_set_60_' . $message_id],
                    ['text' => '2 hours', 'callback_data' => 'delete_set_120_' . $message_id],
                    ['text' => '6 hours', 'callback_data' => 'delete_set_360_' . $message_id]
                ],
                [
                    ['text' => '❌ Cancel', 'callback_data' => 'delete_cancel']
                ]
            ]
        ];
        
        sendMessage($chat_id, $message, $keyboard);
    }
    
    public function process_deletions() {
        $queue = $this->load_queue();
        $now = time();
        $new_queue = [];
        $deleted_count = 0;
        
        foreach ($queue as $item) {
            if ($now >= $item['delete_time']) {
                deleteMessage($item['chat_id'], $item['message_id']);
                $deleted_count++;
                
                sendMessage($item['chat_id'], "🗑️ File auto-deleted as scheduled.");
            } elseif (!$item['warning_sent'] && $now >= ($item['delete_time'] - 300)) {
                $minutes_left = round(($item['delete_time'] - $now) / 60);
                sendMessage($item['chat_id'], "⏰ Reminder: File will be deleted in $minutes_left minutes.");
                
                $item['warning_sent'] = true;
                $new_queue[] = $item;
            } else {
                $new_queue[] = $item;
            }
        }
        
        $this->save_queue($new_queue);
        return $deleted_count;
    }
    
    public function show_status($chat_id) {
        $queue = $this->load_queue();
        $user_queue = array_filter($queue, fn($item) => $item['chat_id'] == $chat_id);
        
        if (empty($user_queue)) {
            sendMessage($chat_id, "📭 No files scheduled for deletion.");
            return;
        }
        
        $message = "⏰ Your Delete Schedule\n\n";
        
        foreach ($user_queue as $item) {
            $time_left = $item['delete_time'] - time();
            $minutes = floor($time_left / 60);
            $message .= "• Message ID: {$item['message_id']}\n  Deletes in: $minutes minutes\n\n";
        }
        
        sendMessage($chat_id, $message);
    }
}

// ==============================
// 21. MOVIE DELIVERY SYSTEM
// ==============================
function deliver_item_to_chat($chat_id, $item) {
    if (!isset($item['channel_id']) || empty($item['channel_id'])) {
        $source_channel = MAIN_CHANNEL_ID;
    } else {
        $source_channel = $item['channel_id'];
    }
    
    if (!empty($item['message_id']) && is_numeric($item['message_id'])) {
        $result = json_decode(copyMessage($chat_id, $source_channel, $item['message_id']), true);
        
        if ($result && $result['ok']) {
            update_stats('total_downloads', 1);
            return true;
        } else {
            $fallback_result = json_decode(forwardMessage($chat_id, $source_channel, $item['message_id']), true);
            
            if ($fallback_result && $fallback_result['ok']) {
                update_stats('total_downloads', 1);
                return true;
            }
        }
    }
    
    $text = "🎬 <b>" . htmlspecialchars($item['movie_name'] ?? 'Unknown') . "</b>\n";
    $text .= "📊 Quality: " . htmlspecialchars($item['quality'] ?? 'Unknown') . "\n";
    $text .= "💾 Size: " . htmlspecialchars($item['size'] ?? 'Unknown') . "\n";
    $text .= "🗣️ Language: " . htmlspecialchars($item['language'] ?? 'Hindi') . "\n";
    
    if (!empty($item['message_id']) && is_numeric($item['message_id']) && !empty($source_channel)) {
        $text .= "🔗 Direct Link: " . get_direct_channel_link($item['message_id'], $source_channel) . "\n\n";
    }
    
    $text .= "⚠️ Join channel to access content: " . get_channel_username_link($item['channel_type'] ?? 'main');
    
    sendMessage($chat_id, $text, null, 'HTML');
    update_stats('total_downloads', 1);
    return false;
}

// ==============================
// 22. SEARCH SYSTEM
// ==============================
function smart_search($query) {
    global $movie_messages;
    $query_lower = strtolower(trim($query));
    $results = array();
    
    $is_theater_search = false;
    $theater_keywords = ['theater', 'theatre', 'print', 'hdcam', 'camrip'];
    foreach ($theater_keywords as $keyword) {
        if (strpos($query_lower, $keyword) !== false) {
            $is_theater_search = true;
            $query_lower = str_replace($keyword, '', $query_lower);
            break;
        }
    }
    $query_lower = trim($query_lower);
    
    foreach ($movie_messages as $movie => $entries) {
        $score = 0;
        
        foreach ($entries as $entry) {
            $entry_channel_type = $entry['channel_type'] ?? 'main';
            
            if ($is_theater_search && $entry_channel_type == 'theater') {
                $score += 20;
            } elseif (!$is_theater_search && $entry_channel_type == 'main') {
                $score += 10;
            }
        }
        
        if ($movie == $query_lower) {
            $score = 100;
        } elseif (strpos($movie, $query_lower) !== false) {
            $score = 80 - (strlen($movie) - strlen($query_lower));
        } else {
            similar_text($movie, $query_lower, $similarity);
            if ($similarity > 60) $score = $similarity;
        }
        
        foreach ($entries as $entry) {
            if (stripos($entry['quality'] ?? '', '1080') !== false) $score += 5;
            if (stripos($entry['quality'] ?? '', '720') !== false) $score += 3;
            if (stripos($entry['language'] ?? '', 'hindi') !== false) $score += 2;
        }
        
        if ($score > 0) {
            $channel_types = array_column($entries, 'channel_type');
            $results[$movie] = [
                'score' => $score,
                'count' => count($entries),
                'latest_entry' => end($entries),
                'qualities' => array_unique(array_column($entries, 'quality')),
                'has_theater' => in_array('theater', $channel_types),
                'has_main' => in_array('main', $channel_types)
            ];
        }
    }
    
    uasort($results, function($a, $b) {
        return $b['score'] - $a['score'];
    });
    
    return array_slice($results, 0, MAX_SEARCH_RESULTS);
}

function detect_language($text) {
    $hindi_keywords = ['फिल्म', 'मूवी', 'हिंदी', 'चाहिए'];
    $english_keywords = ['movie', 'download', 'search', 'find'];
    
    $hindi_score = 0;
    $english_score = 0;
    
    foreach ($hindi_keywords as $k) {
        if (strpos($text, $k) !== false) $hindi_score++;
    }
    
    foreach ($english_keywords as $k) {
        if (stripos($text, $k) !== false) $english_score++;
    }
    
    $hindi_chars = preg_match('/[\x{0900}-\x{097F}]/u', $text);
    if ($hindi_chars) $hindi_score += 3;
    
    return $hindi_score > $english_score ? 'hindi' : 'english';
}

function send_multilingual_response($chat_id, $message_type, $language) {
    $responses = [
        'hindi' => [
            'welcome' => "🎬 Boss, kis movie ki talash hai?",
            'found' => "✅ Mil gayi!",
            'not_found' => "😔 Yeh movie abhi available nahi hai!",
            'searching' => "🔍 Dhoondh raha hoon..."
        ],
        'english' => [
            'welcome' => "🎬 Which movie are you looking for?",
            'found' => "✅ Found it!",
            'not_found' => "😔 This movie isn't available yet!",
            'searching' => "🔍 Searching..."
        ]
    ];
    
    sendMessage($chat_id, $responses[$language][$message_type]);
}

function advanced_search($chat_id, $query, $user_id = null) {
    global $movie_messages, $waiting_users;
    
    sendActionWithDelay($chat_id, 'typing', 2);
    
    $q = strtolower(trim($query));
    
    if (strlen($q) < 2) {
        sendMessage($chat_id, "❌ Please enter at least 2 characters");
        return;
    }
    
    sendActionWithDelay($chat_id, 'typing', 2);
    
    $found = smart_search($q);
    
    if (!empty($found)) {
        update_stats('successful_searches', 1);
        
        sendActionWithDelay($chat_id, 'typing', 2);
        
        $msg = "🔍 Found " . count($found) . " movies:\n\n";
        $i = 1;
        foreach ($found as $movie => $data) {
            $quality_info = !empty($data['qualities']) ? implode('/', $data['qualities']) : 'Unknown';
            $msg .= "$i. $movie (" . $data['count'] . " versions, $quality_info)\n";
            $i++;
            if ($i > 10) break;
        }
        
        sendMessageWithDelay($chat_id, $msg, null, 'HTML', 1500);
        
        sendActionWithDelay($chat_id, 'typing', 1);
        
        $keyboard = ['inline_keyboard' => []];
        $top_movies = array_slice(array_keys($found), 0, 5);
        
        foreach ($top_movies as $movie) {
            $keyboard['inline_keyboard'][] = [[ 
                'text' => "🎬 " . ucwords($movie), 
                'callback_data' => $movie 
            ]];
        }
        
        $keyboard['inline_keyboard'][] = [[
            'text' => "📝 Request Movie", 
            'callback_data' => 'request_movie'
        ]];
        
        sendMessageWithDelay($chat_id, "Top matches:", $keyboard, 'HTML', 1000);
        
        if ($user_id) {
            update_user_activity($user_id, 'found_movie');
            update_user_activity($user_id, 'search');
        }
        
    } else {
        sendActionWithDelay($chat_id, 'typing', 3);
        
        update_stats('failed_searches', 1);
        $lang = detect_language($query);
        send_multilingual_response($chat_id, 'not_found', $lang);
        
        $request_keyboard = [
            'inline_keyboard' => [[
                ['text' => '📝 Request This Movie', 'callback_data' => 'auto_request_' . base64_encode($query)]
            ]]
        ];
        
        sendMessage($chat_id, "Click below to request:", $request_keyboard);
        
        if (!isset($waiting_users[$q])) $waiting_users[$q] = [];
        $waiting_users[$q][] = [$chat_id, $user_id ?? $chat_id];
    }
    
    update_stats('total_searches', 1);
    if ($user_id) update_user_activity($user_id, 'search');
}

// ==============================
// 23. MOVIE REQUEST SYSTEM
// ==============================
function can_user_request($user_id) {
    $requests_data = json_decode(file_get_contents(REQUEST_FILE), true);
    $today = date('Y-m-d');
    
    $user_requests_today = 0;
    foreach ($requests_data['requests'] ?? [] as $request) {
        if ($request['user_id'] == $user_id && $request['date'] == $today) {
            $user_requests_today++;
        }
    }
    
    return $user_requests_today < DAILY_REQUEST_LIMIT;
}

function add_movie_request($user_id, $movie_name, $language = 'hindi') {
    if (!can_user_request($user_id)) {
        return false;
    }
    
    $requests_data = json_decode(file_get_contents(REQUEST_FILE), true);
    
    $request_id = uniqid();
    $requests_data['requests'][] = [
        'id' => $request_id,
        'user_id' => $user_id,
        'movie_name' => $movie_name,
        'language' => $language,
        'date' => date('Y-m-d'),
        'time' => date('H:i:s'),
        'status' => 'pending'
    ];
    
    if (!isset($requests_data['user_request_count'][$user_id])) {
        $requests_data['user_request_count'][$user_id] = 0;
    }
    $requests_data['user_request_count'][$user_id]++;
    
    file_put_contents(REQUEST_FILE, json_encode($requests_data, JSON_PRETTY_PRINT));
    
    $admin_msg = "🎯 New Request\n\nMovie: $movie_name\nUser: $user_id";
    sendMessage(ADMIN_ID, $admin_msg);
    
    return true;
}

function show_user_requests($chat_id, $user_id) {
    $requests_data = json_decode(file_get_contents(REQUEST_FILE), true);
    $user_requests = [];
    
    foreach ($requests_data['requests'] ?? [] as $request) {
        if ($request['user_id'] == $user_id) {
            $user_requests[] = $request;
        }
    }
    
    if (empty($user_requests)) {
        sendMessage($chat_id, "📭 No requests yet!");
        return;
    }
    
    $message = "📝 Your Requests\n\n";
    $i = 1;
    
    foreach (array_slice($user_requests, 0, 10) as $request) {
        $status_emoji = $request['status'] == 'completed' ? '✅' : '⏳';
        $message .= "$i. $status_emoji {$request['movie_name']}\n";
        $message .= "   📅 {$request['date']}\n\n";
        $i++;
    }
    
    sendMessage($chat_id, $message);
}

// ==============================
// 24. PAGINATION SYSTEM
// ==============================
function paginate_movies(array $all, int $page, array $filters = []): array {
    if (!empty($filters)) {
        $filter = new MovieFilter();
        $all = $filter->apply_filters($all, $filters);
    }
    
    $total = count($all);
    if ($total === 0) {
        return [
            'total' => 0, 'total_pages' => 1, 'page' => 1,
            'slice' => [], 'filters' => $filters,
            'has_next' => false, 'has_prev' => false,
            'start_item' => 0, 'end_item' => 0
        ];
    }
    
    $total_pages = (int)ceil($total / ITEMS_PER_PAGE);
    $page = max(1, min($page, $total_pages));
    $start = ($page - 1) * ITEMS_PER_PAGE;
    
    return [
        'total' => $total,
        'total_pages' => $total_pages,
        'page' => $page,
        'slice' => array_slice($all, $start, ITEMS_PER_PAGE),
        'filters' => $filters,
        'has_next' => $page < $total_pages,
        'has_prev' => $page > 1,
        'start_item' => $start + 1,
        'end_item' => min($start + ITEMS_PER_PAGE, $total)
    ];
}

function build_totalupload_keyboard(int $page, int $total_pages, string $session_id = '', array $filters = []): array {
    $kb = ['inline_keyboard' => []];
    
    $nav_row = [];
    
    if ($page > 1) {
        $nav_row[] = ['text' => '⏪', 'callback_data' => 'pag_first_' . $session_id];
        $nav_row[] = ['text' => '◀️', 'callback_data' => 'pag_prev_' . $page . '_' . $session_id];
    }
    
    $start_page = max(1, $page - 2);
    $end_page = min($total_pages, $start_page + 4);
    
    for ($i = $start_page; $i <= $end_page; $i++) {
        if ($i == $page) {
            $nav_row[] = ['text' => "【{$i}】", 'callback_data' => 'current'];
        } else {
            $nav_row[] = ['text' => "{$i}", 'callback_data' => 'pag_' . $i . '_' . $session_id];
        }
    }
    
    if ($page < $total_pages) {
        $nav_row[] = ['text' => '▶️', 'callback_data' => 'pag_next_' . $page . '_' . $session_id];
        $nav_row[] = ['text' => '⏩', 'callback_data' => 'pag_last_' . $total_pages . '_' . $session_id];
    }
    
    if (!empty($nav_row)) {
        $kb['inline_keyboard'][] = $nav_row;
    }
    
    $action_row = [];
    $action_row[] = ['text' => '📥 Send Page', 'callback_data' => 'send_' . $page . '_' . $session_id];
    $action_row[] = ['text' => '📊 Stats', 'callback_data' => 'stats_' . $session_id];
    $action_row[] = ['text' => '❌ Close', 'callback_data' => 'close_' . $session_id];
    
    $kb['inline_keyboard'][] = $action_row;
    
    return $kb;
}

function totalupload_controller($chat_id, $page = 1, $filters = [], $session_id = null) {
    $all = get_all_movies_list();
    if (empty($all)) {
        sendMessage($chat_id, "📭 No movies found!");
        return;
    }
    
    if (!$session_id) {
        $session_id = uniqid('sess_', true);
    }
    
    $pg = paginate_movies($all, (int)$page, $filters);
    
    $title = "🎬 <b>Movie Browser</b>\n\n";
    $title .= "📊 Total: <b>{$pg['total']}</b>\n";
    $title .= "• Page: <b>{$pg['page']}/{$pg['total_pages']}</b>\n\n";
    $title .= "📋 <b>Page {$page}:</b>\n\n";
    
    $i = $pg['start_item'];
    foreach ($pg['slice'] as $movie) {
        $movie_name = htmlspecialchars($movie['movie_name'] ?? 'Unknown');
        $quality = $movie['quality'] ?? 'Unknown';
        
        if (strlen($movie_name) > 40) {
            $movie_name = substr($movie_name, 0, 37) . '...';
        }
        
        $title .= "<b>{$i}.</b> {$movie_name} [{$quality}]\n";
        $i++;
    }
    
    $kb = build_totalupload_keyboard($pg['page'], $pg['total_pages'], $session_id, $filters);
    
    delete_pagination_message($chat_id, $session_id);
    
    $result = sendMessage($chat_id, $title, $kb, 'HTML');
    save_pagination_message($chat_id, $session_id, $result['result']['message_id']);
}

function save_pagination_message($chat_id, $session_id, $message_id) {
    global $user_pagination_sessions;
    
    if (!isset($user_pagination_sessions[$session_id])) {
        $user_pagination_sessions[$session_id] = [];
    }
    
    $user_pagination_sessions[$session_id]['last_message_id'] = $message_id;
    $user_pagination_sessions[$session_id]['chat_id'] = $chat_id;
}

function delete_pagination_message($chat_id, $session_id) {
    global $user_pagination_sessions;
    
    if (isset($user_pagination_sessions[$session_id]['last_message_id'])) {
        $message_id = $user_pagination_sessions[$session_id]['last_message_id'];
        deleteMessage($chat_id, $message_id);
    }
}

// ==============================
// 25. BACKUP SYSTEM
// ==============================
function auto_backup() {
    bot_log("Starting auto-backup...");
    
    $backup_files = [CSV_FILE, USERS_FILE, STATS_FILE, REQUEST_FILE, LOG_FILE];
    $backup_dir = BACKUP_DIR . date('Y-m-d_H-i-s');
    $backup_success = true;
    
    if (!file_exists($backup_dir)) {
        mkdir($backup_dir, 0777, true);
    }
    
    foreach ($backup_files as $file) {
        if (file_exists($file)) {
            $backup_path = $backup_dir . '/' . basename($file) . '.bak';
            if (!copy($file, $backup_path)) {
                bot_log("Failed to backup: $file", 'ERROR');
                $backup_success = false;
            }
        }
    }
    
    $summary = "Backup: " . date('Y-m-d H:i:s');
    file_put_contents($backup_dir . '/summary.txt', $summary);
    
    $old = glob(BACKUP_DIR . '*', GLOB_ONLYDIR);
    if (count($old) > 7) {
        usort($old, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        
        foreach (array_slice($old, 0, count($old) - 7) as $d) {
            $files = glob($d . '/*');
            foreach ($files as $ff) @unlink($ff);
            @rmdir($d);
        }
    }
    
    bot_log("Auto-backup completed");
    return $backup_success;
}

function manual_backup($chat_id) {
    if ($chat_id != ADMIN_ID) {
        sendMessage($chat_id, "❌ Access denied.");
        return;
    }
    
    $progress_msg = sendMessage($chat_id, "🔄 Starting backup...");
    
    try {
        $success = auto_backup();
        
        if ($success) {
            editMessage($chat_id, $progress_msg['result']['message_id'], "✅ Backup completed!");
        } else {
            editMessage($chat_id, $progress_msg['result']['message_id'], "⚠️ Backup completed with warnings.");
        }
    } catch (Exception $e) {
        editMessage($chat_id, $progress_msg['result']['message_id'], "❌ Backup failed!");
    }
}

function backup_status($chat_id) {
    if ($chat_id != ADMIN_ID) {
        sendMessage($chat_id, "❌ Access denied.");
        return;
    }
    
    $backup_dirs = glob(BACKUP_DIR . '*', GLOB_ONLYDIR);
    $latest_backup = null;
    $total_size = 0;
    
    if (!empty($backup_dirs)) {
        usort($backup_dirs, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        $latest_backup = $backup_dirs[0];
    }
    
    foreach ($backup_dirs as $dir) {
        $files = glob($dir . '/*');
        foreach ($files as $file) {
            $total_size += filesize($file);
        }
    }
    
    $total_size_mb = round($total_size / (1024 * 1024), 2);
    
    $status = "💾 Backup Status\n\n";
    $status .= "Total Backups: " . count($backup_dirs) . "\n";
    $status .= "Storage Used: " . $total_size_mb . " MB\n\n";
    
    if ($latest_backup) {
        $latest_time = date('Y-m-d H:i:s', filemtime($latest_backup));
        $status .= "Latest: $latest_time\n";
    }
    
    sendMessage($chat_id, $status);
}

// ==============================
// 26. CHANNEL MANAGEMENT
// ==============================
function show_channel_info($chat_id) {
    $message = "📢 <b>Our Channels</b>\n\n";
    
    $message .= "🍿 <b>Main:</b> " . MAIN_CHANNEL . "\n";
    $message .= "📺 <b>Serial:</b> " . SERIAL_CHANNEL . "\n";
    $message .= "🎭 <b>Theater:</b> " . THEATER_CHANNEL . "\n";
    $message .= "🔒 <b>Backup:</b> " . BACKUP_CHANNEL . "\n";
    $message .= "📥 <b>Request:</b> " . REQUEST_CHANNEL . "\n\n";
    
    $message .= "🔔 Join all channels for updates!";

    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '🍿 Main', 'url' => 'https://t.me/EntertainmentTadka786'],
                ['text' => '📺 Serial', 'url' => 'https://t.me/Entertainment_Tadka_Serial_786']
            ],
            [
                ['text' => '🎭 Theater', 'url' => 'https://t.me/threater_print_movies'],
                ['text' => '🔒 Backup', 'url' => 'https://t.me/ETBackup']
            ],
            [
                ['text' => '📥 Request', 'url' => 'https://t.me/EntertainmentTadka7860']
            ]
        ]
    ];
    
    sendMessage($chat_id, $message, $keyboard, 'HTML');
}

// ==============================
// 27. USER STATS & LEADERBOARD
// ==============================
function show_user_stats($chat_id, $user_id) {
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $user = $users_data['users'][$user_id] ?? null;
    
    if (!$user) {
        sendMessage($chat_id, "❌ User data not found!");
        return;
    }
    
    $message = "👤 Your Stats\n\n";
    $message .= "📅 Joined: " . ($user['joined'] ?? 'N/A') . "\n";
    $message .= "🕒 Last Active: " . ($user['last_active'] ?? 'N/A') . "\n\n";
    $message .= "📊 Activity:\n";
    $message .= "• 🔍 Searches: " . ($user['total_searches'] ?? 0) . "\n";
    $message .= "• 📥 Downloads: " . ($user['total_downloads'] ?? 0) . "\n";
    $message .= "• 📝 Requests: " . ($user['request_count'] ?? 0) . "\n";
    $message .= "• ⭐ Points: " . ($user['points'] ?? 0) . "\n";
    
    sendMessage($chat_id, $message);
}

function show_leaderboard($chat_id) {
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $users = $users_data['users'] ?? [];
    
    if (empty($users)) {
        sendMessage($chat_id, "📭 No users yet!");
        return;
    }
    
    uasort($users, function($a, $b) {
        return ($b['points'] ?? 0) - ($a['points'] ?? 0);
    });
    
    $message = "🏆 Leaderboard\n\n";
    $i = 1;
    
    foreach (array_slice($users, 0, 10) as $user_id => $user) {
        $points = $user['points'] ?? 0;
        $username = $user['username'] ? "@" . $user['username'] : "User" . substr($user_id, -4);
        $medal = $i == 1 ? "🥇" : ($i == 2 ? "🥈" : ($i == 3 ? "🥉" : "🔸"));
        
        $message .= "$medal $i. $username - ⭐ $points\n";
        $i++;
    }
    
    sendMessage($chat_id, $message);
}

function calculate_user_rank($points) {
    if ($points >= 1000) return "Elite";
    if ($points >= 500) return "Pro";
    if ($points >= 250) return "Advanced";
    if ($points >= 100) return "Intermediate";
    if ($points >= 50) return "Beginner";
    return "Newbie";
}

// ==============================
// 28. BROWSE COMMANDS
// ==============================
function show_latest_movies($chat_id, $limit = 10) {
    $all_movies = get_all_movies_list();
    $latest_movies = array_slice($all_movies, -$limit);
    $latest_movies = array_reverse($latest_movies);
    
    if (empty($latest_movies)) {
        sendMessage($chat_id, "📭 No movies found!");
        return;
    }
    
    $message = "🎬 Latest $limit Movies\n\n";
    $i = 1;
    
    foreach ($latest_movies as $movie) {
        $message .= "$i. <b>" . htmlspecialchars($movie['movie_name']) . "</b>\n";
        $message .= "   📊 " . ($movie['quality'] ?? 'Unknown') . "\n\n";
        $i++;
    }
    
    sendMessage($chat_id, $message, null, 'HTML');
}

function show_trending_movies($chat_id) {
    $all_movies = get_all_movies_list();
    $trending = array_slice($all_movies, -15);
    
    if (empty($trending)) {
        sendMessage($chat_id, "📭 No trending movies!");
        return;
    }
    
    $message = "🔥 Trending Movies\n\n";
    $i = 1;
    
    foreach (array_slice($trending, 0, 10) as $movie) {
        $message .= "$i. <b>" . htmlspecialchars($movie['movie_name']) . "</b>\n";
        $i++;
    }
    
    sendMessage($chat_id, $message, null, 'HTML');
}

// ==============================
// 29. CSV & DATA FUNCTIONS
// ==============================
function show_csv_data($chat_id, $show_all = false) {
    if (!file_exists(CSV_FILE)) {
        sendMessage($chat_id, "❌ CSV file not found.");
        return;
    }
    
    $handle = fopen(CSV_FILE, "r");
    if ($handle === FALSE) {
        sendMessage($chat_id, "❌ Error opening CSV file.");
        return;
    }
    
    $header = fgetcsv($handle);
    $movies = [];
    
    while (($row = fgetcsv($handle)) !== FALSE) {
        if (count($row) >= 3) {
            $movies[] = $row;
        }
    }
    fclose($handle);
    
    if (empty($movies)) {
        sendMessage($chat_id, "📊 CSV file is empty.");
        return;
    }
    
    $movies = array_reverse($movies);
    $limit = $show_all ? count($movies) : 10;
    $movies = array_slice($movies, 0, $limit);
    
    $message = "📊 Movie Database\n\n";
    $message .= "Total: " . count($movies) . "\n\n";
    
    $i = 1;
    foreach ($movies as $movie) {
        $movie_name = $movie[0] ?? 'N/A';
        $message_id = $movie[1] ?? 'N/A';
        
        if (strlen($movie_name) > 50) {
            $movie_name = substr($movie_name, 0, 47) . '...';
        }
        
        $message .= "$i. <code>" . htmlspecialchars($movie_name) . "</code>\n";
        $message .= "   📝 ID: $message_id\n\n";
        
        $i++;
        
        if (strlen($message) > 3000) {
            sendMessage($chat_id, $message, null, 'HTML');
            $message = "Continuing...\n\n";
        }
    }
    
    sendMessage($chat_id, $message, null, 'HTML');
}

// ==============================
// 30. MOVIE APPEND SYSTEM
// ==============================
function append_movie($movie_name, $message_id, $channel_id) {
    global $movie_messages, $movie_cache, $waiting_users;
    
    if (empty(trim($movie_name))) return;
    
    $entry = [$movie_name, $message_id, $channel_id];
    
    $handle = fopen(CSV_FILE, "a");
    fputcsv($handle, $entry);
    fclose($handle);

    $channel_type = get_channel_type_by_id($channel_id);
    $quality = MediaInfo::detect_quality($movie_name);
    
    $language = 'Hindi';
    if (stripos($movie_name, 'english') !== false) $language = 'English';
    elseif (stripos($movie_name, 'tamil') !== false) $language = 'Tamil';
    elseif (stripos($movie_name, 'telugu') !== false) $language = 'Telugu';
    
    $size = 'Unknown';
    if (preg_match('/(\d+(?:\.\d+)?)\s*(GB|MB)/i', $movie_name, $matches)) {
        $size = $matches[1] . ' ' . $matches[2];
    }
    
    $item = [
        'movie_name' => $movie_name,
        'message_id' => intval($message_id),
        'message_id_raw' => $message_id,
        'channel_id' => $channel_id,
        'channel_type' => $channel_type,
        'quality' => $quality,
        'language' => $language,
        'size' => $size,
        'date' => date('d-m-Y'),
        'source_channel' => $channel_id
    ];
    
    $movie_key = strtolower(trim($movie_name));
    if (!isset($movie_messages[$movie_key])) {
        $movie_messages[$movie_key] = [];
    }
    $movie_messages[$movie_key][] = $item;
    $movie_cache = [];

    if (!empty($waiting_users[$movie_key])) {
        $notification_msg = "🔔 <b>Movie Added!</b>\n\n";
        $notification_msg .= "🎬 <b>$movie_name</b> has been added!";
        
        sendMessage(MAIN_CHANNEL_ID, $notification_msg, null, 'HTML');
        
        foreach ($waiting_users[$movie_key] as $user_data) {
            list($user_chat_id, $user_id) = $user_data;
            sendMessage($user_chat_id, "🎉 Your requested movie <b>$movie_name</b> has been added!", null, 'HTML');
        }
        unset($waiting_users[$movie_key]);
    }

    update_stats('total_movies', 1);
    bot_log("Movie appended: $movie_name (ID: $message_id)");
}

// ==============================
// 31. SCAN SYSTEM
// ==============================
function scanOldPosts($admin_chat_id = null) {
    global $CHANNELS;
    
    $CHANNELS = [
        MAIN_CHANNEL_ID,
        SERIAL_CHANNEL_ID,
        THEATER_CHANNEL_ID,
        BACKUP_CHANNEL_ID,
        PRIVATE_CHANNEL_1_ID,
        PRIVATE_CHANNEL_2_ID
    ];
    
    bot_log("Starting old post scanner...");
    
    if ($admin_chat_id) {
        sendMessage($admin_chat_id, "🔄 Starting scan...");
    }
    
    $all_movies = loadMovies();
    $existing_count = count($all_movies);
    $new_movies = 0;
    
    foreach ($CHANNELS as $channel_id) {
        $offset = 0;
        
        while (true) {
            $result = apiRequest('getChatHistory', [
                'chat_id' => $channel_id,
                'limit' => 100,
                'offset' => $offset
            ]);
            
            $data = json_decode($result, true);
            
            if (!isset($data['ok']) || !$data['ok'] || !isset($data['result'])) {
                break;
            }
            
            $messages = $data['result'];
            
            if (empty($messages)) {
                break;
            }
            
            foreach ($messages as $msg) {
                if (isset($msg['video']) || isset($msg['document'])) {
                    $title = isset($msg['caption']) ? trim($msg['caption']) : 'Unknown';
                    
                    $exists = false;
                    foreach ($all_movies as $existing) {
                        if ($existing['message_id'] == $msg['message_id'] && 
                            $existing['channel_id'] == $channel_id) {
                            $exists = true;
                            break;
                        }
                    }
                    
                    if (!$exists) {
                        $all_movies[] = [
                            'movie_name' => $title,
                            'message_id' => $msg['message_id'],
                            'channel_id' => $channel_id
                        ];
                        $new_movies++;
                    }
                }
            }
            
            $offset += 100;
            usleep(500000);
        }
    }
    
    if ($new_movies > 0) {
        saveMovies($all_movies);
    }
    
    if ($admin_chat_id) {
        sendMessage($admin_chat_id, "✅ Scan complete!\nNew movies: $new_movies");
    }
    
    return $new_movies;
}

function autoIndex($update) {
    if (!isset($update['channel_post'])) return;
    
    $msg = $update['channel_post'];
    
    if (isset($msg['video']) || isset($msg['document'])) {
        $title = isset($msg['caption']) ? trim($msg['caption']) : 'Unknown';
        append_movie($title, $msg['message_id'], $msg['chat']['id']);
    }
}

// ==============================
// 32. BULK DOWNLOAD FUNCTIONS
// ==============================
function batch_download_with_progress($chat_id, $movies, $page_num) {
    $total = count($movies);
    if ($total === 0) return;
    
    $progress_msg = sendMessage($chat_id, "📦 Sending $total movies...\n\n0%");
    $progress_id = $progress_msg['result']['message_id'];
    
    $success = 0;
    $failed = 0;
    
    for ($i = 0; $i < $total; $i++) {
        $movie = $movies[$i];
        
        if ($i % 2 == 0) {
            $progress = round(($i / $total) * 100);
            editMessage($chat_id, $progress_id, "📦 Progress: $progress%\n$i/$total");
        }
        
        $result = deliver_item_to_chat($chat_id, $movie);
        if ($result) $success++; else $failed++;
        
        usleep(500000);
    }
    
    editMessage($chat_id, $progress_id, "✅ Complete!\nSuccess: $success\nFailed: $failed");
}

// ==============================
// 33. CAPTION GENERATOR
// ==============================
function generate_channel_caption($movie_name) {
    $caption = "🎬 <b>" . htmlspecialchars($movie_name) . "</b>\n\n";
    $caption .= "🔥 <b>Channels:</b>\n";
    $caption .= "🍿 <b>Main:</b> <code>@EntertainmentTadka786</code>\n";
    $caption .= "📥 <b>Request:</b> <code>@EntertainmentTadka7860</code>\n";
    $caption .= "🎭 <b>Theater:</b> <code>@threater_print_movies</code>\n";
    $caption .= "📂 <b>Backup:</b> <code>@ETBackup</code>\n";
    $caption .= "📺 <b>Serial:</b> <code>@Entertainment_Tadka_Serial_786</code>";
    
    return $caption;
}

// ==============================
// 34. BROADCAST & UTILITY FUNCTIONS
// ==============================
function send_broadcast($chat_id, $message) {
    if ($chat_id != ADMIN_ID) {
        sendMessage($chat_id, "❌ Access denied.");
        return;
    }
    
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $total_users = count($users_data['users'] ?? []);
    $success_count = 0;
    
    $progress_msg = sendMessage($chat_id, "📢 Broadcasting to $total_users users...");
    $progress_msg_id = $progress_msg['result']['message_id'];
    
    $i = 0;
    foreach ($users_data['users'] as $user_id => $user) {
        try {
            sendMessage($user_id, "📢 <b>Announcement:</b>\n\n$message", null, 'HTML');
            $success_count++;
            
            if ($i % 10 === 0) {
                $progress = round(($i / $total_users) * 100);
                editMessage($chat_id, $progress_msg_id, "📢 Progress: $progress%");
            }
            
            usleep(100000);
            $i++;
        } catch (Exception $e) {}
    }
    
    editMessage($chat_id, $progress_msg_id, "✅ Broadcast sent to $success_count/$total_users users");
}

function send_alert_to_all($chat_id, $alert_message) {
    if ($chat_id != ADMIN_ID) {
        sendMessage($chat_id, "❌ Access denied.");
        return;
    }
    
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $success_count = 0;
    
    foreach ($users_data['users'] as $user_id => $user) {
        try {
            sendMessage($user_id, "🚨 <b>Alert:</b>\n\n$alert_message", null, 'HTML');
            $success_count++;
            usleep(50000);
        } catch (Exception $e) {}
    }
    
    sendMessage($chat_id, "✅ Alert sent to $success_count users!");
}

function toggle_maintenance_mode($chat_id, $mode) {
    global $MAINTENANCE_MODE;
    
    if ($chat_id != ADMIN_ID) {
        sendMessage($chat_id, "❌ Access denied.");
        return;
    }
    
    if ($mode == 'on') {
        $MAINTENANCE_MODE = true;
        sendMessage($chat_id, "🔧 Maintenance mode ENABLED");
    } elseif ($mode == 'off') {
        $MAINTENANCE_MODE = false;
        sendMessage($chat_id, "✅ Maintenance mode DISABLED");
    }
}

function perform_cleanup($chat_id) {
    if ($chat_id != ADMIN_ID) {
        sendMessage($chat_id, "❌ Access denied.");
        return;
    }
    
    $old = glob(BACKUP_DIR . '*', GLOB_ONLYDIR);
    if (count($old) > 7) {
        usort($old, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        
        foreach (array_slice($old, 0, count($old) - 7) as $d) {
            $files = glob($d . '/*');
            foreach ($files as $ff) @unlink($ff);
            @rmdir($d);
        }
    }
    
    global $movie_cache;
    $movie_cache = [];
    
    sendMessage($chat_id, "🧹 Cleanup completed!");
}

function submit_bug_report($chat_id, $user_id, $bug_report) {
    $report_id = uniqid();
    
    $admin_message = "🐛 Bug Report\n\nID: $report_id\nUser: $user_id\n\n$bug_report";
    sendMessage(ADMIN_ID, $admin_message);
    
    sendMessage($chat_id, "✅ Bug report submitted!\nID: $report_id");
}

function submit_feedback($chat_id, $user_id, $feedback) {
    $feedback_id = uniqid();
    
    $admin_message = "💡 Feedback\n\nID: $feedback_id\nUser: $user_id\n\n$feedback";
    sendMessage(ADMIN_ID, $admin_message);
    
    sendMessage($chat_id, "✅ Feedback submitted!\nID: $feedback_id");
}

function show_bot_info($chat_id) {
    $stats = get_stats();
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    
    $message = "🤖 Bot Info\n\n";
    $message .= "Version: 2.0.0\n";
    $message .= "Movies: " . ($stats['total_movies'] ?? 0) . "\n";
    $message .= "Users: " . count($users_data['users'] ?? []) . "\n";
    $message .= "Searches: " . ($stats['total_searches'] ?? 0) . "\n";
    $message .= "Downloads: " . ($stats['total_downloads'] ?? 0) . "\n\n";
    $message .= "Main: @EntertainmentTadka786";
    
    sendMessage($chat_id, $message);
}

function show_support_info($chat_id) {
    $message = "🆘 Support\n\n";
    $message .= "Channel: " . REQUEST_CHANNEL . "\n";
    $message .= "Admin: @EntertainmentTadka0786\n\n";
    $message .= "Use /report for bugs\n";
    $message .= "Use /feedback for suggestions";
    
    sendMessage($chat_id, $message);
}

function show_version_info($chat_id) {
    $message = "📱 Bot Version\n\n";
    $message .= "Current: v2.0.0\n";
    $message .= "Release: " . date('Y-m-d') . "\n\n";
    $message .= "Features:\n";
    $message .= "• Smart Search\n";
    $message .= "• User Settings\n";
    $message .= "• Filter System\n";
    $message .= "• Series Support\n";
    $message .= "• Auto-Delete\n";
    $message .= "• Folder Navigation";
    
    sendMessage($chat_id, $message);
}

// ==============================
// 35. COMMAND HANDLER
// ==============================
function handle_command($chat_id, $user_id, $command, $params = []) {
    switch ($command) {
        // Core Commands
        case '/start':
            $welcome = "🎬 Welcome to Entertainment Tadka!\n\n";
            $welcome .= "Simply type any movie name to search.\n\n";
            $welcome .= "📢 Join: @EntertainmentTadka786";

            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '🔍 Search', 'switch_inline_query_current_chat' => ''],
                        ['text' => '⚙️ Settings', 'callback_data' => 'settings_back']
                    ],
                    [
                        ['text' => '📺 Series', 'callback_data' => 'bulk_series'],
                        ['text' => '📁 Library', 'callback_data' => 'folder_root']
                    ],
                    [
                        ['text' => '📢 Channel', 'url' => 'https://t.me/EntertainmentTadka786'],
                        ['text' => '❓ Help', 'callback_data' => 'help_command']
                    ]
                ]
            ];
            
            if ($user_id == ADMIN_ID) {
                $keyboard['inline_keyboard'][] = [
                    ['text' => '🔐 Admin', 'callback_data' => 'admin_main']
                ];
            }
            
            sendMessage($chat_id, $welcome, $keyboard, 'HTML');
            update_user_activity($user_id, 'daily_login');
            break;

        case '/help':
            $help = "🤖 <b>Commands</b>\n\n";
            $help .= "/start - Welcome\n";
            $help .= "/help - This menu\n";
            $help .= "/settings - Preferences\n";
            $help .= "/filter - Filter movies\n";
            $help .= "/series - Series list\n";
            $help .= "/delete - Auto-delete\n";
            $help .= "/deletestatus - Check delete\n";
            $help .= "/library - Folder view\n";
            $help .= "/search - Search movies\n";
            $help .= "/totalupload - All movies\n";
            $help .= "/request - Request movie\n";
            $help .= "/myrequests - Your requests\n";
            $help .= "/mystats - Your stats\n";
            $help .= "/leaderboard - Top users\n";
            $help .= "/channel - Our channels\n\n";
            
            if ($user_id == ADMIN_ID) {
                $help .= "🔐 <b>Admin</b>\n";
                $help .= "/admin - Admin panel\n";
                $help .= "/stats - Bot stats\n";
                $help .= "/users - User stats\n";
                $help .= "/backup - Full backup\n";
                $help .= "/backupstatus - Backup status\n";
                $help .= "/broadcast - Broadcast\n";
                $help .= "/panicon - Panic mode ON\n";
                $help .= "/panicoff - Panic mode OFF\n";
            }
            
            sendMessage($chat_id, $help, null, 'HTML');
            break;

        case '/settings':
            $settings = new UserSettings($user_id);
            $settings->show_main_panel($chat_id);
            break;

        case '/filter':
            $filter = new MovieFilter($user_id);
            $current_filters = $filter->load_filter_session();
            $filter->show_filter_panel($chat_id, $current_filters);
            break;

        case '/series':
            $bulk = new BulkManager();
            $bulk->show_series_list($chat_id);
            break;

        case '/delete':
            sendMessage($chat_id, "⏰ Use this command while replying to a message to set auto-delete.");
            break;

        case '/deletestatus':
            $delete = new AutoDeleteManager();
            $delete->show_status($chat_id);
            break;

        case '/library':
            $folder = new FolderNavigator();
            $folder->show_root($chat_id);
            break;

        case '/search':
            $movie_name = implode(' ', $params);
            if (empty($movie_name)) {
                sendMessage($chat_id, "❌ Usage: /search movie name");
                return;
            }
            advanced_search($chat_id, $movie_name, $user_id);
            break;

        case '/totalupload':
            $page = isset($params[0]) ? intval($params[0]) : 1;
            totalupload_controller($chat_id, $page);
            break;

        case '/request':
            $movie_name = implode(' ', $params);
            if (empty($movie_name)) {
                sendMessage($chat_id, "❌ Usage: /request movie name");
                return;
            }
            $lang = detect_language($movie_name);
            if (add_movie_request($user_id, $movie_name, $lang)) {
                sendMessage($chat_id, "✅ Request sent!");
                update_user_activity($user_id, 'movie_request');
            } else {
                sendMessage($chat_id, "❌ Daily limit reached!");
            }
            break;

        case '/myrequests':
            show_user_requests($chat_id, $user_id);
            break;

        case '/mystats':
            show_user_stats($chat_id, $user_id);
            break;

        case '/leaderboard':
            show_leaderboard($chat_id);
            break;

        case '/channel':
            show_channel_info($chat_id);
            break;

        // Admin Commands
        case '/admin':
        case '/panel':
            if ($user_id != ADMIN_ID) {
                sendMessage($chat_id, "❌ Access denied.");
                return;
            }
            $admin = new AdminPanel($user_id);
            $admin->show_main_panel($chat_id);
            break;

        case '/stats':
            if ($user_id != ADMIN_ID) {
                sendMessage($chat_id, "❌ Access denied.");
                return;
            }
            admin_stats($chat_id);
            break;

        case '/users':
            if ($user_id != ADMIN_ID) {
                sendMessage($chat_id, "❌ Access denied.");
                return;
            }
            $users_data = json_decode(file_get_contents(USERS_FILE), true);
            $total_users = count($users_data['users'] ?? []);
            sendMessage($chat_id, "👥 Total Users: $total_users");
            break;

        case '/backup':
            if ($user_id != ADMIN_ID) {
                sendMessage($chat_id, "❌ Access denied.");
                return;
            }
            manual_backup($chat_id);
            break;

        case '/backupstatus':
            if ($user_id != ADMIN_ID) {
                sendMessage($chat_id, "❌ Access denied.");
                return;
            }
            backup_status($chat_id);
            break;

        case '/broadcast':
            if ($user_id != ADMIN_ID) {
                sendMessage($chat_id, "❌ Access denied.");
                return;
            }
            $message = implode(' ', $params);
            if (empty($message)) {
                sendMessage($chat_id, "❌ Usage: /broadcast message");
                return;
            }
            send_broadcast($chat_id, $message);
            break;

        case '/panicon':
            if ($user_id != ADMIN_ID) {
                sendMessage($chat_id, "❌ Access denied.");
                return;
            }
            global $MAINTENANCE_MODE;
            $MAINTENANCE_MODE = true;
            sendMessage($chat_id, "🚨 Panic mode ON");
            break;

        case '/panicoff':
            if ($user_id != ADMIN_ID) {
                sendMessage($chat_id, "❌ Access denied.");
                return;
            }
            global $MAINTENANCE_MODE;
            $MAINTENANCE_MODE = false;
            sendMessage($chat_id, "✅ Panic mode OFF");
            break;

        default:
            if (!empty($command) && $command[0] != '/') {
                advanced_search($chat_id, $command, $user_id);
            } else {
                sendMessage($chat_id, "❌ Unknown command. Use /help");
            }
    }
}

// ==============================
// 36. ADMIN PANEL CLASS
// ==============================
class AdminPanel {
    private $admin_id;
    
    function __construct($admin_id) {
        $this->admin_id = $admin_id;
    }
    
    public function show_main_panel($chat_id) {
        $stats = get_stats();
        $users_data = json_decode(file_get_contents(USERS_FILE), true);
        
        $panel = "🔐 <b>Admin Panel</b>\n\n";
        $panel .= "📊 Movies: " . ($stats['total_movies'] ?? 0) . "\n";
        $panel .= "👥 Users: " . count($users_data['users'] ?? []) . "\n\n";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🎬 Movies', 'callback_data' => 'admin_movies'],
                    ['text' => '👥 Users', 'callback_data' => 'admin_users']
                ],
                [
                    ['text' => '📊 Stats', 'callback_data' => 'admin_stats'],
                    ['text' => '💾 Backup', 'callback_data' => 'admin_backup']
                ],
                [
                    ['text' => '📝 Requests', 'callback_data' => 'admin_requests'],
                    ['text' => '📢 Broadcast', 'callback_data' => 'admin_broadcast']
                ],
                [
                    ['text' => '❌ Close', 'callback_data' => 'admin_close']
                ]
            ]
        ];
        
        sendMessage($chat_id, $panel, $keyboard, 'HTML');
    }
    
    public function show_movie_management($chat_id) {
        $stats = get_stats();
        
        $panel = "🎬 <b>Movie Management</b>\n\n";
        $panel .= "Total Movies: " . ($stats['total_movies'] ?? 0) . "\n\n";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '📋 View CSV', 'callback_data' => 'admin_viewcsv'],
                    ['text' => '📊 Stats', 'callback_data' => 'admin_moviestats']
                ],
                [
                    ['text' => '🔄 Scan Old', 'callback_data' => 'admin_scan_old'],
                    ['text' => '📡 Progressive', 'callback_data' => 'admin_scan_progressive']
                ],
                [
                    ['text' => '⬅️ Back', 'callback_data' => 'admin_back'],
                    ['text' => '❌ Close', 'callback_data' => 'admin_close']
                ]
            ]
        ];
        
        sendMessage($chat_id, $panel, $keyboard, 'HTML');
    }
    
    public function show_user_management($chat_id) {
        $users_data = json_decode(file_get_contents(USERS_FILE), true);
        $total = count($users_data['users'] ?? []);
        
        $panel = "👥 <b>User Management</b>\n\n";
        $panel .= "Total Users: $total\n\n";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '📊 User Stats', 'callback_data' => 'admin_userstats'],
                    ['text' => '🏆 Leaderboard', 'callback_data' => 'admin_leaderboard']
                ],
                [
                    ['text' => '⬅️ Back', 'callback_data' => 'admin_back'],
                    ['text' => '❌ Close', 'callback_data' => 'admin_close']
                ]
            ]
        ];
        
        sendMessage($chat_id, $panel, $keyboard, 'HTML');
    }
    
    public function show_backup_panel($chat_id) {
        $backup_dirs = glob(BACKUP_DIR . '*', GLOB_ONLYDIR);
        $latest = !empty($backup_dirs) ? max($backup_dirs) : null;
        
        $panel = "💾 <b>Backup System</b>\n\n";
        
        if ($latest) {
            $backup_time = date('d-m-Y H:i', filemtime($latest));
            $panel .= "Latest: $backup_time\n";
        }
        
        $panel .= "Total Backups: " . count($backup_dirs) . "\n\n";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🔄 Full Backup', 'callback_data' => 'admin_backup_full'],
                    ['text' => '📋 Status', 'callback_data' => 'admin_backup_status']
                ],
                [
                    ['text' => '⬅️ Back', 'callback_data' => 'admin_back'],
                    ['text' => '❌ Close', 'callback_data' => 'admin_close']
                ]
            ]
        ];
        
        sendMessage($chat_id, $panel, $keyboard, 'HTML');
    }
    
    public function show_requests_panel($chat_id) {
        $requests_data = json_decode(file_get_contents(REQUEST_FILE), true);
        $requests = $requests_data['requests'] ?? [];
        $pending = array_filter($requests, fn($r) => $r['status'] == 'pending');
        
        $panel = "📝 <b>Movie Requests</b>\n\n";
        $panel .= "Total: " . count($requests) . "\n";
        $panel .= "Pending: " . count($pending) . "\n\n";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '📋 View All', 'callback_data' => 'admin_requests_view'],
                    ['text' => '📊 Stats', 'callback_data' => 'admin_requests_stats']
                ],
                [
                    ['text' => '⬅️ Back', 'callback_data' => 'admin_back'],
                    ['text' => '❌ Close', 'callback_data' => 'admin_close']
                ]
            ]
        ];
        
        sendMessage($chat_id, $panel, $keyboard, 'HTML');
    }
    
    public function show_broadcast_panel($chat_id) {
        $users_data = json_decode(file_get_contents(USERS_FILE), true);
        $total = count($users_data['users'] ?? []);
        
        $panel = "📢 <b>Broadcast</b>\n\n";
        $panel .= "Total Users: $total\n\n";
        $panel .= "Use: /broadcast your message";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '⬅️ Back', 'callback_data' => 'admin_back'],
                    ['text' => '❌ Close', 'callback_data' => 'admin_close']
                ]
            ]
        ];
        
        sendMessage($chat_id, $panel, $keyboard, 'HTML');
    }
}

// ==============================
// 37. CALLBACK HANDLER
// ==============================
function handle_callback($chat_id, $user_id, $message_id, $data, $query_id) {
    
    // Movie Selection
    $movie_lower = strtolower($data);
    global $movie_messages;
    if (isset($movie_messages[$movie_lower])) {
        $entries = $movie_messages[$movie_lower];
        $cnt = 0;
        
        foreach ($entries as $entry) {
            deliver_item_to_chat($chat_id, $entry);
            usleep(200000);
            $cnt++;
        }
        
        sendMessage($chat_id, "✅ Sent $cnt items!");
        answerCallbackQuery($query_id, "Sent!");
        return;
    }
    
    // Play Movie
    if (strpos($data, 'play_') === 0) {
        $msg_id_channel = base64_decode(str_replace('play_', '', $data));
        list($msg_id, $channel_id) = explode('||', $msg_id_channel);
        
        $movies = get_all_movies_list();
        foreach ($movies as $movie) {
            if ($movie['message_id'] == $msg_id && $movie['channel_id'] == $channel_id) {
                deliver_item_to_chat($chat_id, $movie);
                answerCallbackQuery($query_id, "Sending...");
                return;
            }
        }
    }
    
    // Settings Panel
    if (strpos($data, 'settings_') === 0) {
        $settings = new UserSettings($user_id);
        
        if ($data == 'settings_back') {
            $settings->show_main_panel($chat_id);
            answerCallbackQuery($query_id, "Back");
        }
        elseif ($data == 'settings_close') {
            deleteMessage($chat_id, $message_id);
            answerCallbackQuery($query_id, "Closed");
        }
        elseif ($data == 'settings_language') {
            $settings->show_language_settings($chat_id);
            answerCallbackQuery($query_id, "Language");
        }
        elseif ($data == 'settings_quality') {
            $settings->show_quality_settings($chat_id);
            answerCallbackQuery($query_id, "Quality");
        }
        elseif ($data == 'settings_layout') {
            $settings->show_layout_settings($chat_id);
            answerCallbackQuery($query_id, "Layout");
        }
        elseif ($data == 'settings_priority') {
            $settings->show_priority_settings($chat_id);
            answerCallbackQuery($query_id, "Priority");
        }
        elseif ($data == 'settings_perpage') {
            $settings->show_perpage_settings($chat_id);
            answerCallbackQuery($query_id, "Per Page");
        }
        elseif ($data == 'settings_theme') {
            $settings->show_theme_settings($chat_id);
            answerCallbackQuery($query_id, "Theme");
        }
        elseif ($data == 'settings_spoiler') {
            $settings->toggle_setting($chat_id, 'spoiler_mode');
            answerCallbackQuery($query_id, "Toggled");
        }
        elseif ($data == 'settings_topsearch') {
            $settings->toggle_setting($chat_id, 'top_search');
            answerCallbackQuery($query_id, "Toggled");
        }
        elseif ($data == 'settings_autoscan') {
            $settings->toggle_setting($chat_id, 'auto_scan');
            answerCallbackQuery($query_id, "Toggled");
        }
        elseif ($data == 'settings_notify') {
            $settings->toggle_setting($chat_id, 'notifications');
            answerCallbackQuery($query_id, "Toggled");
        }
        elseif ($data == 'settings_reset') {
            $settings->reset_to_default();
            sendMessage($chat_id, "🔄 Reset complete!");
            $settings->show_main_panel($chat_id);
            answerCallbackQuery($query_id, "Reset");
        }
        elseif (strpos($data, 'settings_set_language_') === 0) {
            $lang = str_replace('settings_set_language_', '', $data);
            $settings->set('language', $lang);
            sendMessage($chat_id, "✅ Language: " . ucfirst($lang));
            $settings->show_main_panel($chat_id);
            answerCallbackQuery($query_id, "Updated");
        }
        elseif (strpos($data, 'settings_set_quality_') === 0) {
            $quality = str_replace('settings_set_quality_', '', $data);
            $settings->set('default_quality', $quality);
            sendMessage($chat_id, "✅ Quality: $quality");
            $settings->show_main_panel($chat_id);
            answerCallbackQuery($query_id, "Updated");
        }
        elseif (strpos($data, 'settings_set_layout_') === 0) {
            $layout = str_replace('settings_set_layout_', '', $data);
            $settings->set('result_layout', $layout);
            sendMessage($chat_id, "✅ Layout updated");
            $settings->show_main_panel($chat_id);
            answerCallbackQuery($query_id, "Updated");
        }
        elseif (strpos($data, 'settings_set_priority_') === 0) {
            $priority = str_replace('settings_set_priority_', '', $data);
            $settings->set('priority_mode', $priority);
            sendMessage($chat_id, "✅ Priority updated");
            $settings->show_main_panel($chat_id);
            answerCallbackQuery($query_id, "Updated");
        }
        elseif (strpos($data, 'settings_set_perpage_') === 0) {
            $perpage = intval(str_replace('settings_set_perpage_', '', $data));
            $settings->set('results_per_page', $perpage);
            sendMessage($chat_id, "✅ Per page: $perpage");
            $settings->show_main_panel($chat_id);
            answerCallbackQuery($query_id, "Updated");
        }
        elseif (strpos($data, 'settings_set_theme_') === 0) {
            $theme = str_replace('settings_set_theme_', '', $data);
            $settings->set('theme', $theme);
            sendMessage($chat_id, "✅ Theme: " . ucfirst($theme));
            $settings->show_main_panel($chat_id);
            answerCallbackQuery($query_id, "Updated");
        }
        return;
    }
    
    // Filter Panel
    if (strpos($data, 'filter_') === 0) {
        $filter = new MovieFilter($user_id);
        $current_filters = $filter->load_filter_session();
        
        if ($data == 'filter_back') {
            $filter->show_filter_panel($chat_id, $current_filters);
            answerCallbackQuery($query_id, "Back");
        }
        elseif ($data == 'filter_close') {
            deleteMessage($chat_id, $message_id);
            answerCallbackQuery($query_id, "Closed");
        }
        elseif ($data == 'filter_clear_all') {
            $filter->save_filter_session([]);
            sendMessage($chat_id, "🧹 Filters cleared!");
            $filter->show_filter_panel($chat_id, []);
            answerCallbackQuery($query_id, "Cleared");
        }
        elseif ($data == 'filter_menu_language') {
            $filter->show_language_filter($chat_id, $current_filters['language'] ?? null);
            answerCallbackQuery($query_id, "Language");
        }
        elseif ($data == 'filter_menu_quality') {
            $filter->show_quality_filter($chat_id, $current_filters['quality'] ?? null);
            answerCallbackQuery($query_id, "Quality");
        }
        elseif ($data == 'filter_menu_season') {
            $filter->show_season_filter($chat_id, $current_filters['season'] ?? null);
            answerCallbackQuery($query_id, "Season");
        }
        elseif ($data == 'filter_menu_dual') {
            $filter->show_dual_audio_filter($chat_id, $current_filters['dual_audio'] ?? null);
            answerCallbackQuery($query_id, "Dual Audio");
        }
        elseif ($data == 'filter_menu_hdr') {
            $filter->show_hdr_filter($chat_id, $current_filters['hdr'] ?? null);
            answerCallbackQuery($query_id, "HDR");
        }
        elseif ($data == 'filter_menu_subs') {
            $filter->show_subtitle_filter($chat_id, $current_filters['subtitles'] ?? null);
            answerCallbackQuery($query_id, "Subtitles");
        }
        elseif (strpos($data, 'filter_set_language_') === 0) {
            $lang = str_replace('filter_set_language_', '', $data);
            if ($lang == 'any') {
                unset($current_filters['language']);
            } else {
                $current_filters['language'] = $lang;
            }
            $filter->save_filter_session($current_filters);
            sendMessage($chat_id, "✅ Language filter set");
            $filter->show_filter_panel($chat_id, $current_filters);
            answerCallbackQuery($query_id, "Set");
        }
        elseif (strpos($data, 'filter_set_quality_') === 0) {
            $quality = str_replace('filter_set_quality_', '', $data);
            if ($quality == 'any') {
                unset($current_filters['quality']);
            } else {
                $current_filters['quality'] = $quality;
            }
            $filter->save_filter_session($current_filters);
            sendMessage($chat_id, "✅ Quality filter set");
            $filter->show_filter_panel($chat_id, $current_filters);
            answerCallbackQuery($query_id, "Set");
        }
        elseif (strpos($data, 'filter_set_season_') === 0) {
            $season = str_replace('filter_set_season_', '', $data);
            if ($season == 'all') {
                unset($current_filters['season']);
            } else {
                $current_filters['season'] = $season;
            }
            $filter->save_filter_session($current_filters);
            sendMessage($chat_id, "✅ Season filter set");
            $filter->show_filter_panel($chat_id, $current_filters);
            answerCallbackQuery($query_id, "Set");
        }
        elseif (strpos($data, 'filter_set_dual_') === 0) {
            $dual = str_replace('filter_set_dual_', '', $data);
            if ($dual == 'any') {
                unset($current_filters['dual_audio']);
            } else {
                $current_filters['dual_audio'] = $dual;
            }
            $filter->save_filter_session($current_filters);
            sendMessage($chat_id, "✅ Dual audio filter set");
            $filter->show_filter_panel($chat_id, $current_filters);
            answerCallbackQuery($query_id, "Set");
        }
        elseif (strpos($data, 'filter_set_hdr_') === 0) {
            $hdr = str_replace('filter_set_hdr_', '', $data);
            if ($hdr == 'any') {
                unset($current_filters['hdr']);
            } else {
                $current_filters['hdr'] = $hdr;
            }
            $filter->save_filter_session($current_filters);
            sendMessage($chat_id, "✅ HDR filter set");
            $filter->show_filter_panel($chat_id, $current_filters);
            answerCallbackQuery($query_id, "Set");
        }
        elseif (strpos($data, 'filter_set_subs_') === 0) {
            $subs = str_replace('filter_set_subs_', '', $data);
            if ($subs == 'any') {
                unset($current_filters['subtitles']);
            } else {
                $current_filters['subtitles'] = $subs;
            }
            $filter->save_filter_session($current_filters);
            sendMessage($chat_id, "✅ Subtitle filter set");
            $filter->show_filter_panel($chat_id, $current_filters);
            answerCallbackQuery($query_id, "Set");
        }
        return;
    }
    
    // Bulk Manager
    if (strpos($data, 'bulk_') === 0) {
        $bulk = new BulkManager();
        
        if ($data == 'bulk_series') {
            $bulk->show_series_list($chat_id);
            answerCallbackQuery($query_id, "Series");
        }
        elseif (strpos($data, 'bulk_series_') === 0) {
            $series = str_replace('bulk_series_', '', $data);
            $bulk->show_seasons($chat_id, $series);
            answerCallbackQuery($query_id, "Seasons");
        }
        elseif (strpos($data, 'bulk_season_') === 0) {
            $season_data = str_replace('bulk_season_', '', $data);
            $bulk->show_episodes($chat_id, $season_data);
            answerCallbackQuery($query_id, "Episodes");
        }
        elseif (strpos($data, 'bulk_download_season_') === 0) {
            $season_data = str_replace('bulk_download_season_', '', $data);
            $bulk->download_season($chat_id, $season_data);
            answerCallbackQuery($query_id, "Downloading");
        }
        elseif ($data == 'bulk_back') {
            $bulk->show_series_list($chat_id);
            answerCallbackQuery($query_id, "Back");
        }
        elseif ($data == 'bulk_close') {
            deleteMessage($chat_id, $message_id);
            answerCallbackQuery($query_id, "Closed");
        }
        return;
    }
    
    // Folder Navigation
    if (strpos($data, 'folder_') === 0) {
        $folder = new FolderNavigator();
        
        if ($data == 'folder_root' || $data == 'folder_back') {
            $folder->show_root($chat_id);
            answerCallbackQuery($query_id, "Root");
        }
        elseif ($data == 'folder_close') {
            deleteMessage($chat_id, $message_id);
            answerCallbackQuery($query_id, "Closed");
        }
        elseif (strpos($data, 'folder_open_') === 0) {
            $folder_name = str_replace('folder_open_', '', $data);
            $folder->open_folder($chat_id, $folder_name);
            answerCallbackQuery($query_id, "Opening");
        }
        elseif (strpos($data, 'folder_series_') === 0) {
            $series = str_replace('folder_series_', '', $data);
            $folder->show_seasons($chat_id, $series);
            answerCallbackQuery($query_id, "Seasons");
        }
        elseif (strpos($data, 'folder_season_') === 0) {
            $season_data = str_replace('folder_season_', '', $data);
            $folder->show_episodes($chat_id, $season_data);
            answerCallbackQuery($query_id, "Episodes");
        }
        elseif (strpos($data, 'folder_quality_') === 0) {
            $quality_data = str_replace('folder_quality_', '', $data);
            $folder->show_movies_by_quality($chat_id, $quality_data);
            answerCallbackQuery($query_id, "Movies");
        }
        return;
    }
    
    // Auto-Delete
    if (strpos($data, 'delete_') === 0) {
        $delete = new AutoDeleteManager();
        
        if (strpos($data, 'delete_set_') === 0) {
            $parts = explode('_', $data);
            $minutes = $parts[2];
            $msg_id = $parts[3];
            
            $delete_time = $delete->schedule_delete($chat_id, $msg_id, $minutes);
            deleteMessage($chat_id, $message_id);
            sendMessage($chat_id, "✅ Auto-delete set for $minutes minutes");
            answerCallbackQuery($query_id, "Set");
        }
        elseif ($data == 'delete_cancel') {
            deleteMessage($chat_id, $message_id);
            answerCallbackQuery($query_id, "Cancelled");
        }
        return;
    }
    
    // Admin Panel
    if (strpos($data, 'admin_') === 0) {
        if ($user_id != ADMIN_ID) {
            answerCallbackQuery($query_id, "❌ Admin only", true);
            return;
        }
        
        $admin = new AdminPanel($user_id);
        $action = str_replace('admin_', '', $data);
        
        switch ($action) {
            case 'main':
            case 'back':
                $admin->show_main_panel($chat_id);
                answerCallbackQuery($query_id, "Main");
                break;
            case 'close':
                deleteMessage($chat_id, $message_id);
                answerCallbackQuery($query_id, "Closed");
                break;
            case 'movies':
                $admin->show_movie_management($chat_id);
                answerCallbackQuery($query_id, "Movies");
                break;
            case 'users':
                $admin->show_user_management($chat_id);
                answerCallbackQuery($query_id, "Users");
                break;
            case 'stats':
                admin_stats($chat_id);
                answerCallbackQuery($query_id, "Stats");
                break;
            case 'backup':
                $admin->show_backup_panel($chat_id);
                answerCallbackQuery($query_id, "Backup");
                break;
            case 'requests':
                $admin->show_requests_panel($chat_id);
                answerCallbackQuery($query_id, "Requests");
                break;
            case 'broadcast':
                $admin->show_broadcast_panel($chat_id);
                answerCallbackQuery($query_id, "Broadcast");
                break;
            case 'viewcsv':
                show_csv_data($chat_id, false);
                answerCallbackQuery($query_id, "CSV");
                break;
            case 'moviestats':
                $stats = get_stats();
                sendMessage($chat_id, "📊 Movies: " . ($stats['total_movies'] ?? 0));
                answerCallbackQuery($query_id, "Stats");
                break;
            case 'userstats':
                sendMessage($chat_id, "👥 Enter user ID:");
                answerCallbackQuery($query_id, "User Stats");
                break;
            case 'leaderboard':
                show_leaderboard($chat_id);
                answerCallbackQuery($query_id, "Leaderboard");
                break;
            case 'backup_full':
                manual_backup($chat_id);
                answerCallbackQuery($query_id, "Backup Started");
                break;
            case 'backup_status':
                backup_status($chat_id);
                answerCallbackQuery($query_id, "Status");
                break;
            case 'requests_view':
                $requests_data = json_decode(file_get_contents(REQUEST_FILE), true);
                $pending = array_filter($requests_data['requests'] ?? [], fn($r) => $r['status'] == 'pending');
                sendMessage($chat_id, "📝 Pending: " . count($pending));
                answerCallbackQuery($query_id, "Requests");
                break;
            case 'requests_stats':
                $requests_data = json_decode(file_get_contents(REQUEST_FILE), true);
                $total = count($requests_data['requests'] ?? []);
                sendMessage($chat_id, "📊 Total Requests: $total");
                answerCallbackQuery($query_id, "Stats");
                break;
            case 'scan_old':
                scanOldPosts($chat_id);
                answerCallbackQuery($query_id, "Scanning");
                break;
            case 'scan_progressive':
                sendMessage($chat_id, "🔄 Progressive scan started...");
                scanOldPosts($chat_id);
                answerCallbackQuery($query_id, "Scanning");
                break;
            default:
                answerCallbackQuery($query_id, "Unknown");
        }
        return;
    }
    
    // Request Movie
    if ($data == 'request_movie') {
        sendMessage($chat_id, "📝 Use: /request movie name");
        answerCallbackQuery($query_id, "Request");
        return;
    }
    
    if (strpos($data, 'auto_request_') === 0) {
        $movie_name = base64_decode(str_replace('auto_request_', '', $data));
        if (add_movie_request($user_id, $movie_name)) {
            sendMessage($chat_id, "✅ Request sent!");
            answerCallbackQuery($query_id, "Requested");
        } else {
            sendMessage($chat_id, "❌ Daily limit reached!");
            answerCallbackQuery($query_id, "Limit reached", true);
        }
        return;
    }
    
    // Help
    if ($data == 'help_command') {
        handle_command($chat_id, $user_id, '/help', []);
        answerCallbackQuery($query_id, "Help");
        return;
    }
    
    // Pagination
    if (strpos($data, 'pag_') === 0) {
        $parts = explode('_', $data);
        $action = $parts[1];
        $session_id = $parts[2] ?? '';
        
        if ($action == 'first') {
            totalupload_controller($chat_id, 1, [], $session_id);
            answerCallbackQuery($query_id, "First page");
        }
        elseif ($action == 'last') {
            $all = get_all_movies_list();
            $total_pages = ceil(count($all) / ITEMS_PER_PAGE);
            totalupload_controller($chat_id, $total_pages, [], $session_id);
            answerCallbackQuery($query_id, "Last page");
        }
        elseif ($action == 'prev') {
            $current_page = intval($parts[2]);
            $session_id = $parts[3] ?? '';
            totalupload_controller($chat_id, max(1, $current_page - 1), [], $session_id);
            answerCallbackQuery($query_id, "Previous");
        }
        elseif ($action == 'next') {
            $current_page = intval($parts[2]);
            $session_id = $parts[3] ?? '';
            $all = get_all_movies_list();
            $total_pages = ceil(count($all) / ITEMS_PER_PAGE);
            totalupload_controller($chat_id, min($total_pages, $current_page + 1), [], $session_id);
            answerCallbackQuery($query_id, "Next");
        }
        elseif (is_numeric($action)) {
            $page_num = intval($action);
            $session_id = $parts[2] ?? '';
            totalupload_controller($chat_id, $page_num, [], $session_id);
            answerCallbackQuery($query_id, "Page $page_num");
        }
        return;
    }
    
    if (strpos($data, 'send_') === 0) {
        $parts = explode('_', $data);
        $page_num = intval($parts[1]);
        $session_id = $parts[2] ?? '';
        
        $all = get_all_movies_list();
        $pg = paginate_movies($all, $page_num, []);
        batch_download_with_progress($chat_id, $pg['slice'], $page_num);
        answerCallbackQuery($query_id, "Sending...");
        return;
    }
    
    if (strpos($data, 'stats_') === 0) {
        $stats = get_stats();
        sendMessage($chat_id, "📊 Movies: " . ($stats['total_movies'] ?? 0));
        answerCallbackQuery($query_id, "Stats");
        return;
    }
    
    if (strpos($data, 'close_') === 0) {
        deleteMessage($chat_id, $message_id);
        answerCallbackQuery($query_id, "Closed");
        return;
    }
    
    if ($data == 'current') {
        answerCallbackQuery($query_id, "Current page");
        return;
    }
    
    answerCallbackQuery($query_id, "Unknown option");
}

// ==============================
// 38. MAIN UPDATE PROCESSING
// ==============================
$update = json_decode(file_get_contents('php://input'), true);

if ($update) {
    
    // Maintenance Mode Check
    global $MAINTENANCE_MODE, $MAINTENANCE_MESSAGE;
    if ($MAINTENANCE_MODE && isset($update['message'])) {
        $chat_id = $update['message']['chat']['id'];
        if ($chat_id != ADMIN_ID) {
            sendMessage($chat_id, $MAINTENANCE_MESSAGE, null, 'HTML');
            exit;
        }
    }

    // Load cached movies
    get_cached_movies();

    // Auto-index new channel posts
    autoIndex($update);

    // Message Handling
    if (isset($update['message'])) {
        $message = $update['message'];
        $chat_id = $message['chat']['id'];
        $user_id = $message['from']['id'];
        $text = isset($message['text']) ? $message['text'] : '';
        $chat_type = $message['chat']['type'] ?? 'private';

        // Update user data
        $user_info = [
            'first_name' => $message['from']['first_name'] ?? '',
            'last_name' => $message['from']['last_name'] ?? '',
            'username' => $message['from']['username'] ?? ''
        ];
        update_user_data($user_id, $user_info);

        // Command handling
        if (strpos($text, '/') === 0) {
            $parts = explode(' ', $text);
            $command = strtolower($parts[0]);
            $params = array_slice($parts, 1);
            
            handle_command($chat_id, $user_id, $command, $params);
        } else if (!empty(trim($text))) {
            // Direct search
            $lang = detect_language($text);
            send_multilingual_response($chat_id, 'searching', $lang);
            advanced_search($chat_id, $text, $user_id);
        }
    }

    // Callback Query Handling
    if (isset($update['callback_query'])) {
        $query = $update['callback_query'];
        $message = $query['message'];
        $chat_id = $message['chat']['id'];
        $user_id = $query['from']['id'];
        $message_id = $message['message_id'];
        $data = $query['data'];

        handle_callback($chat_id, $user_id, $message_id, $data, $query['id']);
    }

    // Channel Post Handling
    if (isset($update['channel_post'])) {
        $message = $update['channel_post'];
        bot_log("Channel post detected");
    }
}

// ==============================
// 39. SCHEDULED TASKS
// ==============================
$current_hour = date('H');
$current_minute = date('i');

// Daily auto-backup at 3 AM
if ($current_hour == AUTO_BACKUP_HOUR && $current_minute == '00') {
    auto_backup();
}

// Process auto-deletions every minute
$delete_manager = new AutoDeleteManager();
$delete_manager->process_deletions();

// Hourly cache cleanup at 30 minutes
if ($current_minute == '30') {
    global $movie_cache;
    $movie_cache = [];
    bot_log("Cache cleaned");
}

// ==============================
// 40. WEBHOOK SETUP & TESTING
// ==============================
if (php_sapi_name() === 'cli' || isset($_GET['setwebhook'])) {
    $webhook_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    $result = apiRequest('setWebhook', ['url' => $webhook_url]);
    
    echo "<h1>Webhook Setup</h1>";
    echo "<p>Result: " . htmlspecialchars($result) . "</p>";
    echo "<p>URL: " . htmlspecialchars($webhook_url) . "</p>";
    
    $bot_info = json_decode(apiRequest('getMe'), true);
    if ($bot_info && isset($bot_info['ok']) && $bot_info['ok']) {
        echo "<h2>Bot Info</h2>";
        echo "<p>Name: " . htmlspecialchars($bot_info['result']['first_name']) . "</p>";
        echo "<p>Username: @" . htmlspecialchars($bot_info['result']['username']) . "</p>";
    }
    
    exit;
}

// Default page
if (!isset($update) || !$update) {
    $stats = get_stats();
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    
    echo "<h1>🎬 Entertainment Tadka Bot</h1>";
    echo "<p><strong>Status:</strong> ✅ Running</p>";
    echo "<p><strong>Movies:</strong> " . ($stats['total_movies'] ?? 0) . "</p>";
    echo "<p><strong>Users:</strong> " . count($users_data['users'] ?? []) . "</p>";
    echo "<p><strong>Searches:</strong> " . ($stats['total_searches'] ?? 0) . "</p>";
    echo "<p><strong>Last Updated:</strong> " . ($stats['last_updated'] ?? 'N/A') . "</p>";
    
    echo "<h3>Setup</h3>";
    echo "<p><a href='?setwebhook=1'>Set Webhook</a></p>";
}

// Helper function for admin_stats (used in admin panel)
function admin_stats($chat_id) {
    $stats = get_stats();
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    
    $msg = "📊 <b>Bot Statistics</b>\n\n";
    $msg .= "🎬 Total Movies: " . ($stats['total_movies'] ?? 0) . "\n";
    $msg .= "👥 Total Users: " . count($users_data['users'] ?? []) . "\n";
    $msg .= "🔍 Total Searches: " . ($stats['total_searches'] ?? 0) . "\n";
    $msg .= "✅ Successful: " . ($stats['successful_searches'] ?? 0) . "\n";
    $msg .= "❌ Failed: " . ($stats['failed_searches'] ?? 0) . "\n";
    $msg .= "📥 Total Downloads: " . ($stats['total_downloads'] ?? 0) . "\n";
    
    sendMessage($chat_id, $msg, null, 'HTML');
}

?>