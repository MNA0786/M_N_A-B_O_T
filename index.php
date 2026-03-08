<?php
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

define('BOT_TOKEN', '8315381064:AAGk0FGVGmB8j5SjpBvW3rD3_kQHe_hyOWU');
define('BOT_USERNAME', '@EntertainmentTadkaBot');
define('API_ID', '21944581');
define('API_HASH', '7b1c174a5cd3466e25a976c39a791737');
define('ADMIN_ID', 1080317415);

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

$movie_messages = array();
$movie_cache = array();
$waiting_users = array();
$user_sessions = array();
$user_pagination_sessions = array();
$MAINTENANCE_MODE = false;

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

function initialize_files() {
    $files = [
        CSV_FILE => "movie_name,message_id,channel_id\n",
        USERS_FILE => json_encode(['users' => [], 'total_requests' => 0, 'message_logs' => [], 'daily_stats' => []], JSON_PRETTY_PRINT),
        STATS_FILE => json_encode(['total_movies' => 0, 'total_users' => 0, 'total_searches' => 0, 'total_downloads' => 0, 'successful_searches' => 0, 'failed_searches' => 0, 'daily_activity' => [], 'last_updated' => date('Y-m-d H:i:s')], JSON_PRETTY_PRINT),
        REQUEST_FILE => json_encode(['requests' => [], 'pending_approval' => [], 'completed_requests' => [], 'user_request_count' => []], JSON_PRETTY_PRINT)
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

function bot_log($message, $type = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $type: $message\n";
    file_put_contents(LOG_FILE, $log_entry, FILE_APPEND);
}

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
    bot_log("Message sent to $chat_id: " . substr($text, 0, 50) . "...");
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
    $valid_actions = ['typing', 'upload_photo', 'record_video', 'upload_video', 'record_audio', 'upload_audio', 'upload_document', 'find_location'];
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
    $movies = [];
    if (file_exists(CSV_FILE)) {
        $handle = fopen(CSV_FILE, 'r');
        if ($handle !== FALSE) {
            $header = fgetcsv($handle);
            while (($row = fgetcsv($handle)) !== FALSE) {
                if (count($row) >= 3) {
                    $movies[] = [
                        'title' => $row[0],
                        'message_id' => $row[1],
                        'channel_id' => $row[2]
                    ];
                }
            }
            fclose($handle);
        }
    }
    return $movies;
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
    bot_log("Movie cache refreshed - " . count($movie_cache['data']) . " movies");
    return $movie_cache['data'];
}

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

class MediaInfo {
    public static function detect_quality($filename) {
        $filename = strtolower($filename);
        $qualities = [
            '4320p' => ['8k', '4320p', '4320'],
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
            'AC3/Dolby Digital' => ['ac3', 'dd5.1', 'dolby'],
            'EAC3/Dolby Digital Plus' => ['eac3', 'dd+', 'dolby digital plus'],
            'DTS' => ['dts'],
            'DTS-HD' => ['dts-hd', 'dtshd'],
            'TrueHD/Atmos' => ['truehd', 'atmos'],
            'FLAC' => ['flac'],
            'Opus' => ['opus']
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
        } elseif (strpos($filename, 'mono') !== false) {
            $info['channels'] = '1.0';
        } elseif (strpos($filename, 'stereo') !== false) {
            $info['channels'] = '2.0';
        } elseif (strpos($filename, '5.1') !== false) {
            $info['channels'] = '5.1';
        } elseif (strpos($filename, '7.1') !== false) {
            $info['channels'] = '7.1';
        }
        if (strpos($filename, 'dual') !== false || strpos($filename, 'dub') !== false || preg_match('/(hindi|english|tamil|telugu).*(hindi|english|tamil|telugu)/i', $filename)) {
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
        if (strpos($filename, 'sub') !== false || strpos($filename, 'subtitle') !== false || strpos($filename, 'subtitles') !== false) {
            $info['has_subs'] = true;
            $langs = ['english', 'hindi', 'tamil', 'telugu', 'malayalam', 'kannada'];
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
        $hdr_patterns = ['hdr', 'hdr10', 'hdr10+', 'dolby vision', 'dv', 'bt.2020', 'pq', 'hlg'];
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
            if (!empty($info['subtitles']['languages'])) {
                $text .= "• Subtitles: " . implode(', ', $info['subtitles']['languages']) . "\n";
            } else {
                $text .= "• Subtitles: ✅ Available\n";
            }
        }
        $text .= "• Size: " . $info['size'] . "\n";
        $text .= "• Language: " . $info['language'] . "\n";
        return $text;
    }
}

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
    
    function __construct($msg, $channel_id) {
        $this->channel_id = $channel_id;
        $this->channel_type = get_channel_type_by_id($channel_id);
        $this->extract_metadata($msg);
    }
    
    private function extract_metadata($msg) {
        if (isset($msg['caption'])) {
            $this->title = trim($msg['caption']);
        } elseif (isset($msg['video']['file_name'])) {
            $this->title = $msg['video']['file_name'];
        } elseif (isset($msg['document']['file_name'])) {
            $this->title = $msg['document']['file_name'];
        } else {
            $this->title = "Unknown Media";
        }
        if (isset($msg['video']['file_size'])) {
            $this->file_size = $this->format_size($msg['video']['file_size']);
        } elseif (isset($msg['document']['file_size'])) {
            $this->file_size = $this->format_size($msg['document']['file_size']);
        }
        $this->detect_video_codec();
        $this->detect_audio_info();
        $this->detect_episode_info();
        $this->detect_hdr();
        $this->detect_quality();
    }
    
    private function detect_video_codec() {
        $codec_patterns = [
            'x264' => ['x264', 'h264', 'avc'],
            'x265' => ['x265', 'h265', 'hevc'],
            'VP9' => ['vp9'],
            'AV1' => ['av1']
        ];
        foreach ($codec_patterns as $codec => $patterns) {
            foreach ($patterns as $pattern) {
                if (stripos($this->title, $pattern) !== false) {
                    $this->video_codec = $codec;
                    return;
                }
            }
        }
        $this->video_codec = 'Unknown';
    }
    
    private function detect_audio_info() {
        if (stripos($this->title, 'aac') !== false) {
            $this->audio_codec = 'AAC';
        } elseif (stripos($this->title, 'mp3') !== false) {
            $this->audio_codec = 'MP3';
        } elseif (stripos($this->title, 'ac3') !== false || stripos($this->title, 'dd5.1') !== false) {
            $this->audio_codec = 'AC3 (Dolby Digital)';
        } elseif (stripos($this->title, 'dts') !== false) {
            $this->audio_codec = 'DTS';
        } elseif (stripos($this->title, 'eac3') !== false || stripos($this->title, 'dd+') !== false) {
            $this->audio_codec = 'EAC3 (Dolby Digital Plus)';
        } elseif (stripos($this->title, 'truehd') !== false || stripos($this->title, 'atmos') !== false) {
            $this->audio_codec = 'TrueHD / Atmos';
        } else {
            $this->audio_codec = 'Unknown';
        }
        if (preg_match('/(\d)\.(\d)/', $this->title, $matches)) {
            $this->audio_channels = $matches[0];
        } elseif (stripos($this->title, 'mono') !== false) {
            $this->audio_channels = '1.0';
        } elseif (stripos($this->title, 'stereo') !== false) {
            $this->audio_channels = '2.0';
        } elseif (stripos($this->title, '5.1') !== false) {
            $this->audio_channels = '5.1';
        } elseif (stripos($this->title, '7.1') !== false) {
            $this->audio_channels = '7.1';
        }
        if (stripos($this->title, 'dual') !== false || stripos($this->title, 'dub') !== false || preg_match('/(hindi|english|tamil|telugu).*(hindi|english|tamil|telugu)/i', $this->title)) {
            $this->is_dual_audio = true;
        }
        $langs = ['english', 'hindi', 'tamil', 'telugu', 'malayalam', 'kannada', 'bengali', 'punjabi'];
        foreach ($langs as $lang) {
            if (stripos($this->title, $lang . ' sub') !== false || stripos($this->title, 'sub ' . $lang) !== false || (stripos($this->title, 'subtitle') !== false && stripos($this->title, $lang) !== false)) {
                $this->subtitle_languages[] = ucfirst($lang);
            }
        }
    }
    
    private function detect_episode_info() {
        if (preg_match('/[Ss](\d+)[Ee](\d+)/', $this->title, $matches)) {
            $this->is_episode = true;
            $this->season_number = (int)$matches[1];
            $this->episode_number = (int)$matches[2];
            $this->series_name = preg_replace('/[Ss]\d+[Ee]\d+/', '', $this->title);
            $this->series_name = trim(preg_replace('/[.\-_]/', ' ', $this->series_name));
        } elseif (preg_match('/[Ss]eason[.\s]*(\d+).*?[Ee]pisode[.\s]*(\d+)/i', $this->title, $matches)) {
            $this->is_episode = true;
            $this->season_number = (int)$matches[1];
            $this->episode_number = (int)$matches[2];
        }
    }
    
    private function detect_hdr() {
        $hdr_patterns = ['hdr', 'hdr10', 'hdr10+', 'dolby vision', 'dv', 'bt.2020'];
        foreach ($hdr_patterns as $pattern) {
            if (stripos($this->title, $pattern) !== false) {
                $this->is_hdr = true;
                break;
            }
        }
    }
    
    private function detect_quality() {
        $qualities = [
            '4320p' => ['8k', '4320p'],
            '2160p' => ['4k', '2160p', 'uhd'],
            '1440p' => ['2k', '1440p', 'qhd'],
            '1080p' => ['1080p', 'fhd', 'full hd'],
            '720p' => ['720p', 'hd'],
            '576p' => ['576p', 'pal'],
            '480p' => ['480p', 'sd', 'dvd'],
            '360p' => ['360p']
        ];
        foreach ($qualities as $qual => $patterns) {
            foreach ($patterns as $pattern) {
                if (stripos($this->title, $pattern) !== false) {
                    $this->quality = $qual;
                    return;
                }
            }
        }
        $this->quality = 'Unknown';
    }
    
    private function format_size($bytes) {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' B';
        }
    }
    
    public function get_detailed_info() {
        $info = "🎬 <b>" . htmlspecialchars($this->title) . "</b>\n\n";
        $info .= "📊 <b>Technical Details:</b>\n";
        $info .= "• Quality: {$this->quality}\n";
        $info .= "• Size: {$this->file_size}\n";
        $info .= "• Video Codec: {$this->video_codec}\n";
        $info .= "• Audio Codec: {$this->audio_codec}\n";
        if ($this->audio_channels) {
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
        $info .= "\n📢 Channel: " . get_channel_display_name($this->channel_type);
        return $info;
    }
}

class UserSettings {
    private $user_id;
    private $settings_file = SETTINGS_FILE;
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
            bot_log("New settings created for user: $this->user_id");
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
        bot_log("User $this->user_id updated setting: $key = " . json_encode($value));
    }
    
    public function reset_to_default() {
        $settings = $this->load_all_settings();
        $settings[$this->user_id] = $this->default_settings;
        $this->save_all_settings($settings);
        bot_log("User $this->user_id reset settings to default");
    }
    
    private function get_language_emoji($lang) {
        $emojis = [
            'hindi' => '🇮🇳',
            'english' => '🇬🇧',
            'tamil' => '🇮🇳',
            'telugu' => '🇮🇳',
            'malayalam' => '🇮🇳',
            'kannada' => '🇮🇳',
            'bengali' => '🇮🇳',
            'punjabi' => '🇮🇳'
        ];
        return $emojis[$lang] ?? '🌐';
    }
    
    private function get_user_points() {
        $users_data = json_decode(file_get_contents(USERS_FILE), true);
        return $users_data['users'][$this->user_id]['points'] ?? 0;
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
        $message = "⚙️ <b>👤 User Settings Panel</b>\n\n";
        $message .= "🆔 <b>User ID:</b> <code>$this->user_id</code>\n";
        $message .= "⭐ <b>Points:</b> " . $this->get_user_points() . "\n\n";
        $message .= "📋 <b>Current Preferences:</b>\n";
        $message .= "────────────────\n";
        $message .= "🌐 <b>Language:</b> " . $this->get_language_emoji($lang) . " " . ucfirst($lang) . "\n";
        $message .= "📊 <b>Default Quality:</b> 🎬 $quality\n";
        $message .= "📁 <b>Result Layout:</b> " . ($layout == 'buttons' ? '🔘 Buttons' : '📝 Text List') . "\n";
        $message .= "🎯 <b>Priority Mode:</b> " . ($priority == 'quality' ? '📊 Quality First' : '💾 Size First') . "\n";
        $message .= "📄 <b>Results Per Page:</b> $per_page\n";
        $message .= "🎨 <b>Theme:</b> " . ($theme == 'dark' ? '🌙 Dark' : '☀️ Light') . "\n";
        $message .= "────────────────\n";
        $message .= "🔒 <b>Spoiler Mode:</b> $spoiler\n";
        $message .= "🔍 <b>Top Search:</b> $topsearch\n";
        $message .= "🔄 <b>Auto Scan:</b> $autoscan\n";
        $message .= "🔔 <b>Notifications:</b> $notify\n";
        $message .= "────────────────\n\n";
        $message .= "🛠️ <b>Select category to customize:</b>";
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
                    ['text' => '🔄 Reset to Default', 'callback_data' => 'settings_reset'],
                    ['text' => '📊 My Stats', 'callback_data' => 'my_stats']
                ],
                [
                    ['text' => '❌ Close Panel', 'callback_data' => 'settings_close']
                ]
            ]
        ];
        sendMessage($chat_id, $message, $keyboard, 'HTML');
    }
    
    public function show_language_settings($chat_id) {
        $current = $this->get('language');
        $message = "🌐 <b>Select Your Preferred Language</b>\n\n";
        $message .= "🎯 Current: <b>" . $this->get_language_emoji($current) . " " . ucfirst($current) . "</b>\n\n";
        $message .= "Choose language for search results and bot responses:\n\n";
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
                    ['text' => '🇮🇳 Malayalam' . ($current == 'malayalam' ? ' ✅' : ''), 'callback_data' => 'settings_set_language_malayalam'],
                    ['text' => '🇮🇳 Kannada' . ($current == 'kannada' ? ' ✅' : ''), 'callback_data' => 'settings_set_language_kannada']
                ],
                [
                    ['text' => '🇮🇳 Bengali' . ($current == 'bengali' ? ' ✅' : ''), 'callback_data' => 'settings_set_language_bengali'],
                    ['text' => '🇮🇳 Punjabi' . ($current == 'punjabi' ? ' ✅' : ''), 'callback_data' => 'settings_set_language_punjabi']
                ],
                [
                    ['text' => '⬅️ Back to Settings', 'callback_data' => 'settings_back'],
                    ['text' => '❌ Close', 'callback_data' => 'settings_close']
                ]
            ]
        ];
        sendMessage($chat_id, $message, $keyboard, 'HTML');
    }
    
    public function show_quality_settings($chat_id) {
        $current = $this->get('default_quality');
        $message = "📊 <b>Select Default Quality Preference</b>\n\n";
        $message .= "🎯 Current: <b>$current</b>\n\n";
        $message .= "This quality will be prioritized in search results:\n\n";
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
                    ['text' => '576p' . ($current == '576p' ? ' ✅' : ''), 'callback_data' => 'settings_set_quality_576p'],
                    ['text' => '480p (SD)' . ($current == '480p' ? ' ✅' : ''), 'callback_data' => 'settings_set_quality_480p']
                ],
                [
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
        $message = "📁 <b>Select Result Display Layout</b>\n\n";
        $message .= "🎯 Current: <b>" . ($current == 'buttons' ? '🔘 Buttons' : '📝 Text List') . "</b>\n\n";
        $message .= "Choose how you want to see search results:\n";
        $message .= "🔘 <b>Buttons</b> - Clickable buttons for each result\n";
        $message .= "📝 <b>Text List</b> - Simple text list with numbers\n\n";
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
        $message = "🎯 <b>Select Search Priority Mode</b>\n\n";
        $message .= "🎯 Current: <b>" . ($current == 'quality' ? '📊 Quality First' : '💾 Size First') . "</b>\n\n";
        $message .= "📊 <b>Quality First</b> - Show best quality first\n";
        $message .= "💾 <b>Size First</b> - Show smallest file size first\n\n";
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
        $message = "📄 <b>Select Results Per Page</b>\n\n";
        $message .= "🎯 Current: <b>$current results per page</b>\n\n";
        $message .= "Choose how many movies to show on each page:\n\n";
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
        $message = "🎨 <b>Select Theme Preference</b>\n\n";
        $message .= "🎯 Current: <b>" . ($current == 'dark' ? '🌙 Dark' : '☀️ Light') . "</b>\n\n";
        $message .= "Choose your preferred display theme:\n\n";
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🌙 Dark Theme' . ($current == 'dark' ? ' ✅' : ''), 'callback_data' => 'settings_set_theme_dark'],
                    ['text' => '☀️ Light Theme' . ($current == 'light' ? ' ✅' : ''), 'callback_data' => 'settings_set_theme_light']
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

class MovieFilter {
    private $filters = [];
    private $user_id;
    private $filter_session_file = FILTER_SESSION_FILE;
    
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
                    $languages = ['hindi', 'english', 'tamil', 'telugu', 'malayalam', 'kannada', 'bengali', 'punjabi'];
                    foreach ($languages as $lang) {
                        if (stripos($movie_lower, $lang) !== false) {
                            if ($lang == $value) $lang_match = true;
                            break;
                        }
                    }
                    if (!$lang_match && $value == 'hindi' && !preg_match('/english|tamil|telugu/', $movie_lower)) {
                        $lang_match = true;
                    }
                    if (!$lang_match) return false;
                    break;
                case 'quality':
                    $qualities = ['2160p', '1440p', '1080p', '720p', '576p', '480p', '360p', '4k', 'hd', 'fhd', 'uhd'];
                    $quality_found = false;
                    foreach ($qualities as $q) {
                        if (stripos($movie_lower, $q) !== false) {
                            if ($q == $value || ($value == '2160p' && ($q == '4k' || $q == 'uhd')) || ($value == '1080p' && $q == 'fhd')) {
                                $quality_found = true;
                            }
                            break;
                        }
                    }
                    if (!$quality_found) return false;
                    break;
                case 'file_type':
                    $extensions = ['mkv', 'mp4', 'avi', 'mov', 'wmv', 'flv', 'webm'];
                    $ext_found = false;
                    foreach ($extensions as $ext) {
                        if (stripos($movie_lower, '.' . $ext) !== false) {
                            if ($ext == $value) $ext_found = true;
                            break;
                        }
                    }
                    if (!$ext_found && $value != 'any') return false;
                    break;
                case 'season':
                    if (preg_match('/[Ss]eason[.\s]*(\d+)/i', $movie_lower, $matches) || preg_match('/[Ss](\d+)/i', $movie_lower, $matches)) {
                        if ($matches[1] != $value) return false;
                    } else {
                        if ($value != 'all') return false;
                    }
                    break;
                case 'dual_audio':
                    $is_dual = (stripos($movie_lower, 'dual') !== false || stripos($movie_lower, 'dub') !== false || (preg_match('/(hindi|english).*(hindi|english)/i', $movie_lower)));
                    if ($value == 'yes' && !$is_dual) return false;
                    if ($value == 'no' && $is_dual) return false;
                    break;
                case 'hdr':
                    $is_hdr = (stripos($movie_lower, 'hdr') !== false || stripos($movie_lower, 'hdr10') !== false || stripos($movie_lower, 'dolby vision') !== false || stripos($movie_lower, 'dv') !== false || stripos($movie_lower, 'bt.2020') !== false);
                    if ($value == 'yes' && !$is_hdr) return false;
                    if ($value == 'no' && $is_hdr) return false;
                    break;
                case 'min_size':
                case 'max_size':
                    $size_str = $movie['size'] ?? 'Unknown';
                    $size_mb = $this->parse_size_to_mb($size_str);
                    if ($key == 'min_size' && $size_mb < $value) return false;
                    if ($key == 'max_size' && $size_mb > $value) return false;
                    break;
                case 'codec':
                    $codecs = ['x264', 'h264', 'x265', 'h265', 'hevc', 'avc', 'vp9', 'av1'];
                    $codec_found = false;
                    foreach ($codecs as $codec) {
                        if (stripos($movie_lower, $codec) !== false) {
                            if (stripos($codec, $value) !== false || stripos($value, $codec) !== false) {
                                $codec_found = true;
                            }
                            break;
                        }
                    }
                    if (!$codec_found) return false;
                    break;
                case 'subtitles':
                    $has_subs = (stripos($movie_lower, 'sub') !== false || stripos($movie_lower, 'subtitle') !== false || stripos($movie_lower, 'subtitles') !== false);
                    if ($value == 'yes' && !$has_subs) return false;
                    if ($value == 'no' && $has_subs) return false;
                    if (in_array($value, ['english', 'hindi', 'tamil', 'telugu'])) {
                        if (!preg_match('/' . $value . '.*sub/i', $movie_lower) && !preg_match('/sub.*' . $value . '/i', $movie_lower)) {
                            return false;
                        }
                    }
                    break;
            }
        }
        return true;
    }
    
    private function parse_size_to_mb($size_str) {
        if (strpos($size_str, 'GB') !== false) {
            return floatval($size_str) * 1024;
        } elseif (strpos($size_str, 'MB') !== false) {
            return floatval($size_str);
        } elseif (strpos($size_str, 'KB') !== false) {
            return floatval($size_str) / 1024;
        }
        return 0;
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
        if (isset($sessions[$this->user_id]) && (time() - $sessions[$this->user_id]['timestamp'] < 3600)) {
            return $sessions[$this->user_id]['filters'];
        }
        return [];
    }
    
    private function load_filter_sessions() {
        if (!file_exists($this->filter_session_file)) {
            return [];
        }
        return json_decode(file_get_contents($this->filter_session_file), true) ?: [];
    }
    
    private function get_filter_icon($key) {
        $icons = [
            'language' => '🗣️',
            'quality' => '📊',
            'file_type' => '📁',
            'season' => '📺',
            'dual_audio' => '🔊',
            'hdr' => '✨',
            'min_size' => '⬆️',
            'max_size' => '⬇️',
            'codec' => '🎬',
            'subtitles' => '📝'
        ];
        return $icons[$key] ?? '🔹';
    }
    
    public function show_filter_panel($chat_id, $current_filters = []) {
        $active_count = count($current_filters);
        $message = "🔍 <b>Advanced Filter System</b>\n\n";
        if ($active_count > 0) {
            $message .= "🎯 <b>Active Filters ($active_count):</b>\n";
            foreach ($current_filters as $key => $value) {
                $icon = $this->get_filter_icon($key);
                $display_value = is_array($value) ? implode(', ', $value) : $value;
                $message .= "$icon <b>" . ucfirst(str_replace('_', ' ', $key)) . ":</b> $display_value\n";
            }
            $message .= "\n";
        }
        $message .= "📋 <b>Filter Categories:</b>\n";
        $message .= "Select a category to filter movies:\n\n";
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🗣️ Language', 'callback_data' => 'filter_menu_language'],
                    ['text' => '📊 Quality', 'callback_data' => 'filter_menu_quality']
                ],
                [
                    ['text' => '📁 File Type', 'callback_data' => 'filter_menu_filetype'],
                    ['text' => '📺 Season', 'callback_data' => 'filter_menu_season']
                ],
                [
                    ['text' => '🔊 Dual Audio', 'callback_data' => 'filter_menu_dual'],
                    ['text' => '✨ HDR', 'callback_data' => 'filter_menu_hdr']
                ],
                [
                    ['text' => '💾 Size Range', 'callback_data' => 'filter_menu_size'],
                    ['text' => '🎬 Codec', 'callback_data' => 'filter_menu_codec']
                ],
                [
                    ['text' => '📝 Subtitles', 'callback_data' => 'filter_menu_subs']
                ]
            ]
        ];
        $action_row = [];
        if ($active_count > 0) {
            $action_row[] = ['text' => '🧹 Clear All', 'callback_data' => 'filter_clear_all'];
            $action_row[] = ['text' => '✅ Apply', 'callback_data' => 'filter_apply'];
        }
        $action_row[] = ['text' => '❌ Close', 'callback_data' => 'filter_close'];
        $keyboard['inline_keyboard'][] = $action_row;
        sendMessage($chat_id, $message, $keyboard, 'HTML');
    }
    
    public function show_language_filter($chat_id, $current = null) {
        $message = "🗣️ <b>Language Filter</b>\n\n";
        $message .= "Select language to filter movies:\n\n";
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
                    ['text' => '🇮🇳 Malayalam' . ($current == 'malayalam' ? ' ✅' : ''), 'callback_data' => 'filter_set_language_malayalam'],
                    ['text' => '🇮🇳 Kannada' . ($current == 'kannada' ? ' ✅' : ''), 'callback_data' => 'filter_set_language_kannada']
                ],
                [
                    ['text' => '🇮🇳 Bengali' . ($current == 'bengali' ? ' ✅' : ''), 'callback_data' => 'filter_set_language_bengali'],
                    ['text' => '🇮🇳 Punjabi' . ($current == 'punjabi' ? ' ✅' : ''), 'callback_data' => 'filter_set_language_punjabi']
                ],
                [
                    ['text' => '🔹 Any Language', 'callback_data' => 'filter_set_language_any'],
                    ['text' => '⬅️ Back', 'callback_data' => 'filter_back']
                ]
            ]
        ];
        sendMessage($chat_id, $message, $keyboard, 'HTML');
    }
    
    public function show_quality_filter($chat_id, $current = null) {
        $message = "📊 <b>Quality Filter</b>\n\n";
        $message .= "Select quality to filter movies:\n\n";
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '4K (2160p)' . ($current == '2160p' ? ' ✅' : ''), 'callback_data' => 'filter_set_quality_2160p'],
                    ['text' => '2K (1440p)' . ($current == '1440p' ? ' ✅' : ''), 'callback_data' => 'filter_set_quality_1440p']
                ],
                [
                    ['text' => '1080p (FHD)' . ($current == '1080p' ? ' ✅' : ''), 'callback_data' => 'filter_set_quality_1080p'],
                    ['text' => '720p (HD)' . ($current == '720p' ? ' ✅' : ''), 'callback_data' => 'filter_set_quality_720p']
                ],
                [
                    ['text' => '576p' . ($current == '576p' ? ' ✅' : ''), 'callback_data' => 'filter_set_quality_576p'],
                    ['text' => '480p (SD)' . ($current == '480p' ? ' ✅' : ''), 'callback_data' => 'filter_set_quality_480p']
                ],
                [
                    ['text' => '360p' . ($current == '360p' ? ' ✅' : ''), 'callback_data' => 'filter_set_quality_360p'],
                    ['text' => '🔹 Any Quality', 'callback_data' => 'filter_set_quality_any']
                ],
                [
                    ['text' => '⬅️ Back', 'callback_data' => 'filter_back']
                ]
            ]
        ];
        sendMessage($chat_id, $message, $keyboard, 'HTML');
    }
    
    public function show_filetype_filter($chat_id, $current = null) {
        $message = "📁 <b>File Type Filter</b>\n\n";
        $message .= "Select file format:\n\n";
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => 'MKV' . ($current == 'mkv' ? ' ✅' : ''), 'callback_data' => 'filter_set_filetype_mkv'],
                    ['text' => 'MP4' . ($current == 'mp4' ? ' ✅' : ''), 'callback_data' => 'filter_set_filetype_mp4']
                ],
                [
                    ['text' => 'AVI' . ($current == 'avi' ? ' ✅' : ''), 'callback_data' => 'filter_set_filetype_avi'],
                    ['text' => 'MOV' . ($current == 'mov' ? ' ✅' : ''), 'callback_data' => 'filter_set_filetype_mov']
                ],
                [
                    ['text' => '🔹 Any Format', 'callback_data' => 'filter_set_filetype_any'],
                    ['text' => '⬅️ Back', 'callback_data' => 'filter_back']
                ]
            ]
        ];
        sendMessage($chat_id, $message, $keyboard, 'HTML');
    }
    
    public function show_season_filter($chat_id, $current = null) {
        $message = "📺 <b>Season Filter</b>\n\n";
        $message .= "Enter season number (1-20) or select option:\n\n";
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
                    ['text' => 'Season 7', 'callback_data' => 'filter_set_season_7'],
                    ['text' => 'Season 8', 'callback_data' => 'filter_set_season_8'],
                    ['text' => 'Season 9', 'callback_data' => 'filter_set_season_9']
                ],
                [
                    ['text' => 'Season 10', 'callback_data' => 'filter_set_season_10'],
                    ['text' => '🔹 All Seasons', 'callback_data' => 'filter_set_season_all']
                ],
                [
                    ['text' => '⬅️ Back', 'callback_data' => 'filter_back']
                ]
            ]
        ];
        sendMessage($chat_id, $message, $keyboard, 'HTML');
    }
    
    public function show_dual_audio_filter($chat_id, $current = null) {
        $message = "🔊 <b>Dual Audio Filter</b>\n\n";
        $message .= "Filter movies with dual audio:\n\n";
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '✅ Dual Audio Only' . ($current == 'yes' ? ' ✅' : ''), 'callback_data' => 'filter_set_dual_yes'],
                    ['text' => '❌ Single Audio Only' . ($current == 'no' ? ' ✅' : ''), 'callback_data' => 'filter_set_dual_no']
                ],
                [
                    ['text' => '🔹 Any (No Filter)', 'callback_data' => 'filter_set_dual_any'],
                    ['text' => '⬅️ Back', 'callback_data' => 'filter_back']
                ]
            ]
        ];
        sendMessage($chat_id, $message, $keyboard, 'HTML');
    }
    
    public function show_hdr_filter($chat_id, $current = null) {
        $message = "✨ <b>HDR Filter</b>\n\n";
        $message .= "Filter HDR content:\n\n";
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '✅ HDR Only' . ($current == 'yes' ? ' ✅' : ''), 'callback_data' => 'filter_set_hdr_yes'],
                    ['text' => '❌ SDR Only' . ($current == 'no' ? ' ✅' : ''), 'callback_data' => 'filter_set_hdr_no']
                ],
                [
                    ['text' => '🔹 Any (No Filter)', 'callback_data' => 'filter_set_hdr_any'],
                    ['text' => '⬅️ Back', 'callback_data' => 'filter_back']
                ]
            ]
        ];
        sendMessage($chat_id, $message, $keyboard, 'HTML');
    }
    
    public function show_size_filter($chat_id, $current_min = null, $current_max = null) {
        $message = "💾 <b>Size Filter</b>\n\n";
        $message .= "Current: ";
        if ($current_min || $current_max) {
            $message .= ($current_min ? "Min: {$current_min}MB" : "") . ($current_min && $current_max ? " - " : "") . ($current_max ? "Max: {$current_max}MB" : "") . "\n\n";
        } else {
            $message .= "No size filter active\n\n";
        }
        $message .= "Select size range:\n\n";
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '< 100MB', 'callback_data' => 'filter_set_size_0_100'],
                    ['text' => '100-300MB', 'callback_data' => 'filter_set_size_100_300'],
                    ['text' => '300-500MB', 'callback_data' => 'filter_set_size_300_500']
                ],
                [
                    ['text' => '500MB-1GB', 'callback_data' => 'filter_set_size_500_1024'],
                    ['text' => '1GB-2GB', 'callback_data' => 'filter_set_size_1024_2048'],
                    ['text' => '2GB-4GB', 'callback_data' => 'filter_set_size_2048_4096']
                ],
                [
                    ['text' => '4GB-8GB', 'callback_data' => 'filter_set_size_4096_8192'],
                    ['text' => '> 8GB', 'callback_data' => 'filter_set_size_8192_99999']
                ],
                [
                    ['text' => '🔹 Clear Size Filter', 'callback_data' => 'filter_set_size_clear'],
                    ['text' => '⬅️ Back', 'callback_data' => 'filter_back']
                ]
            ]
        ];
        sendMessage($chat_id, $message, $keyboard, 'HTML');
    }
    
    public function show_codec_filter($chat_id, $current = null) {
        $message = "🎬 <b>Codec Filter</b>\n\n";
        $message .= "Select video codec:\n\n";
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => 'H.264 (x264)' . ($current == 'x264' ? ' ✅' : ''), 'callback_data' => 'filter_set_codec_x264'],
                    ['text' => 'H.265 (x265)' . ($current == 'x265' ? ' ✅' : ''), 'callback_data' => 'filter_set_codec_x265']
                ],
                [
                    ['text' => 'HEVC' . ($current == 'hevc' ? ' ✅' : ''), 'callback_data' => 'filter_set_codec_hevc'],
                    ['text' => 'AV1' . ($current == 'av1' ? ' ✅' : ''), 'callback_data' => 'filter_set_codec_av1']
                ],
                [
                    ['text' => 'VP9' . ($current == 'vp9' ? ' ✅' : ''), 'callback_data' => 'filter_set_codec_vp9'],
                    ['text' => '🔹 Any Codec', 'callback_data' => 'filter_set_codec_any']
                ],
                [
                    ['text' => '⬅️ Back', 'callback_data' => 'filter_back']
                ]
            ]
        ];
        sendMessage($chat_id, $message, $keyboard, 'HTML');
    }
    
    public function show_subtitle_filter($chat_id, $current = null) {
        $message = "📝 <b>Subtitle Filter</b>\n\n";
        $message .= "Filter by subtitles:\n\n";
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '✅ With Subtitles', 'callback_data' => 'filter_set_subs_yes'],
                    ['text' => '❌ Without Subtitles', 'callback_data' => 'filter_set_subs_no']
                ],
                [
                    ['text' => '🇬🇧 English Subs', 'callback_data' => 'filter_set_subs_english'],
                    ['text' => '🇮🇳 Hindi Subs', 'callback_data' => 'filter_set_subs_hindi']
                ],
                [
                    ['text' => '🔹 Any (No Filter)', 'callback_data' => 'filter_set_subs_any'],
                    ['text' => '⬅️ Back', 'callback_data' => 'filter_back']
                ]
            ]
        ];
        sendMessage($chat_id, $message, $keyboard, 'HTML');
    }
}

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
            } elseif (preg_match('/^(.*?)[.\s_]*[Ss]eason[.\s]*(\d+)[.\s]*[Ee]pisode[.\s]*(\d+)/i', $name, $matches)) {
                $series_name = trim($matches[1]);
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
            sendMessage($chat_id, "📭 No series found in database!");
            return;
        }
        $message = "📺 <b>Series & Episodes</b>\n\n";
        $message .= "Total Series: " . count($series) . "\n\n";
        $keyboard = ['inline_keyboard' => []];
        foreach ($series as $name => $seasons) {
            $total_eps = 0;
            foreach ($seasons as $eps) {
                $total_eps += count($eps);
            }
            $keyboard['inline_keyboard'][] = [
                ['text' => "📺 $name ($total_eps episodes)", 'callback_data' => 'bulk_series_' . base64_encode($name)]
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
        $message = "📺 <b>$name</b>\n\n";
        $message .= "Total Seasons: " . count($seasons) . "\n\n";
        $keyboard = ['inline_keyboard' => []];
        foreach ($seasons as $season_num => $episodes) {
            $keyboard['inline_keyboard'][] = [
                ['text' => "Season $season_num (" . count($episodes) . " eps)", 'callback_data' => 'bulk_season_' . base64_encode($name . '||' . $season_num)]
            ];
        }
        $keyboard['inline_keyboard'][] = [
            ['text' => '📥 Download All Seasons', 'callback_data' => 'bulk_download_all_' . base64_encode($name)],
            ['text' => '⬅️ Back', 'callback_data' => 'bulk_back_series']
        ];
        $keyboard['inline_keyboard'][] = [
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
        $message = "📺 <b>$series_name - Season $season_num</b>\n\n";
        $message .= "Total Episodes: " . count($episodes) . "\n\n";
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
            ['text' => '📥 Download All Episodes', 'callback_data' => 'bulk_download_season_' . base64_encode($series_name . '||' . $season_num)],
            ['text' => '⬅️ Back to Seasons', 'callback_data' => 'bulk_series_' . base64_encode($series_name)]
        ];
        $keyboard['inline_keyboard'][] = [
            ['text' => '❌ Close', 'callback_data' => 'bulk_close']
        ];
        sendMessage($chat_id, $message, $keyboard, 'HTML');
    }
    
    public function download_all_seasons($chat_id, $series_name) {
        $series = $this->extract_series();
        $name = base64_decode($series_name);
        if (!isset($series[$name])) {
            sendMessage($chat_id, "❌ Series not found!");
            return;
        }
        $all_episodes = [];
        foreach ($series[$name] as $season => $episodes) {
            foreach ($episodes as $ep_num => $movie) {
                $all_episodes[] = $movie;
            }
        }
        $total = count($all_episodes);
        $progress_msg = sendMessage($chat_id, "📦 <b>Downloading All Seasons</b>\n\nSeries: $name\nTotal Episodes: $total\n\n⏳ Starting...");
        $progress_id = $progress_msg['result']['message_id'];
        $success = 0;
        $failed = 0;
        foreach ($all_episodes as $index => $movie) {
            $progress = round(($index / $total) * 100);
            editMessage($chat_id, $progress_id, "📦 <b>Downloading All Seasons</b>\n\nSeries: $name\nProgress: $progress%\nDownloaded: $index/$total\n✅ Success: $success\n❌ Failed: $failed");
            $result = deliver_item_to_chat($chat_id, $movie);
            if ($result) $success++; else $failed++;
            usleep(500000);
        }
        editMessage($chat_id, $progress_id, "✅ <b>Download Complete!</b>\n\n📺 Series: $name\n📊 Total: $total episodes\n✅ Success: $success\n❌ Failed: $failed");
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
        $progress_msg = sendMessage($chat_id, "📦 <b>Downloading Season $season_num</b>\n\nSeries: $series_name\nEpisodes: $total\n\n⏳ Starting...");
        $progress_id = $progress_msg['result']['message_id'];
        $success = 0;
        $failed = 0;
        $index = 0;
        foreach ($episodes as $ep_num => $movie) {
            $index++;
            $progress = round(($index / $total) * 100);
            editMessage($chat_id, $progress_id, "📦 <b>Downloading Season $season_num</b>\n\nSeries: $series_name\nEpisode: $ep_num/$total\nProgress: $progress%\n✅ Success: $success\n❌ Failed: $failed");
            $result = deliver_item_to_chat($chat_id, $movie);
            if ($result) $success++; else $failed++;
            usleep(500000);
        }
        editMessage($chat_id, $progress_id, "✅ <b>Download Complete!</b>\n\n📺 Series: $series_name\n📺 Season: $season_num\n📊 Episodes: $total\n✅ Success: $success\n❌ Failed: $failed");
    }
}

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
    
    private function count_items($folder) {
        $count = 0;
        if ($folder == 'Series') {
            foreach ($this->structure[$folder] as $series => $seasons) {
                foreach ($seasons as $season => $episodes) {
                    foreach ($episodes as $episode => $movies) {
                        $count += count($movies);
                    }
                }
            }
        } else {
            foreach ($this->structure[$folder] as $quality => $movies) {
                $count += count($movies);
            }
        }
        return $count;
    }
    
    public function show_root($chat_id) {
        $message = "📁 <b>Media Library</b>\n\n";
        $message .= "Select a folder:\n\n";
        $keyboard = ['inline_keyboard' => []];
        foreach (array_keys($this->structure) as $folder) {
            $count = $this->count_items($folder);
            $icon = $folder == 'Series' ? '📺' : '🎬';
            $keyboard['inline_keyboard'][] = [
                ['text' => "$icon $folder ($count items)", 'callback_data' => 'folder_open_' . base64_encode($folder)]
            ];
        }
        $keyboard['inline_keyboard'][] = [
            ['text' => '⬅️ Back to Root', 'callback_data' => 'folder_root'],
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
        $message = "📺 <b>Series</b>\n\n";
        $message .= "Total Series: " . count($series_list) . "\n\n";
        $keyboard = ['inline_keyboard' => []];
        foreach ($series_list as $series => $seasons) {
            $total_eps = 0;
            foreach ($seasons as $eps) {
                foreach ($eps as $ep_movies) {
                    $total_eps += count($ep_movies);
                }
            }
            $keyboard['inline_keyboard'][] = [
                ['text' => "📺 $series ($total_eps episodes)", 'callback_data' => 'folder_series_' . base64_encode($series)]
            ];
        }
        $keyboard['inline_keyboard'][] = [
            ['text' => '⬅️ Back to Folders', 'callback_data' => 'folder_back'],
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
        $message = "📺 <b>$series</b>\n\n";
        $message .= "Seasons: " . count($seasons) . "\n\n";
        $keyboard = ['inline_keyboard' => []];
        foreach ($seasons as $season => $episodes) {
            $ep_count = 0;
            foreach ($episodes as $eps) {
                $ep_count += count($eps);
            }
            $keyboard['inline_keyboard'][] = [
                ['text' => "$season ($ep_count episodes)", 'callback_data' => 'folder_season_' . base64_encode($series . '||' . $season)]
            ];
        }
        $keyboard['inline_keyboard'][] = [
            ['text' => '⬅️ Back to Series', 'callback_data' => 'folder_back_series'],
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
                $row[] = ['text' => "📼 Ep $ep_num", 'callback_data' => 'play_' . base64_encode($movie['message_id'] . '||' . $movie['channel_id'])];
                if (count($row) == 4) {
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
            ['text' => '⬅️ Back to Seasons', 'callback_data' => 'folder_series_' . base64_encode($series)],
            ['text' => '📥 Download All', 'callback_data' => 'folder_download_season_' . base64_encode($series . '||' . $season)]
        ];
        $keyboard['inline_keyboard'][] = [
            ['text' => '❌ Close', 'callback_data' => 'folder_close']
        ];
        sendMessage($chat_id, $message, $keyboard, 'HTML');
    }
    
    private function show_quality_folders($chat_id, $category) {
        $qualities = $this->structure[$category];
        $message = "🎬 <b>$category</b>\n\n";
        $message .= "Select quality:\n\n";
        $keyboard = ['inline_keyboard' => []];
        foreach ($qualities as $quality => $movies) {
            $keyboard['inline_keyboard'][] = [
                ['text' => "$quality (" . count($movies) . " movies)", 'callback_data' => 'folder_quality_' . base64_encode($category . '||' . $quality)]
            ];
        }
        $keyboard['inline_keyboard'][] = [
            ['text' => '⬅️ Back to Folders', 'callback_data' => 'folder_back'],
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
        $message = "🎬 <b>$category - $quality</b>\n\n";
        $message .= "Total Movies: " . count($movies) . "\n\n";
        $keyboard = ['inline_keyboard' => []];
        $row = [];
        foreach ($movies as $index => $movie) {
            $name = strlen($movie['movie_name']) > 30 ? substr($movie['movie_name'], 0, 27) . '...' : $movie['movie_name'];
            $row[] = ['text' => ($index+1) . ". " . $name, 'callback_data' => 'play_' . base64_encode($movie['message_id'] . '||' . $movie['channel_id'])];
            if (count($row) == 2) {
                $keyboard['inline_keyboard'][] = $row;
                $row = [];
            }
        }
        if (!empty($row)) {
            $keyboard['inline_keyboard'][] = $row;
        }
        $keyboard['inline_keyboard'][] = [
            ['text' => '⬅️ Back to Qualities', 'callback_data' => 'folder_open_' . base64_encode($category)],
            ['text' => '❌ Close', 'callback_data' => 'folder_close']
        ];
        sendMessage($chat_id, $message, $keyboard, 'HTML');
    }
}

class AutoDeleteManager {
    private $queue_file = DELETE_QUEUE_FILE;
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
        $time_str = $minutes ?? $this->default_time;
        $warning = "⚠️ <b>Auto-Delete Warning</b>\n\n";
        $warning .= "This file will be automatically deleted in <b>$time_str minutes</b> to save space.\n\n";
        $warning .= "⏰ <b>Delete Time:</b> " . date('h:i A', $delete_time) . "\n";
        $warning .= "💾 Save it now if you need it!";
        sendMessage($chat_id, $warning, null, 'HTML');
        bot_log("Scheduled delete for message $message_id in chat $chat_id at " . date('Y-m-d H:i:s', $delete_time));
        return $delete_time;
    }
    
    public function schedule_with_options($chat_id, $message_id) {
        $message = "⏰ <b>Auto-Delete Timer</b>\n\n";
        $message .= "Select when to delete this file:\n\n";
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '5 minutes', 'callback_data' => 'delete_set_5_' . $message_id],
                    ['text' => '15 minutes', 'callback_data' => 'delete_set_15_' . $message_id]
                ],
                [
                    ['text' => '30 minutes', 'callback_data' => 'delete_set_30_' . $message_id],
                    ['text' => '1 hour', 'callback_data' => 'delete_set_60_' . $message_id]
                ],
                [
                    ['text' => '2 hours', 'callback_data' => 'delete_set_120_' . $message_id],
                    ['text' => '6 hours', 'callback_data' => 'delete_set_360_' . $message_id]
                ],
                [
                    ['text' => '12 hours', 'callback_data' => 'delete_set_720_' . $message_id],
                    ['text' => '24 hours', 'callback_data' => 'delete_set_1440_' . $message_id]
                ],
                [
                    ['text' => '❌ Cancel', 'callback_data' => 'delete_cancel']
                ]
            ]
        ];
        sendMessage($chat_id, $message, $keyboard, 'HTML');
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
                $notice = "🗑️ <b>File Auto-Deleted</b>\n\n";
                $notice .= "This file has been automatically deleted as scheduled.\n\n";
                $notice .= "🔄 Use /search to request again if needed!";
                sendMessage($item['chat_id'], $notice, null, 'HTML');
                bot_log("Auto-deleted message {$item['message_id']} in chat {$item['chat_id']}");
            } elseif (!$item['warning_sent'] && $now >= ($item['delete_time'] - 300)) {
                $minutes_left = round(($item['delete_time'] - $now) / 60);
                $reminder = "⏰ <b>Reminder: File Will Be Deleted Soon</b>\n\n";
                $reminder .= "This file will be deleted in <b>$minutes_left minutes</b>.\n\n";
                $reminder .= "💾 Save it now if you haven't already!";
                sendMessage($item['chat_id'], $reminder, null, 'HTML');
                $item['warning_sent'] = true;
                $new_queue[] = $item;
            } else {
                $new_queue[] = $item;
            }
        }
        $this->save_queue($new_queue);
        if ($deleted_count > 0) {
            bot_log("Processed $deleted_count auto-deletions");
        }
        return $deleted_count;
    }
    
    public function show_status($chat_id) {
        $queue = $this->load_queue();
        $user_queue = array_filter($queue, fn($item) => $item['chat_id'] == $chat_id);
        if (empty($user_queue)) {
            sendMessage($chat_id, "📭 No files scheduled for auto-deletion.");
            return;
        }
        $message = "⏰ <b>Your Auto-Delete Schedule</b>\n\n";
        foreach ($user_queue as $item) {
            $time_left = $item['delete_time'] - time();
            $hours = floor($time_left / 3600);
            $minutes = floor(($time_left % 3600) / 60);
            $message .= "• Message ID: <code>{$item['message_id']}</code>\n";
            $message .= "  ⏳ Deletes in: " . ($hours > 0 ? "$hours hours " : "") . "$minutes minutes\n\n";
        }
        sendMessage($chat_id, $message, null, 'HTML');
    }
    
    public function cancel_delete($chat_id, $message_id) {
        $queue = $this->load_queue();
        $new_queue = array_filter($queue, fn($item) => !($item['chat_id'] == $chat_id && $item['message_id'] == $message_id));
        if (count($queue) == count($new_queue)) {
            sendMessage($chat_id, "❌ No auto-delete scheduled for this message.");
            return false;
        }
        $this->save_queue(array_values($new_queue));
        sendMessage($chat_id, "✅ Auto-delete cancelled for this message.");
        return true;
    }
}

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
        }
    }
    return false;
}

