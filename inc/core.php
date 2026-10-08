<?php
/**
 * 공통 코어: 설정 / DB / 세션 / 보안 / 업로드 / 유틸
 * PHP 7.4 이상에서 동작합니다.
 */
if (defined('JH_CORE')) { return; }
define('JH_CORE', true);
define('ROOT', str_replace('\\', '/', dirname(__DIR__)));
date_default_timezone_set('Asia/Seoul');

$GLOBALS['JH_CFG'] = is_file(ROOT . '/inc/config.php') ? require ROOT . '/inc/config.php' : null;

function cfg($k, $d = null) {
    $c = $GLOBALS['JH_CFG'];
    return (is_array($c) && array_key_exists($k, $c)) ? $c[$k] : $d;
}

if (cfg('debug')) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
    ini_set('display_errors', '0');
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
/** 체크박스(TINYINT) 값이 켜짐인지: 서버에 따라 문자"1"/숫자1/불리언true 어느 형태로 와도 안전하게 판정 */
function on1($v) { return $v === '1' || $v === 1 || $v === true; }
function now() { return date('Y-m-d H:i:s'); }

function cut($s, $n) {
    $s = (string)$s;
    return function_exists('mb_substr') ? mb_substr($s, 0, $n, 'UTF-8') : substr($s, 0, $n);
}

/* ---------- 경로 ---------- */
function detect_base() {
    $script = str_replace('\\', '/', isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : '');
    $name = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '/';
    if (strpos($script, ROOT) === 0) {
        $rel = substr($script, strlen(ROOT));
    } else {
        $rel = '/' . basename($script);
    }
    if ($rel !== '' && substr($name, -strlen($rel)) === $rel) {
        $base = substr($name, 0, strlen($name) - strlen($rel));
    } else {
        $base = dirname($name);
    }
    return rtrim(str_replace('\\', '/', $base), '/') . '/';
}
define('BASE', cfg('base') ? cfg('base') : detect_base());
function url($p = '') { return BASE . ltrim($p, '/'); }

function asset($p) {
    $p = (string)$p;
    if ($p === '') return '';
    if (preg_match('#^(https?:)?//#i', $p) || $p[0] === '/') return $p;
    return BASE . $p;
}
function cssurl($u) {
    return str_replace(["\\", "'", '"', '(', ')', ' ', "\n", "\r", '<', '>'], ['%5C', '%27', '%22', '%28', '%29', '%20', '', '', '%3C', '%3E'], (string)$u);
}
function safe_url($u) {
    $u = trim((string)$u);
    if ($u === '') return '';
    if (preg_match('#^\s*(javascript|data|vbscript)\s*:#i', $u)) return '';
    return $u;
}

function redirect($to) { header('Location: ' . $to); exit; }
function json_out($arr, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
    exit;
}
function is_https() {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
}
function is_pjax() { return !empty($_SERVER['HTTP_X_PJAX']); }
function ip() { return isset($_SERVER['REMOTE_ADDR']) ? cut($_SERVER['REMOTE_ADDR'], 45) : '0.0.0.0'; }

/* ---------- 미설치 시 설치 페이지로 ---------- */
if (!is_array($GLOBALS['JH_CFG']) && basename(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '') !== 'install.php') {
    redirect(detect_base() . 'install.php');
}

/* ---------- 오류 처리 ---------- */
set_exception_handler(function ($e) {
    error_log((string)$e);
    if (!headers_sent()) { http_response_code(500); }
    if (cfg('debug')) {
        echo '<pre style="white-space:pre-wrap">' . h((string)$e) . '</pre>';
    } else {
        echo '<!doctype html><meta charset="utf-8"><title>오류</title><body style="font-family:sans-serif;padding:40px;line-height:1.7">'
            . '<h2>문제가 발생했어요</h2><p>잠시 후 다시 시도해 주세요. 계속되면 가이드의 "문제 해결" 항목을 확인해 주세요.</p></body>';
    }
    exit;
});

/* ---------- 기본 보안 헤더 ---------- */
/** 이 페이지 안에서 직접 쓰는 <script>에만 붙이는 일회용 표식 (CSP nonce) */
function csp_nonce() {
    static $n = null;
    if ($n === null) $n = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
    return $n;
}
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Content-Type-Options: nosniff');            // 업로드한 파일을 엉뚱한 형식(HTML 등)으로 해석하지 않게
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: SAMEORIGIN');                // 다른 사이트가 이 사이트를 몰래 틀(iframe) 안에 띄우지 못하게
    // 이 사이트가 불러와도 되는 곳 목록. 스크립트는 이 사이트 파일 + 표식 붙은 것 + 유튜브 플레이어만 허용.
    // 이미지·음악·폰트는 외부 주소를 붙여 넣어 쓰는 경우가 많아서 https 주소는 모두 허용해요.
    header("Content-Security-Policy: default-src 'self'; "
        . "script-src 'self' 'nonce-" . csp_nonce() . "' https://www.youtube.com https://s.ytimg.com; "
        . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; "
        . "font-src 'self' https: data:; "
        . "img-src 'self' https: http: data: blob:; "
        . "media-src 'self' https: http: data: blob:; "
        . "frame-src 'self' https://www.youtube.com https://www.youtube-nocookie.com; "
        . "connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");
    // https로 접속했을 때만: 앞으로 180일 동안은 이 사이트를 항상 https로만 열도록 브라우저에 알려요
    if (is_https()) header('Strict-Transport-Security: max-age=15552000');
}

