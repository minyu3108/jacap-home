<?php
/** LOG / TRPG 공통 게시판 (카드 목록 + 상세) */

function board_thumb($r, $locked, $showWhenLocked = false) {
    // TRPG는 비밀번호가 걸려 있어도 세션 카드 이미지는 보여줌 (이미지가 없을 때만 자물쇠 표시)
    if ($locked && !($showWhenLocked && $r['thumb'] !== '')) return '<span class="pc-th lockth">' . lock_icon() . '</span>';
    if ($r['thumb'] !== '') return '<span class="pc-th"><img loading="lazy" src="' . h(asset($r['thumb'])) . '" alt=""></span>';
    return '<span class="pc-th ph"></span>'; // 이미지가 없으면 텍스트 없이 그라데이션만
}

function board_list($t, $cfg) {
    $g = $cfg['group'];
    $locked = isset($cfg['lock_group']) && $cfg['lock_group'] !== '' ? (string)$cfg['lock_group'] : null;
    $groupLocked = $locked; // 아래 foreach 안에서 $locked가 "글별 비번 잠금" 용도로 재사용되므로 미리 따로 저장해둠
    $c = $locked !== null ? $locked : (isset($_GET['c']) ? trim((string)$_GET['c']) : '');
    $q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
    $page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
    $per = 6;
    $isTrpg = !empty($cfg['trpg']);
    $extra = $isTrpg ? ', players, play_start, play_end' : '';
    $all = rows('SELECT id, title, thumb, summary, card_color, card_text, visibility, created_at' . $extra . ', `' . $g . '` AS grp FROM `' . $t . '` WHERE ' . vis_sql() . ' ORDER BY created_at DESC, id DESC');
    $groups = [];
    foreach ($all as $r) {
        $k = trim($r['grp']);
        if ($k !== '') { $groups[$k] = (isset($groups[$k]) ? $groups[$k] : 0) + 1; }
    }
    if ($c !== '') {
        $all = array_values(array_filter($all, function ($r) use ($c) { return trim($r['grp']) === $c; }));
    }
    if ($q !== '') {
        $all = array_values(array_filter($all, function ($r) use ($q, $isTrpg) {
            if (mb_stripos($r['title'], $q) !== false) return true;
            if (mb_stripos((string)$r['summary'], $q) !== false) return true;
            if ($isTrpg && mb_stripos((string)$r['players'], $q) !== false) return true;
            return false;
        }));
    }
    $total = count($all);
    list($off, $pages, $page) = paginate($total, $per, $page);
    $items = array_slice($all, $off, $per);
    $qsBase = [];
    if ($locked !== null && isset($_GET['who'])) $qsBase['who'] = (string)$_GET['who'];
    elseif ($c !== '') $qsBase['c'] = $c;
    if ($q !== '') $qsBase['q'] = $q;
    // $cfg['file']가 이미 "?who=1" 같은 물음표를 갖고 있을 수 있어서(러닝 게시판 등), 거기에 물음표를
    // 또 붙이면 주소가 깨짐(?who=1?id=5). 이미 물음표가 있으면 &로 이어 붙임.
    $fq = strpos($cfg['file'], '?') !== false ? '&' : '?';

    view_start(['title' => $cfg['label'], 'page' => $cfg['page'], 'menu' => $cfg['menu'], 'bg' => $cfg['bg'], 'bgcss' => isset($cfg['bgcss']) ? $cfg['bgcss'] : '']);
    ?>
<section class="board">
  <?php if (!empty($cfg['back_url'])): ?><?= back_link($cfg['back_url'], '', isset($cfg['back_label']) ? $cfg['back_label'] : '돌아가기') ?><?php endif; ?>
  <header class="pg-head">
    <h1 class="sr-only"><?= h($cfg['label']) ?></h1>
    <div class="pg-count">총 <?= $total ?>개</div>
    <div class="pg-actions">
      <form class="board-search" method="get">
        <?php if ($locked !== null && isset($_GET['who'])): ?><input type="hidden" name="who" value="<?= h((string)$_GET['who']) ?>"><?php endif; ?>
        <?php if ($locked === null && $c !== ''): ?><input type="hidden" name="c" value="<?= h($c) ?>"><?php endif; ?>
        <input type="search" name="q" value="<?= h($q) ?>" placeholder="제목·내용으로 검색">
        <button type="submit" aria-label="검색"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="M21 21l-4.3-4.3"></path></svg></button>
      </form>
      <?= add_button($t, $cfg['file']) ?>
    </div>
    <?php if ($groups && $locked === null): ?>
    <div class="chips">
      <a class="chip <?= $c === '' ? 'on' : '' ?>" href="<?= h(url($cfg['file'])) . ($q !== '' ? $fq . 'q=' . h(rawurlencode($q)) : '') ?>">전체</a>
      <?php foreach ($groups as $name => $n): ?>
        <a class="chip <?= $c === (string)$name ? 'on' : '' ?>" href="<?= h(url($cfg['file'])) ?><?= $fq ?>c=<?= h(rawurlencode($name)) ?><?= $q !== '' ? '&q=' . h(rawurlencode($q)) : '' ?>"><?= h($name) ?> <small><?= $n ?></small></a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </header>

  <?php if (!$items): ?>
    <p class="empty"><?= $q !== '' ? '검색 결과가 없어요.' : '아직 올라온 글이 없어요.' . (is_admin() ? ' 위의 "새 글 쓰기" 버튼으로 첫 글을 올려보세요.' : '') ?></p>
  <?php else: ?>
  <div class="pc-grid">
    <?php foreach ($items as $r):
        $locked = is_locked($t, $r);
        $period = $isTrpg ? fmt_period($r['play_start'], $r['play_end']) : ''; ?>
      <a class="pc" style="<?= h(card_style_attr($r)) ?>" href="<?= h(url($cfg['file'])) ?><?= $fq ?>id=<?= (int)$r['id'] ?>">
        <?= board_thumb($r, $locked && $r['visibility'] === 'password', $isTrpg) ?>
        <span class="pc-b">
          <?php if ($groupLocked === null && trim($r['grp']) !== ''): ?><em class="pc-cat"><?= h($r['grp']) ?></em><?php endif; ?>
          <b><?php if ($r['visibility'] === 'password'): ?><span class="mini-lock"><?= lock_icon() ?></span><?php endif; ?><?= h($r['title']) ?></b>
          <?php if ($isTrpg && !$locked && trim((string)$r['players']) !== ''): ?><span class="pc-pc"><?= h($r['players']) ?></span><?php endif; ?>
          <?php if ($isTrpg && !$locked && $period !== ''): ?><span class="pc-period"><?= h($period) ?></span><?php endif; ?>
          <?php if (!$locked && trim((string)$r['summary']) !== ''): ?><span class="pc-sum"><?= h($r['summary']) ?></span><?php endif; ?>
          <time><?= h(fmt_date($r['created_at'])) ?><?= $r['visibility'] === 'admin' ? ' · 관리자만' : '' ?></time>
        </span>
      </a>
    <?php endforeach; ?>
  </div>
  <?= pager($page, $pages, $qsBase) ?>
  <?php endif; ?>
</section>
<?php
    view_end();
}

