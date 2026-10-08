<?php
/** RP: 캐릭터별 러닝 로그 백업. 진입 화면은 "부" 선택 카드, 상세는 프로필 사진 포함 메신저 대화 (이미지·링크 지원) */
require __DIR__ . '/inc/core.php';
require __DIR__ . '/inc/layout.php';
board_guard('rp');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id) { rp_detail($id); } else { rp_list(); }

function rp_list() {
    view_start(['title' => 'RP', 'page' => 'rp-list', 'menu' => 1, 'bg' => 'rp']);
    $rows = rows('SELECT id, title, card_image, visibility FROM rp WHERE ' . vis_sql() . ' ORDER BY sort_order ASC, id ASC');
    $hex = '/^#[0-9a-fA-F]{6}$/';
    $accVars = '';
    if (preg_match($hex, (string)S('rp_accent_color'))) $accVars .= '--accent:' . S('rp_accent_color') . ';';
    if (preg_match($hex, (string)S('rp_accent_ink_color'))) $accVars .= '--accent-ink:' . S('rp_accent_ink_color') . ';';
    ?>
<section class="rp-page"<?= $accVars !== '' ? ' style="' . h($accVars) . '"' : '' ?>>
  <?= back_link(url('home.php'), 'rp-back', '홈으로') ?>
  <?php if (is_admin()): ?><?= add_button('rp', 'rp.php', '+ 부 추가') ?><?php endif; ?>

  <?php if (!$rows): ?>
    <p class="empty rp-empty">아직 등록된 부가 없어요.</p>
  <?php else: ?>
  <div class="rp-cards">
    <?php foreach ($rows as $r):
        $locked = ($r['visibility'] === 'password') && is_locked('rp', $r);
    ?>
      <?php if ($locked): ?>
        <span class="rp-card locked"><?= lock_icon() ?><span class="rp-card-ttl">비밀글이에요</span></span>
      <?php else: ?>
        <a class="rp-card <?= $r['card_image'] !== '' ? 'has-img' : '' ?>" href="<?= h(url('rp.php')) ?>?id=<?= (int)$r['id'] ?>">
          <?php if ($r['card_image'] !== ''): ?><img loading="lazy" src="<?= h(asset($r['card_image'])) ?>" alt=""><?php endif; ?>
          <span class="rp-card-cap"><?= h($r['title']) ?></span>
        </a>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>
<?php
    view_end();
}

/** RP/썰 백업 이미지 메시지의 src를 실제 주소로 바꿔줌: 1~10 사이 숫자면 업로드한 이미지 슬롯, 아니면 외부 주소 그대로 */
function chat_resolve_img($r, $src, $field = 'rpimg') {
    $src = trim((string)$src);
    if (preg_match('/^([1-9]|10)$/', $src)) {
        $up = trim((string)$r[$field . $src]);
        if ($up !== '') return asset($up);
    }
    return $src;
}
function rp_resolve_img($r, $src) { return chat_resolve_img($r, $src, 'rpimg'); }

