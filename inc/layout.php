<?php
/** 프론트 공통 레이아웃: 좌측 곡선 메뉴 + 뷰 + 음악 위젯 */

function view_start($o) {
    $GLOBALS['JH_VIEW'] = array_merge(['title' => '', 'page' => '', 'menu' => -1, 'bg' => 'home', 'bgcss' => '', 'ghost' => null], $o);
    $GLOBALS['JH_VIEW']['menu'] = menu_vis_index($GLOBALS['JH_VIEW']['menu']);
    $GLOBALS['JH_VIEW_OPEN'] = true;
    ob_start();
}

/** 왼쪽 메뉴 자리(슬롯). 번호는 각 페이지가 view_start에 넘기는 'menu' 값과 같아요. */
function menu_slots() {
    return [
        0 => ['label' => S('m_character'), 'group' => 'character', 'icon' => 'character', 'children' => [
            ['profile.php', S('m_profile'), 'profile'],
            ['running.php', S('m_running'), 'running'],
        ]],
        1 => ['label' => S('m_story'), 'group' => 'story', 'icon' => 'story', 'children' => [
            ['timeline.php', S('m_timeline'), 'timeline'],
            ['rp.php', S('m_rp'), 'rp'],
            ['side.php', S('m_side'), 'side'],
        ]],
        2 => ['gallery.php', S('m_gallery'), 'gallery'],
        3 => ['log.php', S('m_log'), 'log'],
        4 => ['au.php', S('m_au'), 'au'],
        5 => ['trpg.php', S('m_trpg'), 'trpg'],
        6 => ['guestbook.php', S('m_guestbook'), 'guestbook'],
    ];
}

/** 실제로 보이는 메뉴. 꺼둔 게시판은 빠지고, 묶음 안에 하나만 남으면 묶음 없이 바로 연결, 하나도 없으면 묶음이 사라져요. */
function menu_items() {
    $out = [];
    foreach (menu_slots() as $slot => $m) {
        if (isset($m['children'])) {
            $kids = [];
            foreach ($m['children'] as $c) { if (board_on($c[2])) $kids[] = $c; }
            if (!$kids) continue;
            if (count($kids) === 1) { $out[] = [$kids[0][0], $kids[0][1], 'slot' => $slot, 'icon' => $kids[0][2]]; continue; }
            $out[] = ['label' => $m['label'], 'group' => $m['group'], 'children' => $kids, 'slot' => $slot, 'icon' => $m['icon']];
        } else {
            if (!board_on($m[2])) continue;
            $out[] = [$m[0], $m[1], 'slot' => $slot, 'icon' => $m[2]];
        }
    }
    return $out;
}

/** 페이지가 넘긴 슬롯 번호 -> 지금 메뉴에서의 실제 순서 (메뉴에 없으면 -1) */
function menu_vis_index($slot) {
    if ((int)$slot < 0) return -1;
    foreach (menu_items() as $i => $m) { if ($m['slot'] === (int)$slot) return $i; }
    return -1;
}