/* ---------- 세션 ---------- */
if (session_status() !== PHP_SESSION_ACTIVE) {
    $sp = ROOT . '/inc/sessions';
    if (is_dir($sp) && is_writable($sp)) { session_save_path($sp); }
    ini_set('session.gc_maxlifetime', '604800');
    session_name('jhsess');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'httponly' => true,
        'samesite' => 'Lax', 'secure' => is_https(),
    ]);
    session_start();
}

/* ---------- DB ---------- */
function db() {
    static $pdo = null;
    if ($pdo) return $pdo;
    // 설정 파일이 아직 없는 설치 전 상태에서 실수로 db()가 불리면, 알아보기 힘든 MySQL 에러
    // (예: Access denied for user ''@'localhost') 대신 원인을 바로 알 수 있는 메시지를 냄.
    if (!is_array($GLOBALS['JH_CFG'])) { throw new Exception('아직 설정 파일(inc/config.php)이 없어서 DB에 접속할 수 없어요.'); }
    $dsn = 'mysql:host=' . cfg('db_host', 'localhost') . ';dbname=' . cfg('db_name') . ';charset=utf8mb4';
    $pdo = new PDO($dsn, cfg('db_user'), cfg('db_pass'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // 항상 true여야 함: 코드 전체가 "DB 값은 문자열로 온다"고 가정하고 있어서
        // (예: $r['bg_ghost'] === '1'). false로 두면 일부 서버 환경에서 정수형 칸이
        // 진짜 숫자(1)로 와서 저 비교가 전부 어긋나요.
        PDO::ATTR_EMULATE_PREPARES => true,
    ]);
    return $pdo;
}
function q($sql, $args = []) { $st = db()->prepare($sql); $st->execute($args); return $st; }
function rows($sql, $args = []) { return q($sql, $args)->fetchAll(); }
function row($sql, $args = []) { $r = q($sql, $args)->fetch(); return $r ? $r : null; }
function val($sql, $args = []) { $r = q($sql, $args)->fetch(PDO::FETCH_NUM); return $r ? $r[0] : null; }
function insert_row($t, $data) {
    $cols = array_keys($data);
    $sql = 'INSERT INTO `' . $t . '` (`' . implode('`,`', $cols) . '`) VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
    q($sql, array_values($data));
    return (int)db()->lastInsertId();
}
function update_row($t, $id, $data) {
    $sets = [];
    foreach (array_keys($data) as $c) { $sets[] = '`' . $c . '`=?'; }
    $args = array_values($data);
    $args[] = $id;
    q('UPDATE `' . $t . '` SET ' . implode(',', $sets) . ' WHERE id=?', $args);
}

/* ---------- 설정값 ---------- */
require_once ROOT . '/inc/schema.php';

function settings_all() {
    if (!isset($GLOBALS['JH_SETTINGS'])) {
        // 설치 전(설정 파일이 아직 없음)에는 DB에 손대지 않음. entities()가 schema.php에서
        // S()를 미리 부르는 필드(예: 러닝 게시판의 "어느 캐릭터" 옵션)가 있어서, 설치 화면에서
        // entities()만 호출해도 여기로 들어올 수 있음 -- 그때 실제 DB 접속 정보 없이
        // db()를 부르면 "Access denied for user ''@'localhost'" 에러가 남.
        if (!is_array($GLOBALS['JH_CFG'])) return [];
        $a = [];
        foreach (rows('SELECT k, v FROM settings') as $r) { $a[$r['k']] = $r['v']; }
        $GLOBALS['JH_SETTINGS'] = $a;
    }
    return $GLOBALS['JH_SETTINGS'];
}
function S($k, $d = '') {
    $a = settings_all();
    if (array_key_exists($k, $a) && $a[$k] !== null) return $a[$k];
    $def = settings_defaults();
    if (array_key_exists($k, $def)) return $def[$k];
    return $d;
}
function set_setting($k, $v) {
    settings_all();
    $exists = (int)val('SELECT COUNT(*) FROM settings WHERE k=?', [$k]);
    if ($exists) { q('UPDATE settings SET v=? WHERE k=?', [$v, $k]); }
    else { q('INSERT INTO settings (k, v) VALUES (?, ?)', [$k, $v]); }
    $GLOBALS['JH_SETTINGS'][$k] = $v;
}

