<?php
/** AU: 카드 목록(SD 두 명 + 배경, 오버 시 이름) + 상세(캐릭터를 클릭하면 정보 표시) */
require __DIR__ . '/inc/core.php';
require __DIR__ . '/inc/layout.php';
board_guard('au');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id) { au_detail($id); } else { au_list(); }

function au_list() {
    $page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
    $per = 12;
    $all = rows('SELECT * FROM aus WHERE ' . vis_sql() . ' ORDER BY sort_order ASC, id ASC');
    $total = count($all);
    list($off, $pages, $page) = paginate($total, $per, $page);
    $items = array_slice($all, $off, $per);
    view_start(['title' => S('m_au'), 'page' => 'au', 'menu' => 4, 'bg' => 'au']);
    ?>
<section class="au-wrap">
  <header class="pg-head"><h1 class="sr-only"><?= h(S('m_au')) ?></h1><div class="pg-count">총 <?= $total ?>개</div><?= add_button('aus', 'au.php') ?></header>
  <?php if (!$items): ?>
    <p class="empty">아직 등록된 AU가 없어요.<?= is_admin() ? ' 관리자 페이지에서 추가해 보세요.' : '' ?></p>
  <?php else: ?>
  <div class="au-grid">
    <?php foreach ($items as $r):
        $locked = ($r['visibility'] === 'password') && is_locked('aus', $r);
        $st = card_style_attr($r);
        if ($r['card_name_font'] !== '') $st .= '--au-font:' . font_stack($r['card_name_font']) . ';';
        if (preg_match('/^#[0-9a-fA-F]{6}$/', (string)$r['card_name_color'])) $st .= '--au-color:' . $r['card_name_color'] . ';';
        if ((int)$r['card_name_size'] > 0) $st .= '--au-size:' . min(120, max(10, (int)$r['card_name_size'])) . 'px;';
        if (!$locked && $r['card_bg'] !== '') $st .= "background-image:url('" . cssurl(asset($r['card_bg'])) . "');";
        ?>
      <a class="au-card" href="<?= h(url('au.php')) ?>?id=<?= (int)$r['id'] ?>" style="<?= h($st) ?>">
        <?php if ($locked): ?>
          <span class="au-lock"><?= lock_icon() ?></span>
        <?php else: ?>
          <?php if ($r['sd1'] !== ''): ?><img class="sd sd1" loading="lazy" src="<?= h(asset($r['sd1'])) ?>" alt=""><?php endif; ?>
          <?php if ($r['sd2'] !== ''): ?><img class="sd sd2" loading="lazy" src="<?= h(asset($r['sd2'])) ?>" alt=""><?php endif; ?>
        <?php endif; ?>
        <span class="au-name"><?php if (!$locked && $r['title_img'] !== ''): ?><img class="au-name-img" src="<?= h(asset($r['title_img'])) ?>" alt="<?= h($r['title']) ?>"<?= (int)$r['title_img_w'] > 0 ? ' style="width:' . min(900, (int)$r['title_img_w']) . 'px"' : '' ?>><?php else: ?><b><?= h($r['title']) ?></b><?php endif; ?><?php if (!$locked && $r['summary'] !== ''): ?><i><?= h($r['summary']) ?></i><?php endif; ?></span>
      </a>
    <?php endforeach; ?>
  </div>
  <?= pager($page, $pages) ?>
  <?php endif; ?>
</section>
<?php
    view_end();
}

