<?php
/** 러닝: 캐릭터별 러닝 로그. 진입 화면은 캐릭터 카드 2개, 그 안은 로그 게시판과 동일한 방식 */
require __DIR__ . '/inc/core.php';
require __DIR__ . '/inc/layout.php';
require __DIR__ . '/inc/board.php';
board_guard('running');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$who = isset($_GET['who']) && in_array($_GET['who'], ['1', '2'], true) ? (string)$_GET['who'] : '';

/** 캐릭터별 러닝 배경 CSS를 계산: 사이트 설정 "러닝"에 그 캐릭터 배경이 있으면 그걸, 없으면 빈 문자열(사이트 기본 러닝 배경으로 자동 대체) */
function running_bgcss($who) {
    return custom_bg(S('run' . $who . '_bg'), S('run' . $who . '_bg_color'), S('run' . $who . '_bg_color2'));
}

if ($id) {
    $rr = row('SELECT `character` FROM running WHERE id=?', [$id]);
    $backWho = $rr && in_array((string)$rr['character'], ['1', '2'], true) ? (string)$rr['character'] : '';
    $cfg = ['label' => '러닝', 'page' => 'running', 'menu' => 0, 'bg' => 'running', 'file' => $backWho !== '' ? 'running.php?who=' . $backWho : 'running.php', 'group' => 'character'];
    if ($backWho !== '') $cfg['bgcss'] = running_bgcss($backWho);
    board_detail('running', $id, $cfg);
} elseif ($who !== '') {
    running_board($who);
} else {
    running_pick();
}

function running_pick() {
    view_start(['title' => '러닝', 'page' => 'running-pick', 'menu' => 0, 'bg' => 'running']);
    $names = [1 => S('char1_name', '캐릭터 A'), 2 => S('char2_name', '캐릭터 B')];
    $imgs = [1 => S('run1_card'), 2 => S('run2_card')];
    ?>
<section class="rp-page rail-ok">
  <div class="rp-cards">
    <?php foreach ([1, 2] as $k): ?>
      <a class="rp-card <?= $imgs[$k] !== '' ? 'has-img' : '' ?>" href="<?= h(url('running.php')) ?>?who=<?= $k ?>">
        <?php if ($imgs[$k] !== ''): ?><img loading="lazy" src="<?= h(asset($imgs[$k])) ?>" alt=""><?php endif; ?>
        <span class="rp-card-cap"><?= h($names[$k]) ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<?php
    view_end();
}

function running_board($who) {
    $name = S('char' . $who . '_name', '캐릭터 ' . $who);
    $cfg = [
        'label' => $name . ' 러닝', 'page' => 'running', 'menu' => 0, 'bg' => 'running',
        'file' => 'running.php?who=' . $who, 'group' => 'character', 'lock_group' => $who,
        'back_url' => url('running.php'), 'back_label' => '캐릭터 선택으로',
        'bgcss' => running_bgcss($who),
    ];
    board_list('running', $cfg);
}
