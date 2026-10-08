<?php
/** 관리자 페이지 공통: 로그인 확인 + 레이아웃 */
require __DIR__ . '/../inc/core.php';
require __DIR__ . '/../inc/forms.php';

if (!is_admin()) {
    redirect(url('home.php') . '?needlogin=1');
}
header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex');

function post($k, $d = '') { return isset($_POST[$k]) && !is_array($_POST[$k]) ? trim((string)$_POST[$k]) : $d; }

function require_post_csrf() {
    if (!csrf_ok()) {
        flash('보안 확인에 실패했어요. 페이지를 새로고침한 뒤 다시 시도해 주세요.', 'err');
        redirect($_SERVER['REQUEST_URI']);
    }
}

function custom_font_css_admin() {
    require_once __DIR__ . '/../inc/layout.php';
    return custom_font_css();
}

/** 관리자 화면용 작은 선 아이콘 */
function admin_icon($n, $size = 20) {
    static $p = [
        'home' => '<path d="M3.5 11L12 4l8.5 7"/><path d="M6 10v9.5h12V10"/><path d="M10 19.5v-5h4v5"/>',
        'content' => '<rect x="4" y="4" width="16" height="16" rx="3"/><path d="M8 9h8M8 12.5h8M8 16h5"/>',
        'design' => '<circle cx="12" cy="12" r="8.5"/><circle cx="8.5" cy="10.5" r="1.1" fill="currentColor" stroke="none"/><circle cx="12" cy="7.8" r="1.1" fill="currentColor" stroke="none"/><circle cx="15.5" cy="10.5" r="1.1" fill="currentColor" stroke="none"/><path d="M12 20.5c-1.6 0-2-1.4-1.2-2.4.8-1 .4-2.1-.8-2.1H8.5"/>',
        'manage' => '<circle cx="12" cy="12" r="3"/><path d="M12 3v2.5M12 18.5V21M3 12h2.5M18.5 12H21M5.6 5.6l1.8 1.8M16.6 16.6l1.8 1.8M18.4 5.6l-1.8 1.8M7.4 16.6l-1.8 1.8"/>',
        'search' => '<circle cx="11" cy="11" r="6.5"/><path d="M20 20l-4-4"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'chev' => '<path d="M6 9l6 6 6-6"/>',
        'ext' => '<path d="M14 4h6v6"/><path d="M20 4l-9 9"/><path d="M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
        'image' => '<rect x="3.5" y="4.5" width="17" height="15" rx="2"/><circle cx="8.5" cy="9.5" r="1.4"/><path d="M20 15l-4.5-4.5-4 4-2.5-2.5L4 17"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h10"/>',
    ];
    if (!isset($p[$n])) return '';
    return '<svg viewBox="0 0 24 24" width="' . (int)$size . '" height="' . (int)$size . '" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p[$n] . '</svg>';
}

/** 항목별 글 개수 (한 번만 계산) */
function admin_counts() {
    static $c = null;
    if ($c === null) {
        $c = [];
        foreach (entities() as $t => $e) {
            try { $c[$t] = (int)val('SELECT COUNT(*) FROM `' . $t . '`'); } catch (Exception $ex) { $c[$t] = 0; }
        }
    }
    return $c;
}

/** 메뉴에 보일 짧은 이름: 괄호 설명은 뗌 */
function admin_short($label) { return trim(preg_replace('/\s*[\(（].*$/u', '', (string)$label)); }