function board_detail($t, $id, $cfg) {
    $r = row('SELECT * FROM `' . $t . '` WHERE id=?', [$id]);
    if (!$r) page_message('글을 찾을 수 없어요', '삭제되었거나 주소가 잘못되었어요.', $cfg['menu']);
    gate($t, $r, $cfg['menu'], $cfg['bg']);
    list($newer, $older) = neighbors($t, $id, 'created_at DESC, id DESC');

    view_start(['title' => $r['title'], 'page' => $cfg['page'] . '-detail', 'menu' => $cfg['menu'], 'bg' => $cfg['bg'], 'bgcss' => isset($cfg['bgcss']) ? $cfg['bgcss'] : '']);

    $style = '';
    $isTrpg = !empty($cfg['trpg']);
    $skin = $isTrpg && roll20_skin_applies($r);
    $period = $isTrpg ? fmt_period($r['play_start'], $r['play_end']) : '';
    $room = $isTrpg ? safe_url($r['room_url']) : '';
    if ($room !== '' && !preg_match('#^https?://#i', $room)) $room = '';
    if ($isTrpg) {
        if ($r['t_font'] !== '') $style .= 'font-family:' . font_stack($r['t_font']) . ';';
        if ((int)$r['t_size'] > 0) $style .= 'font-size:' . min(40, max(10, (int)$r['t_size'])) . 'px;';
        if ($r['t_color'] !== '') $style .= 'color:' . $r['t_color'] . ';';
        if ($r['t_bg'] !== '') $style .= 'background:' . $r['t_bg'] . ';';
        if ((int)$r['t_width'] > 0) $style .= 'max-width:' . min(2400, (int)$r['t_width']) . 'px;';
    }
    $panelSt = '';
    if (($t === 'logs' || $t === 'running') && preg_match('/^#[0-9a-fA-F]{6}$/', (string)$r['panel_color'])) {
        $pop = trim((string)$r['panel_opacity']) === '' ? null : min(100, max(10, (int)$r['panel_opacity']));
        $panelSt = '--panel:' . hex_rgba($r['panel_color'], ($pop !== null ? $pop : 68) / 100) . ';';
    }
    ?>
<article class="reading <?= $isTrpg ? 'trpg' : '' ?>" style="<?= h($panelSt) ?>">
  <?= back_link(url($cfg['file']), '', '목록으로', $r['title_color']) ?>
  <?php if ($isTrpg && is_admin()): ?><a class="edit trpg-edit" data-nopjax href="<?= h(url('admin/content.php')) ?>?t=<?= h($t) ?>&amp;a=edit&amp;id=<?= (int)$r['id'] ?>&amp;ret=<?= urlencode($cfg['file'] . (strpos($cfg['file'], '?') !== false ? '&' : '?') . 'id=' . (int)$r['id']) ?>">수정</a><?php endif; ?>
  <?php if ($isTrpg && $r['thumb'] !== ''): ?>
    <div class="rd-hero"><img src="<?= h(asset($r['thumb'])) ?>" alt="<?= h($r['title']) ?>"></div>
  <?php endif; ?>
  <header class="rd-head">
    <?php $groupLockedD = isset($cfg['lock_group']) && $cfg['lock_group'] !== ''; ?>
    <?php if (!$groupLockedD && trim($r[$cfg['group']]) !== '') : ?><em class="pc-cat"><?= h($r[$cfg['group']]) ?></em><?php endif; ?>
    <?php $titleSt = (isset($r['title_color']) && preg_match('/^#[0-9a-fA-F]{6}$/', (string)$r['title_color'])) ? ' style="color:' . h($r['title_color']) . '"' : ''; ?>
    <h1<?= $titleSt ?>><?php if ($r['visibility'] !== 'public'): ?><span class="mini-lock"><?= lock_icon() ?></span><?php endif; ?><?= h($r['title']) ?></h1>
    <div class="rd-meta">
      <time><?= h(fmt_date($r['created_at'])) ?></time>
      <?php if ($isTrpg && trim($r['players']) !== ''): ?><span><?= h($r['players']) ?></span><?php endif; ?>
      <?php if ($period !== ''): ?><span>플레이 <?= h($period) ?></span><?php endif; ?>
      <?php if ($room !== ''): ?><a class="btn sm room" data-nopjax href="<?= h($room) ?>" target="_blank" rel="noopener noreferrer">세션방 열기</a><?php endif; ?>
      <?php if (is_admin() && !$isTrpg): ?><a class="edit" data-nopjax href="<?= h(url('admin/content.php')) ?>?t=<?= h($t) ?>&amp;a=edit&amp;id=<?= (int)$r['id'] ?>&amp;ret=<?= urlencode($cfg['file'] . (strpos($cfg['file'], '?') !== false ? '&' : '?') . 'id=' . (int)$r['id']) ?>">수정</a><?php endif; ?>
    </div>
  </header>
  <?php if ($t === 'logs' || $t === 'running' || $t === 'trpg'): ?>
  <?= board_bgm_data($r, $t === 'trpg' ? '이 세션 음악' : ($t === 'running' ? '이 러닝 음악' : '이 로그 음악')) ?>
  <?php endif; ?>
  <?php if (($t === 'logs' || $t === 'running')):
      $imgs = [];
      if (trim((string)$r['images']) !== '') { $d = json_decode($r['images'], true); if (is_array($d)) { foreach ($d as $u) { $u = trim((string)$u); if ($u !== '') $imgs[] = asset($u); } } }
  ?>
  <?php if ($imgs): ?>
    <div class="log-images" data-images='<?= h(json_encode($imgs, JSON_UNESCAPED_SLASHES)) ?>'>
      <?php foreach ($imgs as $i => $u): ?><img src="<?= h($u) ?>" data-idx="<?= $i ?>" alt="" loading="lazy"><?php endforeach; ?>
    </div>
  <?php endif; ?>
  <?php endif; ?>
  <?php if ($isTrpg && trim((string)$r['body_css']) !== ''): ?><style><?= scope_css(clean_css($r['body_css']), '.trpg-log') ?></style><?php endif; ?>
  <?php if ($isTrpg && trim((string)$r['raw_html']) !== ''):
      $inj = '';
      if ($r['t_font'] !== '') $inj .= 'font-family:' . font_stack($r['t_font']) . ' !important;';
      if ((int)$r['t_size'] > 0) $inj .= 'font-size:' . min(40, max(10, (int)$r['t_size'])) . 'px !important;';
      if (preg_match('/^#[0-9a-fA-F]{6}$/', (string)$r['t_color'])) $inj .= 'color:' . $r['t_color'] . ' !important;';
      $doc = (string)$r['raw_html'];
      $skinHead = '';
      if ($skin) {
          $skinHead = '<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.css">'
              . '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Gowun+Batang:wght@400;700&family=Gowun+Dodum&family=Nanum+Myeongjo:wght@400;700&family=Nanum+Gothic:wght@400;700&display=swap">'
              . '<style>' . custom_font_css() . 'html,body{margin:0;background:transparent}body{' . roll20_skin_vars($r) . '}' . roll20_skin_css('body') . '</style>';
      }
      if (!$skin && preg_match('/^#[0-9a-fA-F]{6}$/', (string)$r['t_skin_line'])) $skinHead .= '<style>hr{border-color:' . $r['t_skin_line'] . ' !important}</style>';
      $prefix = '<base target="_blank">' . $skinHead . ($inj !== '' ? '<style>body{' . $inj . '}</style>' : '');
      $esc = str_replace(['\\', '$'], ['\\\\', '\\$'], $prefix);
      if (preg_match('/<\/head>/i', $doc)) { $doc = preg_replace('/<\/head>/i', $esc . '</head>', $doc, 1); }
      elseif (preg_match('/<html[^>]*>/i', $doc)) { $doc = preg_replace('/(<html[^>]*>)/i', '$1<head>' . $esc . '</head>', $doc, 1); }
      else { $doc = '<!doctype html><html><head>' . $prefix . '</head><body>' . $doc . '</body></html>'; }
      $wst = ((int)$r['t_width'] > 0 ? 'max-width:' . min(2400, (int)$r['t_width']) . 'px;' : '') . (preg_match('/^#[0-9a-fA-F]{6}$/', (string)$r['t_bg']) ? 'background:' . $r['t_bg'] . ';' : ''); ?>
    <div class="rd-raw" style="<?= h($wst) ?>"><iframe class="raw-frame" sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox" title="로그" srcdoc="<?= h($doc) ?>"></iframe></div>
  <?php else: ?>
  <?php if (trim((string)$r['body']) !== ''): ?>
  <?php if ($skin): ?><style><?= roll20_skin_css('.rl-skin') ?></style><?php endif; ?>
  <div class="rd-body <?= $isTrpg ? 'trpg-log' : '' ?><?= $skin ? ' rl-skin' : '' ?>" style="<?= h(($skin ? roll20_skin_vars($r) : (preg_match('/^#[0-9a-fA-F]{6}$/', (string)$r['t_skin_line']) ? '--rl-line:' . $r['t_skin_line'] . ';' : '')) . $style) ?>"><?= $r['body'] ?></div>
  <?php endif; ?>
  <?php endif; ?>
  <nav class="rd-nav">
    <?php $fqD = strpos($cfg['file'], '?') !== false ? '&' : '?'; ?>
    <?php if ($older): ?><a href="<?= h(url($cfg['file'])) ?><?= $fqD ?>id=<?= (int)$older['id'] ?>"><small>이전 글</small><?= h($older['title']) ?></a><?php else: ?><span></span><?php endif; ?>
    <?php if ($newer): ?><a class="r" href="<?= h(url($cfg['file'])) ?><?= $fqD ?>id=<?= (int)$newer['id'] ?>"><small>다음 글</small><?= h($newer['title']) ?></a><?php endif; ?>
  </nav>
</article>
<?php
    view_end();
}