/** 왼쪽 메뉴용 작은 선 아이콘 */
function menu_icon($k) {
    $p = [
        'profile' => '<circle cx="12" cy="8" r="3.4"/><path d="M5 20c1.2-4 4-6 7-6s5.8 2 7 6"/>',
        'character' => '<circle cx="9" cy="8" r="3"/><path d="M3 20c.8-3.5 3-5 6-5s5.2 1.5 6 5"/><path d="M16 5.5a3 3 0 0 1 0 5.5M18 15c1.8.6 3 2.2 3.5 5"/>',
        'running' => '<path d="M5 21V4"/><path d="M5 4h11l-2 4 2 4H5"/>',
        'story' => '<path d="M5 4h13a1 1 0 0 1 1 1v15H6a2 2 0 0 1-2-2V5a1 1 0 0 1 1-1z"/><path d="M4 18a2 2 0 0 1 2-2h13"/>',
        'timeline' => '<path d="M4 18c3-8 6 6 9-2s5 4 7-4"/><circle cx="4" cy="18" r="1.3" fill="currentColor" stroke="none"/><circle cx="20" cy="12" r="1.3" fill="currentColor" stroke="none"/>',
        'rp' => '<path d="M4 5h16a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H9l-5 4V6a1 1 0 0 1 1-1z"/><path d="M8 10h8M8 13h5"/>',
        'side' => '<path d="M4 5h16a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H9l-5 4V6a1 1 0 0 1 1-1z"/><circle cx="8.5" cy="11" r=".9" fill="currentColor" stroke="none"/><circle cx="12" cy="11" r=".9" fill="currentColor" stroke="none"/><circle cx="15.5" cy="11" r=".9" fill="currentColor" stroke="none"/>',
        'gallery' => '<rect x="3.5" y="4.5" width="17" height="15" rx="2"/><circle cx="8.5" cy="9.5" r="1.4"/><path d="M20 15l-4.5-4.5-4 4-2.5-2.5L4 17"/>',
        'log' => '<path d="M6 3.5h9l4 4V20a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4.5a1 1 0 0 1 1-1z"/><path d="M9 12h7M9 15.5h7M9 8.5h3"/>',
        'au' => '<path d="M12 3l2 5 5 .8-3.6 3.5.9 5-4.3-2.4L7.7 17.3l.9-5L5 8.8 10 8z"/>',
        'trpg' => '<rect x="4" y="4" width="16" height="16" rx="3"/><circle cx="8.3" cy="8.3" r="1.1" fill="currentColor" stroke="none"/><circle cx="15.7" cy="8.3" r="1.1" fill="currentColor" stroke="none"/><circle cx="12" cy="12" r="1.1" fill="currentColor" stroke="none"/><circle cx="8.3" cy="15.7" r="1.1" fill="currentColor" stroke="none"/><circle cx="15.7" cy="15.7" r="1.1" fill="currentColor" stroke="none"/>',
        'guestbook' => '<rect x="3.5" y="5.5" width="17" height="13" rx="2"/><path d="M4 6.5l8 6.5 8-6.5"/>',
    ];
    if (!isset($p[$k])) return '';
    return '<span class="mi-ic" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $p[$k] . '</svg></span>';
}
function rail_style() { $v = S('rail_style', 'line'); return in_array($v, ['line', 'line_icon', 'icon'], true) ? $v : 'line'; }

/** 꺼둔 게시판 주소로 들어오면 안내만 보여주고 멈춤 */
function board_guard($key) {
    if (board_on($key)) return;
    page_message('사용하지 않는 게시판이에요', '이 게시판은 지금 사용하지 않도록 설정되어 있어요.', -1, 404);
}

/** 배경 CSS 조립: 색 / 그라데이션(색 2개) / 이미지 */
function bg_css($c1, $c2, $img) {
    $hex = '/^#[0-9a-fA-F]{6}$/';
    $c1 = preg_match($hex, (string)$c1) ? $c1 : '';
    $c2 = preg_match($hex, (string)$c2) ? $c2 : '';
    $layers = [];
    $s = '';
    if ($img !== '' && $img !== null) $layers[] = "url('" . cssurl(asset($img)) . "')";
    if ($c1 !== '' && $c2 !== '') {
        $ang = (int)S('bg_angle');
        $layers[] = 'linear-gradient(' . $ang . 'deg,' . $c1 . ',' . $c2 . ')';
    }
    if ($c1 !== '') $s .= 'background-color:' . $c1 . ';';
    elseif ($c2 !== '') $s .= 'background-color:' . $c2 . ';';
    if ($layers) $s .= 'background-image:' . implode(',', $layers) . ';background-size:cover;background-position:center;';
    return $s;
}
function bg_style($key) {
    $c = S('bgc_' . $key); $c2 = S('bgc2_' . $key); $i = S('bgi_' . $key);
    if ($c === '' && $c2 === '' && $i === '' && $key !== 'home') return bg_style('home');
    return bg_css($c, $c2, $i);
}
/** 개별 상세 페이지(프로필/AU)용 배경 */
function custom_bg($img, $color, $color2 = '') {
    return bg_css($color, $color2, $img);
}