function au_detail($id) {
    $r = row('SELECT * FROM aus WHERE id=?', [$id]);
    if (!$r) page_message('AU를 찾을 수 없어요', '삭제되었거나 주소가 잘못되었어요.', 3);
    gate('aus', $r, 3, 'au');

    function au_char_data($r, $k) {
        $who = 'char' . $k . '_name';
        $bigName = trim((string)$r['name' . $k]) !== '' ? $r['name' . $k] : S($who);
        $nameSt = '';
        if ($r['name_font' . $k] !== '') $nameSt .= 'font-family:' . font_stack($r['name_font' . $k]) . ';';
        if (preg_match('/^#[0-9a-fA-F]{6}$/', (string)$r['name_color' . $k])) $nameSt .= 'color:' . $r['name_color' . $k] . ';';
        $nameHl = preg_match('/^#[0-9a-fA-F]{6}$/', (string)$r['name_hl' . $k]) ? 'background:' . $r['name_hl' . $k] . ';' : '';
        $subSt = '';
        if ($r['sub_font' . $k] !== '') $subSt .= 'font-family:' . font_stack($r['sub_font' . $k]) . ';';
        if (preg_match('/^#[0-9a-fA-F]{6}$/', (string)$r['sub_color' . $k])) $subSt .= 'color:' . $r['sub_color' . $k] . ';';
        $subHl = preg_match('/^#[0-9a-fA-F]{6}$/', (string)$r['sub_hl' . $k]) ? 'background:' . $r['sub_hl' . $k] . ';' : '';
        $panelSt = '';
        if (preg_match('/^#[0-9a-fA-F]{6}$/', (string)$r['panel_color' . $k])) {
            $op = trim((string)$r['panel_opacity' . $k]) === '' ? null : min(100, max(10, (int)$r['panel_opacity' . $k]));
            $panelSt = '--panel:' . hex_rgba($r['panel_color' . $k], ($op !== null ? $op : 78) / 100) . ';';
        }
        return [
            'name' => $bigName, 'nameSt' => $nameSt, 'nameHl' => $nameHl,
            'sub' => $r['subtitle' . $k], 'subSt' => $subSt, 'subHl' => $subHl,
            'facts' => parse_info($r['facts' . $k]), 'body' => $r['info' . $k], 'panelSt' => $panelSt,
        ];
    }
    $names = [1 => au_char_data($r, 1), 2 => au_char_data($r, 2)];
    $nst = ''; // 무대 위 캐릭터 이름표(작은 라벨)용 - 이 AU의 설정
    if ($r['stage_font'] !== '') $nst .= 'font-family:' . font_stack($r['stage_font']) . ';';
    if (preg_match('/^#[0-9a-fA-F]{6}$/', (string)$r['stage_color'])) $nst .= 'color:' . $r['stage_color'] . ';';
    if ((int)$r['stage_size'] > 0) $nst .= 'font-size:' . min(120, max(10, (int)$r['stage_size'])) . 'px;';

    $auFacts = parse_info($r['au_facts']);
    $hasStory = trim(strip_tags((string)$r['story'], '<img>')) !== '';
    $hasCombo = $r['combo_img'] !== '';
    $hasInfo = [1 => trim(strip_tags((string)$r['info1'], '<img>')) !== '' || trim((string)$r['facts1']) !== '', 2 => trim(strip_tags((string)$r['info2'], '<img>')) !== '' || trim((string)$r['facts2']) !== ''];

    view_start(['title' => $r['title'], 'page' => 'au-detail', 'menu' => 4, 'bg' => 'au', 'bgcss' => custom_bg($r['page_bg'], $r['page_color'], $r['page_color2'])]);
    $hex = '/^#[0-9a-fA-F]{6}$/';
    $accVars = '';
    if (preg_match($hex, (string)$r['accent_color'])) $accVars .= '--accent:' . $r['accent_color'] . ';';
    if (preg_match($hex, (string)$r['accent_ink_color'])) $accVars .= '--accent-ink:' . $r['accent_ink_color'] . ';';
    ?>
<section class="au-d au-tabbed"<?= $accVars !== '' ? ' style="' . h($accVars) . '"' : '' ?>>
  <?= back_link(url('au.php'), 'au-back', '목록으로', $r['title_color']) ?>

  <?= board_bgm_data($r, '테마곡') ?>

  <?php if (is_admin()): ?><a class="edit au-edit" data-nopjax href="<?= h(url('admin/content.php')) ?>?t=aus&amp;a=edit&amp;id=<?= (int)$r['id'] ?>&amp;ret=<?= urlencode('au.php?id=' . (int)$r['id']) ?>">수정</a><?php endif; ?>

  <nav class="au-tabs">
    <button type="button" class="au-tab on" data-tab="info">정보</button>
    <button type="button" class="au-tab" data-tab="char">캐릭터</button>
  </nav>

  <div class="au-panel" data-panel="info">
    <div class="au-info-wrap">
      <?php if ($r['info_image'] !== ''): ?>
      <div class="au-info-img"><img src="<?= h(asset($r['info_image'])) ?>" alt=""></div>
      <?php endif; ?>
      <?php $auHl = preg_match('/^#[0-9a-fA-F]{6}$/', (string)$r['title_color']) ? $r['title_color'] : ''; ?>
      <?php
      $auVars = $auHl !== '' ? '--au-hl: ' . $auHl . ';' : '';
      if (preg_match('/^#[0-9a-fA-F]{6}$/', (string)$r['tag_bg_color'])) {
          $tagOp = trim((string)$r['tag_bg_opacity']) === '' ? 100 : min(100, max(10, (int)$r['tag_bg_opacity']));
          $auVars .= '--au-tag-bg: ' . hex_rgba($r['tag_bg_color'], $tagOp / 100) . ';';
      }
      if (preg_match('/^#[0-9a-fA-F]{6}$/', (string)$r['tag_text_color'])) $auVars .= '--au-tag-ink: ' . $r['tag_text_color'] . ';';
      ?>
      <div class="au-info-body <?= $r['info_image'] === '' ? 'wide' : '' ?>"<?= $auVars !== '' ? ' style="' . h($auVars) . '"' : '' ?>>
        <?php $auTitleSt = $auHl !== '' ? ' style="color:' . h($auHl) . '"' : ''; ?>
        <h1 class="au-ttl"<?= $auTitleSt ?>><?= h($r['title']) ?></h1>
        <?php if ($r['summary'] !== ''): ?><p class="au-summary"><?= h($r['summary']) ?></p><?php endif; ?>
        <?php if ($auFacts): ?>
        <div class="au-tagrow">
          <?php foreach ($auFacts as $row): ?>
            <?php if ($row[0] !== ''): ?><div class="au-tag"><b><?= h($row[0]) ?></b><?= h($row[1]) ?></div>
            <?php else: ?><div class="au-tag full"><?= h($row[1]) ?></div><?php endif; ?>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if ($hasStory): ?>
        <div class="au-divider"><span>서사</span></div>
        <div class="au-story rd-body"><?= $r['story'] ?></div>
        <?php endif; ?>
        <?php if (!$auFacts && !$hasStory): ?><p class="empty">아직 등록된 정보가 없어요.</p><?php endif; ?>
      </div>
    </div>
  </div>

  <div class="au-panel" data-panel="char" hidden>
    <?php if ($hasCombo): ?>
    <div class="au-combo" data-picked="">
      <img class="au-combo-img" src="<?= h(asset($r['combo_img'])) ?>" alt="">
      <?php if ($hasInfo[1]): ?><button type="button" class="au-combo-zone au-combo-zone-l" data-k="1" aria-label="<?= h($names[1]['name']) ?> 정보 보기"></button><?php endif; ?>
      <?php if ($hasInfo[2]): ?><button type="button" class="au-combo-zone au-combo-zone-r" data-k="2" aria-label="<?= h($names[2]['name']) ?> 정보 보기"></button><?php endif; ?>
      <?php foreach ([1, 2] as $k): if (!$hasInfo[$k]) continue; $d = $names[$k]; ?>
      <div class="au-info" data-for="<?= $k ?>" hidden style="<?= h($d['panelSt']) ?>">
        <button type="button" class="au-x" aria-label="닫기">&times;</button>
        <div class="au-info-in">
          <header class="pd-head">
            <h1 class="pd-bigname" style="<?= h($d['nameSt']) ?>"><span style="<?= h($d['nameHl']) ?>"><?= h($d['name']) ?></span></h1>
            <?php if ($d['sub'] !== ''): ?><p class="pd-sub" style="<?= h($d['subSt']) ?>"><span style="<?= h($d['subHl']) ?>"><?= h($d['sub']) ?></span></p><?php endif; ?>
          </header>
          <?php if ($d['facts']): ?>
          <dl class="pd-info">
            <?php foreach ($d['facts'] as $row): ?>
              <?php if ($row[0] !== ''): ?><dt><?= h($row[0]) ?></dt><dd><?= h($row[1]) ?></dd>
              <?php else: ?><dd class="full"><?= h($row[1]) ?></dd><?php endif; ?>
            <?php endforeach; ?>
          </dl>
          <?php endif; ?>
          <?php if (trim((string)$d['body']) !== ''): ?><div class="pd-body rd-body"><?= $d['body'] ?></div><?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="au-stage" data-picked="">
      <?php foreach ([1, 2] as $k):
          $img = $r['img' . $k] !== '' ? $r['img' . $k] : $r['sd' . $k]; ?>
        <div class="au-char <?= $hasInfo[$k] ? 'has-info' : '' ?>" data-k="<?= $k ?>"<?= $hasInfo[$k] ? ' tabindex="0" role="button" aria-expanded="false"' : '' ?>>
          <?php if ($img !== ''): ?><img src="<?= h(asset($img)) ?>" alt="<?= h($names[$k]['name']) ?>"><?php else: ?><div class="pf-empty"><?= h($names[$k]['name']) ?></div><?php endif; ?>
          <span class="au-nm" style="<?= h($nst) ?>"><?= h($names[$k]['name']) ?></span>
        </div>
      <?php endforeach; ?>
      <?php foreach ([1, 2] as $k): if (!$hasInfo[$k]) continue; $d = $names[$k]; ?>
      <div class="au-info" data-for="<?= $k ?>" hidden style="<?= h($d['panelSt']) ?>">
        <button type="button" class="au-x" aria-label="닫기">&times;</button>
        <div class="au-info-in">
          <header class="pd-head">
            <h1 class="pd-bigname" style="<?= h($d['nameSt']) ?>"><span style="<?= h($d['nameHl']) ?>"><?= h($d['name']) ?></span></h1>
            <?php if ($d['sub'] !== ''): ?><p class="pd-sub" style="<?= h($d['subSt']) ?>"><span style="<?= h($d['subHl']) ?>"><?= h($d['sub']) ?></span></p><?php endif; ?>
          </header>
          <?php if ($d['facts']): ?>
          <dl class="pd-info">
            <?php foreach ($d['facts'] as $row): ?>
              <?php if ($row[0] !== ''): ?><dt><?= h($row[0]) ?></dt><dd><?= h($row[1]) ?></dd>
              <?php else: ?><dd class="full"><?= h($row[1]) ?></dd><?php endif; ?>
            <?php endforeach; ?>
          </dl>
          <?php endif; ?>
          <?php if (trim((string)$d['body']) !== ''): ?><div class="pd-body rd-body"><?= $d['body'] ?></div><?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>
<?php
    view_end();
}