/** 관리자 메뉴: 대분류 -> 소분류 -> 항목. 꺼둔 게시판은 빠짐 */
function admin_menu() {
    static $m = null;
    if ($m !== null) return $m;
    $E = entities();
    $G = setting_groups();
    $cnt = admin_counts();
    $seenE = [];
    $seenG = [];
    $ent = function ($t) use ($E, $cnt, &$seenE) {
        $seenE[$t] = 1;
        if (!isset($E[$t]) || !entity_on($t)) return null;
        return ['key' => 'ent:' . $t, 'label' => admin_short($E[$t]['label']), 'url' => url('admin/content.php') . '?t=' . $t, 'count' => isset($cnt[$t]) ? $cnt[$t] : null];
    };
    $set = function ($k) use ($G, &$seenG) {
        $seenG[$k] = 1;
        if (!isset($G[$k])) return null;
        $b = setting_group_board($k);
        if ($b !== null && !board_on($b)) return null;
        return ['key' => 'set:' . $k, 'label' => admin_short($G[$k]['label']), 'url' => url('admin/settings.php') . '?g=' . $k, 'count' => null];
    };
    $pick = function ($fn, $keys) {
        $o = [];
        foreach ($keys as $k) { $i = $fn($k); if ($i) $o[] = $i; }
        return $o;
    };
    $content = [
        '캐릭터' => $pick($ent, ['profiles', 'running', 'voices']),
        '스토리' => $pick($ent, ['timeline', 'rp', 'side']),
        '자료' => $pick($ent, ['gallery', 'logs', 'trpg', 'aus']),
        '방문자' => $pick($ent, ['guestbook']),
        '꾸밈 자산' => $pick($ent, ['stickers', 'banners', 'tracks']),
    ];
    $design = [
        '기본' => $pick($set, ['site', 'favicon', 'boards', 'menu', 'bg', 'homebanner']),
        '화면' => $pick($set, ['landing', 'home', 'cards', 'hover']),
        '효과' => $pick($set, ['clicksound', 'particles', 'mtrail', 'music', 'dday']),
        '게시판별' => $pick($set, ['profile', 'timeline_set', 'running_set', 'guestbook']),
        '글꼴' => $pick($ent, ['fonts']),
    ];
    // 위에 나열하지 않은 것은 빠지지 않도록 '기타'로 모음
    $restE = [];
    foreach ($E as $t => $e) { if (!isset($seenE[$t])) { $i = $ent($t); if ($i) $restE[] = $i; } }
    if ($restE) $content['기타'] = $restE;
    $restG = [];
    foreach ($G as $k => $g) { if (!isset($seenG[$k])) { $i = $set($k); if ($i) $restG[] = $i; } }
    if ($restG) $design['기타'] = $restG;
    $manage = ['' => [
        ['key' => 'account', 'label' => '내 계정', 'url' => url('admin/account.php'), 'count' => null],
        ['key' => 'backup', 'label' => '백업', 'url' => url('admin/backup.php'), 'count' => null],
    ]];
    $clean = function ($groups) { return array_filter($groups, function ($items) { return count($items) > 0; }); };
    $m = [];
    $c1 = $clean($content); if ($c1) $m['content'] = ['label' => '콘텐츠', 'icon' => 'content', 'groups' => $c1];
    $c2 = $clean($design); if ($c2) $m['design'] = ['label' => '꾸미기', 'icon' => 'design', 'groups' => $c2];
    $m['manage'] = ['label' => '관리', 'icon' => 'manage', 'groups' => $manage];
    return $m;
}

/** 지금 화면이 메뉴의 어디에 해당하는지: [대분류키, 대분류이름, 소분류이름, 항목이름] */
function admin_locate($active) {
    foreach (admin_menu() as $ck => $c) {
        foreach ($c['groups'] as $gl => $items) {
            foreach ($items as $it) { if ($it['key'] === $active) return [$ck, $c['label'], (string)$gl, $it['label']]; }
        }
    }
    return ['home', '', '', ''];
}