/* ---------- 관리자 / CSRF ---------- */
function is_admin() { return !empty($_SESSION['admin_id']); }
function admin_name() { return isset($_SESSION['admin_name']) ? $_SESSION['admin_name'] : ''; }
function csrf_token() {
    if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
    return $_SESSION['csrf'];
}
function csrf_ok() {
    $t = isset($_POST['csrf']) ? $_POST['csrf'] : (isset($_SERVER['HTTP_X_CSRF']) ? $_SERVER['HTTP_X_CSRF'] : '');
    return is_string($t) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t);
}
function csrf_field() { return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">'; }

/* ---------- 시도 횟수 제한 (로그인/비밀번호/방명록 스팸 방지) ---------- */
function attempts_count($kind, $window) {
    return (int)val('SELECT COUNT(*) FROM attempts WHERE ip=? AND kind=? AND t>?', [ip(), $kind, time() - $window]);
}
function attempts_add($kind) {
    q('INSERT INTO attempts (ip, kind, t) VALUES (?, ?, ?)', [ip(), $kind, time()]);
    if (mt_rand(1, 25) === 1) { q('DELETE FROM attempts WHERE t<?', [time() - 86400]); }
}

/* ---------- 게시글 접근 권한 ---------- */
function vis_sql() { return is_admin() ? '1=1' : "visibility<>'admin'"; }
function can_view($type, $row) {
    if (is_admin()) return true;
    $v = isset($row['visibility']) ? $row['visibility'] : 'public';
    if ($v === 'public') return true;
    if ($v === 'admin') return false;
    return !empty($_SESSION['unlock'][$type . ':' . $row['id']]);
}
function is_locked($type, $row) { return !can_view($type, $row); }

/* ---------- 게시판 사용 여부 (설정 → 게시판 사용 설정) ---------- */
function board_keys() { return ['profile', 'running', 'timeline', 'rp', 'side', 'gallery', 'log', 'au', 'trpg', 'guestbook']; }
function board_on($key) { return S('bd_' . $key, '1') !== '0'; }
/** 콘텐츠 표(entity) 이름 -> 게시판 키. 게시판이 아닌 것(배너·트랙·스티커)은 null */
function entity_board($t) {
    $m = ['profiles' => 'profile', 'running' => 'running', 'timeline' => 'timeline', 'rp' => 'rp', 'side' => 'side', 'gallery' => 'gallery', 'logs' => 'log', 'aus' => 'au', 'trpg' => 'trpg', 'guestbook' => 'guestbook'];
    return isset($m[$t]) ? $m[$t] : null;
}
function entity_on($t) { $b = entity_board($t); return $b === null || board_on($b); }
/** 설정 묶음 -> 게시판 키 (게시판 전용 설정 묶음만) */
function setting_group_board($g) {
    $m = ['profile' => 'profile', 'running_set' => 'running', 'timeline_set' => 'timeline', 'gallery' => 'gallery', 'au' => 'au', 'guestbook' => 'guestbook'];
    return isset($m[$g]) ? $m[$g] : null;
}

/** <head> 안에 넣을 파비콘·홈 화면 아이콘·상단바 색 태그 (설정 → 파비콘) */
function favicon_tags() {
    $types = ['ico' => 'image/x-icon', 'png' => 'image/png', 'gif' => 'image/gif', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'avif' => 'image/avif', 'svg' => 'image/svg+xml'];
    $ext = function ($u) { return strtolower(pathinfo(parse_url($u, PHP_URL_PATH) ?: $u, PATHINFO_EXTENSION)); };
    $o = '';
    $fav = trim((string)S('favicon'));
    if ($fav !== '') {
        $u = asset($fav); $e = $ext($u);
        $o .= '<link rel="icon" href="' . h($u) . '"' . (isset($types[$e]) ? ' type="' . $types[$e] . '"' : '') . '>' . "\n";
    }
    $ap = trim((string)S('apple_icon'));
    if ($ap === '' && $fav !== '' && $ext(asset($fav)) !== 'ico') $ap = $fav;
    if ($ap !== '') { $o .= '<link rel="apple-touch-icon" href="' . h(asset($ap)) . '">' . "\n"; }
    $tc = (string)S('theme_color');
    if (preg_match('/^#[0-9a-fA-F]{6}$/', $tc)) { $o .= '<meta name="theme-color" content="' . h($tc) . '">' . "\n"; }
    return $o;
}

/** 갤러리 글의 이미지 목록: [대표 이미지, 추가 이미지...] (주소는 asset() 처리 전 값) */
function gallery_images($r) {
    $out = [];
    if (isset($r['image']) && trim((string)$r['image']) !== '') $out[] = trim((string)$r['image']);
    if (isset($r['more_images']) && trim((string)$r['more_images']) !== '') {
        $d = json_decode((string)$r['more_images'], true);
        if (is_array($d)) { foreach ($d as $u) { $u = trim((string)$u); if ($u !== '') $out[] = $u; } }
    }
    return $out;
}

/** 주소가 영상 파일(mp4/webm/m4v)인지 */
function media_is_video($u) {
    $p = parse_url((string)$u, PHP_URL_PATH);
    $e = strtolower(pathinfo($p !== null && $p !== false && $p !== '' ? $p : (string)$u, PATHINFO_EXTENSION));
    return in_array($e, ['mp4', 'webm', 'm4v'], true);
}

/** <head> 안에 넣을 링크 미리보기(OG·트위터 카드) 태그. $title/$desc를 페이지별로 넘기면 그 값이 우선, 없으면 사이트 기본값 */
function og_tags($title = null, $desc = null) {
    $t = $title !== null && trim((string)$title) !== '' ? $title : (S('og_title') !== '' ? S('og_title') : S('site_title'));
    $d = $desc !== null && trim((string)$desc) !== '' ? $desc : S('og_desc');
    $img = trim((string)S('og_image'));
    $o = '<meta property="og:type" content="website">' . "\n";
    $o .= '<meta property="og:title" content="' . h($t) . '">' . "\n";
    $o .= '<meta name="twitter:title" content="' . h($t) . '">' . "\n";
    $o .= '<meta name="twitter:card" content="' . ($img !== '' ? 'summary_large_image' : 'summary') . '">' . "\n";
    if ($d !== '') {
        $d = mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags($d))), 0, 200);
        $o .= '<meta property="og:description" content="' . h($d) . '">' . "\n";
        $o .= '<meta name="twitter:description" content="' . h($d) . '">' . "\n";
    }
    if ($img !== '') {
        $u = asset($img);
        $o .= '<meta property="og:image" content="' . h($u) . '">' . "\n";
        $o .= '<meta name="twitter:image" content="' . h($u) . '">' . "\n";
    }
    if (function_exists('current_url')) { $o .= '<meta property="og:url" content="' . h(current_url()) . '">' . "\n"; }
    return $o;
}

function current_url() {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : 'http';
    return $scheme . '://' . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '') . (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '');
}