function rp_detail($id) {
    $r = row('SELECT * FROM rp WHERE id=?', [$id]);
    if (!$r) page_message('부를 찾을 수 없어요', '삭제되었거나 주소가 잘못되었어요.', 1);
    gate('rp', $r, -1, 'rp');

    $convs = [];
    for ($i = 1; $i <= 5; $i++) {
        $body = trim((string)$r['conv' . $i . '_body']);
        if ($body === '') continue;
        $convs[] = ['idx' => $i, 'title' => trim((string)$r['conv' . $i . '_title']), 'msgs' => parse_rp_chat($body)];
    }
    if (!$convs) $convs[] = ['idx' => 1, 'title' => '', 'msgs' => []];
    $activeIdx = $convs[0]['idx'];

    $hex = '/^#[0-9a-fA-F]{6}$/';
    $vars = '';
    if (preg_match($hex, (string)$r['bub1_color'])) $vars .= '--bub1-bg:' . $r['bub1_color'] . ';';
    if (preg_match($hex, (string)$r['bub1_text'])) $vars .= '--bub1-ink:' . $r['bub1_text'] . ';';
    if (preg_match($hex, (string)$r['name1_color'])) $vars .= '--nm1-color:' . $r['name1_color'] . ';';
    if (preg_match($hex, (string)$r['bub2_color'])) $vars .= '--bub2-bg:' . $r['bub2_color'] . ';';
    if (preg_match($hex, (string)$r['bub2_text'])) $vars .= '--bub2-ink:' . $r['bub2_text'] . ';';
    if (preg_match($hex, (string)$r['name2_color'])) $vars .= '--nm2-color:' . $r['name2_color'] . ';';
    if (preg_match($hex, (string)$r['tab_color'])) $vars .= '--tab-bg:' . $r['tab_color'] . ';';
    if (preg_match($hex, (string)$r['tab_text'])) $vars .= '--tab-ink:' . $r['tab_text'] . ';';
    if (preg_match($hex, (string)$r['accent_color'])) $vars .= '--accent:' . $r['accent_color'] . ';';
    if (preg_match($hex, (string)$r['accent_ink_color'])) $vars .= '--accent-ink:' . $r['accent_ink_color'] . ';';

    $titleSt = '';
    if ($r['title_font'] !== '') $titleSt .= 'font-family:' . font_stack($r['title_font']) . ';';
    if (preg_match($hex, (string)$r['title_color'])) $titleSt .= 'color:' . $r['title_color'] . ';';

    $hasImg = $r['rep_image'] !== '';
    $layout = $hasImg && $r['img_layout'] === 'top' ? 'top' : 'side';

    view_start(['title' => $r['title'], 'page' => 'rp-detail', 'menu' => 1, 'bg' => 'rp', 'bgcss' => custom_bg($r['page_bg'], $r['page_color'], $r['page_color2'])]);
    ?>
<section class="rp-d rp-<?= $layout ?> <?= $hasImg ? '' : 'rp-plain' ?>"<?= $vars !== '' ? ' style="' . h($vars) . '"' : '' ?>>
  <?= back_link(url('rp.php'), 'rp-back', 'RP로') ?>
  <?php if (is_admin()): ?><a class="edit rp-edit" data-nopjax href="<?= h(url('admin/content.php')) ?>?t=rp&amp;a=edit&amp;id=<?= (int)$r['id'] ?>&amp;ret=<?= urlencode('rp.php?id=' . (int)$r['id']) ?>">수정</a><?php endif; ?>

  <?php if (count($convs) > 1): ?>
  <nav class="rp-tabs">
    <?php foreach ($convs as $c): ?>
      <button type="button" class="rp-tab <?= $c['idx'] === $activeIdx ? 'on' : '' ?>" data-conv="<?= $c['idx'] ?>"><?= h($c['title'] !== '' ? $c['title'] : '대화 ' . $c['idx']) ?></button>
    <?php endforeach; ?>
  </nav>
  <?php endif; ?>

  <?php if ($hasImg && $layout === 'side'): ?><img class="rp-img" src="<?= h(asset($r['rep_image'])) ?>" alt=""><?php endif; ?>

  <div class="rp-wrap">
    <?php if ($hasImg && $layout === 'top'): ?><div class="rp-hero"><img src="<?= h(asset($r['rep_image'])) ?>" alt=""></div><?php endif; ?>
    <h1 class="rp-ttl"<?= $titleSt !== '' ? ' style="' . h($titleSt) . '"' : '' ?>><?= h($r['title']) ?></h1>
    <?= board_bgm_data($r, '이 부(RP) 음악') ?>

    <?php foreach ($convs as $c):
        $imgList = [];
        foreach ($c['msgs'] as $mm) { if ($mm['type'] === 'img') $imgList[] = rp_resolve_img($r, $mm['src']); }
    ?>
    <div class="rp-chat" data-conv-panel="<?= $c['idx'] ?>" data-images='<?= h(json_encode($imgList, JSON_UNESCAPED_SLASHES)) ?>' <?= $c['idx'] === $activeIdx ? '' : 'hidden' ?>>
      <div class="rp-msgs">
      <?php
      $lastWho = null; $imgSeen = 0;
      foreach ($c['msgs'] as $m):
          $isNew = $m['who'] !== $lastWho;
          $lastWho = $m['who'];
          $nm = $m['who'] === 1 ? $r['name1'] : $r['name2'];
          $img = $m['who'] === 1 ? $r['img1'] : $r['img2'];
          $side = $m['who'] === 1 ? 'l' : 'r';
      ?>
        <?php if ($isNew): ?>
        <div class="rp-grp <?= $side ?>">
          <span class="rp-av"><?php if ($img !== ''): ?><img src="<?= h(asset($img)) ?>" alt=""><?php else: ?><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="8" r="3.4"></circle><path d="M5 20c1.2-4 4-6 7-6s5.8 2 7 6"></path></svg><?php endif; ?></span>
          <span class="rp-nm <?= $side ?>"><?= h($nm) ?></span>
        </div>
        <?php endif; ?>
        <div class="rp-row-msg <?= $side ?> <?= $isNew ? '' : 'cont' ?>">
          <?php if ($m['type'] === 'img'): ?>
            <div class="rp-bub rp-bub-img <?= $side ?>"><img loading="lazy" src="<?= h(rp_resolve_img($r, $m['src'])) ?>" data-idx="<?= $imgSeen++ ?>" alt=""></div>
          <?php elseif ($m['type'] === 'link'): ?>
            <a class="rp-link <?= $side ?>" href="<?= h($m['url']) ?>" target="_blank" rel="noopener noreferrer nofollow">
              <span class="rp-link-ic"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 14a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"></path><path d="M14 10a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"></path></svg></span>
              <span class="rp-link-tx"><span class="t1"><?= h($m['ttl']) ?></span><span class="t2"><?= h($m['url']) ?></span></span>
            </a>
          <?php else: ?>
            <div class="rp-bub <?= $side ?>"><?= nl2br_h($m['text']) ?></div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      <?php if (!$c['msgs']): ?><p class="empty">이 대화는 아직 내용이 없어요.</p><?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</section>
<?php
    view_end();
}