function admin_head($title, $active = '', $opts = []) {
    $ver = @filemtime(ROOT . '/assets/css/admin.css') ?: 1;
    $menu = admin_menu();
    $loc = admin_locate($active);
    $curCat = $loc[0];
    $siteTitle = (string)S('site_title', '');
    $mark = $siteTitle !== '' ? mb_substr($siteTitle, 0, 1) : 'H';
    ?><!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<?= favicon_tags() ?>
<title><?= h($title) ?> · 관리자</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Gowun+Batang:wght@400;700&family=Gowun+Dodum&family=Nanum+Myeongjo:wght@400;700&family=Nanum+Gothic:wght@400;700&display=swap">
<style><?= custom_font_css_admin() ?></style>
<link rel="stylesheet" href="<?= h(url('assets/css/admin.css')) ?>?v=<?= $ver ?>">
</head>
<body>
<div class="ad-shell">
  <aside class="ad-side <?= $curCat === 'home' ? '' : 'has-panel' ?>" id="adSide">
    <nav class="ad-rail" aria-label="관리자 대분류">
      <a class="ad-mark" href="<?= h(url('admin/')) ?>" title="대시보드"><?= h($mark) ?></a>
      <a class="ad-r <?= $curCat === 'home' ? 'on' : '' ?>" href="<?= h(url('admin/')) ?>"><?= admin_icon('home', 22) ?><span>홈</span></a>
      <?php foreach ($menu as $ck => $c): ?>
        <button type="button" class="ad-r <?= $curCat === $ck ? 'on' : '' ?>" data-cat="<?= h($ck) ?>"><?= admin_icon($c['icon'], 22) ?><span><?= h($c['label']) ?></span></button>
      <?php endforeach; ?>
    </nav>
    <div class="ad-panelwrap">
      <?php foreach ($menu as $ck => $c):
      ?>
      <div class="ad-panel" data-panel="<?= h($ck) ?>" <?= $curCat === $ck ? '' : 'hidden' ?>>
        <h3><?= h($c['label']) ?></h3>
        <?php if ($ck !== 'manage'): ?>
        <label class="ad-find"><?= admin_icon('search', 16) ?><input type="search" placeholder="이 안에서 찾기 ( / )" data-navfind autocomplete="off"></label>
        <?php endif; ?>
        <?php foreach ($c['groups'] as $gl => $items): ?>
          <?php if ($gl === ''): foreach ($items as $it): ?>
            <a class="lnk <?= $active === $it['key'] ? 'on' : '' ?>" href="<?= h($it['url']) ?>"><?= h($it['label']) ?></a>
          <?php endforeach; else:
              $open = false;
              foreach ($items as $it) { if ($active === $it['key']) $open = true; }
          ?>
          <div class="ad-grp<?= $open ? ' open' : '' ?>" data-g="<?= h($ck . ':' . $gl) ?>">
            <button type="button" class="ad-gh" aria-expanded="<?= $open ? 'true' : 'false' ?>"><span><?= h($gl) ?></span><em><?= count($items) ?></em><?= admin_icon('chev', 15) ?></button>
            <div class="ad-gb">
              <?php foreach ($items as $it): ?>
                <a class="lnk <?= $active === $it['key'] ? 'on' : '' ?>" href="<?= h($it['url']) ?>"><span><?= h($it['label']) ?></span><?php if ($it['count'] !== null): ?><i class="c"><?= (int)$it['count'] ?></i><?php endif; ?></a>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </aside>
  <div class="ad-body">
    <header class="ad-top">
      <button type="button" class="ad-burger" aria-label="메뉴 열기">&#9776;</button>
      <div class="crumb">
        <?php if ($loc[1] === ''): ?><b>홈</b><?php else: ?>
          <?= h($loc[1]) ?><?php if ($loc[2] !== ''): ?> <i>/</i> <?= h($loc[2]) ?><?php endif; ?> <i>/</i> <b><?= h($loc[3]) ?></b>
        <?php endif; ?>
      </div>
      <span class="grow"></span>
      <a href="<?= h(url('home.php')) ?>" target="_blank" rel="noopener"><?= admin_icon('ext', 15) ?>사이트 보기</a>
      <a href="<?= h(url('login.php')) ?>?out=1&amp;t=<?= h(csrf_token()) ?>">로그아웃 (<?= h(admin_name()) ?>)</a>
    </header>
    <main class="ad-main">
      <div class="ad-head">
        <div>
          <h1 class="ad-h1"><?= h($title) ?></h1>
          <?php if (!empty($opts['sub'])): ?><p class="ad-sub"><?= h($opts['sub']) ?></p><?php endif; ?>
        </div>
        <?php if (!empty($opts['actions'])): ?><div class="ad-actions"><?= $opts['actions'] ?></div><?php endif; ?>
      </div>
      <?php foreach (flashes() as $f): ?><div class="flash <?= h($f[0]) ?>"><?= h($f[1]) ?></div><?php endforeach; ?>
<?php
}