/** 게시판 글의 음악: 화면 오른쪽 아래 뮤직 위젯이 이 글의 곡으로 바뀌도록 정보만 심어둠(보이지 않음). mp3 파일·직접 주소·유튜브 링크를 모두 지원 */
function board_bgm_data($r, $defaultLabel, $prefix = 'bgm') {
    $fileKey = $prefix . '_file'; $titleKey = $prefix . '_title'; $autoKey = $prefix . '_auto';
    if (!isset($r[$fileKey]) || trim((string)$r[$fileKey]) === '') return '';
    $yt = yt_id($r[$fileKey]);
    $src = $yt === '' ? asset($r[$fileKey]) : '';
    $label = (isset($r[$titleKey]) && $r[$titleKey] !== '') ? $r[$titleKey] : $defaultLabel;
    $auto = isset($r[$autoKey]) && on1($r[$autoKey]);
    return '<div class="page-bgm" hidden data-title="' . h($label) . '" data-src="' . h($src) . '" data-yt="' . h($yt) . '"' . ($auto ? ' data-auto="1"' : '') . '></div>';
}

/* ---------- 텍스트 유틸 ---------- */
function nl2br_h($s) { return nl2br(h($s)); }
function parse_tags($s) {
    $s = str_replace('#', '', (string)$s);
    $parts = preg_split('/\s*[,，]\s*/u', trim($s));
    $out = [];
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p !== '' && !in_array($p, $out, true)) $out[] = $p;
    }
    return $out;
}
function fmt_period($s, $e) {
    $f = function ($d) { return $d ? date('Y.m.d', strtotime($d)) : ''; };
    $s = $f($s); $e = $f($e);
    if ($s !== '' && $e !== '' && $s !== $e) return $s . ' ~ ' . $e;
    if ($s !== '' && $e === '') return $s . ' ~';
    if ($s === '' && $e !== '') return '~ ' . $e;
    return $s;
}
function card_style_attr($r) {
    $s = (isset($r['card_color']) && preg_match('/^#[0-9a-fA-F]{6}$/', (string)$r['card_color'])) ? 'background-color:' . $r['card_color'] . ';' : '';
    $s .= (isset($r['card_text']) && preg_match('/^#[0-9a-fA-F]{6}$/', (string)$r['card_text'])) ? 'color:' . $r['card_text'] . ';' : '';
    return $s;
}
function add_button($t, $ret, $label = '+ 새 글 쓰기') {
    if (!is_admin()) return '';
    return '<a class="btn sm add" data-nopjax href="' . h(url('admin/content.php')) . '?t=' . h($t) . '&amp;a=new&amp;ret=' . h($ret) . '">' . h($label) . '</a>';
}
function fmt_date($dt) { return $dt ? date('Y.m.d', strtotime($dt)) : ''; }

/** 여러 줄 "항목: 내용" 텍스트를 [[label, value], ...]로 */
function parse_info($text) {
    $out = [];
    foreach (preg_split('/\r\n|\r|\n/', (string)$text) as $line) {
        $line = trim($line);
        if ($line === '') continue;
        if (preg_match('/^(.{1,30}?)\s*[:：|]\s*(.+)$/u', $line, $m)) { $out[] = [trim($m[1]), trim($m[2])]; }
        else { $out[] = ['', $line]; }
    }
    return $out;
}
/** 썰 백업 대화 파싱: "1: 내용" / "2: 내용" 한 줄씩 -> [['who'=>1|2, 'text'=>...], ...] */
function parse_chat($text) {
    $out = [];
    foreach (preg_split('/\r\n|\r|\n/', (string)$text) as $line) {
        $line = trim($line);
        if ($line === '') continue;
        if (preg_match('/^([12])\s*[:.\)）]\s*(.+)$/u', $line, $m)) {
            $out[] = ['who' => (int)$m[1], 'text' => $m[2]];
        }
    }
    return $out;
}
/** RP 대화 파싱: 텍스트 "1:"/"2:", 이미지 "1img:"/"2img:", 링크 "1link: 제목 | 주소" 지원 */
function parse_rp_chat($text) {
    $out = [];
    foreach (preg_split('/\r\n|\r|\n/', (string)$text) as $line) {
        $line = trim($line);
        if ($line === '') continue;
        if (preg_match('/^([12])img\s*[:.\)）]\s*(.+)$/u', $line, $m)) {
            $out[] = ['who' => (int)$m[1], 'type' => 'img', 'src' => trim($m[2])];
        } elseif (preg_match('/^([12])link\s*[:.\)）]\s*(.+?)\s*\|\s*(.+)$/u', $line, $m)) {
            $out[] = ['who' => (int)$m[1], 'type' => 'link', 'ttl' => trim($m[2]), 'url' => trim($m[3])];
        } elseif (preg_match('/^([12])\s*[:.\)）]\s*(.+)$/u', $line, $m)) {
            $out[] = ['who' => (int)$m[1], 'type' => 'text', 'text' => $m[2]];
        }
    }
    return $out;
}

/* ---------- 페이지네이션 ---------- */
function paginate($total, $per, $page) {
    $pages = max(1, (int)ceil($total / $per));
    $page = min(max(1, $page), $pages);
    return [($page - 1) * $per, $pages, $page];
}
function pager($page, $pages, $params = []) {
    if ($pages < 2) return '';
    $o = '<nav class="pager">';
    for ($i = 1; $i <= $pages; $i++) {
        $p = $params;
        $p['p'] = $i;
        $o .= '<a class="' . ($i === $page ? 'on' : '') . '" href="?' . h(http_build_query($p)) . '">' . $i . '</a>';
    }
    return $o . '</nav>';
}

