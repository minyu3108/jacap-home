<?php
/** 타임라인 게시판: 사건을 세로선으로 잇는 목록 + 사건별 긴 서술 상세 페이지 */
require __DIR__ . '/inc/core.php';
require __DIR__ . '/inc/layout.php';
board_guard('timeline');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id) { timeline_detail($id); } else { timeline_list(); }

function timeline_side_style() {
    $side = S('tl_image_side') === 'left' ? 'left' : 'right';
    return $side;
}

function timeline_list() {
    $accent = preg_match('/^#[0-9a-fA-F]{6}$/', (string)S('tl_accent')) ? S('tl_accent') : '#45d6c8';
    $accentMid = preg_match('/^#[0-9a-fA-F]{6}$/', (string)S('tl_accent_mid')) ? S('tl_accent_mid') : '#ffffff';
    $accent2 = preg_match('/^#[0-9a-fA-F]{6}$/', (string)S('tl_accent2')) ? S('tl_accent2') : '#e5483f';
    $side = timeline_side_style();
    $hasArt = S('tl_image') !== '';

    view_start(['title' => S('tl_title', '타임라인'), 'page' => 'timeline-list', 'menu' => 1, 'bg' => 'timeline']);
    $rows = rows('SELECT id, title, date_text, summary, image, title_font, card_title_color, visibility FROM timeline WHERE ' . vis_sql() . ' ORDER BY sort_order ASC, id ASC');
    ?>
<section class="tl-page">
  <?= back_link(url('home.php'), 'tl-back', '홈으로') ?>

  <div class="tl-wrap4 <?= $hasArt ? '' : 'no-art' ?> <?= $side === 'left' ? 'art-left' : 'art-right' ?>">
    <div class="tl-area">
      <?php if (S('tl_intro') !== ''): ?><p class="tl-intro"><?= nl2br_h(S('tl_intro')) ?></p><?php endif; ?>

      <?php if (!$rows): ?>
        <p class="tl-empty">아직 등록된 사건이 없어요.</p>
      <?php else: ?>
      <div class="tl-grid">
        <div class="tl-line" style="background: linear-gradient(180deg, <?= h($accent) ?>, <?= h($accentMid) ?>, <?= h($accent2) ?>)"></div>
        <?php foreach ($rows as $i => $r):
            $locked = ($r['visibility'] === 'password') && is_locked('timeline', $r);
            $onLeft = $i % 2 === 0;
            $col = ($i % 4 < 2) ? $accent : $accent2;
            $ttlSt = '';
            if ($r['title_font'] !== '') $ttlSt .= 'font-family:' . font_stack($r['title_font']) . ';';
            if (preg_match('/^#[0-9a-fA-F]{6}$/', (string)$r['card_title_color'])) $ttlSt .= 'color:' . $r['card_title_color'] . ';';
            $ttlAttr = $ttlSt !== '' ? ' style="' . h($ttlSt) . '"' : '';
        ?>
        <div class="tl-row" style="--tlc: <?= h($col) ?>">
          <div class="tl-side <?= $onLeft ? 'l' : 'r' ?>">
            <?php if ($onLeft): ?>
              <?php if ($locked): ?>
                <span class="tl-card locked"><?= lock_icon() ?><span class="tl-lockttl">비밀글이에요</span></span>
              <?php else: ?>
                <a class="tl-card <?= $r['image'] !== '' ? 'has-img' : 'no-img' ?>" href="<?= h(url('timeline.php')) ?>?id=<?= (int)$r['id'] ?>">
                  <?php if ($r['image'] !== ''): ?><span class="tl-banner"><img loading="lazy" src="<?= h(asset($r['image'])) ?>" alt=""></span><?php endif; ?>
                  <span class="tl-fpad">
                    <span class="tl-date"><?= h($r['date_text']) ?></span>
                    <b class="tl-ttl"<?= $ttlAttr ?>><?= h($r['title']) ?></b>
                    <?php if ($r['summary'] !== ''): ?><span class="tl-sum"><?= h($r['summary']) ?></span><?php endif; ?>
                  </span>
                </a>
              <?php endif; ?>
            <?php endif; ?>
          </div>
          <div class="tl-mid"><span class="tl-dot" style="background: <?= h($col) ?>"></span></div>
          <div class="tl-side <?= $onLeft ? 'r' : 'l' ?>">
            <?php if (!$onLeft): ?>
              <?php if ($locked): ?>
                <span class="tl-card locked"><?= lock_icon() ?><span class="tl-lockttl">비밀글이에요</span></span>
              <?php else: ?>
                <a class="tl-card <?= $r['image'] !== '' ? 'has-img' : 'no-img' ?>" href="<?= h(url('timeline.php')) ?>?id=<?= (int)$r['id'] ?>">
                  <?php if ($r['image'] !== ''): ?><span class="tl-banner"><img loading="lazy" src="<?= h(asset($r['image'])) ?>" alt=""></span><?php endif; ?>
                  <span class="tl-fpad">
                    <span class="tl-date"><?= h($r['date_text']) ?></span>
                    <b class="tl-ttl"<?= $ttlAttr ?>><?= h($r['title']) ?></b>
                    <?php if ($r['summary'] !== ''): ?><span class="tl-sum"><?= h($r['summary']) ?></span><?php endif; ?>
                  </span>
                </a>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($hasArt): ?>
  <img class="tl-art" src="<?= h(asset(S('tl_image'))) ?>" alt="" aria-hidden="true">
  <?php endif; ?>

  <?= add_button('timeline', 'timeline.php', '+ 사건 추가') ?>
</section>
<?php
    view_end();
}

