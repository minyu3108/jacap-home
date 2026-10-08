<?php
require __DIR__ . '/_boot.php';

$me = row('SELECT * FROM admins WHERE id=?', [(int)$_SESSION['admin_id']]);
if (!$me) { redirect(url('login.php') . '?out=1&t=' . urlencode(csrf_token())); }

$adminCount = (int)val('SELECT COUNT(*) FROM admins');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('do') === 'add_admin') {
    require_post_csrf();
    $errs = [];
    $cur = isset($_POST['cur_pw']) ? (string)$_POST['cur_pw'] : '';
    $u = post('new_user');
    $dn = cut(post('new_name'), 100);
    $pw = isset($_POST['new_admin_pw']) ? (string)$_POST['new_admin_pw'] : '';
    if ($adminCount >= 2) $errs[] = '관리자는 최대 2명까지예요.';
    if (!password_verify($cur, $me['pass_hash'])) $errs[] = '내 현재 비밀번호가 맞지 않아요.';
    if (!preg_match('/^[A-Za-z0-9_.-]{3,30}$/', $u)) $errs[] = '아이디는 영문/숫자 3~30자로 입력해 주세요.';
    if (strlen($pw) < 8) $errs[] = '비밀번호는 8자 이상이어야 해요.';
    if (!$errs && (int)val('SELECT COUNT(*) FROM admins WHERE username=?', [$u]) > 0) $errs[] = '이미 있는 아이디예요.';
    if (!$errs) {
        insert_row('admins', ['username' => $u, 'pass_hash' => password_hash($pw, PASSWORD_DEFAULT), 'display_name' => $dn !== '' ? $dn : $u, 'created_at' => now()]);
        flash('관리자를 추가했어요. 새 관리자는 메인 홈의 로그인 카드로 로그인하면 돼요.');
    }
    foreach ($errs as $e) flash($e, 'err');
    redirect(url('admin/account.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $errs = [];
    $name = cut(post('display_name'), 100);
    $cur = isset($_POST['cur_pw']) ? (string)$_POST['cur_pw'] : '';
    $new = isset($_POST['new_pw']) ? (string)$_POST['new_pw'] : '';
    $new2 = isset($_POST['new_pw2']) ? (string)$_POST['new_pw2'] : '';

    if ($name === '') $errs[] = '표시 이름을 입력해 주세요.';
    if (!password_verify($cur, $me['pass_hash'])) $errs[] = '현재 비밀번호가 맞지 않아요.';
    if ($new !== '') {
        if (strlen($new) < 8) $errs[] = '새 비밀번호는 8자 이상이어야 해요.';
        if ($new !== $new2) $errs[] = '새 비밀번호 확인이 일치하지 않아요.';
    }
    if (!$errs) {
        $data = ['display_name' => $name];
        if ($new !== '') $data['pass_hash'] = password_hash($new, PASSWORD_DEFAULT);
        update_row('admins', $me['id'], $data);
        $_SESSION['admin_name'] = $name;
        flash($new !== '' ? '이름과 비밀번호를 바꿨어요.' : '이름을 바꿨어요.');
        redirect(url('admin/account.php'));
    }
    foreach ($errs as $e) flash($e, 'err');
    redirect(url('admin/account.php'));
}

admin_head('내 계정', 'account');
?>
<form method="post" class="card form">
  <?= csrf_field() ?>
  <div class="fld"><label>아이디</label><input type="text" value="<?= h($me['username']) ?>" disabled></div>
  <div class="fld"><label for="dn">표시 이름</label><input type="text" id="dn" name="display_name" value="<?= h($me['display_name']) ?>" maxlength="100" required></div>
  <div class="fld"><label for="cp">현재 비밀번호 (변경할 때마다 필요해요)</label><input type="password" id="cp" name="cur_pw" required autocomplete="current-password"></div>
  <div class="fld"><label for="np">새 비밀번호 (바꿀 때만 입력)</label><input type="password" id="np" name="new_pw" autocomplete="new-password"></div>
  <div class="fld"><label for="np2">새 비밀번호 확인</label><input type="password" id="np2" name="new_pw2" autocomplete="new-password"></div>
  <div class="actions"><button class="btn" type="submit">저장하기</button></div>
</form>
<?php if ($adminCount < 2): ?>
<form method="post" class="card form">
  <h2>관리자 추가 (현재 <?= $adminCount ?>명 / 최대 2명)</h2>
  <?= csrf_field() ?><input type="hidden" name="do" value="add_admin">
  <div class="fld"><label for="nu">새 관리자 아이디 (영문/숫자)</label><input type="text" id="nu" name="new_user" required autocomplete="off"></div>
  <div class="fld"><label for="nn">표시 이름</label><input type="text" id="nn" name="new_name" maxlength="100" autocomplete="off"></div>
  <div class="fld"><label for="nw">새 관리자 비밀번호 (8자 이상)</label><input type="password" id="nw" name="new_admin_pw" required autocomplete="new-password"></div>
  <div class="fld"><label for="ncp">내 현재 비밀번호 (확인용)</label><input type="password" id="ncp" name="cur_pw" required autocomplete="current-password"></div>
  <div class="actions"><button class="btn" type="submit">관리자 추가</button></div>
</form>
<?php endif; ?>
<?php admin_foot();