function view_end() {
    $o = $GLOBALS['JH_VIEW'];
    $html = ob_get_clean();
    $GLOBALS['JH_VIEW_OPEN'] = false;
    $bg = $o['bgcss'] !== '' ? $o['bgcss'] : bg_style($o['bg']);
    $title = ($o['title'] !== '' && $o['page'] !== 'home') ? $o['title'] . ' · ' . S('site_title') : S('site_title');
    header('Vary: X-PJAX');
    header('Cache-Control: no-store, no-cache, must-revalidate, private');
    header('Pragma: no-cache');
    header('Expires: 0');
    if (is_pjax()) {
        json_out(['title' => $title, 'page' => $o['page'], 'menu' => $o['menu'], 'bg' => $bg, 'html' => $html, 'ghost' => $o['ghost']]);
    }
    render_shell($title, $o, $bg, $html);
}

/** 메시지 전용 페이지 (404/권한 없음 등). 호출 즉시 종료 */
function page_message($title, $msg, $menu = -1, $code = 404) {
    if (!empty($GLOBALS['JH_VIEW_OPEN'])) { ob_end_clean(); }
    http_response_code($code);
    view_start(['title' => $title, 'page' => 'msg', 'menu' => $menu, 'bg' => 'home']);
    echo '<div class="msg-box"><h1>' . h($title) . '</h1><p>' . h($msg) . '</p><a class="btn" href="' . h(url('home.php')) . '">홈으로</a></div>';
    view_end();
    exit;
}

/** 비밀글/관리자 전용글 접근 확인. 볼 수 없으면 안내 화면을 출력하고 종료 */
function gate($type, $row, $menu, $bgkey = 'home') {
    if (can_view($type, $row)) return;
    if (!empty($GLOBALS['JH_VIEW_OPEN'])) { ob_end_clean(); }
    $isPw = ($row['visibility'] === 'password');
    view_start(['title' => $isPw ? '비밀글' : '알림', 'page' => 'lock', 'menu' => $menu, 'bg' => $bgkey]);
    if ($isPw) {
        echo '<div class="lock-box">' . lock_icon()
            . '<h1>비밀글이에요</h1><p>비밀번호를 입력하면 볼 수 있어요.</p>'
            . '<form data-unlock data-type="' . h($type) . '" data-id="' . (int)$row['id'] . '">'
            . '<input type="password" name="pw" autocomplete="off" required placeholder="비밀번호">'
            . '<button class="btn" type="submit">열기</button></form><p class="lock-err" role="alert"></p></div>';
    } else {
        echo '<div class="lock-box">' . lock_icon() . '<h1>볼 수 없는 글이에요</h1><p>관리자만 볼 수 있는 글이거나 삭제된 글이에요.</p>'
            . '<a class="btn" href="' . h(url('home.php')) . '">홈으로</a></div>';
    }
    view_end();
    exit;
}

/** 목록으로 돌아가는 화살표 버튼 (글자 없이 큰 아이콘만) */
function back_link($href, $extraClass = '', $label = '목록으로', $color = '') {
    $st = preg_match('/^#[0-9a-fA-F]{6}$/', (string)$color) ? ' style="color:' . h($color) . '"' : '';
    return '<a class="back-btn ' . h($extraClass) . '" href="' . h($href) . '" aria-label="' . h($label) . '"' . $st . '>'
        . '<svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true"><path d="M15 4L15 20L5 12Z"/></svg></a>';
}

function lock_icon() {
    return '<svg class="lock-ico" viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>';
}

function panel_var() {
    $c = hex_rgba(S('card_color'), min(100, max(10, (int)S('card_opacity'))) / 100);
    $t = preg_match('/^#[0-9a-fA-F]{6}$/', (string)S('card_text')) ? '--pt:' . S('card_text') . ';' : '';
    return ($c !== '' ? '--panel:' . $c . ';' : '') . $t;
}
/** 커스텀 마우스 커서 CSS 변수 */
function cursor_css_var() {
    $css = '';
    $img = S('cursor_img');
    if ($img !== '') $css .= "--cursor:url('" . cssurl(asset($img)) . "') 4 4, auto;";
    $hover = S('cursor_hover_img');
    if ($hover !== '') $css .= "--cursor-hover:url('" . cssurl(asset($hover)) . "') 4 4, pointer;";
    return $css;
}
/** 내가 올린 폰트의 @font-face */
function custom_font_css() {
    $css = '';
    foreach (custom_fonts() as $f) {
        $u = asset($f['file']);
        $ext = strtolower(pathinfo(parse_url($u, PHP_URL_PATH) ?: $u, PATHINFO_EXTENSION));
        $fmt = ['woff2' => 'woff2', 'woff' => 'woff', 'otf' => 'opentype', 'ttf' => 'truetype'];
        $css .= "@font-face{font-family:'" . $f['family'] . "';src:url('" . cssurl($u) . "')" . (isset($fmt[$ext]) ? " format('" . $fmt[$ext] . "')" : '') . ';font-display:swap}';
    }
    return $css;
}