/* ---------- 플래시 메시지 ---------- */
function flash($msg, $type = 'ok') { $_SESSION['flash'][] = [$type, $msg]; }
function flashes() { $f = isset($_SESSION['flash']) ? $_SESSION['flash'] : []; unset($_SESSION['flash']); return $f; }

/* ---------- 파일 업로드 ---------- */
/** @return array [저장경로|null, 오류메시지|null] */
function save_upload($f, $kind = 'image') {
    if (!isset($f['error']) || is_array($f['error'])) return [null, '잘못된 업로드 요청입니다.'];
    if ($f['error'] === UPLOAD_ERR_NO_FILE) return [null, null];
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) {
        return [null, '파일이 너무 커요. (서버 업로드 제한: ' . ini_get('upload_max_filesize') . ') ' . ($kind === 'media' ? '영상은 몇 초짜리 짧은 클립으로 줄이거나 해상도를 낮춰서 올려주세요.' : '이미지를 줄여서 올려주세요.')];
    }
    if ($f['error'] !== UPLOAD_ERR_OK) return [null, '업로드 중 오류가 났어요. (코드 ' . (int)$f['error'] . ')'];
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if ($kind === 'audio') { $allowed = ['mp3', 'm4a', 'ogg', 'wav', 'aac']; }
    elseif ($kind === 'font') { $allowed = ['ttf', 'otf', 'woff', 'woff2']; }
    elseif ($kind === 'icon') { $allowed = ['ico', 'png', 'gif', 'jpg', 'jpeg', 'webp']; }
    elseif ($kind === 'media') { $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'mp4', 'webm']; }
    else { $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif']; }
    if (!in_array($ext, $allowed, true)) {
        return [null, '허용되지 않는 파일 형식이에요 (.' . $ext . '). 가능한 형식: ' . implode(', ', $allowed)];
    }
    if ($kind === 'icon') {
        if (!empty($f['size']) && (int)$f['size'] > 1048576) return [null, '아이콘 파일은 1MB 이하로 올려 주세요.'];
        if ($ext === 'ico') {
            $head = @file_get_contents($f['tmp_name'], false, null, 0, 4);
            if ($head !== "\x00\x00\x01\x00") return [null, 'ICO 파일이 아닌 것 같아요.'];
        } elseif (!@getimagesize($f['tmp_name'])) { return [null, '이미지 파일이 아닌 것 같아요.']; }
    }
    if ($kind === 'media') {
        if ($ext === 'mp4' || $ext === 'webm') {
            if (!empty($f['size']) && (int)$f['size'] > 52428800) return [null, '영상은 50MB 이하로 올려 주세요. 몇 초짜리 짧은 클립이면 보통 훨씬 작아요.'];
            $head = (string)@file_get_contents($f['tmp_name'], false, null, 0, 12);
            $okMp4 = (strlen($head) >= 12 && substr($head, 4, 4) === 'ftyp');
            $okWebm = (strlen($head) >= 4 && substr($head, 0, 4) === "\x1A\x45\xDF\xA3");
            if (($ext === 'mp4' && !$okMp4) || ($ext === 'webm' && !$okWebm)) return [null, '올바른 ' . strtoupper($ext) . ' 영상 파일이 아닌 것 같아요.'];
        } elseif ($ext !== 'avif' && !@getimagesize($f['tmp_name'])) { return [null, '이미지 파일이 아닌 것 같아요.']; }
    }
    if ($kind === 'image' && $ext !== 'avif') {
        if (!@getimagesize($f['tmp_name'])) return [null, '이미지 파일이 아닌 것 같아요.'];
    }
    $sub = date('Ym');
    $dir = ROOT . '/uploads/' . $sub;
    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
    if (!is_dir($dir) || !is_writable($dir)) {
        return [null, 'uploads 폴더에 쓰기 권한이 없어요. 가이드의 "권한 설정"을 확인해 주세요.'];
    }
    $name = bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) return [null, '파일을 저장하지 못했어요.'];
    @chmod($dir . '/' . $name, 0644);
    return ['uploads/' . $sub . '/' . $name, null];
}

/** 이미지가 지정한 크기보다 크면 줄여서 같은 파일에 덮어씀 (GD 필요, 없으면 그냥 무시) */
function shrink_image_if_needed($absPath, $maxSize = 40) {
    if (!function_exists('imagecreatefromstring') || !is_file($absPath)) return;
    // GIF는 움직이는 그림일 수 있어서(움짤), 다시 그리면 첫 프레임만 남아 애니메이션이 깨짐. 그대로 둠.
    if (strtolower(pathinfo($absPath, PATHINFO_EXTENSION)) === 'gif') return;
    $data = @file_get_contents($absPath);
    if (!$data) return;
    $img = @imagecreatefromstring($data);
    if (!$img) return;
    $w = imagesx($img); $h = imagesy($img);
    if ($w <= $maxSize && $h <= $maxSize) { imagedestroy($img); return; }
    $scale = min($maxSize / $w, $maxSize / $h);
    $nw = max(1, (int)round($w * $scale));
    $nh = max(1, (int)round($h * $scale));
    $dst = imagecreatetruecolor($nw, $nh);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
    imagefill($dst, 0, 0, $transparent);
    imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
    $ext = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));
    if ($ext === 'png') { imagepng($dst, $absPath); }
    elseif ($ext === 'gif') { imagegif($dst, $absPath); }
    elseif ($ext === 'jpg' || $ext === 'jpeg') { imagejpeg($dst, $absPath, 90); }
    elseif ($ext === 'webp' && function_exists('imagewebp')) { imagewebp($dst, $absPath); }
    imagedestroy($img); imagedestroy($dst);
}
/* ---------- 기타 유틸 ---------- */
function hex_rgba($hex, $a) {
    if (!preg_match('/^#([0-9a-fA-F]{2})([0-9a-fA-F]{2})([0-9a-fA-F]{2})$/', (string)$hex, $m)) return '';
    return 'rgba(' . hexdec($m[1]) . ',' . hexdec($m[2]) . ',' . hexdec($m[3]) . ',' . $a . ')';
}
/** 유튜브 주소에서 영상 ID 추출 (아니면 빈 문자열) */
function yt_id($u) {
    $u = trim((string)$u);
    if (preg_match('#(?:youtu\.be/|youtube\.com/(?:watch\?(?:[^\s]*&)?v=|embed/|shorts/|live/)|music\.youtube\.com/watch\?(?:[^\s]*&)?v=)([A-Za-z0-9_-]{11})#', $u, $m)) return $m[1];
    return '';
}