function get_direct_channel_link($message_id, $channel_id) {
    if (empty($channel_id)) {
        return "Channel ID not available";
    }
    $channel_id_clean = str_replace('-100', '', $channel_id);
    return "https://t.me/c/" . $channel_id_clean . "/" . $message_id;
}

function smart_search($query) {
    global $movie_messages;
    $query_lower = strtolower(trim($query));
    $results = array();
    $is_theater_search = false;
    $theater_keywords = ['theater', 'theatre', 'print', 'hdcam', 'camrip', 'hq', 'hdrip'];
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
            if (in_array($entry_channel_type, ['backup', 'private1', 'private2', 'other'])) {
                $score += 5;
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
                'has_main' => in_array('main', $channel_types),
                'has_backup' => in_array('backup', $channel_types),
                'has_private' => in_array('private1', $channel_types) || in_array('private2', $channel_types),
                'all_channels' => array_unique($channel_types)
            ];
        }
    }
    uasort($results, function($a, $b) {
        return $b['score'] - $a['score'];
    });
    return array_slice($results, 0, MAX_SEARCH_RESULTS);
}

function detect_language($text) {
    $hindi_keywords = ['फिल्म', 'मूवी', 'डाउनलोड', 'हिंदी', 'चाहिए', 'कहाँ', 'कैसे', 'खोज', 'तलाश'];
    $english_keywords = ['movie', 'download', 'watch', 'print', 'search', 'find', 'looking', 'want', 'need'];
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
            'found' => "✅ Mil gayi! Movie info bhej raha hoon...",
            'not_found' => "😔 Yeh movie abhi available nahi hai!\n\n📝 Aap ise request kar sakte hain: " . REQUEST_CHANNEL . "\n\n🔔 Jab bhi yeh add hogi, main automatically bhej dunga!",
            'searching' => "🔍 Dhoondh raha hoon... Zara wait karo",
            'multiple_found' => "🎯 Kai versions mili hain! Aap konsi chahte hain?",
            'request_success' => "✅ Request receive ho gayi! Hum jald hi add karenge.",
            'request_limit' => "❌ Aaj ke liye aap maximum " . DAILY_REQUEST_LIMIT . " requests hi kar sakte hain."
        ],
        'english' => [
            'welcome' => "🎬 Boss, which movie are you looking for?",
            'found' => "✅ Found it! Sending movie info...",
            'not_found' => "😔 This movie isn't available yet!\n\n📝 You can request it here: " . REQUEST_CHANNEL . "\n\n🔔 I'll send it automatically once it's added!",
            'searching' => "🔍 Searching... Please wait",
            'multiple_found' => "🎯 Multiple versions found! Which one do you want?",
            'request_success' => "✅ Request received! We'll add it soon.",
            'request_limit' => "❌ You've reached the daily limit of " . DAILY_REQUEST_LIMIT . " requests."
        ]
    ];
    sendMessage($chat_id, $responses[$language][$message_type]);
}