/** 롤20 채팅 기본 스킨 CSS ($scope: 적용 범위 선택자) */
function roll20_skin_css($scope) {
    $f = ROOT . '/inc/roll20_skin.css';
    return is_file($f) ? str_replace('%S%', $scope, (string)file_get_contents($f)) : '';
}
/** 스킨에 쓰는 CSS 변수 선언 문자열 */
function roll20_skin_vars($r) {
    $hex = '/^#[0-9a-fA-F]{6}$/';
    $ink = preg_match($hex, (string)$r['t_color']) ? $r['t_color'] : '#000000';
    $line = preg_match($hex, (string)$r['t_skin_line']) ? $r['t_skin_line'] : '#000000';
    $rowc = preg_match($hex, (string)$r['t_skin_row']) ? $r['t_skin_row'] : '#ffffff';
    $op = trim((string)$r['t_skin_op']) === '' ? 55 : min(100, max(0, (int)$r['t_skin_op']));
    $font = $r['t_font'] !== '' ? font_stack($r['t_font']) : font_stack(S('font'));
    return '--rl-ink:' . $ink . ';--rl-line:' . $line . ';--rl-row:' . hex_rgba($rowc, $op / 100) . ';--rl-font:' . $font . ';';
}
function roll20_skin_applies($r) {
    if ($r['t_skin'] === 'off') return false;
    if ($r['t_skin'] === 'on') return true;
    return (bool)preg_match('/class="[^"]*\bmessage\b[^"]*"/', (string)$r['raw_html'] . (string)$r['body']);
}

function cssvar($name, $val) { return preg_match('/^#[0-9a-fA-F]{3,8}$/', (string)$val) ? '--' . $name . ':' . $val . ';' : ''; }

function dday_items() {
    $out = [];
    $today = strtotime(date('Y-m-d'));
    foreach (preg_split('/\r\n|\r|\n/', (string)S('dday_items')) as $line) {
        $line = trim($line);
        if ($line === '') continue;
        $p = preg_split('/\s*[|｜]\s*/u', $line, 2);
        if (count($p) < 2) continue;
        $ts = strtotime(trim($p[1]));
        if (!$ts) continue;
        $diff = (int)round(($ts - $today) / 86400);
        if ($diff > 0) $txt = 'D-' . $diff;
        elseif ($diff === 0) $txt = 'D-DAY';
        else $txt = 'D+' . (abs($diff) + (S('dday_first') === '1' ? 1 : 0));
        if (S('dday_spaced') === '1') $txt = preg_replace('/^D([+-])(\d)/', 'D $1 $2', $txt);
        $out[] = ['label' => trim($p[0]), 'text' => $txt, 'date' => date('Y.m.d', $ts)];
    }
    return $out;
}

function svg_icon($name) {
    $p = [
        'play' => '<path d="M8 5v14l11-7z" fill="currentColor" stroke="none"/>',
        'pause' => '<path d="M7 5h4v14H7zM13 5h4v14h-4z" fill="currentColor" stroke="none"/>',
        'prev' => '<path d="M6 5v14M19 5v14l-10-7z" fill="currentColor"/>',
        'next' => '<path d="M18 5v14M5 5v14l10-7z" fill="currentColor"/>',
        'min' => '<path d="M6 12h12"/>',
        'note' => '<path d="M9 18V5l10-2v13"/><circle cx="6" cy="18" r="3" fill="currentColor" stroke="none"/><circle cx="16" cy="16" r="3" fill="currentColor" stroke="none"/>',
        'mic' => '<path d="M12 2a3 3 0 0 1 3 3v6a3 3 0 0 1-6 0V5a3 3 0 0 1 3-3Z"/><path d="M19 11a7 7 0 0 1-14 0"/><path d="M12 18v4"/><path d="M9 22h6"/>',
    ];
    return '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p[$name] . '</svg>';
}

