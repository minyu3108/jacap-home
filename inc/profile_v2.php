<?php
/**
 * 프로필 상세 (새 디자인 · 에디토리얼형)
 * - 기본 정보 탭: 큰 이름(배경 글씨) + 전신 + 한 줄 소개/짧은 문구 + 기본 정보 목록
 * - 상세 프로필 탭: 전신이 옆으로 비켜나고, 화면 오른쪽 반에 흐릿한 오버레이 + 상세 내용
 * - 아래: 보이스 버튼(왼쪽), 프로필 선택(오른쪽, 캐릭터마다 한 줄)
 * $self: 프로필 선택 링크가 가리킬 파일
 */

function pd2_hex($v) { return preg_match('/^#[0-9a-fA-F]{6}$/', (string)$v) ? (string)$v : ''; }

function profile_detail_v2($id, $self = 'profile.php') {
    $p = row('SELECT * FROM profiles WHERE id=?', [$id]);
    if (!$p) page_message('프로필을 찾을 수 없어요', '삭제되었거나 주소가 잘못되었어요.', 0);

    $bg = custom_bg($p['page_bg'], $p['page_color'], $p['page_color2']);
    view_start(['title' => $p['name'], 'page' => 'profile-v2', 'menu' => 0, 'bg' => 'profile', 'bgcss' => $bg, 'ghost' => null]);

    // 프로필 선택 (캐릭터마다 한 줄)
    $groups = [1 => [], 2 => []];
    foreach (rows('SELECT id, name, side, head_img FROM profiles ORDER BY side ASC, sort_order ASC, id ASC') as $a) {
        $k = (int)$a['side'];
        if (isset($groups[$k]) && count($groups[$k]) < 4) $groups[$k][] = $a;
    }

    $info = parse_info($p['info']);
    $bigName = trim((string)$p['disp_name']) !== '' ? $p['disp_name'] : $p['name'];
    $hasBody = trim(strip_tags((string)$p['body'], '<img><iframe><video>')) !== '';

    // ---- 색 / 글꼴 / 크기 → CSS 변수 ----
    $v = '';
    if (($c = pd2_hex($p['accent_color'])) !== '') $v .= '--accent:' . $c . ';';
    if (($c = pd2_hex($p['accent_ink_color'])) !== '') $v .= '--accent-ink:' . $c . ';';
    if (($c = pd2_hex($p['text_color'])) !== '') $v .= '--pd2-ink:' . $c . ';';
    if (($c = pd2_hex($p['disp_color'])) !== '') $v .= '--pd2-name:' . $c . ';';
    if (($c = pd2_hex($p['sub_color'])) !== '') $v .= '--pd2-sub:' . $c . ';';
    if ($p['disp_font'] !== '') $v .= '--pd2-name-font:' . font_stack($p['disp_font']) . ';';
    // 글꼴마다 실제로 있는 굵기가 달라서, 없는 굵기를 억지로 두껍게 만들지 않도록 맞춰요
    $df = (string)$p['disp_font'];
    $v .= '--pd2-name-w:' . (($df === '' || $df === 'noto_serif') ? 900 : (in_array($df, ['pretendard', 'gowun_batang', 'nanum_myeongjo', 'nanum_gothic'], true) ? 700 : 400)) . ';';
    if ($p['sub_font'] !== '') $v .= '--pd2-sub-font:' . font_stack($p['sub_font']) . ';';
    $ns = (int)$p['disp_size'];
    if ($ns > 0) { $ns = min(320, max(40, $ns)); $v .= '--pd2-name-size:' . $ns . 'px;'; }

    $ovHex = pd2_hex($p['ov_color']) !== '' ? $p['ov_color'] : '#0c0c12';
    $ovOp = (int)$p['ov_opacity'] > 0 ? min(100, (int)$p['ov_opacity']) : 50;
    $ovBlur = (int)$p['ov_blur'] > 0 ? min(40, (int)$p['ov_blur']) : 22;
    $v .= '--pd2-ov:' . hex_rgba($ovHex, $ovOp / 100) . ';--pd2-ov-blur:' . $ovBlur . 'px;';
    $v .= '--pd2-shade:' . hex_rgba($ovHex, 0.72) . ';';

    $ghost = on1($p['bg_ghost']) && $p['full_img'] !== '';
    if ($ghost) {
        $gRaw = trim((string)$p['bg_ghost_op']);
        $gOp = ($gRaw === '' || (int)$gRaw <= 0) ? 30 : min(60, max(5, (int)$gRaw));
        $v .= '--pd2-ghost-op:' . round($gOp / 100, 2) . ';';
    }

    $nameHl = pd2_hex($p['disp_hl']) !== '' ? 'background:' . $p['disp_hl'] . ';' : '';
    $subHl = pd2_hex($p['sub_hl']) !== '' ? 'background:' . $p['sub_hl'] . ';' : '';

    $voices = [];
    try { $voices = rows("SELECT line, file FROM voices WHERE profile_id=? AND file<>'' ORDER BY id ASC", [$p['id']]); } catch (Exception $e) { /* 표가 없으면 무시 */ }
    $hasSwitch = (bool)($groups[1] || $groups[2]);
    $v .= '--pd2-sw-n:' . max(1, count($groups[1]), count($groups[2])) . ';';
    ?>
<section class="pd2" data-pd2 style="<?= h($v) ?>">
  <?= board_bgm_data($p, '테마곡', 'theme') ?>

  <header class="pd2-top">
    <?= back_link(url('profile.php'), 'pd2-back') ?>
    <?php if ($hasBody): ?>
    <div class="pd2-tabs" role="tablist" aria-label="프로필 보기">
      <button type="button" role="tab" class="pd2-tab on" aria-selected="true" data-pd2-tab="basic">기본 정보</button>
      <button type="button" role="tab" class="pd2-tab" aria-selected="false" data-pd2-tab="detail">상세 프로필</button>
    </div>
    <?php endif; ?>
    <span class="pd2-rule" aria-hidden="true"></span>
    <span class="pd2-tag">PROFILE — <?= h($p['name']) ?></span>
    <?php if (is_admin()): ?><a class="pd2-edit" data-nopjax href="<?= h(url('admin/content.php')) ?>?t=profiles&amp;a=edit&amp;id=<?= (int)$p['id'] ?>&amp;ret=<?= urlencode($self . '?id=' . (int)$p['id']) ?>">수정</a><?php endif; ?>
  </header>

  <div class="pd2-title">
    <h1 class="pd2-name"><span style="<?= h($nameHl) ?>"><?= h($bigName) ?></span></h1>
    <div class="pd2-lead">
      <?php if ($p['subtitle'] !== ''): ?><p class="pd2-sub"><span style="<?= h($subHl) ?>"><?= h($p['subtitle']) ?></span></p><?php endif; ?>
      <?php if (trim((string)$p['intro']) !== ''): ?><p class="pd2-intro"><?= nl2br_h(trim((string)$p['intro'])) ?></p><?php endif; ?>
    </div>
  </div>

  <div class="pd2-fig">
    <?php if ($ghost): ?><img class="pd2-ghost" src="<?= h(asset($p['full_img'])) ?>" alt="" aria-hidden="true"><?php endif; ?>
    <?php if ($p['full_img'] !== ''): ?><img class="pd2-full" src="<?= h(asset($p['full_img'])) ?>" alt="<?= h($bigName) ?>"><?php endif; ?>
    <?php if ($voices): ?><div class="pd-caption pd2-caption" hidden></div><?php endif; ?>
  </div>

  <?php if ($info): ?>
  <dl class="pd2-info">
    <?php $n = 0; foreach ($info as $row): $n++; ?>
      <div class="pd2-row<?= $row[0] === '' ? ' full' : '' ?>">
        <span class="pd2-no" aria-hidden="true"><?= sprintf('%02d', $n) ?></span>
        <?php if ($row[0] !== ''): ?><dt><?= h($row[0]) ?></dt><dd><?= h($row[1]) ?></dd>
        <?php else: ?><dd><?= h($row[1]) ?></dd><?php endif; ?>
      </div>
    <?php endforeach; ?>
  </dl>
  <?php endif; ?>

  <div class="pd2-shade" aria-hidden="true"></div>

  <?php if ($hasBody): ?>
  <div class="pd2-ov" id="pd2-detail" role="tabpanel" aria-label="상세 프로필" aria-hidden="true">
    <div class="pd2-ov-in rd-body"><?= $p['body'] ?></div>
  </div>
  <?php endif; ?>

  <?php if ($voices): ?>
  <div class="pd2-voices">
    <div class="pd2-vlist">
      <?php foreach ($voices as $i => $vc): ?>
      <button type="button" class="pd2-voice" data-src="<?= h(asset($vc['file'])) ?>" data-line="<?= h($vc['line']) ?>"<?= $vc['line'] !== '' ? ' title="' . h(cut($vc['line'], 60)) . '"' : '' ?>>
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 9v6h4l5 4V5L8 9z"/><path d="M16 9a4 4 0 0 1 0 6"/></svg>
        <span>보이스 <?= $i + 1 ?></span>
      </button>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($hasSwitch): ?>
  <nav class="pd2-switch" aria-label="다른 프로필 선택">
    <?php foreach ([1, 2] as $k): if (!$groups[$k]) continue; ?>
    <div class="pd2-sw-row">
      <span class="pd2-sw-who"><?= h(S('char' . $k . '_name')) ?></span>
      <?php foreach ($groups[$k] as $a): $on = (int)$a['id'] === (int)$p['id']; ?>
      <a class="pd2-sw<?= $on ? ' on' : '' ?>" href="<?= h(url($self)) ?>?id=<?= (int)$a['id'] ?>"<?= $on ? ' aria-current="page"' : '' ?>>
        <span class="pd2-sw-head"><?php if ($a['head_img'] !== ''): ?><img loading="lazy" src="<?= h(asset($a['head_img'])) ?>" alt=""><?php else: ?><svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true"><circle cx="12" cy="9" r="4.5"/><path d="M3.5 22c1-4.5 4.6-7 8.5-7s7.5 2.5 8.5 7z"/></svg><?php endif; ?></span>
        <span class="pd2-sw-name"><?= h($a['name']) ?></span>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  </nav>
  <?php endif; ?>
</section>
<?php
    view_end();
}