/** 롤20 등의 HTML 문서 전체를 (스타일은 살리고) 안전하게 정리: 스크립트/프레임/폼/이벤트 속성 제거 */
function sanitize_doc($html) {
    $html = (string)$html;
    if (trim($html) === '') return '';
    $html = preg_replace('#<!--.*?-->#s', '', $html);
    $bad = ['script', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'base', 'form', 'input', 'button', 'textarea', 'select', 'option', 'noscript', 'template'];
    if (!class_exists('DOMDocument')) {
        $html = preg_replace('#<(script|iframe|object|embed|form)\b.*?</\1>#is', '', $html);
        $html = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
        return $html;
    }
    $prev = libxml_use_internal_errors(true);
    $doc = new DOMDocument('1.0', 'UTF-8');
    $ok = $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    if (!$ok) return '';
    foreach (iterator_to_array($doc->childNodes) as $c) { if ($c->nodeType === XML_PI_NODE) $doc->removeChild($c); }
    strip_hidden_messages($doc, new DOMXPath($doc));
    $kill = [];
    foreach ($doc->getElementsByTagName('*') as $el) {
        $nm = strtolower($el->nodeName);
        if (in_array($nm, $bad, true)) { $kill[] = $el; continue; }
        if ($nm === 'meta' && $el->hasAttribute('http-equiv')) { $kill[] = $el; continue; }
        if ($nm === 'link') {
            $rel = strtolower((string)$el->getAttribute('rel'));
            $href = preg_replace('/[\x00-\x20]+/', '', (string)$el->getAttribute('href'));
            if (strpos($rel, 'stylesheet') === false || preg_match('#^(javascript|vbscript|data):#i', $href)) $kill[] = $el;
        }
    }
    foreach ($kill as $el) { if ($el->parentNode) $el->parentNode->removeChild($el); }
    foreach ($doc->getElementsByTagName('*') as $el) {
        $rm = [];
        foreach ($el->attributes as $a) {
            $nm = strtolower($a->nodeName);
            $v = (string)$a->nodeValue;
            if (strpos($nm, 'on') === 0 || $nm === 'srcdoc' || $nm === 'formaction') { $rm[] = $a->nodeName; continue; }
            if (in_array($nm, ['href', 'src', 'xlink:href', 'action', 'poster', 'background'], true)) {
                $clean = preg_replace('/[\x00-\x20]+/', '', $v);
                if (preg_match('#^(javascript|vbscript|data):#i', $clean)) {
                    if (!($nm === 'src' && preg_match('#^data:image/(png|jpe?g|gif|webp)#i', $clean))) { $rm[] = $a->nodeName; }
                }
            }
        }
        foreach ($rm as $r) { $el->removeAttribute($r); }
    }
    return '<!doctype html>' . $doc->saveHTML();
}

/** 붙여넣은 CSS에서 위험한 부분 제거 */
function clean_css($css) {
    $css = (string)$css;
    $css = preg_replace('#/\*.*?\*/#s', '', $css);
    $css = preg_replace('/@(charset|import|namespace)[^;]*;?/i', '', $css);
    $css = preg_replace('/expression\s*\(|behavior\s*:|-moz-binding\s*:|javascript\s*:|vbscript\s*:/i', '', $css);
    $css = str_replace(['<', '>'], '', $css);
    return trim($css);
}
/** CSS의 모든 선택자 앞에 $scope를 붙여 이 글 안쪽에만 적용되게 함 (html/body/:root는 $scope 자체로) */
function scope_css($css, $scope) {
    $out = '';
    $i = 0;
    $n = strlen($css);
    while ($i < $n) {
        $j = strpos($css, '{', $i);
        if ($j === false) break;
        $sel = trim(substr($css, $i, $j - $i));
        $depth = 1;
        $k = $j + 1;
        while ($k < $n && $depth > 0) {
            $ch = $css[$k];
            if ($ch === '{') $depth++;
            elseif ($ch === '}') $depth--;
            $k++;
        }
        $body = substr($css, $j + 1, $k - $j - 2);
        $i = $k;
        if ($sel === '') continue;
        if ($sel[0] === '@') {
            if (preg_match('/^@(media|supports)\b/i', $sel)) { $out .= $sel . '{' . scope_css($body, $scope) . '}'; }
            elseif (preg_match('/^@(font-face|(-webkit-)?keyframes)\b/i', $sel)) { $out .= $sel . '{' . $body . '}'; }
            continue;
        }
        $sc = [];
        foreach (explode(',', $sel) as $p) {
            $p = trim($p);
            if ($p === '') continue;
            if (preg_match('/^(html|body|:root)$/i', $p)) { $sc[] = $scope; }
            else { $sc[] = $scope . ' ' . preg_replace('/^(html|body)\s+/i', '', $p); }
        }
        if (!$sc) continue;
        $body = preg_replace('/position\s*:\s*fixed/i', 'position:relative', $body);
        $out .= implode(',', $sc) . '{' . $body . '}';
    }
    return $out;
}