function advanced_search($chat_id, $query, $user_id = null) {
    global $movie_messages, $waiting_users;
    $q = strtolower(trim($query));
    if (strlen($q) < 2) {
        sendMessage($chat_id, "❌ Please enter at least 2 characters for search");
        return;
    }
    $filters = [];
    if ($user_id) {
        $filter = new MovieFilter($user_id);
        $filters = $filter->load_filter_session();
    }
    $invalid_keywords = [
        'vlc', 'audio', 'track', 'change', 'open', 'kar', 'me', 'hai',
        'how', 'what', 'problem', 'issue', 'help', 'solution', 'fix',
        'error', 'not working', 'download', 'play', 'video', 'sound',
        'subtitle', 'quality', 'hd', 'full', 'part', 'scene',
        'hi', 'hello', 'hey', 'good', 'morning', 'night', 'bye',
        'thanks', 'thank', 'ok', 'okay', 'yes', 'no', 'maybe',
        'who', 'when', 'where', 'why', 'how', 'can', 'should',
        'kaise', 'kya', 'kahan', 'kab', 'kyun', 'kon', 'kisne',
        'hai', 'hain', 'ho', 'raha', 'raha', 'rah', 'tha', 'thi',
        'mere', 'apne', 'tumhare', 'hamare', 'sab', 'log', 'group'
    ];
    $query_words = explode(' ', $q);
    $total_words = count($query_words);
    $invalid_count = 0;
    foreach ($query_words as $word) {
        if (in_array($word, $invalid_keywords)) {
            $invalid_count++;
        }
    }
    if ($invalid_count > 0 && ($invalid_count / $total_words) > 0.5) {
        $help_msg = "🎬 Please enter a movie name!\n\n";
        $help_msg .= "🔍 Examples of valid movie names:\n";
        $help_msg .= "• kgf\n• pushpa\n• avengers\n• hindi movie\n• spider-man\n\n";
        $help_msg .= "❌ Technical queries like 'vlc', 'audio track', etc. are not movie names.\n\n";
        $help_msg .= "📢 Join: " . MAIN_CHANNEL . "\n";
        $help_msg .= "💬 Help: " . REQUEST_CHANNEL;
        sendMessage($chat_id, $help_msg, null, 'HTML');
        return;
    }
    $movie_pattern = '/^[a-zA-Z0-9\s\-\.\,\&\+\(\)\:\'\"]+$/';
    if (!preg_match($movie_pattern, $query)) {
        sendMessage($chat_id, "❌ Invalid movie name format. Only letters, numbers, and basic punctuation allowed.");
        return;
    }
    $found = smart_search($q);
    if (!empty($filters) && !empty($found)) {
        $filtered_results = [];
        $filter_obj = new MovieFilter();
        foreach ($found as $movie => $data) {
            $movie_item = [
                'movie_name' => $movie,
                'quality' => $data['qualities'][0] ?? 'Unknown',
                'size' => $data['latest_entry']['size'] ?? 'Unknown'
            ];
            if ($filter_obj->apply_filters([$movie_item], $filters)) {
                $filtered_results[$movie] = $data;
            }
        }
        $found = $filtered_results;
    }
    if (!empty($found)) {
        update_stats('successful_searches', 1);
        $msg = "🔍 Found " . count($found) . " movies for '$query':\n\n";
        $i = 1;
        foreach ($found as $movie => $data) {
            $quality_info = !empty($data['qualities']) ? implode('/', $data['qualities']) : 'Unknown';
            $channel_info = "";
            if ($data['has_theater']) $channel_info .= "🎭 ";
            if ($data['has_main']) $channel_info .= "🍿 ";
            if ($data['has_backup']) $channel_info .= "🔒 ";
            if ($data['has_private']) $channel_info .= "🔐 ";
            $msg .= "$i. $movie ($channel_info" . $data['count'] . " versions, $quality_info)\n";
            $i++;
            if ($i > 10) break;
        }
        sendMessage($chat_id, $msg);
        $keyboard = ['inline_keyboard' => []];
        $top_movies = array_slice(array_keys($found), 0, 5);
        foreach ($top_movies as $movie) {
            $movie_data = $found[$movie];
            $channel_icon = '🍿';
            if ($movie_data['has_theater']) $channel_icon = '🎭';
            elseif ($movie_data['has_backup']) $channel_icon = '🔒';
            elseif ($movie_data['has_private']) $channel_icon = '🔐';
            $keyboard['inline_keyboard'][] = [[ 
                'text' => $channel_icon . ucwords($movie), 
                'callback_data' => $movie 
            ]];
        }
        $keyboard['inline_keyboard'][] = [[
            'text' => "📝 Request Different Movie", 
            'callback_data' => 'request_movie'
        ]];
        $keyboard['inline_keyboard'][] = [
            ['text' => '🔍 Filter Results', 'callback_data' => 'filter_menu']
        ];
        sendMessage($chat_id, "🚀 Top matches (click for info):", $keyboard);
        if ($user_id) {
            update_user_activity($user_id, 'found_movie');
            update_user_activity($user_id, 'search');
        }
    } else {
        update_stats('failed_searches', 1);
        $lang = detect_language($query);
        send_multilingual_response($chat_id, 'not_found', $lang);
        $request_keyboard = [
            'inline_keyboard' => [[
                ['text' => '📝 Request This Movie', 'callback_data' => 'auto_request_' . base64_encode($query)]
            ]]
        ];
        sendMessage($chat_id, "💡 Click below to automatically request this movie:", $request_keyboard);
        if (!isset($waiting_users[$q])) $waiting_users[$q] = [];
        $waiting_users[$q][] = [$chat_id, $user_id ?? $chat_id];
    }
    update_stats('total_searches', 1);
    if ($user_id) update_user_activity($user_id, 'search');
}

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
    $admin_msg = "🎯 New Movie Request\n\n";
    $admin_msg .= "🎬 Movie: $movie_name\n";
    $admin_msg .= "🗣️ Language: $language\n";
    $admin_msg .= "👤 User ID: $user_id\n";
    $admin_msg .= "📅 Date: " . date('Y-m-d H:i:s') . "\n";
    $admin_msg .= "🆔 Request ID: $request_id";
    sendMessage(ADMIN_ID, $admin_msg);
    bot_log("Movie request added: $movie_name by $user_id");
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
        sendMessage($chat_id, "📭 Aapne abhi tak koi movie request nahi ki hai!");
        return;
    }
    $message = "📝 <b>Your Movie Requests</b>\n\n";
    $i = 1;
    foreach (array_slice($user_requests, 0, 10) as $request) {
        $status_emoji = $request['status'] == 'completed' ? '✅' : '⏳';
        $message .= "$i. $status_emoji <b>" . htmlspecialchars($request['movie_name']) . "</b>\n";
        $message .= "   📅 " . $request['date'] . " | 🗣️ " . ucfirst($request['language']) . "\n";
        $message .= "   🆔 " . $request['id'] . "\n\n";
        $i++;
    }
    $pending_count = count(array_filter($user_requests, function($req) {
        return $req['status'] == 'pending';
    }));
    $message .= "📊 <b>Summary:</b>\n";
    $message .= "• Total Requests: " . count($user_requests) . "\n";
    $message .= "• Pending: $pending_count\n";
    $message .= "• Completed: " . (count($user_requests) - $pending_count);
    sendMessage($chat_id, $message, null, 'HTML');
}

