<?php
require __DIR__ . '/_boot.php';

/** DB 전체를 .sql 텍스트로 내보내기 (phpMyAdmin에서 그대로 가져오기 가능) */
function dump_sql($fh) {
    $pdo = db();
    $tables = array_merge(['settings', 'admins'], array_keys(entities()));
    fwrite($fh, "-- 자캐 커플 홈 백업 " . date('Y-m-d H:i:s') . "\n");
    fwrite($fh, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
    foreach ($tables as $t) {
        $c = $pdo->query('SHOW CREATE TABLE `' . $t . '`')->fetch(PDO::FETCH_NUM);
        if (!$c) continue;
        fwrite($fh, 'DROP TABLE IF EXISTS `' . $t . "`;\n" . $c[1] . ";\n\n");
        $st = $pdo->query('SELECT * FROM `' . $t . '`');
        while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
            $vals = [];
            foreach ($r as $v) { $vals[] = ($v === null) ? 'NULL' : $pdo->quote((string)$v); }
            fwrite($fh, 'INSERT INTO `' . $t . '` (`' . implode('`,`', array_keys($r)) . '`) VALUES (' . implode(',', $vals) . ");\n");
        }
        fwrite($fh, "\n");
    }
    fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
}

function send_file_and_delete($path, $name, $mime) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: no-store');
    readfile($path);
    @unlink($path);
    exit;
}

$zipOk = class_exists('ZipArchive');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    @set_time_limit(300);
    $do = post('do');
    $stamp = date('Ymd_His');

    if ($do === 'sql') {
        $tmp = tempnam(sys_get_temp_dir(), 'jhb');
        $fh = fopen($tmp, 'w');
        dump_sql($fh);
        fclose($fh);
        set_setting('last_backup', now());
        send_file_and_delete($tmp, 'backup_db_' . $stamp . '.sql', 'application/sql');
    }

    if (($do === 'zip_all' || $do === 'zip_uploads') && $zipOk) {
        $tmp = tempnam(sys_get_temp_dir(), 'jhb');
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            flash('압축 파일을 만들지 못했어요. 임시 폴더 권한을 확인해 주세요.', 'err');
            redirect(url('admin/backup.php'));
        }
        $sqlTmp = null;
        if ($do === 'zip_all') {
            $sqlTmp = tempnam(sys_get_temp_dir(), 'jhs');
            $fh = fopen($sqlTmp, 'w');
            dump_sql($fh);
            fclose($fh);
            $zip->addFile($sqlTmp, 'database.sql');
        }
        $root = ROOT . '/uploads';
        if (is_dir($root)) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if (!$f->isFile()) continue;
                $rel = 'uploads/' . str_replace('\\', '/', substr($f->getPathname(), strlen($root) + 1));
                $zip->addFile($f->getPathname(), $rel);
            }
        }
        $zip->close();
        if ($sqlTmp) { $GLOBALS['jh_cleanup'] = $sqlTmp; }
        set_setting('last_backup', now());
        register_shutdown_function(function () { if (!empty($GLOBALS['jh_cleanup'])) @unlink($GLOBALS['jh_cleanup']); });
        send_file_and_delete($tmp, ($do === 'zip_all' ? 'backup_full_' : 'backup_uploads_') . $stamp . '.zip', 'application/zip');
    }
    flash('알 수 없는 요청이에요.', 'err');
    redirect(url('admin/backup.php'));
}

$last = S('last_backup', '');
$fileCount = 0; $bytes = 0;
if (is_dir(ROOT . '/uploads')) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(ROOT . '/uploads', FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) { if ($f->isFile() && $f->getFilename() !== '.htaccess' && $f->getFilename() !== 'index.html') { $fileCount++; $bytes += $f->getSize(); } }
}
admin_head('백업', 'backup');
?>
<div class="card">
  <h2>백업 받기</h2>
  <p class="help">마지막 백업: <b><?= $last !== '' ? h($last) : '아직 없어요' ?></b> · 올린 파일 <?= $fileCount ?>개 (<?= h(number_format($bytes / 1048576, 1)) ?>MB)</p>
  <p class="help">사이트의 전부는 <b>글·설정(데이터베이스)</b>과 <b>올린 이미지·음악(uploads)</b> 두 가지예요. 한 달에 한 번, 또는 큰 작업을 하기 전에 받아 두세요.</p>
  <div class="bk-btns">
    <?php if ($zipOk): ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="zip_all"><button class="btn" type="submit">전체 백업 받기 (글 + 이미지, .zip)</button></form>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="zip_uploads"><button class="btn ghost" type="submit">이미지/음악만 (.zip)</button></form>
    <?php else: ?>
    <p class="help">이 서버는 압축(zip) 기능을 지원하지 않아요. 이미지는 FTP로 uploads 폴더를 내려받아 주세요.</p>
    <?php endif; ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="sql"><button class="btn ghost" type="submit">글/설정만 (.sql)</button></form>
  </div>
  <p class="help">파일이 많으면 만드는 데 시간이 걸려요. 화면이 멈춘 것처럼 보여도 기다려 주세요.</p>
</div>

<div class="card">
  <h2>주의</h2>
  <p class="help">백업 파일에는 <b>관리자 비밀번호(암호화된 형태)와 비밀글 정보</b>가 들어 있어요. 다른 사람에게 보내거나 공개된 곳에 올리지 마세요.</p>
</div>

<div class="card">
  <h2>복원하려면</h2>
  <p class="help">
    1) 새로 설치했다면 <b>글/설정</b>: 호스팅의 DB 관리(phpMyAdmin) → 내 DB 선택 → <b>가져오기</b>에서 백업의 <code>.sql</code> 파일을 올려요. (같은 이름의 테이블은 덮어써져요.)<br>
    2) <b>이미지</b>: 전체 백업 zip을 풀면 <code>uploads</code> 폴더가 나와요. FTP로 사이트의 <code>uploads</code> 폴더에 그대로 올려요.
  </p>
</div>
<?php admin_foot();