/** 롤20 등에서 붙여넣은 로그: 줄바꿈을 <br>로 바꾸고 들여쓰기 공백 줄을 정리 */
/** 롤20에서 GM이 숨긴 굴림 등, "This message was hidden/has been hidden" 자리표시 줄을 DOM에서 통째로 지움 (이미 로드된 DOMDocument에 대해 실행) */
function strip_hidden_messages($doc, $xp) {
    $hiddenPat = '/this\s+message\s+(was|has\s+been)\s+hidden(\s+from\s+you)?/iu';
    $spaceLike = ["\xC2\xA0", "\xE3\x80\x80", "\xE2\x80\x87", "\xE2\x80\x89", "\xE2\x80\x8B"]; // nbsp, 전각 공백, 얇은 공백 등도 보통 공백으로 취급
    $drop = [];
    // ".message" 요소(안에 이름표 span·타임스탬프 등이 있어도 통째로 대상)와, 그 외에는 자식 태그가 없는 순수 텍스트 요소만 대상으로 함
    foreach ($xp->query('//*[contains(concat(" ", normalize-space(@class), " "), " message ")] | //p[not(*)] | //div[not(*)] | //span[not(*)]') as $msg) {
        $raw = str_replace($spaceLike, ' ', $msg->textContent);
        $txt = trim(preg_replace('/\s+/u', ' ', $raw));
        if ($txt !== '' && preg_match($hiddenPat, $txt)) { $drop[] = $msg; }
    }
    foreach ($drop as $msg) { if ($msg->parentNode) $msg->parentNode->removeChild($msg); }
}

