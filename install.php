<?php
/**
 * 설치 마법사 - 브라우저에서 한 번만 실행하는 파일입니다.
 * 설치가 끝나면 이 파일은 반드시 삭제하세요. (자동 삭제를 시도합니다)
 */
require __DIR__ . '/inc/core.php';

ini_set('display_errors', '1');
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

$installed = is_array($GLOBALS['JH_CFG']);
$errors = [];
$bdSel = board_keys();
$bdLabels = ['profile' => '프로필', 'running' => '러닝', 'timeline' => '타임라인', 'rp' => 'RP', 'side' => '썰 백업', 'gallery' => '갤러리', 'log' => '로그', 'au' => 'AU', 'trpg' => 'TRPG', 'guestbook' => '방명록'];
$done = false;
$deleted = false;
$v = [
    'db_host' => 'localhost', 'db_name' => '', 'db_user' => '', 'db_pass' => '',
    'site_title' => '', 'a1_user' => '', 'a1_name' => '', 'a2_user' => '', 'a2_name' => '',
];

if (!$installed && $_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($v as $k => $_) { $v[$k] = isset($_POST[$k]) ? trim((string)$_POST[$k]) : ''; }
    $db_pass = isset($_POST['db_pass']) ? (string)$_POST['db_pass'] : '';
    $a1p = isset($_POST['a1_pass']) ? (string)$_POST['a1_pass'] : '';
    $a2p = isset($_POST['a2_pass']) ? (string)$_POST['a2_pass'] : '';

    $bdSel = (isset($_POST['bd']) && is_array($_POST['bd'])) ? array_values(array_intersect(board_keys(), $_POST['bd'])) : [];
    if (!$bdSel) $errors[] = '사용할 게시판을 하나 이상 골라 주세요.';
    if ($v['db_name'] === '' || $v['db_user'] === '') $errors[] = 'DB 이름과 DB 아이디를 입력해 주세요.';
    if ($v['a1_user'] === '' || !preg_match('/^[A-Za-z0-9_.-]{3,30}$/', $v['a1_user'])) $errors[] = '관리자 1의 아이디는 영문/숫자 3~30자로 입력해 주세요.';
    if (strlen($a1p) < 8) $errors[] = '관리자 1의 비밀번호는 8자 이상이어야 해요.';
    if ($v['a2_user'] !== '') {
        if (!preg_match('/^[A-Za-z0-9_.-]{3,30}$/', $v['a2_user'])) $errors[] = '관리자 2의 아이디는 영문/숫자 3~30자로 입력해 주세요.';
        if ($v['a2_user'] === $v['a1_user']) $errors[] = '관리자 1과 2의 아이디가 같아요.';
        if (strlen($a2p) < 8) $errors[] = '관리자 2의 비밀번호는 8자 이상이어야 해요.';
    }

    $pdo = null;
    if (!$errors) {
        try {
            $pdo = new PDO('mysql:host=' . $v['db_host'] . ';dbname=' . $v['db_name'] . ';charset=utf8mb4', $v['db_user'], $db_pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (Exception $e) {
            $errors[] = 'DB에 연결하지 못했어요. 입력한 DB 정보를 다시 확인해 주세요. (' . $e->getMessage() . ')';
        }
    }

    if (!$errors && $pdo) {
        try {
            $pdo->exec('CREATE TABLE IF NOT EXISTS settings (k VARCHAR(100) NOT NULL, v MEDIUMTEXT NULL, PRIMARY KEY (k)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
            $pdo->exec('CREATE TABLE IF NOT EXISTS admins (id INT NOT NULL AUTO_INCREMENT, username VARCHAR(50) NOT NULL, pass_hash VARCHAR(255) NOT NULL, display_name VARCHAR(100) NOT NULL DEFAULT \'\', created_at DATETIME NULL, PRIMARY KEY (id), UNIQUE KEY uq_user (username)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
            $pdo->exec('CREATE TABLE IF NOT EXISTS attempts (id INT NOT NULL AUTO_INCREMENT, ip VARCHAR(45) NOT NULL, kind VARCHAR(20) NOT NULL, t INT NOT NULL, PRIMARY KEY (id), KEY idx_a (ip, kind, t)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
            foreach (entities() as $t => $e) { $pdo->exec(create_sql($t, $e)); }

            // 설정 파일이 없다는 것은 아직 설치가 끝나지 않았다는 뜻이므로, 이전에 실패한 시도의 흔적을 비우고 다시 시작합니다.
            $pdo->exec('DELETE FROM admins');
            $pdo->exec("DELETE FROM settings WHERE k='site_title'");

            $ins = $pdo->prepare('INSERT INTO admins (username, pass_hash, display_name, created_at) VALUES (?, ?, ?, ?)');
            $ins->execute([$v['a1_user'], password_hash($a1p, PASSWORD_DEFAULT), $v['a1_name'] !== '' ? $v['a1_name'] : $v['a1_user'], now()]);
            if ($v['a2_user'] !== '') {
                $ins->execute([$v['a2_user'], password_hash($a2p, PASSWORD_DEFAULT), $v['a2_name'] !== '' ? $v['a2_name'] : $v['a2_user'], now()]);
            }
            $pdo->exec("DELETE FROM settings WHERE k LIKE 'bd\\_%'");
            $insBd = $pdo->prepare('INSERT INTO settings (k, v) VALUES (?, ?)');
            foreach (board_keys() as $bk) { $insBd->execute(['bd_' . $bk, in_array($bk, $bdSel, true) ? '1' : '0']); }
            if ($v['site_title'] !== '') {
                $pdo->prepare('INSERT INTO settings (k, v) VALUES (?, ?)')->execute(['site_title', $v['site_title']]);
            }
        } catch (Exception $e) {
            $errors[] = '테이블을 만드는 중 문제가 생겼어요: ' . $e->getMessage() . '';
        }
    }

    if (!$errors) {
        foreach (['uploads', 'inc/sessions'] as $d) {
            if (!is_dir(ROOT . '/' . $d)) { @mkdir(ROOT . '/' . $d, 0755, true); }
        }
        $cfgArr = [
            'db_host' => $v['db_host'], 'db_name' => $v['db_name'], 'db_user' => $v['db_user'], 'db_pass' => $db_pass,
            'base' => detect_base(), 'debug' => false,
        ];
        $code = "<?php\n// 자동 생성된 설정 파일입니다. 함부로 공유하지 마세요.\nreturn " . var_export($cfgArr, true) . ";\n";
        if (@file_put_contents(ROOT . '/inc/config.php', $code) === false) {
            $errors[] = 'inc/config.php 파일을 만들지 못했어요. inc 폴더의 쓰기 권한을 확인해 주세요. 아래 내용을 직접 inc/config.php로 저장해도 돼요.';
            $manual = $code;
        } else {
            $done = true;
            $deleted = @unlink(__FILE__);
        }
    }
}
?><!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>설치 마법사</title>
<style>
  body{font-family:-apple-system,'Malgun Gothic',sans-serif;background:#12131a;color:#ecebf1;margin:0;line-height:1.7}
  main{max-width:640px;margin:0 auto;padding:40px 20px 80px}
  h1{font-size:26px;margin:0 0 6px}
  h2{font-size:17px;margin:32px 0 6px;padding-top:20px;border-top:1px solid #2a2b38}
  p.lead{color:#a5a4b3;margin:0 0 10px}
  label{display:block;margin:14px 0 4px;font-size:14px;color:#c9c8d6}
  input{width:100%;padding:10px 12px;border-radius:8px;border:1px solid #34354a;background:#1b1c27;color:#fff;font-size:15px;box-sizing:border-box}
  small{color:#8d8ca0;display:block;margin-top:4px}
  button{margin-top:28px;padding:13px 22px;border:0;border-radius:10px;background:#45d6c8;color:#0b1a18;font-size:16px;font-weight:700;cursor:pointer;width:100%}
  .err{background:#3a1c1f;border:1px solid #7a2b31;color:#ffc9cc;padding:12px 14px;border-radius:8px;margin:14px 0}
  .ok{background:#12302c;border:1px solid #1f6a60;padding:16px;border-radius:10px;margin:14px 0}
  a{color:#45d6c8}
  code,textarea{background:#1b1c27;padding:2px 6px;border-radius:4px}
  textarea{width:100%;height:160px;color:#fff;border:1px solid #34354a}
</style>
</head>
<body>
<main>
<h1>자캐 커플 홈 설치</h1>

<?php if ($installed): ?>
  <div class="ok"><b>이미 설치되어 있어요.</b><br>보안을 위해 FTP에서 <code>install.php</code> 파일을 삭제해 주세요.<br><br>
  <a href="<?= h(url('index.php')) ?>">사이트 보기</a></div>

<?php elseif ($done): ?>
  <div class="ok"><b>설치가 끝났어요! 🎉</b><br><br>
  <?php if ($deleted): ?>install.php는 자동으로 삭제되었어요.<?php else: ?><b style="color:#ffd38a">install.php 파일을 자동으로 지우지 못했어요. FTP에서 직접 삭제해 주세요!</b><?php endif; ?>
  <br><br>다음 순서로 진행하세요.<br>
  1) <a href="<?= h(url('home.php')) ?>">메인 홈</a>으로 가서 오른쪽 위 로그인 카드에서 로그인<br>
  2) "관리자 페이지"에 들어가 배경/이미지/메뉴를 설정</div>

<?php else: ?>
  <p class="lead">아래 정보를 입력하면 필요한 데이터베이스 테이블과 관리자 계정이 자동으로 만들어져요. 설치 직후 이 파일을 삭제할 거라, 다른 사람이 먼저 접속하지 않도록 업로드 후 바로 진행하세요.</p>

  <?php foreach ($errors as $e): ?><div class="err"><?= h($e) ?></div><?php endforeach; ?>
  <?php if (!empty($manual)): ?><textarea readonly><?= h($manual) ?></textarea><?php endif; ?>

  <form method="post" autocomplete="off">
    <h2>1. 데이터베이스 정보 (호스팅 업체에서 확인)</h2>
    <label>DB 서버 주소</label><input name="db_host" value="<?= h($v['db_host']) ?>" required>
    <small>닷홈 등 대부분은 <code>localhost</code> 그대로 두면 돼요.</small>
    <label>DB 이름</label><input name="db_name" value="<?= h($v['db_name']) ?>" required>
    <label>DB 아이디</label><input name="db_user" value="<?= h($v['db_user']) ?>" required>
    <label>DB 비밀번호</label><input type="password" name="db_pass" value="">

    <h2>2. 사이트 이름</h2>
    <label>사이트 이름 (나중에 바꿀 수 있어요)</label><input name="site_title" value="<?= h($v['site_title']) ?>" placeholder="예: 우리의 홈">

    <h2>3. 사용할 게시판</h2>
    <small>쓰지 않을 게시판은 체크를 빼세요. 나중에 관리자 화면의 "게시판 사용 설정"에서 언제든 바꿀 수 있어요.</small>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:6px 12px;margin:8px 0 16px">
    <?php foreach ($bdLabels as $bk => $bl): ?>
      <label style="display:flex;align-items:center;gap:6px;font-weight:400"><input type="checkbox" name="bd[]" value="<?= h($bk) ?>" <?= in_array($bk, $bdSel, true) ? 'checked' : '' ?> style="width:auto"> <?= h($bl) ?></label>
    <?php endforeach; ?>
    </div>

    <h2>4. 관리자 계정 (최대 2명)</h2>
    <label>관리자 1 - 아이디 (영문/숫자)</label><input name="a1_user" value="<?= h($v['a1_user']) ?>" required>
    <label>관리자 1 - 표시 이름</label><input name="a1_name" value="<?= h($v['a1_name']) ?>" placeholder="예: 한결">
    <label>관리자 1 - 비밀번호 (8자 이상)</label><input type="password" name="a1_pass" required>
    <label>관리자 2 - 아이디 (선택)</label><input name="a2_user" value="<?= h($v['a2_user']) ?>">
    <label>관리자 2 - 표시 이름</label><input name="a2_name" value="<?= h($v['a2_name']) ?>">
    <label>관리자 2 - 비밀번호 (8자 이상)</label><input type="password" name="a2_pass">
    <small>방문자는 계정이 없고, 이 관리자 계정으로만 글을 쓰고 관리할 수 있어요.</small>

    <button type="submit">설치하기</button>
  </form>
<?php endif; ?>
</main>
</body>
</html>