function paginate_movies(array $all, int $page, array $filters = []): array {
    if (!empty($filters)) {
        $filter_obj = new MovieFilter();
        $all = $filter_obj->apply_filters($all, $filters);
    }
    $total = count($all);
    if ($total === 0) {
        return [
            'total' => 0,
            'total_pages' => 1, 
            'page' => 1,
            'slice' => [],
            'filters' => $filters,
            'has_next' => false,
            'has_prev' => false,
            'start_item' => 0,
            'end_item' => 0
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

function totalupload_controller($chat_id, $page = 1, $filters = [], $session_id = null) {
    $all = get_all_movies_list();
    if (empty($all)) {
        sendMessage($chat_id, "📭 Koi movies nahi mili! Pehle kuch movies add karo.");
        return;
    }
    if (!$session_id) {
        $session_id = uniqid('sess_', true);
    }
    $pg = paginate_movies($all, (int)$page, $filters);
    $title = "🎬 <b>Movie Browser</b>\n\n";
    $title .= "📊 <b>Statistics:</b>\n";
    $title .= "• Total Movies: <b>{$pg['total']}</b>\n";
    $title .= "• Page: <b>{$pg['page']}/{$pg['total_pages']}</b>\n";
    $title .= "• Items: <b>{$pg['start_item']}-{$pg['end_item']}</b>\n";
    if (!empty($filters)) {
        $title .= "• Filters: <b>" . count($filters) . " active</b>\n";
    }
    $title .= "\n";
    $title .= "📋 <b>Page {$page} Movies:</b>\n\n";
    $i = $pg['start_item'];
    foreach ($pg['slice'] as $movie) {
        $movie_name = htmlspecialchars($movie['movie_name'] ?? 'Unknown');
        $quality = $movie['quality'] ?? 'Unknown';
        $language = $movie['language'] ?? 'Hindi';
        $channel_icon = get_channel_display_name($movie['channel_type'] ?? 'main');
        $title .= "<b>{$i}.</b> $channel_icon {$movie_name}\n";
        $title .= "   🏷️ {$quality} | 🗣️ {$language}\n\n";
        $i++;
    }
    $kb = build_totalupload_keyboard($pg['page'], $pg['total_pages'], $session_id, $filters);
    delete_pagination_message($chat_id, $session_id);
    $result = sendMessage($chat_id, $title, $kb, 'HTML');
    save_pagination_message($chat_id, $session_id, $result['result']['message_id']);
    bot_log("Pagination - Chat: $chat_id, Page: $page, Session: " . substr($session_id, 0, 8));
}

function build_totalupload_keyboard(int $page, int $total_pages, string $session_id = '', array $filters = []): array {
    $kb = ['inline_keyboard' => []];
    $nav_row = [];
    if ($page > 1) {
        $nav_row[] = ['text' => '⏪', 'callback_data' => 'pag_first_' . $session_id];
        $nav_row[] = ['text' => '◀️', 'callback_data' => 'pag_prev_' . $page . '_' . $session_id];
    }
    $start_page = max(1, $page - 3);
    $end_page = min($total_pages, $start_page + 6);
    if ($end_page - $start_page < 6) {
        $start_page = max(1, $end_page - 6);
    }
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
    $action_row[] = ['text' => '👁️ Preview', 'callback_data' => 'prev_' . $page . '_' . $session_id];
    $action_row[] = ['text' => '📊 Stats', 'callback_data' => 'stats_' . $session_id];
    $kb['inline_keyboard'][] = $action_row;
    if (empty($filters)) {
        $filter_row = [];
        $filter_row[] = ['text' => '🎬 HD Only', 'callback_data' => 'flt_hd_' . $session_id];
        $filter_row[] = ['text' => '🎭 Theater Only', 'callback_data' => 'flt_theater_' . $session_id];
        $filter_row[] = ['text' => '🔒 Backup Only', 'callback_data' => 'flt_backup_' . $session_id];
        $kb['inline_keyboard'][] = $filter_row;
    } else {
        $filter_row = [];
        $filter_row[] = ['text' => '🧹 Clear Filter', 'callback_data' => 'flt_clr_' . $session_id];
        $kb['inline_keyboard'][] = $filter_row;
    }
    $ctrl_row = [];
    $ctrl_row[] = ['text' => '💾 Save', 'callback_data' => 'save_' . $session_id];
    $ctrl_row[] = ['text' => '🔍 Search', 'switch_inline_query_current_chat' => ''];
    $ctrl_row[] = ['text' => '❌ Close', 'callback_data' => 'close_' . $session_id];
    $kb['inline_keyboard'][] = $ctrl_row;
    return $kb;
}

function save_pagination_message($chat_id, $session_id, $message_id) {
    global $user_pagination_sessions;
    if (!isset($user_pagination_sessions[$session_id])) {
        $user_pagination_sessions[$session_id] = [];
    }
    $user_pagination_sessions[$session_id]['last_message_id'] = $message_id;
    $user_pagination_sessions[$session_id]['chat_id'] = $chat_id;
    $user_pagination_sessions[$session_id]['last_updated'] = time();
}

function delete_pagination_message($chat_id, $session_id) {
    global $user_pagination_sessions;
    if (isset($user_pagination_sessions[$session_id]) && isset($user_pagination_sessions[$session_id]['last_message_id'])) {
        $message_id = $user_pagination_sessions[$session_id]['last_message_id'];
        deleteMessage($chat_id, $message_id);
    }
}

function batch_download_with_progress($chat_id, $movies, $page_num) {
    $total = count($movies);
    if ($total === 0) return;
    $progress_msg = sendMessage($chat_id, "📦 <b>Batch Info Started</b>\n\nPage: {$page_num}\nTotal: {$total} movies\n\n⏳ Initializing...");
    $progress_id = $progress_msg['result']['message_id'];
    $success = 0;
    $failed = 0;
    for ($i = 0; $i < $total; $i++) {
        $movie = $movies[$i];
        if ($i % 2 == 0) {
            $progress = round(($i / $total) * 100);
            editMessage($chat_id, $progress_id, "📦 <b>Sending Page {$page_num} Info</b>\n\nProgress: {$progress}%\nProcessed: {$i}/{$total}\n✅ Success: {$success}\n❌ Failed: {$failed}\n\n⏳ Please wait...");
        }
        try {
            $result = deliver_item_to_chat($chat_id, $movie);
            if ($result) {
                $success++;
            } else {
                $failed++;
            }
        } catch (Exception $e) {
            $failed++;
        }
        usleep(500000);
    }
    editMessage($chat_id, $progress_id, "✅ <b>Batch Info Complete</b>\n\n📄 Page: {$page_num}\n🎬 Total: {$total} movies\n✅ Successfully sent: {$success}\n❌ Failed: {$failed}\n\n📊 Success rate: " . round(($success / $total) * 100, 2) . "%\n⏱️ Time: " . date('H:i:s'));
}

function auto_backup() {
    bot_log("Starting auto-backup process...");
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
            } else {
                bot_log("Backed up: $file");
            }
        }
    }
    $summary = "📊 BACKUP SUMMARY\n";
    $summary .= "================\n\n";
    $summary .= "📅 Backup Date: " . date('Y-m-d H:i:s') . "\n";
    $summary .= "🤖 Bot: Entertainment Tadka\n\n";
    $stats = get_stats();
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $requests_data = json_decode(file_get_contents(REQUEST_FILE), true);
    $summary .= "📈 STATISTICS:\n";
    $summary .= "• Total Movies: " . ($stats['total_movies'] ?? 0) . "\n";
    $summary .= "• Total Users: " . count($users_data['users'] ?? []) . "\n";
    $summary .= "• Total Searches: " . ($stats['total_searches'] ?? 0) . "\n";
    $summary .= "• Total Downloads: " . ($stats['total_downloads'] ?? 0) . "\n";
    $summary .= "• Pending Requests: " . count($requests_data['requests'] ?? []) . "\n\n";
    file_put_contents($backup_dir . '/backup_summary.txt', $summary);
    $old = glob(BACKUP_DIR . '*', GLOB_ONLYDIR);
    if (count($old) > 7) {
        usort($old, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        $deleted_count = 0;
        foreach (array_slice($old, 0, count($old) - 7) as $d) {
            $files = glob($d . '/*');
            foreach ($files as $ff) @unlink($ff);
            if (@rmdir($d)) $deleted_count++;
        }
        bot_log("Cleaned $deleted_count old backups");
    }
    bot_log("Auto-backup process completed");
    return $backup_success;
}

function manual_backup($chat_id) {
    if ($chat_id != ADMIN_ID) {
        sendMessage($chat_id, "❌ Access denied. Admin only command.");
        return;
    }
    $progress_msg = sendMessage($chat_id, "🔄 Starting manual backup...");
    try {
        $success = auto_backup();
        if ($success) {
            editMessage($chat_id, $progress_msg['result']['message_id'], "✅ Manual backup completed successfully!\n\n📊 Backup has been saved locally.");
        } else {
            editMessage($chat_id, $progress_msg['result']['message_id'], "⚠️ Backup completed with some warnings.\n\nSome files may not have been backed up properly. Check logs for details.");
        }
    } catch (Exception $e) {
        editMessage($chat_id, $progress_msg['result']['message_id'], "❌ Backup failed!\n\nError: " . $e->getMessage());
        bot_log("Manual backup failed: " . $e->getMessage(), 'ERROR');
    }
}

function backup_status($chat_id) {
    if ($chat_id != ADMIN_ID) {
        sendMessage($chat_id, "❌ Access denied. Admin only command.");
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
    $status_message = "💾 <b>Backup System Status</b>\n\n";
    $status_message .= "📊 <b>Storage Info:</b>\n";
    $status_message .= "• Total Backups: " . count($backup_dirs) . "\n";
    $status_message .= "• Storage Used: " . $total_size_mb . " MB\n";
    $status_message .= "• Backup Directory: " . BACKUP_DIR . "\n\n";
    if ($latest_backup) {
        $latest_time = date('Y-m-d H:i:s', filemtime($latest_backup));
        $status_message .= "🕒 <b>Latest Backup:</b>\n";
        $status_message .= "• Time: " . $latest_time . "\n";
        $status_message .= "• Folder: " . basename($latest_backup) . "\n\n";
    } else {
        $status_message .= "❌ <b>No backups found!</b>\n\n";
    }
    $status_message .= "⏰ <b>Auto-backup Schedule:</b>\n";
    $status_message .= "• Daily at " . AUTO_BACKUP_HOUR . ":00\n";
    $status_message .= "• Keep last 7 backups\n";
    sendMessage($chat_id, $status_message, null, 'HTML');
}

function show_channel_info($chat_id) {
    $message = "📢 <b>Join Our Channels</b>\n\n";
    $message .= "🍿 <b>Main Channel:</b> " . MAIN_CHANNEL . "\n";
    $message .= "• Latest movie updates\n";
    $message .= "• Daily new additions\n\n";
    $message .= "📥 <b>Requests Channel:</b> " . REQUEST_CHANNEL . "\n";
    $message .= "• Movie requests\n";
    $message .= "• Support & help\n\n";
    $message .= "🎭 <b>Theater Prints:</b> " . THEATER_CHANNEL . "\n";
    $message .= "• Theater quality prints\n\n";
    $message .= "📺 <b>Serial Channel:</b> " . SERIAL_CHANNEL . "\n";
    $message .= "• Web series episodes\n\n";
    $message .= "🔒 <b>Backup Channel:</b> " . BACKUP_CHANNEL . "\n";
    $message .= "• Secure data backups\n\n";
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '🍿 ' . MAIN_CHANNEL, 'url' => 'https://t.me/EntertainmentTadka786'],
                ['text' => '📥 ' . REQUEST_CHANNEL, 'url' => 'https://t.me/EntertainmentTadka7860']
            ],
            [
                ['text' => '🎭 ' . THEATER_CHANNEL, 'url' => 'https://t.me/threater_print_movies'],
                ['text' => '📺 ' . SERIAL_CHANNEL, 'url' => 'https://t.me/Entertainment_Tadka_Serial_786']
            ],
            [
                ['text' => '🔒 ' . BACKUP_CHANNEL, 'url' => 'https://t.me/ETBackup']
            ]
        ]
    ];
    sendMessage($chat_id, $message, $keyboard, 'HTML');
}

function show_user_stats($chat_id, $user_id) {
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $user = $users_data['users'][$user_id] ?? null;
    if (!$user) {
        sendMessage($chat_id, "❌ User data not found!");
        return;
    }
    $message = "👤 <b>Your Statistics</b>\n\n";
    $message .= "🆔 User ID: <code>$user_id</code>\n";
    $message .= "📅 Joined: " . ($user['joined'] ?? 'N/A') . "\n";
    $message .= "🕒 Last Active: " . ($user['last_active'] ?? 'N/A') . "\n\n";
    $message .= "📊 <b>Activity:</b>\n";
    $message .= "• 🔍 Searches: " . ($user['total_searches'] ?? 0) . "\n";
    $message .= "• 📥 Downloads: " . ($user['total_downloads'] ?? 0) . "\n";
    $message .= "• 📝 Requests: " . ($user['request_count'] ?? 0) . "\n";
    $message .= "• ⭐ Points: " . ($user['points'] ?? 0) . "\n\n";
    $message .= "🎯 <b>Rank:</b> " . calculate_user_rank($user['points'] ?? 0);
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '📈 Leaderboard', 'callback_data' => 'show_leaderboard'],
                ['text' => '🔄 Refresh', 'callback_data' => 'refresh_stats']
            ]
        ]
    ];
    sendMessage($chat_id, $message, $keyboard, 'HTML');
}

function show_leaderboard($chat_id) {
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $users = $users_data['users'] ?? [];
    if (empty($users)) {
        sendMessage($chat_id, "📭 Koi user data nahi mila!");
        return;
    }
    uasort($users, function($a, $b) {
        return ($b['points'] ?? 0) - ($a['points'] ?? 0);
    });
    $message = "🏆 <b>Top Users Leaderboard</b>\n\n";
    $i = 1;
    foreach (array_slice($users, 0, 10) as $user_id => $user) {
        $points = $user['points'] ?? 0;
        $username = $user['username'] ? "@" . $user['username'] : "User#" . substr($user_id, -4);
        $medal = $i == 1 ? "🥇" : ($i == 2 ? "🥈" : ($i == 3 ? "🥉" : "🔸"));
        $message .= "$medal $i. $username\n";
        $message .= "   ⭐ $points points | 🎯 " . calculate_user_rank($points) . "\n\n";
        $i++;
    }
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '📊 My Stats', 'callback_data' => 'my_stats'],
                ['text' => '🔄 Refresh', 'callback_data' => 'refresh_leaderboard']
            ]
        ]
    ];
    sendMessage($chat_id, $message, $keyboard, 'HTML');
}

