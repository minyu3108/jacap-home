<?php
require __DIR__ . '/_boot.php';

// 구조 업데이트 (새 버전의 항목을 DB에 반영)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('do') === 'schema') {
    require_post_csrf();
    ensure_schema();
    try { migrate_data(); } catch (Exception $e2) { error_log('data migration failed: ' . $e2->getMessage()); }
    flash('데이터 구조를 최신 상태로 맞췄어요.');
    redirect(url('admin/'));
}
if ((string)S('schema_ver', '0') !== '70') {
    ensure_schema();
    set_setting('schema_ver', '70');
}

$counts = [];
foreach (['profiles', 'gallery', 'logs', 'trpg', 'aus', 'timeline', 'side', 'rp', 'running', 'guestbook', 'banners', 'tracks', 'stickers'] as $t) {
    $counts[$t] = (int)val('SELECT COUNT(*) FROM `' . $t . '`');
}
$E = entities();

$todo = [
    ['사이트 이름/캐릭터 이름/글꼴 정하기', 'settings.php?g=site', S('site_title') !== '우리의 홈'],
    ['랜딩 페이지 로고와 배경 넣기', 'settings.php?g=landing', S('landing_logo') !== ''],
    ['메인 홈 큰 이미지 넣기', 'settings.php?g=home', S('home_image') !== ''],
    ['프로필 선택 화면 이미지 넣기', 'settings.php?g=profile', S('pf_char1_img') !== ''],
    ['프로필 만들기 (캐릭터별 최대 4장)', 'content.php?t=profiles', $counts['profiles'] > 0],
    ['음악 트랙 추가하기', 'content.php?t=tracks', $counts['tracks'] > 0],
    ['디데이 목록 적기', 'settings.php?g=dday', trim((string)S('dday_items')) !== ''],
];
$todoShown = array_values(array_filter($todo, function ($t) {
    return board_on('profile') || (strpos($t[1], 'g=profile') === false && strpos($t[1], 't=profiles') === false);
}));
$todoAllDone = $todoShown && !array_filter($todoShown, function ($t) { return !$t[2]; });

admin_head('대시보드', 'dash');
?>
<?php if (!$todoAllDone): ?>
<div class="card">
  <h2>처음이라면 이 순서로 해보세요</h2>
  <ul class="todo">
    <?php foreach ($todoShown as $t): ?>
      <li class="<?= $t[2] ? 'done' : '' ?>"><a href="<?= h(url('admin/' . $t[1])) ?>"><?= h($t[0]) ?></a><?= $t[2] ? ' <span class="tag">완료</span>' : '' ?></li>
    <?php endforeach; ?>
  </ul>
  <p class="help">메인 홈에서 디데이/뮤직 위젯을 마우스로 옮긴 뒤, 로그인 카드의 <b>"위젯 위치 저장"</b>을 누르면 그 위치가 방문자들의 기본 위치가 돼요.</p>
</div>
<?php endif; ?>

<div class="card">
  <h2>현재 등록된 콘텐츠</h2>
  <div class="stats">
    <?php foreach ($counts as $t => $n): if (!entity_on($t)) continue; ?>
      <a href="<?= h(url('admin/content.php')) ?>?t=<?= h($t) ?>"><b><?= $n ?></b><span><?= h($E[$t]['label']) ?></span></a>
    <?php endforeach; ?>
  </div>
</div>

<?php $lb = S('last_backup', ''); $old = ($lb === '' || (time() - strtotime($lb)) > 30 * 86400); ?>
<div class="card">
  <h2>백업</h2>
  <p class="help"><?= $lb === '' ? '아직 백업한 적이 없어요.' : '마지막 백업: ' . h($lb) . '.' ?><?= $old ? ' <b>한 달 넘게 백업하지 않았어요.</b>' : '' ?></p>
  <a class="btn ghost" href="<?= h(url('admin/backup.php')) ?>">백업하러 가기</a>
</div>

<div class="card">
  <h2>업데이트 후 점검</h2>
  <p class="help">사이트 파일을 새 버전으로 교체한 뒤에는 아래 버튼을 한 번 눌러주세요. 새로 생긴 입력 항목을 데이터베이스에 반영해요. (기존 데이터는 지워지지 않아요)</p>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="schema"><button class="btn ghost" type="submit">데이터 구조 업데이트</button></form>
</div>
<?php admin_foot();