function timeline_detail($id) {
    $r = row('SELECT * FROM timeline WHERE id=?', [$id]);
    if (!$r) page_message('사건을 찾을 수 없어요', '삭제되었거나 주소가 잘못되었어요.', 1);
    gate('timeline', $r, 1, 'timeline');

    $titleSt = preg_match('/^#[0-9a-fA-F]{6}$/', (string)$r['title_color']) ? 'color:' . h($r['title_color']) . ';' : '';
    $titleSt .= $r['title_font'] !== '' ? 'font-family:' . h(font_stack($r['title_font'])) . ';' : '';
    $titleAttr = $titleSt !== '' ? ' style="' . $titleSt . '"' : '';
    $panelSt = '';
    if (preg_match('/^#[0-9a-fA-F]{6}$/', (string)$r['panel_color'])) {
        $op = trim((string)$r['panel_opacity']) === '' ? null : min(100, max(10, (int)$r['panel_opacity']));
        $panelSt = '--panel:' . hex_rgba($r['panel_color'], ($op !== null ? $op : 68) / 100) . ';';
    }
    $hex = '/^#[0-9a-fA-F]{6}$/';
    if (preg_match($hex, (string)$r['accent_color'])) $panelSt .= '--accent:' . $r['accent_color'] . ';';
    if (preg_match($hex, (string)$r['accent_ink_color'])) $panelSt .= '--accent-ink:' . $r['accent_ink_color'] . ';';
    $hasImg = $r['image'] !== '';
    $layout = $hasImg && $r['img_layout'] === 'top' ? 'top' : 'side';
    $side = $r['img_side'] === 'left' ? 'left' : 'right';

    view_start(['title' => $r['title'], 'page' => 'timeline-detail', 'menu' => 1, 'bg' => 'timeline', 'bgcss' => custom_bg($r['page_bg'], $r['page_color'], $r['page_color2'])]);
    ?>
<section class="tld <?= !$hasImg ? 'tld-plain' : ('tld-' . $layout . ' side-' . $side) ?>" style="<?= h($panelSt) ?>">
  <?= back_link(url('timeline.php'), 'tl-back', '타임라인으로') ?>
  <?php if (is_admin()): ?><a class="edit tld-edit" data-nopjax href="<?= h(url('admin/content.php')) ?>?t=timeline&a=edit&id=<?= (int)$r['id'] ?>&ret=<?= urlencode('timeline.php?id=' . (int)$r['id']) ?>">수정</a><?php endif; ?>

  <?php if ($hasImg && $layout === 'side'): ?>
    <img class="tld-sideimg" src="<?= h(asset($r['image'])) ?>" alt="<?= h($r['title']) ?>">
  <?php endif; ?>

  <div class="tld-wrap">
    <?php if ($hasImg && $layout === 'top'): ?>
      <div class="tld-hero"><img src="<?= h(asset($r['image'])) ?>" alt="<?= h($r['title']) ?>"></div>
    <?php endif; ?>

    <div class="tld-textcol">
      <div class="tld-kicker" style="color: <?= h(preg_match('/^#[0-9a-fA-F]{6}$/', (string)$r['hl_color']) ? $r['hl_color'] : S('tl_accent', '#45d6c8')) ?>">타임라인 · <?= h($r['date_text']) ?></div>
      <h1 class="tld-ttl"<?= $titleAttr ?>><?= h($r['title']) ?></h1>
      <?php if ($r['summary'] !== ''): ?><p class="tld-sub"><?= h($r['summary']) ?></p><?php endif; ?>
      <?= board_bgm_data($r, '이 사건 음악') ?>
      <div class="tld-hr"></div>
      <div class="tld-body rd-body"><?= $r['body'] ?></div>
    </div>
  </div>
</section>
<?php
    view_end();
}
