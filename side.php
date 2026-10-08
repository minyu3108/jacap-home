<?php
/** 썰 백업: 대화 로그 백업 게시판. 목록은 제목만 있는 가로형 리스트, 상세는 메신저 스타일 대화 */
require __DIR__ . '/inc/core.php';
require __DIR__ . '/inc/layout.php';
board_guard('side');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id) { side_detail($id); } else { side_list(); }

function side_conv_count($r) {
    $n = 0;
    for ($i = 1; $i <= 5; $i++) { if (trim((string)$r['conv' . $i . '_body']) !== '') $n++; }
    return max(1, $n);
}

function side_list() {
    view_start(['title' => '썰 백업', 'page' => 'side-list', 'menu' => 1, 'bg' => 'side']);
    $rows = rows('SELECT * FROM side WHERE ' . vis_sql() . ' ORDER BY created_at DESC, id DESC');
    ?>
<section class="side-list-page">
  <header class="pg-head">
    <h1>썰 백업</h1>
    <?= add_button('side', 'side.php', '+ 썰 추가') ?>
  </header>

  <?php if (!$rows): ?>
    <p class="empty">아직 등록된 썰이 없어요.</p>
  <?php else: ?>
  <div class="side-rows">
    <?php foreach ($rows as $r):
        $locked = ($r['visibility'] === 'password') && is_locked('side', $r);
        $n = side_conv_count($r);
    ?>
      <a class="side-row" href="<?= h(url('side.php')) ?>?id=<?= (int)$r['id'] ?>">
        <?php if ($locked): ?>
          <span class="side-tag"><?= lock_icon() ?></span>
          <span class="side-row-ttl">비밀글이에요</span>
        <?php else: ?>
          <span class="side-tag"><?= (int)$n ?>개 대화</span>
          <span class="side-row-ttl"><?= h($r['title']) ?></span>
          <span class="side-row-meta"><?= h(fmt_date($r['created_at'])) ?></span>
        <?php endif; ?>
        <span class="side-row-arrow">&rsaquo;</span>
      </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>
<?php
    view_end();
}

function side_detail($id) {
    $r = row('SELECT * FROM side WHERE id=?', [$id]);
    if (!$r) page_message('썰을 찾을 수 없어요', '삭제되었거나 주소가 잘못되었어요.', 1);
    gate('side', $r, -1, 'side');

    $convs = [];
    for ($i = 1; $i <= 5; $i++) {
        $body = trim((string)$r['conv' . $i . '_body']);
        if ($body === '') continue;
        $convs[] = ['idx' => $i, 'title' => trim((string)$r['conv' . $i . '_title']), 'msgs' => parse_rp_chat($body)];
    }
    if (!$convs) $convs[] = ['idx' => 1, 'title' => '', 'msgs' => []];
    $activeIdx = $convs[0]['idx'];

    $bubVars = '';
    $hex = '/^#[0-9a-fA-F]{6}$/';
    if (preg_match($hex, (string)$r['bub1_color'])) $bubVars .= '--bub1-bg:' . $r['bub1_color'] . ';';
    if (preg_match($hex, (string)$r['bub1_text'])) $bubVars .= '--bub1-ink:' . $r['bub1_text'] . ';';
    if (preg_match($hex, (string)$r['name1_color'])) $bubVars .= '--nm1-color:' . $r['name1_color'] . ';';
    if (preg_match($hex, (string)$r['bub2_color'])) $bubVars .= '--bub2-bg:' . $r['bub2_color'] . ';';
    if (preg_match($hex, (string)$r['bub2_text'])) $bubVars .= '--bub2-ink:' . $r['bub2_text'] . ';';
    if (preg_match($hex, (string)$r['name2_color'])) $bubVars .= '--nm2-color:' . $r['name2_color'] . ';';
    if (preg_match($hex, (string)$r['tab_color'])) $bubVars .= '--tab-bg:' . $r['tab_color'] . ';';
    if (preg_match($hex, (string)$r['tab_text'])) $bubVars .= '--tab-ink:' . $r['tab_text'] . ';';

    view_start(['title' => $r['title'], 'page' => 'side-detail', 'menu' => 1, 'bg' => 'side']);
    ?>
<section class="side-d"<?= $bubVars !== '' ? ' style="' . h($bubVars) . '"' : '' ?>>
  <?= back_link(url('side.php'), '', '목록으로') ?>

  <?php if (is_admin()): ?><a class="edit side-edit" data-nopjax href="<?= h(url('admin/content.php')) ?>?t=side&amp;a=edit&amp;id=<?= (int)$r['id'] ?>&amp;ret=<?= urlencode('side.php?id=' . (int)$r['id']) ?>">수정</a><?php endif; ?>

  <h1 class="side-ttl"><?= h($r['title']) ?></h1>

  <?php if (count($convs) > 1): ?>
  <nav class="side-tabs">
    <?php foreach ($convs as $c): ?>
      <button type="button" class="side-tab <?= $c['idx'] === $activeIdx ? 'on' : '' ?>" data-conv="<?= $c['idx'] ?>"><?= h($c['title'] !== '' ? $c['title'] : '대화 ' . $c['idx']) ?></button>
    <?php endforeach; ?>
  </nav>
  <?php endif; ?>

  <?php foreach ($convs as $c): ?>
  <?php
    $imgList = [];
    foreach ($c['msgs'] as $mm) { if ($mm['type'] === 'img') $imgList[] = chat_resolve_img($r, $mm['src'], 'sideimg'); }
  ?>
  <div class="side-chat" data-conv-panel="<?= $c['idx'] ?>" data-images='<?= h(json_encode($imgList, JSON_UNESCAPED_SLASHES)) ?>' <?= $c['idx'] === $activeIdx ? '' : 'hidden' ?>>
    <?php
    $lastWho = null; $imgSeen = 0;
    foreach ($c['msgs'] as $m):
        $isNew = $m['who'] !== $lastWho;
        $lastWho = $m['who'];
        $nm = $m['who'] === 1 ? $r['name1'] : $r['name2'];
        $side = $m['who'] === 1 ? 'l' : 'r';
    ?>
      <?php if ($isNew): ?><div class="side-name <?= $side ?>"><?= h($nm) ?></div><?php endif; ?>
      <div class="side-row-msg <?= $side ?>">
        <?php if ($m['type'] === 'img'): ?>
          <div class="side-bub side-bub-img <?= $side ?>"><img loading="lazy" src="<?= h(chat_resolve_img($r, $m['src'], 'sideimg')) ?>" data-idx="<?= $imgSeen++ ?>" alt=""></div>
        <?php elseif ($m['type'] === 'link'): ?>
          <a class="side-link <?= $side ?>" href="<?= h($m['url']) ?>" target="_blank" rel="noopener noreferrer nofollow">
            <span class="side-link-ic"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 14a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"></path><path d="M14 10a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"></path></svg></span>
            <span class="side-link-tx"><span class="t1"><?= h($m['ttl']) ?></span><span class="t2"><?= h($m['url']) ?></span></span>
          </a>
        <?php else: ?>
          <div class="side-bub <?= $side ?>"><?= nl2br_h($m['text']) ?></div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <?php if (!$c['msgs']): ?><p class="empty">이 대화는 아직 내용이 없어요.</p><?php endif; ?>
  </div>
  <?php endforeach; ?>
</section>
<?php
    view_end();
}