function music_widget($tracks) {
    if (S('music_on') !== '1' || !$tracks) return '';
    $st = '';
    if (S('music_bg_color') !== '') $st .= 'background-color:' . S('music_bg_color') . ';';
    if (S('music_bg') !== '') $st .= "background-image:url('" . cssurl(asset(S('music_bg'))) . "');background-size:cover;background-position:center;";
    if (S('music_text') !== '') $st .= 'color:' . S('music_text') . ';';
    ob_start(); ?>
<div id="music" class="widget music" data-widget="music" data-scope="<?= h(S('music_scope')) ?>" style="<?= h($st) ?>">
  <div class="mw-veil"></div>
  <div class="mw-in">
    <div class="mw-media" hidden><img class="mw-cover" alt=""><div class="mw-video"></div><div class="mw-shield"></div></div>
    <div class="mw-top">
      <div class="mw-txt"><span class="mw-tag">이 글의 음악</span><b class="mw-title">-</b><span class="mw-artist"></span></div>
      <button type="button" class="mw-min" aria-label="플레이어 접기/펼치기"><?= svg_icon('min') ?></button>
    </div>
    <div class="mw-ctl">
      <button type="button" class="mw-prev" aria-label="이전 곡"><?= svg_icon('prev') ?></button>
      <button type="button" class="mw-play" aria-label="재생/일시정지"><span class="i-play"><?= svg_icon('play') ?></span><span class="i-pause"><?= svg_icon('pause') ?></span></button>
      <button type="button" class="mw-next" aria-label="다음 곡"><?= svg_icon('next') ?></button>
      <input type="range" class="mw-vol" min="0" max="1" step="0.05" value="0.7" aria-label="볼륨">
    </div>
    <div class="mw-bar" role="progressbar"><div class="mw-fill"></div></div>
  </div>
</div>
<?php
    return ob_get_clean();
}