function normalize_log($html) {
    $html = (string)$html;
    if (trim($html) === '' || !class_exists('DOMDocument')) return $html;
    $prev = libxml_use_internal_errors(true);
    $doc = new DOMDocument('1.0', 'UTF-8');
    $ok = $doc->loadHTML('<?xml encoding="UTF-8"><div id="jh-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    if (!$ok) return $html;
    $root = $doc->getElementsByTagName('div')->item(0);
    if (!$root) return $html;
    $xp = new DOMXPath($doc);
    strip_hidden_messages($doc, $xp);

    $nodes = [];
    foreach ($xp->query('//text()') as $n) { $nodes[] = $n; }
    foreach ($nodes as $n) {
        $par = $n->parentNode;
        if (!$par || strtolower($par->nodeName) === 'pre') continue;
        $v = str_replace("\r", '', $n->nodeValue);
        if (strpos($v, "\n") === false) continue;
        if (trim($v) === '') { $par->removeChild($n); continue; }
        $parts = preg_split('/[ \t]*\n[ \t]*/', $v);
        while ($parts && $parts[0] === '') array_shift($parts);
        while ($parts && $parts[count($parts) - 1] === '') array_pop($parts);
        $frag = $doc->createDocumentFragment();
        foreach ($parts as $i => $part) {
            if ($i > 0) $frag->appendChild($doc->createElement('br'));
            if ($part !== '') $frag->appendChild($doc->createTextNode($part));
        }
        $par->replaceChild($frag, $n);
    }
    $out = '';
    foreach ($root->childNodes as $c) { $out .= $doc->saveHTML($c); }
    return trim($out);
}

/* ---------- HTML 정리 (게시글 본문) ---------- */
function sanitize_html($html) {
    $html = (string)$html;
    if (trim($html) === '') return '';
    $html = preg_replace('#<!--.*?-->#s', '', $html);
    if (!class_exists('DOMDocument')) return sanitize_fallback($html);

    $prev = libxml_use_internal_errors(true);
    $doc = new DOMDocument('1.0', 'UTF-8');
    $ok = $doc->loadHTML('<?xml encoding="UTF-8"><div id="jh-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    if (!$ok) return sanitize_fallback($html);
    $root = $doc->getElementsByTagName('div')->item(0);
    if (!$root) return sanitize_fallback($html);

    $bad = ['script', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'link', 'meta', 'base', 'style',
        'form', 'input', 'button', 'textarea', 'select', 'option', 'noscript', 'template', 'svg', 'math'];
    $kill = [];
    foreach ($root->getElementsByTagName('*') as $el) {
        if (in_array(strtolower($el->nodeName), $bad, true)) $kill[] = $el;
    }
    foreach ($kill as $el) { if ($el->parentNode) $el->parentNode->removeChild($el); }

    foreach ($root->getElementsByTagName('*') as $el) {
        $rm = [];
        foreach ($el->attributes as $a) {
            $nm = strtolower($a->nodeName);
            $v = (string)$a->nodeValue;
            if (strpos($nm, 'on') === 0 || $nm === 'srcdoc' || $nm === 'formaction') { $rm[] = $a->nodeName; continue; }
            if (in_array($nm, ['href', 'src', 'xlink:href', 'action', 'poster', 'background'], true)) {
                $clean = preg_replace('/[\x00-\x20]+/', '', $v);
                if (preg_match('#^(javascript|vbscript|data):#i', $clean)) {
                    if (!($nm === 'src' && preg_match('#^data:image/(png|jpe?g|gif|webp)#i', $clean))) { $rm[] = $a->nodeName; }
                }
                continue;
            }
            if ($nm === 'style' && preg_match('/expression\s*\(|javascript\s*:|behavior\s*:|-moz-binding/i', $v)) { $rm[] = $a->nodeName; }
        }
        foreach ($rm as $r) { $el->removeAttribute($r); }
    }
    $out = '';
    foreach ($root->childNodes as $c) { $out .= $doc->saveHTML($c); }
    $out = trim($out);
    if ($out === '' && trim(strip_tags($html)) !== '') return sanitize_fallback($html);
    return $out;
}
function sanitize_fallback($html) {
    $allow = '<p><br><b><strong><i><em><u><s><strike><span><div><img><a><h1><h2><h3><h4><blockquote><ul><ol><li><table><thead><tbody><tr><td><th><hr><font><center><small><sub><sup><pre><code>';
    $html = strip_tags($html, $allow);
    $html = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
    $html = preg_replace('/(href|src)\s*=\s*(["\']?)\s*javascript:/i', '$1=$2#', $html);
    return $html;
}

/* ---------- 테이블 스키마 보정 (업데이트 후 새 항목 추가용) ---------- */
function ensure_schema() {
    q('CREATE TABLE IF NOT EXISTS settings (k VARCHAR(100) NOT NULL, v MEDIUMTEXT NULL, PRIMARY KEY (k)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    q('CREATE TABLE IF NOT EXISTS attempts (id INT NOT NULL AUTO_INCREMENT, ip VARCHAR(45) NOT NULL, kind VARCHAR(20) NOT NULL, t INT NOT NULL, PRIMARY KEY (id), KEY idx_a (ip, kind, t)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    foreach (entities() as $t => $e) {
        q(create_sql($t, $e));
        $have = [];
        foreach (rows('SHOW COLUMNS FROM `' . $t . '`') as $c) { $have[$c['Field']] = 1; }
        foreach (column_defs($e) as $name => $def) {
            if (!isset($have[$name])) { q('ALTER TABLE `' . $t . '` ADD COLUMN `' . $name . '` ' . $def); }
        }
    }
}

/** 새 버전으로 바뀔 때 한 번만 옮겨 주는 데이터 (표시가 남아 있어서 여러 번 실행돼도 안전) */
function migrate_data() {
    // 1) 예전 "내 폰트 올리기 (3개)" -> "내 폰트"로 합침. 이미 골라둔 글꼴은 새 이름으로 바꿔서 그대로 유지
    if ((string)S('mig_fonts') !== '1') {
        $a = settings_all();
        $map = [];
        for ($i = 1; $i <= 3; $i++) {
            $file = isset($a['cf' . $i . '_file']) ? (string)$a['cf' . $i . '_file'] : '';
            if ($file === '') continue;
            $n = isset($a['cf' . $i . '_name']) ? trim(preg_replace('/[^\p{L}\p{N} _-]/u', '', (string)$a['cf' . $i . '_name'])) : '';
            $id = insert_row('fonts', ['name' => $n !== '' ? $n : '내 폰트 ' . $i, 'file' => $file, 'created_at' => now()]);
            $map['cf' . $i] = 'fd' . $id;
        }
        foreach ($map as $old => $new) {
            q('UPDATE settings SET v=? WHERE v=?', [$new, $old]);
            foreach (entities() as $t => $e) {
                foreach ($e['fields'] as $f) {
                    if ($f['t'] === 'select') { try { q('UPDATE `' . $t . '` SET `' . $f['n'] . '`=? WHERE `' . $f['n'] . '`=?', [$new, $old]); } catch (Exception $ex) { /* 무시 */ } }
                }
            }
        }
        for ($i = 1; $i <= 3; $i++) { q('DELETE FROM settings WHERE k IN (?, ?)', ['cf' . $i . '_name', 'cf' . $i . '_file']); }
        set_setting('mig_fonts', '1');
    }
    // 2) 사이트 전체 "AU 카드" 설정 -> AU마다 따로 (비어 있는 AU에만 예전 값을 채워 넣어서 모양이 그대로 유지됨)
    if ((string)S('mig_au_card') !== '1') {
        $map = ['au_name_font' => 'card_name_font', 'au_name_color' => 'card_name_color', 'au_name_size' => 'card_name_size',
                'au_char_font' => 'stage_font', 'au_char_color' => 'stage_color', 'au_char_size' => 'stage_size'];
        foreach ($map as $sk => $col) {
            $v = (string)S($sk);
            if ($v === '' || $v === '0') continue;
            if (substr($col, -5) === '_size') { q('UPDATE aus SET `' . $col . '`=? WHERE `' . $col . '`=0 OR `' . $col . '` IS NULL', [(int)$v]); }
            else { q('UPDATE aus SET `' . $col . '`=? WHERE `' . $col . '`=\'\' OR `' . $col . '` IS NULL', [$v]); }
        }
        foreach (array_keys($map) as $sk) { q('DELETE FROM settings WHERE k=?', [$sk]); }
        set_setting('mig_au_card', '1');
    }
}

/* ---------- 새 버전 파일로 교체한 뒤 첫 접속 때 DB 구조를 자동으로 맞춤 ---------- */
if (is_array($GLOBALS['JH_CFG']) && basename(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '') !== 'install.php') {
    try {
        if ((string)S('schema_ver', '0') !== '70') {
            ensure_schema();
            try { migrate_data(); } catch (Exception $e2) { error_log('data migration failed: ' . $e2->getMessage()); }
            set_setting('schema_ver', '70');
        }
    } catch (Exception $e) {
        error_log('schema auto-update failed: ' . $e->getMessage());
    }
}
