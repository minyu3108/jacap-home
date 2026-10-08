<?php
require __DIR__ . '/_boot.php';

$G = setting_groups();
$g = isset($_GET['g']) ? (string)$_GET['g'] : 'site';
if (!isset($G[$g]) || (setting_group_board($g) !== null && !board_on(setting_group_board($g)))) $g = 'site';
$grp = $G[$g];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $errs = [];
    foreach ($grp['fields'] as $f) {
        $old = S($f['n']);
        $new = collect_field($f, $old, $errs);
        if ((string)$new !== (string)$old || !array_key_exists($f['n'], settings_all())) {
            set_setting($f['n'], $new);
        }
    }
    foreach ($errs as $e) { flash($e, 'err'); }
    if (!$errs) { flash('저장했어요.'); } else { flash('나머지 항목은 저장했어요. 위 오류를 확인해 주세요.', 'err'); }
    redirect(url('admin/settings.php') . '?g=' . urlencode($g));
}

$desc = [
    'site' => '사이트 이름, 캐릭터 이름, 글꼴과 기본 색을 정해요.',
    'favicon' => '브라우저 탭과 북마크, 폰 홈 화면에 보이는 사이트 아이콘을 정해요.',
    'boards' => '쓸 게시판만 켜고 끌 수 있어요. 끈 게시판의 글은 지워지지 않아요.',
    'landing' => '사이트에 처음 들어왔을 때 보이는 첫 화면이에요.',
    'home' => '메인 홈의 큰 이미지와 로그인 카드를 꾸며요.',
    'bg' => '페이지마다 배경 이미지·색·그라데이션을 정해요.',
    'menu' => '왼쪽 메뉴의 모양·이름·로고를 정해요.',
    'cards' => '카드 색과 마우스 커서 이미지를 정해요.',
    'clicksound' => '누를 때 나는 소리를 정해요.',
    'particles' => '배경에 떨어지는 조각 효과를 정해요.',
    'mtrail' => '마우스를 움직일 때 반짝이며 떨어지는 효과를 정해요.',
    'homebanner' => '내 홈페이지를 알리는 배너를 정해요.',
    'timeline_set' => '타임라인 페이지의 선 색과 대표 이미지를 정해요.',
    'running_set' => '러닝 진입 카드와 캐릭터별 배경을 정해요.',
    'hover' => '마우스를 올렸을 때의 색 변화를 정해요.',
    'dday' => '디데이 위젯에 보일 기념일 목록을 적어요.',
    'music' => '뮤직 플레이어의 기본 동작을 정해요.',
    'profile' => '프로필 선택 화면의 이미지와 글씨를 정해요.',
    'guestbook' => '방명록의 카드 색, 안내 문구, 방문자 쓰기 허용을 정해요.',
];
$previews = ['guestbook' => 'guestbook.php', 'profile' => 'profile.php', 'timeline_set' => 'timeline.php', 'running_set' => 'running.php', 'au' => 'au.php'];
$prevUrl = url(isset($previews[$g]) ? $previews[$g] : 'home.php');
admin_head($grp['label'], 'set:' . $g, [
    'sub' => isset($desc[$g]) ? $desc[$g] : '',
    'actions' => '<a class="btn ghost" href="' . h($prevUrl) . '" target="_blank" rel="noopener">' . admin_icon('ext', 15) . '미리 보기</a>',
]);
?>
<?php if ($g === 'favicon'):
    $fv = trim((string)S('favicon'));
    $ap = trim((string)S('apple_icon'));
    if ($ap === '') $ap = $fv;
    $tc = preg_match('/^#[0-9a-fA-F]{6}$/', (string)S('theme_color')) ? S('theme_color') : '';
    $siteName = (string)S('site_title', '');
?>
<div class="card fav-preview">
  <h2>지금 이렇게 보여요</h2>
  <div class="fav-row">
    <div class="fav-tab"><?php if ($fv !== ''): ?><img src="<?= h(asset($fv)) ?>" alt=""><?php else: ?><span class="fav-none"></span><?php endif; ?><span class="fav-title"><?= h($siteName !== '' ? $siteName : '사이트 이름') ?></span><i>&times;</i></div>
    <div class="fav-sizes">
      <?php foreach ([16, 32, 64] as $px): ?><div><span class="fav-box"><?php if ($fv !== ''): ?><img src="<?= h(asset($fv)) ?>" width="<?= $px ?>" height="<?= $px ?>" alt=""><?php endif; ?></span><small><?= $px ?>px</small></div><?php endforeach; ?>
      <div><span class="fav-box fav-app"><?php if ($ap !== ''): ?><img src="<?= h(asset($ap)) ?>" alt=""><?php endif; ?></span><small>홈 화면</small></div>
      <?php if ($tc !== ''): ?><div><span class="fav-box" style="background:<?= h($tc) ?>"></span><small>상단바 색</small></div><?php endif; ?>
    </div>
  </div>
  <?php if ($fv === ''): ?><p class="help">아직 파비콘이 없어요. 아래에서 이미지를 올려 보세요.</p><?php endif; ?>
  <p class="help">저장한 뒤에도 브라우저가 예전 아이콘을 기억하고 있어서 바로 안 바뀔 수 있어요. 탭을 닫았다가 다시 열거나 Ctrl+Shift+R(맥은 Cmd+Shift+R)로 새로고침해 보세요.</p>
</div>
<?php endif; ?>
<form method="post" enctype="multipart/form-data" class="card form set-form">
  <?= csrf_field() ?>
  <?php foreach ($grp['fields'] as $f): echo render_field($f, S($f['n'])); endforeach; ?>
  <div class="savebar">
    <span class="sb-dot"></span><span class="sb-msg">바뀐 내용이 없어요</span>
    <span class="grow"></span>
    <button type="button" class="btn plain" data-sb-reset disabled>되돌리기</button>
    <button class="btn" type="submit">변경사항 저장</button>
  </div>
</form>
<?php admin_foot();