function render_shell($title, $o, $bg, $html) {
    header('Content-Type: text/html; charset=utf-8');
    $vars = cssvar('ink', S('text_color')) . cssvar('accent', S('accent_color')) . cssvar('accent-ink', S('accent_ink_color'))
        . '--font:' . font_stack(S('font')) . ';'
        . '--head:' . font_stack(S('head_font')) . ';'
        . cssvar('m-top', S('menu_c_top')) . cssvar('m-mid', S('menu_c_mid')) . cssvar('m-bot', S('menu_c_bot'))
        . cssvar('menu-ink', S('menu_text'))
        . cssvar('h-top', S('hover_c_top')) . cssvar('h-mid', S('hover_c_mid')) . cssvar('h-bot', S('hover_c_bot'))
        . '--h-a:' . (min(100, max(10, (int)S('hover_strength'))) / 100) . ';'
        . '--dim:' . (min(80, max(0, (int)S('bg_dim'))) / 100) . ';'
        . cssvar('logo-shadow', S('logo_shadow_color'))
        . cssvar('card-shadow', S('card_shadow_color'))
        . cursor_css_var()
        . panel_var();

    $tracks = [];
    if (S('music_on') === '1') {
        foreach (rows('SELECT title, artist, file, cover FROM tracks ORDER BY sort_order ASC, id ASC') as $t) {
            if ($t['file'] === '') continue;
            $y = yt_id($t['file']);
            $tracks[] = ['title' => $t['title'], 'artist' => $t['artist'], 'src' => $y !== '' ? '' : asset($t['file']), 'yt' => $y,
                'cover' => $t['cover'] !== '' ? asset($t['cover']) : ($y !== '' ? 'https://i.ytimg.com/vi/' . $y . '/hqdefault.jpg' : '')];
        }
    }
    $pos = json_decode((string)S('widget_pos', '{}'), true);
    if (!is_array($pos)) $pos = [];
    $jh = [
        'base' => BASE, 'csrf' => csrf_token(), 'admin' => is_admin(),
        'tracks' => $tracks, 'autoplay' => S('music_autoplay') === '1',
        'widgetPos' => $pos, 'widgetVer' => (string)S('widget_ver', '0'),
        'particles' => on1(S('particles_on')) ? [
            'shape' => S('particles_shape', 'star'),
            'color' => preg_match('/^#[0-9a-fA-F]{6}$/', (string)S('particles_color')) ? S('particles_color') : '#ffffff',
            'count' => min(150, max(5, (int)S('particles_count', 40))),
            'speed' => min(10, max(1, (int)S('particles_speed', 4))),
            'size' => min(20, max(2, (int)S('particles_size', 7))),
        ] : null,
        'mtrail' => on1(S('mtrail_on')) ? [
            'shape' => S('mtrail_shape', 'star'),
            'color' => preg_match('/^#[0-9a-fA-F]{6}$/', (string)S('mtrail_color')) ? S('mtrail_color') : '#ffffff',
            'color2' => preg_match('/^#[0-9a-fA-F]{6}$/', (string)S('mtrail_color2')) ? S('mtrail_color2') : null,
            'size' => min(30, max(4, (int)S('mtrail_size', 10))),
            'amount' => min(10, max(1, (int)S('mtrail_amount', 4))),
            'life' => min(10, max(1, (int)S('mtrail_life', 5))),
        ] : null,
        'cursorFx' => (on1(S('cursor_fx_on')) && S('cursor_fx_img') !== '') ? [
            'img' => asset(S('cursor_fx_img')),
            'hover' => S('cursor_fx_hover_img') !== '' ? asset(S('cursor_fx_hover_img')) : null,
            'size' => min(200, max(12, (int)S('cursor_fx_size', 32))),
            'hx' => max(0, (int)S('cursor_fx_hx', 0)), 'hy' => max(0, (int)S('cursor_fx_hy', 0)),
            'hide' => S('cursor_fx_hide') !== '0',
        ] : null,
        'clickSound' => on1(S('click_sound_on')) ? [
            'file' => S('click_sound_file') !== '' ? asset(S('click_sound_file')) : null,
            'volume' => min(100, max(1, (int)S('click_sound_volume', 50))) / 100,
        ] : null,
    ];
    $ver = @filemtime(ROOT . '/assets/js/site.js') ?: 1;
    $verc = @filemtime(ROOT . '/assets/css/site.css') ?: 1;
    $fav = S('favicon');
    ?><!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= h($title) ?></title>
<?= favicon_tags() ?>
<?= og_tags($o['og_title'] ?? null, $o['og_desc'] ?? null) ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Gowun+Batang:wght@400;700&family=Noto+Serif+KR:wght@400;700;900&family=Gowun+Dodum&family=Nanum+Myeongjo:wght@400;700&family=Nanum+Gothic:wght@400;700&display=swap">
<link rel="stylesheet" href="<?= h(url('assets/css/site.css')) ?>?v=<?= $verc ?>">
<style><?= custom_font_css() ?>:root{<?= $vars ?>}@media (min-width:861px){:root{--rail:<?= max(100, min(300, (int)S('rail_width', 160))) ?>px}}</style>
</head>
<body>
<div id="bg" style="<?= h($bg) ?>"></div>
<?php $ghost = isset($o['ghost']) ? $o['ghost'] : null; if (is_array($ghost) && !empty($ghost['img'])): ?>
<img id="pageGhost" class="<?= !empty($ghost['flip']) ? 'flip' : '' ?>" src="<?= h($ghost['img']) ?>" alt="" aria-hidden="true" style="opacity:<?= h((string)$ghost['opacity']) ?>">
<?php endif; ?>
<canvas id="particles" aria-hidden="true"></canvas>

<nav id="rail" class="rs-<?= h(rail_style()) ?>" aria-label="메인 메뉴">
  <svg id="rail-svg" aria-hidden="true">
    <defs>
      <linearGradient id="rg" gradientUnits="userSpaceOnUse" x1="0" y1="0" x2="0" y2="800">
        <stop offset="0" stop-color="#45d6c8" style="stop-color:var(--m-top)"/>
        <stop offset="0.5" stop-color="#ffffff" style="stop-color:var(--m-mid)"/>
        <stop offset="1" stop-color="#e5483f" style="stop-color:var(--m-bot)"/>
      </linearGradient>
    </defs>
    <path id="rail-path" fill="none" stroke="url(#rg)" stroke-width="2" stroke-linecap="round"/>
    <g id="rail-dots"></g>
  </svg>
  <?php if (S('rail_logo') !== ''): ?>
    <a id="rail-logo" href="<?= h(url('home.php')) ?>"><img src="<?= h(asset(S('rail_logo'))) ?>" alt="<?= h(S('site_title')) ?>" style="width:<?= max(40, min(400, (int)S('rail_logo_w'))) ?>px"></a>
  <?php else: ?>
    <a id="rail-home" href="<?= h(url('home.php')) ?>"><?= h(S('site_title')) ?></a>
  <?php endif; ?>
  <?php $railIc = rail_style() !== 'line'; foreach (menu_items() as $i => $m): ?>
    <?php if (isset($m['children'])): ?>
    <button type="button" class="mi mi-group" data-i="<?= $i ?>">
      <?= $railIc ? menu_icon($m['icon']) : '' ?><span class="mi-txt"><?= h($m['label']) ?></span>
      <span class="mi-sub">
        <?php foreach ($m['children'] as $c): ?><a href="<?= h(url($c[0])) ?>"><?= $railIc ? menu_icon($c[2]) : '' ?><?= h($c[1]) ?></a><?php endforeach; ?>
      </span>
    </button>
    <?php else: ?>
    <a class="mi" data-i="<?= $i ?>" href="<?= h(url($m[0])) ?>"><?= $railIc ? menu_icon($m['icon']) : '' ?><span class="mi-txt"><?= h($m[1]) ?></span></a>
    <?php endif; ?>
  <?php endforeach; ?>
</nav>

<main id="view" tabindex="-1" data-page="<?= h($o['page']) ?>" data-menu="<?= (int)$o['menu'] ?>"><?= $html ?></main>

<div id="persist"><?= music_widget($tracks) ?></div>

<div id="lb" class="lb" hidden>
  <div class="lb-stage"><img class="lb-img" alt=""><video class="lb-vid" controls loop muted playsinline hidden></video><div class="lb-blur-veil" hidden><div class="lb-blur-msg">흐림 처리된 이미지예요<br><span>눌러서 보기</span></div></div><div class="lb-lock"></div></div>
  <div class="lb-grid" hidden></div>
  <div class="lb-bar" hidden>
    <span class="lb-count"></span>
    <button type="button" class="lb-gridbtn" aria-pressed="false"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="4" width="6.5" height="6.5" rx="1.2"></rect><rect x="13.5" y="4" width="6.5" height="6.5" rx="1.2"></rect><rect x="4" y="13.5" width="6.5" height="6.5" rx="1.2"></rect><rect x="13.5" y="13.5" width="6.5" height="6.5" rx="1.2"></rect></svg><span>전체 보기</span></button>
  </div>
  <button type="button" class="lb-x" aria-label="닫기">&times;</button>
  <button type="button" class="lb-nav lb-prev" aria-label="이전">&#8249;</button>
  <button type="button" class="lb-nav lb-next" aria-label="다음">&#8250;</button>
  <button type="button" class="lb-pp" title="이전 글 (↑)" hidden>이전 글</button>
  <button type="button" class="lb-pn" title="다음 글 (↓)" hidden>다음 글</button>
  <div class="lb-cap"></div>
</div>


<script nonce="<?= h(csp_nonce()) ?>">window.JH=<?= json_encode($jh, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="<?= h(url('assets/js/site.js')) ?>?v=<?= $ver ?>"></script>
</body>
</html>
<?php
}

/** 게시글 이전/다음 글 */
function neighbors($t, $id, $order) {
    $ids = [];
    foreach (rows('SELECT id, title FROM `' . $t . '` WHERE ' . vis_sql() . ' ORDER BY ' . $order) as $r) { $ids[] = $r; }
    $pos = -1;
    foreach ($ids as $i => $r) { if ((int)$r['id'] === (int)$id) { $pos = $i; break; } }
    $newer = ($pos > 0) ? $ids[$pos - 1] : null;
    $older = ($pos >= 0 && $pos < count($ids) - 1) ? $ids[$pos + 1] : null;
    return [$newer, $older];
}