function calculate_user_rank($points) {
    if ($points >= 1000) return "🎖️ Elite";
    if ($points >= 500) return "🔥 Pro";
    if ($points >= 250) return "⭐ Advanced";
    if ($points >= 100) return "🚀 Intermediate";
    if ($points >= 50) return "👍 Beginner";
    return "🌱 Newbie";
}

function show_latest_movies($chat_id, $limit = 10) {
    $all_movies = get_all_movies_list();
    $latest_movies = array_slice($all_movies, -$limit);
    $latest_movies = array_reverse($latest_movies);
    if (empty($latest_movies)) {
        sendMessage($chat_id, "📭 Koi movies nahi mili!");
        return;
    }
    $message = "🎬 <b>Latest $limit Movies</b>\n\n";
    $i = 1;
    foreach ($latest_movies as $movie) {
        $channel_icon = get_channel_display_name($movie['channel_type'] ?? 'main');
        $message .= "$i. $channel_icon <b>" . htmlspecialchars($movie['movie_name']) . "</b>\n";
        $message .= "   📊 " . ($movie['quality'] ?? 'Unknown') . " | 🗣️ " . ($movie['language'] ?? 'Hindi') . "\n";
        $message .= "   📅 " . ($movie['date'] ?? 'N/A') . "\n\n";
        $i++;
    }
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '📥 Get All Latest Info', 'callback_data' => 'download_latest'],
                ['text' => '📊 Browse All', 'callback_data' => 'browse_all']
            ]
        ]
    ];
    sendMessage($chat_id, $message, $keyboard, 'HTML');
}

function show_trending_movies($chat_id) {
    $all_movies = get_all_movies_list();
    $trending_movies = array_slice($all_movies, -15);
    if (empty($trending_movies)) {
        sendMessage($chat_id, "📭 Koi trending movies nahi mili!");
        return;
    }
    $message = "🔥 <b>Trending Movies</b>\n\n";
    $i = 1;
    foreach (array_slice($trending_movies, 0, 10) as $movie) {
        $channel_icon = get_channel_display_name($movie['channel_type'] ?? 'main');
        $message .= "$i. $channel_icon <b>" . htmlspecialchars($movie['movie_name']) . "</b>\n";
        $message .= "   ⭐ " . ($movie['quality'] ?? 'HD') . " | 🗣️ " . ($movie['language'] ?? 'Hindi') . "\n\n";
        $i++;
    }
    $message .= "💡 <i>Based on recent popularity and downloads</i>";
    sendMessage($chat_id, $message, null, 'HTML');
}

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
    $message = "📊 <b>Movie Database</b>\n\n";
    $message .= "📁 Total Movies: " . count($movies) . "\n";
    $message .= "📋 Format: movie_name, message_id, channel_id\n\n";
    $i = 1;
    foreach ($movies as $movie) {
        $movie_name = $movie[0] ?? 'N/A';
        $message_id = $movie[1] ?? 'N/A';
        $channel_id = $movie[2] ?? 'N/A';
        if (strlen($movie_name) > 50) {
            $movie_name = substr($movie_name, 0, 47) . '...';
        }
        $message .= "$i. <code>" . htmlspecialchars($movie_name) . "</code>\n";
        $message .= "   📝 ID: $message_id | 📢 Channel: $channel_id\n\n";
        $i++;
        if (strlen($message) > 3000) {
            sendMessage($chat_id, $message, null, 'HTML');
            $message = "📊 Continuing...\n\n";
        }
    }
    $message .= "💾 File: " . CSV_FILE . "\n";
    $message .= "⏰ Last Updated: " . date('Y-m-d H:i:s', filemtime(CSV_FILE));
    sendMessage($chat_id, $message, null, 'HTML');
}

function append_movie($movie_name, $message_id, $channel_id) {
    global $movie_messages, $movie_cache, $waiting_users;
    if (empty(trim($movie_name))) return;
    $entry = [$movie_name, $message_id, $channel_id];
    $handle = fopen(CSV_FILE, "a");
    fputcsv($handle, $entry);
    fclose($handle);
    $channel_type = get_channel_type_by_id($channel_id);
    $quality = 'Unknown';
    if (preg_match('/(2160p|1440p|1080p|720p|576p|480p|360p|4k|uhd|fhd|hd)/i', $movie_name, $matches)) {
        $quality = $matches[0];
    }
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
        $notification_msg .= "🎬 <b>$movie_name</b> has been added!\n\n";
        $notification_msg .= "📢 Join: " . get_channel_username_link($channel_type);
        sendMessage(MAIN_CHANNEL_ID, $notification_msg, null, 'HTML');
        foreach ($waiting_users[$movie_key] as $user_data) {
            list($user_chat_id, $user_id) = $user_data;
            sendMessage($user_chat_id, "🎉 Your requested movie <b>$movie_name</b> has been added!", null, 'HTML');
        }
        unset($waiting_users[$movie_key]);
    }
    update_stats('total_movies', 1);
    bot_log("Movie appended: $movie_name (ID: $message_id) in channel $channel_id");
}

function get_channel_username_link($channel_type) {
    switch ($channel_type) {
        case 'main':
            return "https://t.me/" . ltrim(MAIN_CHANNEL, '@');
        case 'theater':
            return "https://t.me/" . ltrim(THEATER_CHANNEL, '@');
        case 'serial':
            return "https://t.me/" . ltrim(SERIAL_CHANNEL, '@');
        case 'backup':
            return "https://t.me/" . ltrim(BACKUP_CHANNEL, '@');
        default:
            return "https://t.me/EntertainmentTadka786";
    }
}

function scanOldPosts($admin_chat_id = null) {
    $channels = [
        MAIN_CHANNEL_ID,
        SERIAL_CHANNEL_ID,
        THEATER_CHANNEL_ID,
        BACKUP_CHANNEL_ID,
        PRIVATE_CHANNEL_1_ID,
        PRIVATE_CHANNEL_2_ID,
    ];
    $channel_names = [
        MAIN_CHANNEL_ID => 'Main Channel',
        SERIAL_CHANNEL_ID => 'Serial Channel',
        THEATER_CHANNEL_ID => 'Theater Channel',
        BACKUP_CHANNEL_ID => 'Backup Channel',
        PRIVATE_CHANNEL_1_ID => 'Private Channel 1',
        PRIVATE_CHANNEL_2_ID => 'Private Channel 2',
    ];
    bot_log("Starting old post scanner...");
    if ($admin_chat_id) {
        sendMessage($admin_chat_id, "🔄 <b>Starting Old Post Scanner</b>\n\nScanning all channels...");
    }
    $all_movies = loadMovies();
    $existing_count = count($all_movies);
    $new_movies = 0;
    $scanned = 0;
    foreach ($channels as $channel_id) {
        $channel_name = $channel_names[$channel_id] ?? "Channel $channel_id";
        bot_log("Scanning channel: $channel_name ($channel_id)");
        if ($admin_chat_id) {
            sendMessage($admin_chat_id, "📡 Scanning: <b>$channel_name</b>");
        }
        $offset = 0;
        $channel_movies = 0;
        while (true) {
            try {
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
                    $scanned++;
                    if (isset($msg['video']) || isset($msg['document'])) {
                        $title = isset($msg['caption']) ? trim($msg['caption']) : 'Unknown Media';
                        $exists = false;
                        foreach ($all_movies as $existing) {
                            if ($existing['message_id'] == $msg['message_id'] && $existing['channel_id'] == $channel_id) {
                                $exists = true;
                                break;
                            }
                        }
                        if (!$exists) {
                            $all_movies[] = [
                                'title' => $title,
                                'message_id' => $msg['message_id'],
                                'channel_id' => $channel_id
                            ];
                            $new_movies++;
                            $channel_movies++;
                        }
                    }
                }
                $offset += 100;
                usleep(500000);
            } catch (Exception $e) {
                bot_log("Error scanning $channel_name: " . $e->getMessage(), 'ERROR');
                break;
            }
        }
        bot_log("Channel $channel_name: Found $channel_movies new movies");
    }
    if ($new_movies > 0) {
        saveMovies($all_movies);
    }
    $message = "✅ <b>Scan Complete!</b>\n\n";
    $message .= "📊 <b>Results:</b>\n";
    $message .= "• Total Scanned: <b>$scanned</b> messages\n";
    $message .= "• Existing Movies: <b>$existing_count</b>\n";
    $message .= "• New Movies Found: <b>$new_movies</b>\n";
    $message .= "• Total Movies Now: <b>" . count($all_movies) . "</b>\n\n";
    if ($new_movies > 0) {
        $message .= "✅ Database updated successfully!";
    } else {
        $message .= "📭 No new movies found!";
    }
    bot_log("Scan completed: $new_movies new movies found");
    if ($admin_chat_id) {
        sendMessage($admin_chat_id, $message, null, 'HTML');
    }
    return [
        'total' => count($all_movies),
        'new' => $new_movies,
        'scanned' => $scanned
    ];
}