/** 목록용 썸네일 주소 (이미지 칸이 있으면 첫 번째 이미지) */
function admin_thumb($e, $r) {
    foreach ($e['fields'] as $f) {
        if ($f['t'] === 'image' && isset($r[$f['n']]) && trim((string)$r[$f['n']]) !== '') return asset($r[$f['n']]);
        if ($f['t'] === 'images' && isset($r[$f['n']]) && trim((string)$r[$f['n']]) !== '') {
            $d = json_decode((string)$r[$f['n']], true);
            if (is_array($d)) {
                foreach ($d as $it) {
                    if (is_array($it)) $it = isset($it['src']) ? $it['src'] : (isset($it['url']) ? $it['url'] : '');
                    if (is_string($it) && $it !== '' && !media_is_video($it)) return asset($it);
                }
            }
        }
    }
    return '';
}

function editor_head($title, $backUrl) {
    require_once __DIR__ . '/../inc/layout.php';
    $ver = @filemtime(ROOT . '/assets/css/admin.css') ?: 1;
    $ver2 = @filemtime(ROOT . '/assets/css/editor-theme.css') ?: 1;
    $bg = bg_style('home');
    $theme = S('editor_theme') === 'light' ? 'light' : 'dark';
    ?><!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<?= favicon_tags() ?>
<title><?= h($title) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Gowun+Batang:wght@400;700&family=Gowun+Dodum&family=Nanum+Myeongjo:wght@400;700&family=Nanum+Gothic:wght@400;700&display=swap">
<style><?= custom_font_css_admin() ?></style>
<link rel="stylesheet" href="<?= h(url('assets/css/admin.css')) ?>?v=<?= $ver ?>">
<link rel="stylesheet" href="<?= h(url('assets/css/editor-theme.css')) ?>?v=<?= $ver2 ?>">
</head>
<body class="site-editor et-<?= h($theme) ?>">
<div class="se-bg" style="<?= h($bg) ?>"></div>
<div class="se-top">
  <a class="se-back" href="<?= h($backUrl) ?>" aria-label="목록으로"><svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M15 4L15 20L5 12Z"></path></svg></a>
  <span class="se-ttl"><?= h($title) ?></span>
</div>
<div class="se-wrap">
    <?php foreach (flashes() as $f): ?><div class="flash <?= h($f[0]) ?>"><?= h($f[1]) ?></div><?php endforeach; ?>
<?php
}

function editor_foot() {
    $ver = @filemtime(ROOT . '/assets/js/admin.js') ?: 1;
    ?>
</div>
<script nonce="<?= h(csp_nonce()) ?>">window.JHA={csrf:<?= json_encode(csrf_token()) ?>,base:<?= json_encode(BASE) ?>};</script>
<script src="<?= h(url('assets/js/admin.js')) ?>?v=<?= $ver ?>"></script>
</body>
</html>
<?php
}

function admin_foot() {
    $ver = @filemtime(ROOT . '/assets/js/admin.js') ?: 1;
    ?>
    </main>
  </div>
</div>
<script nonce="<?= h(csp_nonce()) ?>">window.JHA={csrf:<?= json_encode(csrf_token()) ?>,base:<?= json_encode(BASE) ?>};</script>
<script src="<?= h(url('assets/js/admin.js')) ?>?v=<?= $ver ?>"></script>
</body>
</html>
<?php
}
