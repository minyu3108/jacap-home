<?php
/** 프로필: 캐릭터 선택 화면(마우스 오버 시 카드) + 개별 프로필 상세 */
require __DIR__ . '/inc/core.php';
require __DIR__ . '/inc/layout.php';
require __DIR__ . '/inc/profile_v2.php';
board_guard('profile');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id) { profile_detail_v2($id, 'profile.php'); } else { profile_select(); }

function card_style($p) {
    $s = '';
    if ($p['card_color'] !== '') $s .= 'background-color:' . $p['card_color'] . ';';
    if ($p['card_bg'] !== '') $s .= "background-image:url('" . cssurl(asset($p['card_bg'])) . "');background-size:cover;background-position:center;";
    return $s;
}

function profile_select() {
    view_start(['title' => S('m_profile'), 'page' => 'profile', 'menu' => 0, 'bg' => 'profile']);
    $by = [1 => [], 2 => []];
    foreach (rows('SELECT * FROM profiles ORDER BY side ASC, sort_order ASC, id ASC') as $r) {
        $k = (int)$r['side'];
        if (isset($by[$k]) && count($by[$k]) < 4) $by[$k][] = $r;
    }
    $gap = max(0, min(400, (int)S('pf_gap')));
    $comboImg = S('pf_combo_img');
    $nst = '';
    if (S('pf_name_font') !== '') $nst .= 'font-family:' . font_stack(S('pf_name_font')) . ';';
    if (preg_match('/^#[0-9a-fA-F]{6}$/', (string)S('pf_name_color'))) $nst .= 'color:' . S('pf_name_color') . ';';
    if ((int)S('pf_name_size') > 0) $nst .= 'font-size:' . min(120, max(10, (int)S('pf_name_size'))) . 'px;';
    $renderCards = function ($k) use ($by) {
        ob_start(); ?>
      <div class="pf-cards">
        <?php foreach ($by[$k] as $i => $p): ?>
          <a class="pf-card" style="--i:<?= $i ?>;<?= h(card_style($p)) ?>" href="<?= h(url('profile.php')) ?>?id=<?= (int)$p['id'] ?>">
            <span class="pf-veil"></span>
            <span class="pf-head"><?php if ($p['head_img'] !== ''): ?><img loading="lazy" src="<?= h(asset($p['head_img'])) ?>" alt=""><?php endif; ?></span>
            <span class="pf-txt"><b><?= h($p['name']) ?></b><i><?= h($p['subtitle']) ?></i></span>
          </a>
        <?php endforeach; ?>
        <?php if (!$by[$k] && is_admin()): ?><span class="pf-none">등록된 프로필이 없어요</span><?php endif; ?>
      </div>
        <?php
        return ob_get_clean();
    };
    ?>
<section class="pf-wrap">
  <?= add_button('profiles', 'profile.php', '+ 프로필 추가') ?>
  <?php if ($comboImg !== ''): ?>
  <div class="pf-combo" data-picked="">
    <img class="pf-combo-img" src="<?= h(asset($comboImg)) ?>" alt="">
    <?php foreach ([1, 2] as $k): $nm = S('char' . $k . '_name'); ?>
    <div class="pf-char pf-combo-zone side<?= $k ?>" tabindex="0" role="button" aria-label="<?= h($nm) ?> 프로필 목록 보기">
      <?= $renderCards($k) ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <div class="pf-stage"<?= $gap > 0 ? ' style="gap:' . $gap . 'px"' : '' ?>>
  <?php foreach ([1, 2] as $k):
      $img = S('pf_char' . $k . '_img');
      $nm = S('char' . $k . '_name'); ?>
    <div class="pf-char side<?= $k ?>" tabindex="0">
      <div class="pf-fig">
        <?php if ($img !== ''): ?><img src="<?= h(asset($img)) ?>" alt="<?= h($nm) ?>">
        <?php else: ?><div class="pf-empty"><?= h($nm) ?><small><?= is_admin() ? '프로필 선택 화면 이미지를 설정해 주세요' : '' ?></small></div><?php endif; ?>
      </div>
      <div class="pf-name" style="<?= h($nst) ?>"><?= h($nm) ?></div>
      <?= $renderCards($k) ?>
    </div>
  <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>
<?php
    view_end();
}