function autoIndex($update) {
    $channels = [
        MAIN_CHANNEL_ID,
        SERIAL_CHANNEL_ID,
        THEATER_CHANNEL_ID,
        BACKUP_CHANNEL_ID,
        PRIVATE_CHANNEL_1_ID,
        PRIVATE_CHANNEL_2_ID,
    ];
    if (!isset($update['channel_post'])) {
        return;
    }
    $msg = $update['channel_post'];
    $chat_id = $msg['chat']['id'];
    if (!in_array($chat_id, $channels)) {
        return;
    }
    if (isset($msg['video']) || isset($msg['document'])) {
        $title = isset($msg['caption']) ? trim($msg['caption']) : 'Unknown Media';
        $movies = loadMovies();
        $exists = false;
        foreach ($movies as $movie) {
            if ($movie['message_id'] == $msg['message_id'] && $movie['channel_id'] == $chat_id) {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            $movies[] = [
                'title' => $title,
                'message_id' => $msg['message_id'],
                'channel_id' => $chat_id
            ];
            saveMovies($movies);
            bot_log("Auto-indexed: $title in channel $chat_id");
        }
    }
}

function generate_channel_caption($movie_name, $style = 'default') {
    $caption = "🎬 <b>" . htmlspecialchars($movie_name) . "</b>\n\n";
    $caption .= "🔥 <b>Channels:</b>\n";
    $caption .= "🍿 <b>Main:</b> <code>@EntertainmentTadka786</code>\n";
    $caption .= "📥 <b>Request:</b> <code>@EntertainmentTadka7860</code>\n";
    $caption .= "🎭 <b>Theater:</b> <code>@threater_print_movies</code>\n";
    $caption .= "📺 <b>Serial:</b> <code>@Entertainment_Tadka_Serial_786</code>\n";
    $caption .= "📂 <b>Backup:</b> <code>@ETBackup</code>";
    return $caption;
}

function send_broadcast($chat_id, $message) {
    if ($chat_id != ADMIN_ID) {
        sendMessage($chat_id, "❌ Access denied. Admin only command.");
        return;
    }
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $total_users = count($users_data['users'] ?? []);
    $success_count = 0;
    $progress_msg = sendMessage($chat_id, "📢 Broadcasting to $total_users users...\n\nProgress: 0%");
    $progress_msg_id = $progress_msg['result']['message_id'];
    $i = 0;
    foreach ($users_data['users'] as $user_id => $user) {
        try {
            sendMessage($user_id, "📢 <b>Announcement from Admin:</b>\n\n$message", null, 'HTML');
            $success_count++;
            if ($i % 10 === 0) {
                $progress = round(($i / $total_users) * 100);
                editMessage($chat_id, $progress_msg_id, "📢 Broadcasting to $total_users users...\n\nProgress: $progress%");
            }
            usleep(100000);
            $i++;
        } catch (Exception $e) {}
    }
    editMessage($chat_id, $progress_msg_id, "✅ Broadcast completed!\n\n📊 Sent to: $success_count/$total_users users");
    bot_log("Broadcast sent by $chat_id to $success_count users");
}

function toggle_maintenance_mode($chat_id, $mode) {
    global $MAINTENANCE_MODE;
    if ($chat_id != ADMIN_ID) {
        sendMessage($chat_id, "❌ Access denied. Admin only command.");
        return;
    }
    if ($mode == 'on') {
        $MAINTENANCE_MODE = true;
        sendMessage($chat_id, "🔧 Maintenance mode ENABLED\n\nBot is now in maintenance mode. Users will see maintenance message.");
        bot_log("Maintenance mode enabled by $chat_id");
    } elseif ($mode == 'off') {
        $MAINTENANCE_MODE = false;
        sendMessage($chat_id, "✅ Maintenance mode DISABLED\n\nBot is now operational.");
        bot_log("Maintenance mode disabled by $chat_id");
    } else {
        sendMessage($chat_id, "❌ Usage: <code>/maintenance on</code> or <code>/maintenance off</code>", null, 'HTML');
    }
}

function perform_cleanup($chat_id) {
    if ($chat_id != ADMIN_ID) {
        sendMessage($chat_id, "❌ Access denied. Admin only command.");
        return;
    }
    $old = glob(BACKUP_DIR . '*', GLOB_ONLYDIR);
    if (count($old) > 7) {
        usort($old, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        $deleted_count = 0;
        foreach (array_slice($old, 0, count($old) - 7) as $d) {
            $files = glob($d . '/*');
            foreach ($files as $ff) @unlink($ff);
            if (@rmdir($d)) $deleted_count++;
        }
    }
    global $movie_cache;
    $movie_cache = [];
    sendMessage($chat_id, "🧹 Cleanup completed!\n\n• Old backups removed\n• Cache cleared\n• System optimized");
    bot_log("Cleanup performed by $chat_id");
}

function submit_bug_report($chat_id, $user_id, $bug_report) {
    $report_id = uniqid();
    $admin_message = "🐛 <b>New Bug Report</b>\n\n";
    $admin_message .= "🆔 Report ID: $report_id\n";
    $admin_message .= "👤 User ID: $user_id\n";
    $admin_message .= "📅 Time: " . date('Y-m-d H:i:s') . "\n\n";
    $admin_message .= "📝 <b>Bug Description:</b>\n$bug_report";
    sendMessage(ADMIN_ID, $admin_message, null, 'HTML');
    sendMessage($chat_id, "✅ Bug report submitted!\n\n🆔 Report ID: <code>$report_id</code>\n\nWe'll fix it soon! 🛠️", null, 'HTML');
    bot_log("Bug report submitted by $user_id: $report_id");
}

function submit_feedback($chat_id, $user_id, $feedback) {
    $feedback_id = uniqid();
    $admin_message = "💡 <b>New User Feedback</b>\n\n";
    $admin_message .= "🆔 Feedback ID: $feedback_id\n";
    $admin_message .= "👤 User ID: $user_id\n";
    $admin_message .= "📅 Time: " . date('Y-m-d H:i:s') . "\n\n";
    $admin_message .= "📝 <b>Feedback:</b>\n$feedback";
    sendMessage(ADMIN_ID, $admin_message, null, 'HTML');
    sendMessage($chat_id, "✅ Feedback submitted!\n\n🆔 Feedback ID: <code>$feedback_id</code>\n\nThanks for your input! 🌟", null, 'HTML');
    bot_log("Feedback submitted by $user_id: $feedback_id");
}

function show_bot_info($chat_id) {
    $stats = get_stats();
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $message = "🤖 <b>Entertainment Tadka Bot</b>\n\n";
    $message .= "📱 <b>Version:</b> 2.0.0\n";
    $message .= "🆙 <b>Last Updated:</b> " . date('Y-m-d') . "\n";
    $message .= "👨‍💻 <b>Developer:</b> @EntertainmentTadka0786\n\n";
    $message .= "📊 <b>Bot Statistics:</b>\n";
    $message .= "• 🎬 Movies: " . ($stats['total_movies'] ?? 0) . "\n";
    $message .= "• 👥 Users: " . count($users_data['users'] ?? []) . "\n";
    $message .= "• 🔍 Searches: " . ($stats['total_searches'] ?? 0) . "\n";
    $message .= "• 📥 Downloads: " . ($stats['total_downloads'] ?? 0) . "\n\n";
    $message .= "📢 <b>Channels:</b>\n";
    $message .= "• Main: " . MAIN_CHANNEL . "\n";
    $message .= "• Support: " . REQUEST_CHANNEL . "\n";
    $message .= "• Theater: " . THEATER_CHANNEL . "\n";
    $message .= "• Serial: " . SERIAL_CHANNEL . "\n";
    $message .= "• Backup: " . BACKUP_CHANNEL;
    sendMessage($chat_id, $message, null, 'HTML');
}

function is_valid_movie_query($text) {
    $text = strtolower(trim($text));
    if (strpos($text, '/') === 0) {
        return true;
    }
    if (strlen($text) < 3) {
        return false;
    }
    $invalid_phrases = [
        'good morning', 'good night', 'hello', 'hi ', 'hey ', 'thank you', 'thanks',
        'welcome', 'bye', 'see you', 'ok ', 'okay', 'yes', 'no', 'maybe',
        'how are you', 'whats up', 'anyone', 'someone', 'everyone',
        'problem', 'issue', 'help', 'question', 'doubt', 'query'
    ];
    foreach ($invalid_phrases as $phrase) {
        if (strpos($text, $phrase) !== false) {
            return false;
        }
    }
    $movie_patterns = [
        'movie', 'film', 'video', 'download', 'watch', 'hd', 'full', 'part',
        'series', 'episode', 'season', 'bollywood', 'hollywood',
        'theater', 'theatre', 'print', 'hdcam', 'camrip'
    ];
    foreach ($movie_patterns as $pattern) {
        if (strpos($text, $pattern) !== false) {
            return true;
        }
    }
    if (preg_match('/^[a-zA-Z0-9\s\-\.\,]{3,}$/', $text)) {
        return true;
    }
    return false;
}

function handle_command($chat_id, $user_id, $command, $params = []) {
    switch ($command) {
        case '/start':
            $welcome = "🎬 Welcome to Entertainment Tadka Bot!\n\n";
            $welcome .= "📢 <b>Our Channels:</b>\n";
            $welcome .= "🍿 Main: @EntertainmentTadka786\n";
            $welcome .= "📺 Serials: @Entertainment_Tadka_Serial_786\n";
            $welcome .= "🎭 Theater: @threater_print_movies\n";
            $welcome .= "🔒 Backup: @ETBackup\n";
            $welcome .= "📝 Requests: @EntertainmentTadka7860\n\n";
            $welcome .= "🔍 <b>How to use:</b>\n";
            $welcome .= "• Simply type any movie/serial name\n";
            $welcome .= "• Use /help for all commands\n\n";
            $welcome .= "🎯 <b>Examples:</b>\n";
            $welcome .= "• Mandala Murders 2025\n";
            $welcome .= "• Lokah Chapter 1\n";
            $welcome .= "• kgf theater print";
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '🍿 Main', 'url' => 'https://t.me/EntertainmentTadka786'],
                        ['text' => '📺 Serials', 'url' => 'https://t.me/Entertainment_Tadka_Serial_786']
                    ],
                    [
                        ['text' => '🎭 Theater', 'url' => 'https://t.me/threater_print_movies'],
                        ['text' => '🔒 Backup', 'url' => 'https://t.me/ETBackup']
                    ],
                    [
                        ['text' => '📝 Requests', 'url' => 'https://t.me/EntertainmentTadka7860'],
                        ['text' => '⚙️ Settings', 'callback_data' => 'settings_back']
                    ],
                    [
                        ['text' => '❓ Help', 'callback_data' => 'help_command']
                    ]
                ]
            ];
            if ($user_id == ADMIN_ID) {
                $keyboard['inline_keyboard'][] = [
                    ['text' => '🔐 Admin Panel', 'callback_data' => 'admin_main']
                ];
            }
            sendMessage($chat_id, $welcome, $keyboard, 'HTML');
            update_user_activity($user_id, 'daily_login');
            break;
            
        case '/help':
            $help = "🤖 <b>Available Commands</b>\n\n";
            $help .= "👤 <b>User Commands:</b>\n";
            $help .= "/start - Start bot\n";
            $help .= "/help - This menu\n";
            $help .= "/settings - Customize preferences\n";
            $help .= "/filter - Filter movies\n";
            $help .= "/series - Series list\n";
            $help .= "/delete - Set auto-delete\n";
            $help .= "/deletestatus - Check delete schedule\n";
            $help .= "/library - Folder navigation\n\n";
            $help .= "🔍 <b>Search:</b>\n";
            $help .= "/search [name] - Search movies\n";
            $help .= "/totalupload - Browse all\n\n";
            $help .= "📝 <b>Requests:</b>\n";
            $help .= "/request [name] - Request movie\n";
            $help .= "/myrequests - Your requests\n\n";
            $help .= "👑 <b>User Stats:</b>\n";
            $help .= "/mystats - Your statistics\n";
            $help .= "/leaderboard - Top users\n\n";
            $help .= "📢 <b>Channels:</b>\n";
            $help .= "/channel - All channels info\n\n";
            if ($user_id == ADMIN_ID) {
                $help .= "🔧 <b>Admin Commands:</b>\n";
                $help .= "/admin - Admin panel\n";
                $help .= "/stats - Bot statistics\n";
                $help .= "/users - User statistics\n";
                $help .= "/backup - Full backup\n";
                $help .= "/backupstatus - Backup status\n";
                $help .= "/broadcast [msg] - Broadcast message\n";
                $help .= "/maintenance on/off - Toggle maintenance\n\n";
                $help .= "👑 <b>Owner Commands:</b>\n";
                $help .= "/panicon - Emergency panic mode\n";
                $help .= "/panicoff - Deactivate panic mode\n";
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
            $msg_id = $params[0] ?? null;
            if ($msg_id && is_numeric($msg_id)) {
                $delete = new AutoDeleteManager();
                $delete->schedule_with_options($chat_id, $msg_id);
            } else {
                sendMessage($chat_id, "❌ Usage: /delete [message_id]\nExample: /delete 1234");
            }
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
                sendMessage($chat_id, "❌ Usage: /search movie_name\nExample: /search kgf 2", null, 'HTML');
                return;
            }
            $lang = detect_language($movie_name);
            send_multilingual_response($chat_id, 'searching', $lang);
            advanced_search($chat_id, $movie_name, $user_id);
            break;
            
        case '/totalupload':
            $page = isset($params[0]) ? intval($params[0]) : 1;
            totalupload_controller($chat_id, $page);
            break;
            
        case '/latest':
            show_latest_movies($chat_id, 10);
            break;
            
        case '/trending':
            show_trending_movies($chat_id);
            break;
            
        case '/request':
            $movie_name = implode(' ', $params);
            if (empty($movie_name)) {
                sendMessage($chat_id, "❌ Usage: /request movie_name\nExample: /request Animal Park", null, 'HTML');
                return;
            }
            $lang = detect_language($movie_name);
            if (add_movie_request($user_id, $movie_name, $lang)) {
                send_multilingual_response($chat_id, 'request_success', $lang);
                update_user_activity($user_id, 'movie_request');
            } else {
                send_multilingual_response($chat_id, 'request_limit', $lang);
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
            
        case '/admin':
        case '/panel':
            if ($user_id != ADMIN_ID) {
                sendMessage($chat_id, "❌ Access denied. Admin only command.");
                break;
            }
            $admin = new AdminPanel($chat_id);
            $admin->show_main_panel($chat_id);
            break;
            
        case '/stats':
            if ($user_id != ADMIN_ID) {
                sendMessage($chat_id, "❌ Access denied. Admin only command.");
                break;
            }
            $stats = get_stats();
            $users_data = json_decode(file_get_contents(USERS_FILE), true);
            $total_users = count($users_data['users'] ?? []);
            $msg = "📊 <b>Bot Statistics</b>\n\n";
            $msg .= "🎬 Total Movies: " . ($stats['total_movies'] ?? 0) . "\n";
            $msg .= "👥 Total Users: " . $total_users . "\n";
            $msg .= "🔍 Total Searches: " . ($stats['total_searches'] ?? 0) . "\n";
            $msg .= "✅ Successful Searches: " . ($stats['successful_searches'] ?? 0) . "\n";
            $msg .= "❌ Failed Searches: " . ($stats['failed_searches'] ?? 0) . "\n";
            $msg .= "📥 Total Downloads: " . ($stats['total_downloads'] ?? 0) . "\n";
            $msg .= "🕒 Last Updated: " . ($stats['last_updated'] ?? 'N/A');
            sendMessage($chat_id, $msg, null, 'HTML');
            break;
            
        case '/users':
            if ($user_id != ADMIN_ID) {
                sendMessage($chat_id, "❌ Access denied. Admin only command.");
                break;
            }
            $users_data = json_decode(file_get_contents(USERS_FILE), true);
            $total_users = count($users_data['users'] ?? []);
            $active_today = 0;
            $active_week = 0;
            $today_start = strtotime('today');
            $week_start = strtotime('-7 days');
            foreach ($users_data['users'] ?? [] as $uid => $user) {
                $last_active = strtotime($user['last_active'] ?? '');
                if ($last_active >= $today_start) $active_today++;
                if ($last_active >= $week_start) $active_week++;
            }
            $message = "👥 <b>User Statistics</b>\n\n";
            $message .= "📊 <b>Total Users:</b> $total_users\n";
            $message .= "✅ <b>Active Today:</b> $active_today\n";
            $message .= "📅 <b>Active This Week:</b> $active_week\n\n";
            $users = $users_data['users'] ?? [];
            uasort($users, fn($a, $b) => ($b['points'] ?? 0) - ($a['points'] ?? 0));
            $top_users = array_slice($users, 0, 5, true);
            $message .= "🏆 <b>Top Users:</b>\n";
            $i = 1;
            foreach ($top_users as $uid => $user) {
                $name = $user['username'] ? "@" . $user['username'] : "User" . substr($uid, -4);
                $message .= "$i. $name - ⭐ " . ($user['points'] ?? 0) . " pts\n";
                $i++;
            }
            sendMessage($chat_id, $message, null, 'HTML');
            break;
            
        case '/backup':
            if ($user_id != ADMIN_ID) {
                sendMessage($chat_id, "❌ Access denied. Admin only command.");
                break;
            }
            manual_backup($chat_id);
            break;
            
        case '/backupstatus':
            if ($user_id != ADMIN_ID) {
                sendMessage($chat_id, "❌ Access denied. Admin only command.");
                break;
            }
            backup_status($chat_id);
            break;
            
        case '/broadcast':
            if ($user_id != ADMIN_ID) {
                sendMessage($chat_id, "❌ Access denied. Admin only command.");
                break;
            }
            $message = implode(' ', $params);
            if (empty($message)) {
                sendMessage($chat_id, "❌ Usage: /broadcast your_message", null, 'HTML');
                return;
            }
            send_broadcast($chat_id, $message);
            break;
            
        case '/maintenance':
            if ($user_id != ADMIN_ID) {
                sendMessage($chat_id, "❌ Access denied. Admin only command.");
                break;
            }
            $mode = isset($params[0]) ? strtolower($params[0]) : '';
            toggle_maintenance_mode($chat_id, $mode);
            break;
            
        case '/panicon':
            if ($user_id != ADMIN_ID) {
                sendMessage($chat_id, "❌ Access denied. Admin only command.");
                break;
            }
            global $MAINTENANCE_MODE;
            $MAINTENANCE_MODE = true;
            file_put_contents('PANIC_MODE.active', date('Y-m-d H:i:s'));
            sendMessage($chat_id, "🚨 <b>PANIC MODE ACTIVATED!</b>\n\nBot is now in emergency mode. Only admin can use bot.\nUse /panicoff to deactivate.");
            bot_log("PANIC MODE ACTIVATED by $user_id", 'ALERT');
            break;
            
        case '/panicoff':
            if ($user_id != ADMIN_ID) {
                sendMessage($chat_id, "❌ Access denied. Admin only command.");
                break;
            }
            global $MAINTENANCE_MODE;
            $MAINTENANCE_MODE = false;
            if (file_exists('PANIC_MODE.active')) {
                unlink('PANIC_MODE.active');
            }
            sendMessage($chat_id, "✅ <b>Panic Mode Deactivated</b>\n\nBot is back to normal operation.");
            bot_log("PANIC MODE DEACTIVATED by $user_id", 'INFO');
            break;
            
        default:
            sendMessage($chat_id, "❌ Unknown command. Use /help to see available commands.", null, 'HTML');
    }
}

class AdminPanel {
    private $admin_id;
    
    function __construct($admin_id) {
        $this->admin_id = $admin_id;
    }
    
    public function show_main_panel($chat_id) {
        $stats = get_stats();
        $users_data = json_decode(file_get_contents(USERS_FILE), true);
        $requests_data = json_decode(file_get_contents(REQUEST_FILE), true);
        $panel = "🔐 <b>ADMIN CONTROL PANEL</b> 🔐\n\n";
        $panel .= "📊 <b>Quick Stats:</b>\n";
        $panel .= "• 🎬 Movies: " . ($stats['total_movies'] ?? 0) . "\n";
        $panel .= "• 👥 Users: " . count($users_data['users'] ?? []) . "\n";
        $panel .= "• 📝 Pending Requests: " . count(array_filter($requests_data['requests'] ?? [], fn($r) => $r['status'] == 'pending')) . "\n";
        $panel .= "• 💾 Last Backup: " . (file_exists(BACKUP_DIR) ? date('d-m-Y H:i', filemtime(BACKUP_DIR)) : 'Never') . "\n\n";
        $panel .= "🛠️ <b>Select Category:</b>";
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🎬 Movie Management', 'callback_data' => 'admin_movies'],
                    ['text' => '👥 User Management', 'callback_data' => 'admin_users']
                ],
                [
                    ['text' => '📊 Statistics', 'callback_data' => 'admin_stats'],
                    ['text' => '💾 Backup System', 'callback_data' => 'admin_backup']
                ],
                [
                    ['text' => '📝 Requests', 'callback_data' => 'admin_requests'],
                    ['text' => '⚙️ Settings', 'callback_data' => 'admin_settings']
                ],
                [
                    ['text' => '📢 Broadcast', 'callback_data' => 'admin_broadcast'],
                    ['text' => '🔧 Maintenance', 'callback_data' => 'admin_maintenance']
                ],
                [
                    ['text' => '❌ Close Panel', 'callback_data' => 'admin_close']
                ]
            ]
        ];
        sendMessage($chat_id, $panel, $keyboard, 'HTML');
    }
}

$update = json_decode(file_get_contents('php://input'), true);

