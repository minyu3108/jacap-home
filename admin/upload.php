<?php
/** 본문 편집기의 이미지 삽입용 업로드 */
require __DIR__ . '/../inc/core.php';
if (!is_admin()) { json_out(['ok' => false, 'msg' => '로그인이 필요해요.'], 403); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_ok()) { json_out(['ok' => false, 'msg' => '보안 확인에 실패했어요. 새로고침 후 다시 해주세요.'], 403); }
if (!isset($_FILES['file'])) { json_out(['ok' => false, 'msg' => '파일이 없어요.']); }
$kind = (isset($_POST['kind']) && $_POST['kind'] === 'media') ? 'media' : 'image';   // 영상도 받는 칸에서만 media
list($path, $err) = save_upload($_FILES['file'], $kind);
if ($err || !$path) { json_out(['ok' => false, 'msg' => $err ? $err : '업로드에 실패했어요.']); }
json_out(['ok' => true, 'url' => asset($path)]);