if ($update) {
    global $MAINTENANCE_MODE;
    if ($MAINTENANCE_MODE && isset($update['message'])) {
        $chat_id = $update['message']['chat']['id'];
        if ($chat_id != ADMIN_ID) {
            sendMessage($chat_id, "🛠️ <b>Bot Under Maintenance</b>\n\nWe'll be back soon! 🙏", null, 'HTML');
            exit;
        }
    }
    get_cached_movies();
    autoIndex($update);
    if (isset($update['channel_post'])) {
        $message = $update['channel_post'];
        $message_id = $message['message_id'];
        $chat_id = $message['chat']['id'];
        $channel_type = get_channel_type_by_id($chat_id);
        if ($channel_type != 'other' && (isset($message['video']) || isset($message['document']))) {
            $text = '';
            if (isset($message['caption'])) {
                $text = $message['caption'];
            } elseif (isset($message['text'])) {
                $text = $message['text'];
            } elseif (isset($message['document'])) {
                $text = $message['document']['file_name'];
            } else {
                $text = 'Uploaded Media - ' . date('d-m-Y H:i');
            }
            if (!empty(trim($text))) {
                append_movie($text, $message_id, $chat_id);
            }
        }
    }
    if (isset($update['message'])) {
        $message = $update['message'];
        $chat_id = $message['chat']['id'];
        $user_id = $message['from']['id'];
        $text = isset($message['text']) ? $message['text'] : '';
        $chat_type = $message['chat']['type'] ?? 'private';
        $user_info = [
            'first_name' => $message['from']['first_name'] ?? '',
            'last_name' => $message['from']['last_name'] ?? '',
            'username' => $message['from']['username'] ?? ''
        ];
        update_user_data($user_id, $user_info);
        if ($chat_type !== 'private' && strpos($text, '/') !== 0) {
            if (!is_valid_movie_query($text)) {
                bot_log("Invalid group message blocked from $chat_id: $text");
                return;
            }
        }
        if (strpos($text, '/') === 0) {
            $parts = explode(' ', $text);
            $command = strtolower($parts[0]);
            $params = array_slice($parts, 1);
            handle_command($chat_id, $user_id, $command, $params);
        } else if (!empty(trim($text))) {
            $lang = detect_language($text);
            send_multilingual_response($chat_id, 'searching', $lang);
            advanced_search($chat_id, $text, $user_id);
        }
    }
    if (isset($update['callback_query'])) {
        $query = $update['callback_query'];
        $message = $query['message'];
        $chat_id = $message['chat']['id'];
        $user_id = $query['from']['id'];
        $data = $query['data'];
        global $movie_messages;
        $movie_lower = strtolower($data);
        if (isset($movie_messages[$movie_lower])) {
            $entries = $movie_messages[$movie_lower];
            $cnt = 0;
            foreach ($entries as $entry) {
                deliver_item_to_chat($chat_id, $entry);
                usleep(200000);
                $cnt++;
            }
            sendMessage($chat_id, "✅ '$data' ke $cnt items ka info mil gaya!\n\n📢 Join our channel to download: " . MAIN_CHANNEL);
            answerCallbackQuery($query['id'], "🎬 $cnt items ka info sent!");
            update_user_activity($user_id, 'download');
        } elseif (strpos($data, 'pag_') === 0) {
            $parts = explode('_', $data);
            $action = $parts[1];
            $session_id = isset($parts[2]) ? $parts[2] : '';
            if ($action == 'first') {
                totalupload_controller($chat_id, 1, [], $session_id);
                answerCallbackQuery($query['id'], "First page");
            } elseif ($action == 'last') {
                $all = get_all_movies_list();
                $total_pages = ceil(count($all) / ITEMS_PER_PAGE);
                totalupload_controller($chat_id, $total_pages, [], $session_id);
                answerCallbackQuery($query['id'], "Last page");
            } elseif ($action == 'prev') {
                $current_page = isset($parts[2]) ? intval($parts[2]) : 1;
                $session_id = isset($parts[3]) ? $parts[3] : '';
                totalupload_controller($chat_id, max(1, $current_page - 1), [], $session_id);
                answerCallbackQuery($query['id'], "Previous page");
            } elseif ($action == 'next') {
                $current_page = isset($parts[2]) ? intval($parts[2]) : 1;
                $session_id = isset($parts[3]) ? $parts[3] : '';
                $all = get_all_movies_list();
                $total_pages = ceil(count($all) / ITEMS_PER_PAGE);
                totalupload_controller($chat_id, min($total_pages, $current_page + 1), [], $session_id);
                answerCallbackQuery($query['id'], "Next page");
            } elseif (is_numeric($action)) {
                $page_num = intval($action);
                $session_id = isset($parts[2]) ? $parts[2] : '';
                totalupload_controller($chat_id, $page_num, [], $session_id);
                answerCallbackQuery($query['id'], "Page $page_num");
            }
        } elseif (strpos($data, 'send_') === 0) {
            $parts = explode('_', $data);
            $page_num = isset($parts[1]) ? intval($parts[1]) : 1;
            $session_id = isset($parts[2]) ? $parts[2] : '';
            $all = get_all_movies_list();
            $pg = paginate_movies($all, $page_num, []);
            batch_download_with_progress($chat_id, $pg['slice'], $page_num);
            answerCallbackQuery($query['id'], "📦 Batch info started!");
        } elseif (strpos($data, 'prev_') === 0) {
            $parts = explode('_', $data);
            $page_num = isset($parts[1]) ? intval($parts[1]) : 1;
            $session_id = isset($parts[2]) ? $parts[2] : '';
            $all = get_all_movies_list();
            $pg = paginate_movies($all, $page_num, []);
            $preview_msg = "👁️ <b>Page {$page_num} Preview</b>\n\n";
            $limit = min(5, count($pg['slice']));
            for ($i = 0; $i < $limit; $i++) {
                $movie = $pg['slice'][$i];
                $channel_icon = get_channel_display_name($movie['channel_type'] ?? 'main');
                $preview_msg .= ($i + 1) . ". $channel_icon <b>" . htmlspecialchars($movie['movie_name']) . "</b>\n";
                $preview_msg .= "   ⭐ " . ($movie['quality'] ?? 'Unknown') . "\n\n";
            }
            sendMessage($chat_id, $preview_msg, null, 'HTML');
            answerCallbackQuery($query['id'], "Preview sent");
        } elseif (strpos($data, 'flt_') === 0) {
            $parts = explode('_', $data);
            $filter_type = $parts[1];
            $session_id = isset($parts[2]) ? $parts[2] : '';
            $filters = [];
            if ($filter_type == 'hd') {
                $filters = ['quality' => '1080'];
                answerCallbackQuery($query['id'], "HD filter applied");
            } elseif ($filter_type == 'theater') {
                $filters = ['channel_type' => 'theater'];
                answerCallbackQuery($query['id'], "Theater filter applied");
            } elseif ($filter_type == 'backup') {
                $filters = ['channel_type' => 'backup'];
                answerCallbackQuery($query['id'], "Backup filter applied");
            } elseif ($filter_type == 'clr') {
                answerCallbackQuery($query['id'], "Filters cleared");
            }
            totalupload_controller($chat_id, 1, $filters, $session_id);
        } elseif ($data == 'close_' || strpos($data, 'close_') === 0) {
            deleteMessage($chat_id, $message['message_id']);
            sendMessage($chat_id, "🗂️ Closed. Use /totalupload to browse again.");
            answerCallbackQuery($query['id'], "Closed");
        } elseif (strpos($data, 'auto_request_') === 0) {
            $movie_name = base64_decode(str_replace('auto_request_', '', $data));
            $lang = detect_language($movie_name);
            if (add_movie_request($user_id, $movie_name, $lang)) {
                send_multilingual_response($chat_id, 'request_success', $lang);
                answerCallbackQuery($query['id'], "Request sent!");
                update_user_activity($user_id, 'movie_request');
            } else {
                send_multilingual_response($chat_id, 'request_limit', $lang);
                answerCallbackQuery($query['id'], "Daily limit reached!", true);
            }
        } elseif ($data == 'request_movie') {
            sendMessage($chat_id, "📝 To request a movie, use:\n<code>/request movie_name</code>\n\nExample: <code>/request Avengers Endgame</code>", null, 'HTML');
            answerCallbackQuery($query['id'], "Request instructions");
        } elseif ($data == 'my_stats') {
            show_user_stats($chat_id, $user_id);
            answerCallbackQuery($query['id'], "Your statistics");
        } elseif ($data == 'show_leaderboard') {
            show_leaderboard($chat_id);
            answerCallbackQuery($query['id'], "Leaderboard");
        } elseif ($data == 'refresh_stats' || $data == 'refresh_leaderboard') {
            if ($data == 'refresh_stats') show_user_stats($chat_id, $user_id);
            else show_leaderboard($chat_id);
            answerCallbackQuery($query['id'], "Refreshed");
        } elseif ($data == 'download_latest') {
            $all = get_all_movies_list();
            $latest = array_slice($all, -10);
            $latest = array_reverse($latest);
            batch_download_with_progress($chat_id, $latest, "latest");
            answerCallbackQuery($query['id'], "Latest movies info sent");
        } elseif ($data == 'browse_all') {
            totalupload_controller($chat_id, 1);
            answerCallbackQuery($query['id'], "Browse all movies");
        } elseif ($data == 'help_command') {
            $command = '/help';
            $params = [];
            handle_command($chat_id, $user_id, $command, $params);
            answerCallbackQuery($query['id'], "Help menu");
        } elseif (strpos($data, 'settings_') === 0) {
            $settings = new UserSettings($user_id);
            if ($data == 'settings_back') {
                $settings->show_main_panel($chat_id);
                answerCallbackQuery($query['id'], "Back to Settings");
            } elseif ($data == 'settings_close') {
                deleteMessage($chat_id, $message['message_id']);
                sendMessage($chat_id, "⚙️ Settings panel closed. Use /settings to reopen.");
                answerCallbackQuery($query['id'], "Settings Closed");
            } elseif ($data == 'settings_language') {
                $settings->show_language_settings($chat_id);
                answerCallbackQuery($query['id'], "Language Settings");
            } elseif ($data == 'settings_quality') {
                $settings->show_quality_settings($chat_id);
                answerCallbackQuery($query['id'], "Quality Settings");
            } elseif ($data == 'settings_layout') {
                $settings->show_layout_settings($chat_id);
                answerCallbackQuery($query['id'], "Layout Settings");
            } elseif ($data == 'settings_priority') {
                $settings->show_priority_settings($chat_id);
                answerCallbackQuery($query['id'], "Priority Settings");
            } elseif ($data == 'settings_perpage') {
                $settings->show_perpage_settings($chat_id);
                answerCallbackQuery($query['id'], "Results Per Page");
            } elseif ($data == 'settings_theme') {
                $settings->show_theme_settings($chat_id);
                answerCallbackQuery($query['id'], "Theme Settings");
            } elseif ($data == 'settings_spoiler') {
                $settings->toggle_setting($chat_id, 'spoiler_mode');
                answerCallbackQuery($query['id'], "Spoiler Mode Toggled");
            } elseif ($data == 'settings_topsearch') {
                $settings->toggle_setting($chat_id, 'top_search');
                answerCallbackQuery($query['id'], "Top Search Toggled");
            } elseif ($data == 'settings_autoscan') {
                $settings->toggle_setting($chat_id, 'auto_scan');
                answerCallbackQuery($query['id'], "Auto Scan Toggled");
            } elseif ($data == 'settings_notify') {
                $settings->toggle_setting($chat_id, 'notifications');
                answerCallbackQuery($query['id'], "Notifications Toggled");
            } elseif (strpos($data, 'settings_set_language_') === 0) {
                $lang = str_replace('settings_set_language_', '', $data);
                $settings->set('language', $lang);
                $lang_names = ['hindi' => '🇮🇳 Hindi', 'english' => '🇬🇧 English', 'tamil' => '🇮🇳 Tamil', 'telugu' => '🇮🇳 Telugu', 'malayalam' => '🇮🇳 Malayalam', 'kannada' => '🇮🇳 Kannada', 'bengali' => '🇮🇳 Bengali', 'punjabi' => '🇮🇳 Punjabi'];
                sendMessage($chat_id, "✅ Language updated to: <b>" . ($lang_names[$lang] ?? ucfirst($lang)) . "</b>");
                $settings->show_main_panel($chat_id);
                answerCallbackQuery($query['id'], "Language Updated");
            } elseif (strpos($data, 'settings_set_quality_') === 0) {
                $quality = str_replace('settings_set_quality_', '', $data);
                $settings->set('default_quality', $quality);
                sendMessage($chat_id, "✅ Default quality set to: <b>$quality</b>");
                $settings->show_main_panel($chat_id);
                answerCallbackQuery($query['id'], "Quality Updated");
            } elseif (strpos($data, 'settings_set_layout_') === 0) {
                $layout = str_replace('settings_set_layout_', '', $data);
                $settings->set('result_layout', $layout);
                $layout_names = ['buttons' => '🔘 Buttons', 'text' => '📝 Text List'];
                sendMessage($chat_id, "✅ Layout set to: <b>" . $layout_names[$layout] . "</b>");
                $settings->show_main_panel($chat_id);
                answerCallbackQuery($query['id'], "Layout Updated");
            } elseif (strpos($data, 'settings_set_priority_') === 0) {
                $priority = str_replace('settings_set_priority_', '', $data);
                $settings->set('priority_mode', $priority);
                $priority_names = ['quality' => '📊 Quality First', 'size' => '💾 Size First'];
                sendMessage($chat_id, "✅ Priority mode set to: <b>" . $priority_names[$priority] . "</b>");
                $settings->show_main_panel($chat_id);
                answerCallbackQuery($query['id'], "Priority Updated");
            } elseif (strpos($data, 'settings_set_perpage_') === 0) {
                $perpage = intval(str_replace('settings_set_perpage_', '', $data));
                $settings->set('results_per_page', $perpage);
                sendMessage($chat_id, "✅ Results per page set to: <b>$perpage</b>");
                $settings->show_main_panel($chat_id);
                answerCallbackQuery($query['id'], "Per Page Updated");
            } elseif (strpos($data, 'settings_set_theme_') === 0) {
                $theme = str_replace('settings_set_theme_', '', $data);
                $settings->set('theme', $theme);
                $theme_names = ['dark' => '🌙 Dark', 'light' => '☀️ Light'];
                sendMessage($chat_id, "✅ Theme set to: <b>" . $theme_names[$theme] . "</b>");
                $settings->show_main_panel($chat_id);
                answerCallbackQuery($query['id'], "Theme Updated");
            } elseif ($data == 'settings_reset') {
                $settings->reset_to_default();
                sendMessage($chat_id, "🔄 <b>Settings Reset Complete!</b>\n\nAll settings restored to default values.");
                $settings->show_main_panel($chat_id);
                answerCallbackQuery($query['id'], "Reset Complete");
            }
        } elseif (strpos($data, 'filter_') === 0) {
            $filter = new MovieFilter($user_id);
            $current_filters = $filter->load_filter_session();
            if ($data == 'filter_back') {
                $filter->show_filter_panel($chat_id, $current_filters);
                answerCallbackQuery($query['id'], "Back to Filter Menu");
            } elseif ($data == 'filter_close') {
                deleteMessage($chat_id, $message['message_id']);
                sendMessage($chat_id, "🔍 Filter panel closed. Use /filter to reopen.");
                answerCallbackQuery($query['id'], "Filter Closed");
            } elseif ($data == 'filter_clear_all') {
                $filter->save_filter_session([]);
                sendMessage($chat_id, "🧹 All filters cleared!");
                $filter->show_filter_panel($chat_id, []);
                answerCallbackQuery($query['id'], "Filters Cleared");
            } elseif ($data == 'filter_apply') {
                if (!empty($current_filters)) {
                    $_SESSION['active_filters'] = $current_filters;
                    sendMessage($chat_id, "✅ Filters applied! Use /search to find movies.");
                }
                answerCallbackQuery($query['id'], "Filters Applied");
            } elseif ($data == 'filter_menu_language') {
                $filter->show_language_filter($chat_id, $current_filters['language'] ?? null);
                answerCallbackQuery($query['id'], "Language Filter");
            } elseif ($data == 'filter_menu_quality') {
                $filter->show_quality_filter($chat_id, $current_filters['quality'] ?? null);
                answerCallbackQuery($query['id'], "Quality Filter");
            } elseif ($data == 'filter_menu_filetype') {
                $filter->show_filetype_filter($chat_id, $current_filters['file_type'] ?? null);
                answerCallbackQuery($query['id'], "File Type Filter");
            } elseif ($data == 'filter_menu_season') {
                $filter->show_season_filter($chat_id, $current_filters['season'] ?? null);
                answerCallbackQuery($query['id'], "Season Filter");
            } elseif ($data == 'filter_menu_dual') {
                $filter->show_dual_audio_filter($chat_id, $current_filters['dual_audio'] ?? null);
                answerCallbackQuery($query['id'], "Dual Audio Filter");
            } elseif ($data == 'filter_menu_hdr') {
                $filter->show_hdr_filter($chat_id, $current_filters['hdr'] ?? null);
                answerCallbackQuery($query['id'], "HDR Filter");
            } elseif ($data == 'filter_menu_size') {
                $filter->show_size_filter($chat_id, $current_filters['min_size'] ?? null, $current_filters['max_size'] ?? null);
                answerCallbackQuery($query['id'], "Size Filter");
            } elseif ($data == 'filter_menu_codec') {
                $filter->show_codec_filter($chat_id, $current_filters['codec'] ?? null);
                answerCallbackQuery($query['id'], "Codec Filter");
            } elseif ($data == 'filter_menu_subs') {
                $filter->show_subtitle_filter($chat_id, $current_filters['subtitles'] ?? null);
                answerCallbackQuery($query['id'], "Subtitle Filter");
            } elseif (strpos($data, 'filter_set_language_') === 0) {
                $lang = str_replace('filter_set_language_', '', $data);
                if ($lang == 'any') {
                    unset($current_filters['language']);
                } else {
                    $current_filters['language'] = $lang;
                }
                $filter->save_filter_session($current_filters);
                sendMessage($chat_id, "✅ Language filter: " . ($lang == 'any' ? 'Cleared' : ucfirst($lang)));
                $filter->show_filter_panel($chat_id, $current_filters);
                answerCallbackQuery($query['id'], "Language Set");
            } elseif (strpos($data, 'filter_set_quality_') === 0) {
                $quality = str_replace('filter_set_quality_', '', $data);
                if ($quality == 'any') {
                    unset($current_filters['quality']);
                } else {
                    $current_filters['quality'] = $quality;
                }
                $filter->save_filter_session($current_filters);
                sendMessage($chat_id, "✅ Quality filter: " . ($quality == 'any' ? 'Cleared' : $quality));
                $filter->show_filter_panel($chat_id, $current_filters);
                answerCallbackQuery($query['id'], "Quality Set");
            } elseif (strpos($data, 'filter_set_filetype_') === 0) {
                $type = str_replace('filter_set_filetype_', '', $data);
                if ($type == 'any') {
                    unset($current_filters['file_type']);
                } else {
                    $current_filters['file_type'] = $type;
                }
                $filter->save_filter_session($current_filters);
                sendMessage($chat_id, "✅ File type filter: " . ($type == 'any' ? 'Cleared' : strtoupper($type)));
                $filter->show_filter_panel($chat_id, $current_filters);
                answerCallbackQuery($query['id'], "File Type Set");
            } elseif (strpos($data, 'filter_set_season_') === 0) {
                $season = str_replace('filter_set_season_', '', $data);
                if ($season == 'all') {
                    unset($current_filters['season']);
                } else {
                    $current_filters['season'] = $season;
                }
                $filter->save_filter_session($current_filters);
                sendMessage($chat_id, "✅ Season filter: " . ($season == 'all' ? 'Cleared' : "Season $season"));
                $filter->show_filter_panel($chat_id, $current_filters);
                answerCallbackQuery($query['id'], "Season Set");
            } elseif (strpos($data, 'filter_set_dual_') === 0) {
                $dual = str_replace('filter_set_dual_', '', $data);
                if ($dual == 'any') {
                    unset($current_filters['dual_audio']);
                } else {
                    $current_filters['dual_audio'] = $dual;
                }
                $filter->save_filter_session($current_filters);
                $msg = $dual == 'yes' ? 'Dual Audio Only' : ($dual == 'no' ? 'Single Audio Only' : 'Filter Cleared');
                sendMessage($chat_id, "✅ $msg");
                $filter->show_filter_panel($chat_id, $current_filters);
                answerCallbackQuery($query['id'], "Dual Audio Set");
            } elseif (strpos($data, 'filter_set_hdr_') === 0) {
                $hdr = str_replace('filter_set_hdr_', '', $data);
                if ($hdr == 'any') {
                    unset($current_filters['hdr']);
                } else {
                    $current_filters['hdr'] = $hdr;
                }
                $filter->save_filter_session($current_filters);
                $msg = $hdr == 'yes' ? 'HDR Only' : ($hdr == 'no' ? 'SDR Only' : 'Filter Cleared');
                sendMessage($chat_id, "✅ $msg");
                $filter->show_filter_panel($chat_id, $current_filters);
                answerCallbackQuery($query['id'], "HDR Set");
            } elseif (strpos($data, 'filter_set_size_') === 0) {
                $size_range = str_replace('filter_set_size_', '', $data);
                if ($size_range == 'clear') {
                    unset($current_filters['min_size']);
                    unset($current_filters['max_size']);
                    sendMessage($chat_id, "✅ Size filter cleared");
                } else {
                    list($min, $max) = explode('_', $size_range);
                    $current_filters['min_size'] = intval($min);
                    $current_filters['max_size'] = intval($max);
                    sendMessage($chat_id, "✅ Size filter: " . ($min == 0 ? "<" : "$min-") . "$max MB");
                }
                $filter->save_filter_session($current_filters);
                $filter->show_filter_panel($chat_id, $current_filters);
                answerCallbackQuery($query['id'], "Size Set");
            } elseif (strpos($data, 'filter_set_codec_') === 0) {
                $codec = str_replace('filter_set_codec_', '', $data);
                if ($codec == 'any') {
                    unset($current_filters['codec']);
                } else {
                    $current_filters['codec'] = $codec;
                }
                $filter->save_filter_session($current_filters);
                sendMessage($chat_id, "✅ Codec filter: " . ($codec == 'any' ? 'Cleared' : strtoupper($codec)));
                $filter->show_filter_panel($chat_id, $current_filters);
                answerCallbackQuery($query['id'], "Codec Set");
            } elseif (strpos($data, 'filter_set_subs_') === 0) {
                $subs = str_replace('filter_set_subs_', '', $data);
                if ($subs == 'any') {
                    unset($current_filters['subtitles']);
                } else {
                    $current_filters['subtitles'] = $subs;
                }
                $filter->save_filter_session($current_filters);
                $subs_msgs = ['yes' => 'With Subtitles', 'no' => 'Without Subtitles', 'english' => 'English Subtitles', 'hindi' => 'Hindi Subtitles'];
                sendMessage($chat_id, "✅ " . ($subs_msgs[$subs] ?? 'Subtitle filter applied'));
                $filter->show_filter_panel($chat_id, $current_filters);
                answerCallbackQuery($query['id'], "Subtitle Set");
            }
        } elseif (strpos($data, 'bulk_') === 0) {
            $bulk = new BulkManager();
            if ($data == 'bulk_series') {
                $bulk->show_series_list($chat_id);
                answerCallbackQuery($query['id'], "Series List");
            } elseif (strpos($data, 'bulk_series_') === 0) {
                $series = str_replace('bulk_series_', '', $data);
                $bulk->show_seasons($chat_id, $series);
                answerCallbackQuery($query['id'], "Seasons");
            } elseif (strpos($data, 'bulk_season_') === 0) {
                $season_data = str_replace('bulk_season_', '', $data);
                $bulk->show_episodes($chat_id, $season_data);
                answerCallbackQuery($query['id'], "Episodes");
            } elseif (strpos($data, 'bulk_download_all_') === 0) {
                $series = str_replace('bulk_download_all_', '', $data);
                $bulk->download_all_seasons($chat_id, $series);
                answerCallbackQuery($query['id'], "Downloading All");
            } elseif (strpos($data, 'bulk_download_season_') === 0) {
                $season_data = str_replace('bulk_download_season_', '', $data);
                $bulk->download_season($chat_id, $season_data);
                answerCallbackQuery($query['id'], "Downloading Season");
            } elseif ($data == 'bulk_back_series') {
                $bulk->show_series_list($chat_id);
                answerCallbackQuery($query['id'], "Back");
            } elseif ($data == 'bulk_close') {
                deleteMessage($chat_id, $message['message_id']);
                answerCallbackQuery($query['id'], "Closed");
            }
        } elseif (strpos($data, 'delete_') === 0) {
            $delete = new AutoDeleteManager();
            if (strpos($data, 'delete_set_') === 0) {
                $parts = explode('_', $data);
                $minutes = $parts[2];
                $msg_id = $parts[3];
                $delete_time = $delete->schedule_delete($chat_id, $msg_id, $minutes);
                deleteMessage($chat_id, $message['message_id']);
                $time_str = $minutes < 60 ? "$minutes minutes" : ($minutes/60 . " hours");
                sendMessage($chat_id, "✅ Auto-delete set for <b>$time_str</b>\n\nMessage will be deleted at " . date('h:i A', $delete_time));
                answerCallbackQuery($query['id'], "Timer Set");
            } elseif ($data == 'delete_cancel') {
                deleteMessage($chat_id, $message['message_id']);
                answerCallbackQuery($query['id'], "Cancelled");
            }
        } elseif (strpos($data, 'folder_') === 0) {
            $folder = new FolderNavigator();
            if ($data == 'folder_root' || $data == 'folder_back') {
                $folder->show_root($chat_id);
                answerCallbackQuery($query['id'], "Root Folder");
            } elseif ($data == 'folder_close') {
                deleteMessage($chat_id, $message['message_id']);
                answerCallbackQuery($query['id'], "Closed");
            } elseif (strpos($data, 'folder_open_') === 0) {
                $folder_name = str_replace('folder_open_', '', $data);
                $folder->open_folder($chat_id, $folder_name);
                answerCallbackQuery($query['id'], "Opening...");
            } elseif (strpos($data, 'folder_series_') === 0) {
                $series = str_replace('folder_series_', '', $data);
                $folder->show_seasons($chat_id, $series);
                answerCallbackQuery($query['id'], "Seasons");
            } elseif (strpos($data, 'folder_season_') === 0) {
                $season_data = str_replace('folder_season_', '', $data);
                $folder->show_episodes($chat_id, $season_data);
                answerCallbackQuery($query['id'], "Episodes");
            } elseif (strpos($data, 'folder_quality_') === 0) {
                $quality_data = str_replace('folder_quality_', '', $data);
                $folder->show_movies_by_quality($chat_id, $quality_data);
                answerCallbackQuery($query['id'], "Movies");
            } elseif ($data == 'folder_back_series') {
                $folder->show_series($chat_id);
                answerCallbackQuery($query['id'], "Back");
            } elseif (strpos($data, 'folder_download_season_') === 0) {
                $season_data = str_replace('folder_download_season_', '', $data);
                $folder->download_all_episodes ? $folder->download_all_episodes($chat_id, $season_data) : null;
                answerCallbackQuery($query['id'], "Downloading");
            }
        } elseif (strpos($data, 'info_') === 0) {
            $msg_id_channel = base64_decode(str_replace('info_', '', $data));
            list($msg_id, $channel_id) = explode('||', $msg_id_channel);
            $movies = get_all_movies_list();
            foreach ($movies as $movie) {
                if ($movie['message_id'] == $msg_id && $movie['channel_id'] == $channel_id) {
                    $info = MediaInfo::format_info($movie);
                    $keyboard = [
                        'inline_keyboard' => [
                            [
                                ['text' => '📥 Download', 'callback_data' => 'play_' . base64_encode($msg_id . '||' . $channel_id)],
                                ['text' => '⬅️ Back', 'callback_data' => 'back_to_results']
                            ]
                        ]
                    ];
                    sendMessage($chat_id, $info, $keyboard, 'HTML');
                    answerCallbackQuery($query['id'], "Media Info");
                    break;
                }
            }
        } elseif (strpos($data, 'play_') === 0) {
            $msg_id_channel = base64_decode(str_replace('play_', '', $data));
            list($msg_id, $channel_id) = explode('||', $msg_id_channel);
            $movies = get_all_movies_list();
            foreach ($movies as $movie) {
                if ($movie['message_id'] == $msg_id && $movie['channel_id'] == $channel_id) {
                    deliver_item_to_chat($chat_id, $movie);
                    answerCallbackQuery($query['id'], "Downloading...");
                    break;
                }
            }
        } elseif (strpos($data, 'admin_') === 0) {
            if ($user_id != ADMIN_ID) {
                answerCallbackQuery($query['id'], "❌ Admin only!", true);
                break;
            }
            $admin = new AdminPanel($user_id);
            $action = str_replace('admin_', '', $data);
            if ($action == 'back') {
                $admin->show_main_panel($chat_id);
                answerCallbackQuery($query['id'], "Main Panel");
            } elseif ($action == 'close') {
                deleteMessage($chat_id, $message['message_id']);
                sendMessage($chat_id, "🔒 Admin panel closed. Use /admin to reopen.");
                answerCallbackQuery($query['id'], "Panel Closed");
            } elseif ($action == 'movies') {
                $stats = get_stats();
                $total = $stats['total_movies'] ?? 0;
                $panel = "🎬 <b>Movie Management</b>\n\n";
                $panel .= "📊 <b>Current Stats:</b>\n";
                $panel .= "• Total Movies: <b>$total</b>\n";
                $panel .= "• CSV Size: <b>" . round(filesize(CSV_FILE)/1024, 2) . " KB</b>\n\n";
                $panel .= "📋 <b>Quick Actions:</b>";
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => '📋 View CSV', 'callback_data' => 'admin_viewcsv'],
                            ['text' => '📊 Movie Stats', 'callback_data' => 'admin_moviestats']
                        ],
                        [
                            ['text' => '🔄 Scan Old Posts', 'callback_data' => 'admin_scan_old'],
                            ['text' => '📡 Progressive Scan', 'callback_data' => 'admin_scan_progressive']
                        ],
                        [
                            ['text' => '⬅️ Back to Main', 'callback_data' => 'admin_back'],
                            ['text' => '❌ Close', 'callback_data' => 'admin_close']
                        ]
                    ]
                ];
                sendMessage($chat_id, $panel, $keyboard, 'HTML');
                answerCallbackQuery($query['id'], "Movie Management");
            } elseif ($action == 'users') {
                $users_data = json_decode(file_get_contents(USERS_FILE), true);
                $users = $users_data['users'] ?? [];
                $active_today = 0;
                $active_week = 0;
                $today_start = strtotime('today');
                $week_start = strtotime('-7 days');
                foreach ($users as $uid => $user) {
                    $last_active = strtotime($user['last_active'] ?? '');
                    if ($last_active >= $today_start) $active_today++;
                    if ($last_active >= $week_start) $active_week++;
                }
                $panel = "👥 <b>User Management</b>\n\n";
                $panel .= "📊 <b>Statistics:</b>\n";
                $panel .= "• Total Users: <b>" . count($users) . "</b>\n";
                $panel .= "• Active Today: <b>$active_today</b>\n";
                $panel .= "• Active This Week: <b>$active_week</b>\n\n";
                $panel .= "📋 <b>Actions:</b>";
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
                answerCallbackQuery($query['id'], "User Management");
            } elseif ($action == 'stats') {
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
                answerCallbackQuery($query['id'], "Statistics");
            } elseif ($action == 'backup') {
                $backup_dirs = glob(BACKUP_DIR . '*', GLOB_ONLYDIR);
                $latest = !empty($backup_dirs) ? max($backup_dirs) : null;
                $panel = "💾 <b>Backup System</b>\n\n";
                if ($latest) {
                    $backup_time = date('d-m-Y H:i', filemtime($latest));
                    $panel .= "📅 <b>Latest Backup:</b> $backup_time\n";
                } else {
                    $panel .= "⚠️ No backups found!\n";
                }
                $panel .= "\n📊 <b>Total Backups:</b> " . count($backup_dirs) . "\n\n";
                $panel .= "🛠️ <b>Actions:</b>";
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => '🔄 Full Backup', 'callback_data' => 'admin_backup_full'],
                            ['text' => '📋 Backup Status', 'callback_data' => 'admin_backup_status']
                        ],
                        [
                            ['text' => '⬅️ Back', 'callback_data' => 'admin_back'],
                            ['text' => '❌ Close', 'callback_data' => 'admin_close']
                        ]
                    ]
                ];
                sendMessage($chat_id, $panel, $keyboard, 'HTML');
                answerCallbackQuery($query['id'], "Backup System");
            } elseif ($action == 'requests') {
                $requests_data = json_decode(file_get_contents(REQUEST_FILE), true);
                $requests = $requests_data['requests'] ?? [];
                $pending = array_filter($requests, fn($r) => $r['status'] == 'pending');
                $panel = "📝 <b>Movie Requests</b>\n\n";
                $panel .= "📊 <b>Statistics:</b>\n";
                $panel .= "• Total Requests: <b>" . count($requests) . "</b>\n";
                $panel .= "• ⏳ Pending: <b>" . count($pending) . "</b>\n\n";
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => '📋 View Pending', 'callback_data' => 'admin_requests_view'],
                            ['text' => '📊 Request Stats', 'callback_data' => 'admin_requests_stats']
                        ],
                        [
                            ['text' => '⬅️ Back', 'callback_data' => 'admin_back'],
                            ['text' => '❌ Close', 'callback_data' => 'admin_close']
                        ]
                    ]
                ];
                sendMessage($chat_id, $panel, $keyboard, 'HTML');
                answerCallbackQuery($query['id'], "Requests");
            } elseif ($action == 'settings') {
                global $MAINTENANCE_MODE;
                $panel = "⚙️ <b>Bot Settings</b>\n\n";
                $panel .= "• Items per page: <b>" . ITEMS_PER_PAGE . "</b>\n";
                $panel .= "• Daily request limit: <b>" . DAILY_REQUEST_LIMIT . "</b>\n";
                $panel .= "• Maintenance mode: <b>" . ($MAINTENANCE_MODE ? '🔴 ON' : '🟢 OFF') . "</b>\n\n";
                $panel .= "🛠️ <b>Actions:</b>";
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => '🔄 Toggle Maintenance', 'callback_data' => 'admin_toggle_maintenance'],
                            ['text' => '🧹 Clear Cache', 'callback_data' => 'admin_clearcache']
                        ],
                        [
                            ['text' => '⬅️ Back', 'callback_data' => 'admin_back'],
                            ['text' => '❌ Close', 'callback_data' => 'admin_close']
                        ]
                    ]
                ];
                sendMessage($chat_id, $panel, $keyboard, 'HTML');
                answerCallbackQuery($query['id'], "Settings");
            } elseif ($action == 'broadcast') {
                $users_data = json_decode(file_get_contents(USERS_FILE), true);
                $total_users = count($users_data['users'] ?? []);
                $panel = "📢 <b>Broadcast Message</b>\n\n";
                $panel .= "👥 Total Users: <b>$total_users</b>\n\n";
                $panel .= "💡 Use:\n";
                $panel .= "<code>/broadcast Your message here</code>\n\n";
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => '⬅️ Back', 'callback_data' => 'admin_back'],
                            ['text' => '❌ Close', 'callback_data' => 'admin_close']
                        ]
                    ]
                ];
                sendMessage($chat_id, $panel, $keyboard, 'HTML');
                answerCallbackQuery($query['id'], "Broadcast");
            } elseif ($action == 'maintenance') {
                global $MAINTENANCE_MODE;
                $panel = "🔧 <b>Maintenance Mode</b>\n\n";
                $panel .= "Current status: <b>" . ($MAINTENANCE_MODE ? '🔴 ON' : '🟢 OFF') . "</b>\n\n";
                $panel .= "Use commands:\n";
                $panel .= "<code>/maintenance on</code> - Enable\n";
                $panel .= "<code>/maintenance off</code> - Disable";
                sendMessage($chat_id, $panel, null, 'HTML');
                answerCallbackQuery($query['id'], "Maintenance");
            } elseif ($action == 'viewcsv') {
                show_csv_data($chat_id, false);
                answerCallbackQuery($query['id'], "CSV Data");
            } elseif ($action == 'moviestats') {
                $stats = get_stats();
                $daily = $stats['daily_activity'] ?? [];
                $msg = "📊 <b>Movie Statistics</b>\n\n";
                $msg .= "• Total Movies: " . ($stats['total_movies'] ?? 0) . "\n";
                $msg .= "• Total Downloads: " . ($stats['total_downloads'] ?? 0) . "\n\n";
                $msg .= "📅 <b>Last 7 Days Activity:</b>\n";
                $dates = array_slice(array_keys($daily), -7);
                foreach ($dates as $date) {
                    $msg .= "• $date: " . ($daily[$date]['downloads'] ?? 0) . " downloads\n";
                }
                sendMessage($chat_id, $msg, null, 'HTML');
                answerCallbackQuery($query['id'], "Movie Stats");
            } elseif ($action == 'userstats') {
                sendMessage($chat_id, "📊 Enter user ID to view stats:");
                answerCallbackQuery($query['id'], "User Stats");
            } elseif ($action == 'leaderboard') {
                show_leaderboard($chat_id);
                answerCallbackQuery($query['id'], "Leaderboard");
            } elseif ($action == 'backup_full') {
                manual_backup($chat_id);
                answerCallbackQuery($query['id'], "Full Backup Started");
            } elseif ($action == 'backup_status') {
                backup_status($chat_id);
                answerCallbackQuery($query['id'], "Backup Status");
            } elseif ($action == 'requests_view') {
                $requests_data = json_decode(file_get_contents(REQUEST_FILE), true);
                $pending = array_filter($requests_data['requests'] ?? [], fn($r) => $r['status'] == 'pending');
                if (empty($pending)) {
                    sendMessage($chat_id, "✅ No pending requests!");
                } else {
                    $msg = "📝 <b>Pending Requests (" . count($pending) . ")</b>\n\n";
                    foreach (array_slice($pending, 0, 10) as $req) {
                        $msg .= "• {$req['movie_name']} - " . date('d-m', strtotime($req['date'])) . "\n";
                    }
                    sendMessage($chat_id, $msg, null, 'HTML');
                }
                answerCallbackQuery($query['id'], "Requests");
            } elseif ($action == 'requests_stats') {
                $requests_data = json_decode(file_get_contents(REQUEST_FILE), true);
                $requests = $requests_data['requests'] ?? [];
                $stats = "📊 <b>Request Statistics</b>\n\n";
                $stats .= "• Total: " . count($requests) . "\n";
                $stats .= "• Pending: " . count(array_filter($requests, fn($r) => $r['status'] == 'pending')) . "\n";
                sendMessage($chat_id, $stats, null, 'HTML');
                answerCallbackQuery($query['id'], "Request Stats");
            } elseif ($action == 'toggle_maintenance') {
                global $MAINTENANCE_MODE;
                $MAINTENANCE_MODE = !$MAINTENANCE_MODE;
                sendMessage($chat_id, "🔄 Maintenance mode: " . ($MAINTENANCE_MODE ? '🔴 ON' : '🟢 OFF'));
                answerCallbackQuery($query['id'], "Toggled");
            } elseif ($action == 'clearcache') {
                global $movie_cache;
                $movie_cache = [];
                sendMessage($chat_id, "✅ Cache cleared!");
                answerCallbackQuery($query['id'], "Cache Cleared");
            } elseif ($action == 'scan_old') {
                answerCallbackQuery($query['id'], "Starting old scan...");
                scanOldPosts($chat_id);
            } elseif ($action == 'scan_progressive') {
                answerCallbackQuery($query['id'], "Starting progressive scan...");
                progressiveScan($chat_id);
            } else {
                answerCallbackQuery($query['id'], "Unknown action");
            }
        } else {
            sendMessage($chat_id, "❌ Movie not found: " . $data . "\n\nTry searching with exact name!");
            answerCallbackQuery($query['id'], "❌ Movie not available");
        }
    }
    $current_hour = date('H');
    $current_minute = date('i');
    if ($current_hour == AUTO_BACKUP_HOUR && $current_minute == '00') {
        auto_backup();
        bot_log("Daily auto-backup completed");
    }
    if ($current_minute == '30') {
        global $movie_cache;
        $movie_cache = [];
        bot_log("Hourly cache cleanup");
    }
    $delete = new AutoDeleteManager();
    $delete->process_deletions();
}

if (isset($_GET['setwebhook'])) {
    $webhook_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    $result = apiRequest('setWebhook', ['url' => $webhook_url]);
    echo "<h1>Webhook Setup</h1>";
    echo "<p>Result: " . htmlspecialchars($result) . "</p>";
    echo "<p>Webhook URL: " . htmlspecialchars($webhook_url) . "</p>";
    exit;
}

if (!isset($update) || !$update) {
    $stats = get_stats();
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    echo "<h1>🎬 Entertainment Tadka Bot</h1>";
    echo "<p><strong>Status:</strong> ✅ Running</p>";
    echo "<p><strong>Total Movies:</strong> " . ($stats['total_movies'] ?? 0) . "</p>";
    echo "<p><strong>Total Users:</strong> " . count($users_data['users'] ?? []) . "</p>";
    echo "<p><a href='?setwebhook=1'>Set Webhook</a></p>";
}
?>