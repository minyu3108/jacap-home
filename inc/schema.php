<?php
/**
 * 데이터 구조 정의
 * - entities(): 게시판/목록(프로필, 갤러리, 로그 ...) 테이블 + 관리자 입력폼 정의
 * - setting_groups(): 관리자 "사이트 설정" 화면 정의
 *
 * 필드 타입: text, textarea, html, image, audio, select, int, check, color
 * 나중에 항목을 추가하고 싶으면 여기에 한 줄만 추가하면 됩니다 (관리자 → 대시보드 → "구조 업데이트" 실행).
 */

function F($n, $t, $l, $x = []) { return array_merge(['n' => $n, 't' => $t, 'l' => $l], $x); }

function font_base_opts() {
    return [
        'pretendard' => 'Pretendard (기본 고딕)',
        'gowun_dodum' => '고운돋움',
        'noto_serif' => '본명조 굵게 (Noto Serif KR)',
        'gowun_batang' => '고운바탕 (명조)',
        'nanum_myeongjo' => '나눔명조',
        'nanum_gothic' => '나눔고딕',
        'sans' => '시스템 기본 서체',
        'mono' => '고정폭 (코드/로그 느낌)',
    ];
}
/** 관리자가 올린 내 폰트 (최대 3개). settings_all()만 사용해서 순환 호출을 피함 */
function custom_fonts() {
    static $c = null;
    if ($c !== null) return $c;
    $c = [];
    if (!is_array($GLOBALS['JH_CFG'])) return $c;
    try {
        $a = settings_all();
        for ($i = 1; $i <= 3; $i++) {
            $f = isset($a['cf' . $i . '_file']) ? (string)$a['cf' . $i . '_file'] : '';
            if ($f === '') continue;
            $n = isset($a['cf' . $i . '_name']) ? trim(preg_replace('/[^\p{L}\p{N} _-]/u', '', (string)$a['cf' . $i . '_name'])) : '';
            $c['cf' . $i] = ['name' => $n !== '' ? $n : '내 폰트 ' . $i, 'file' => $f, 'family' => 'JHFont' . $i];
        }
    } catch (Exception $e) { /* 무시 */ }
    // 개수 제한 없이 추가하는 "내 폰트" (콘텐츠 → 내 폰트)
    try {
        foreach (rows('SELECT id, name, file FROM fonts ORDER BY id ASC') as $r) {
            if ($r['file'] === '') continue;
            $n = trim(preg_replace('/[^\p{L}\p{N} _-]/u', '', (string)$r['name']));
            $c['fd' . $r['id']] = ['name' => $n !== '' ? $n : '내 폰트 ' . $r['id'], 'file' => $r['file'], 'family' => 'JHFontD' . $r['id']];
        }
    } catch (Exception $e) { /* 표가 아직 없으면 무시 */ }
    return $c;
}
function font_opts() {
    $o = font_base_opts();
    foreach (custom_fonts() as $k => $f) { $o[$k] = $f['name'] . ' (내가 올린 폰트)'; }
    return $o;
}
function font_stack($k) {
    $m = [
        'pretendard' => "'Pretendard',-apple-system,'Malgun Gothic',sans-serif",
        'gowun_dodum' => "'Gowun Dodum','Pretendard',sans-serif",
        'noto_serif' => "'Noto Serif KR','Nanum Myeongjo',serif",
        'gowun_batang' => "'Gowun Batang','Nanum Myeongjo',serif",
        'nanum_myeongjo' => "'Nanum Myeongjo',serif",
        'nanum_gothic' => "'Nanum Gothic',sans-serif",
        'sans' => "-apple-system,'Malgun Gothic',sans-serif",
        'mono' => "ui-monospace,Consolas,'D2Coding',monospace",
    ];
    $cf = custom_fonts();
    if (isset($cf[$k])) return "'" . $cf[$k]['family'] . "','Pretendard',sans-serif";
    return isset($m[$k]) ? $m[$k] : $m['pretendard'];
}
/** 본문 편집기의 글꼴 목록 [CSS font-family 값 => 이름] */
function editor_fonts() {
    $o = [];
    foreach (font_opts() as $k => $label) { $o[font_stack($k)] = preg_replace('/ \(.*\)$/u', '', $label); }
    return $o;
}

function entities() {
    static $E = null;
    if ($E !== null) return $E;
    $E = [
        'profiles' => [
            'label' => '프로필', 'title' => 'name', 'protect' => false,
            'order' => 'side ASC, sort_order ASC, id ASC',
            'sub' => 'side',
            'fields' => [
                F('side', 'select', '어느 캐릭터의 프로필인가요?', ['opts' => '@chars', 'h' => '캐릭터별로 카드는 최대 4장까지 나타나요.']),
                F('name', 'text', '프로필 이름', ['h' => '카드와 프로필 페이지 제목에 표시돼요. 예: 기본 / 현대AU / 유년기']),
                F('disp_name', 'text', '이름 (전문 상단에 크게 표시)', ['h' => '비워두면 위의 "프로필 이름"을 그대로 크게 보여줘요. 보통은 캐릭터의 실제 이름을 적어요.']),
                F('disp_font', 'select', '이름 글꼴', ['opts' => ['' => '기본 (제목용 글꼴)'] + font_opts()]),
                F('disp_size', 'int', '이름 글자 크기 (px, 선택)', ['h' => '프로필 상세 화면 뒤쪽에 크게 깔리는 이름의 크기예요. 비워두면 화면 크기에 맞춰 자동(최대 210px)으로 정해져요. 이름이 길어서 화면을 넘치면 줄여 주세요.']),
                F('disp_color', 'color', '이름 글자색'),
                F('disp_hl', 'color', '이름 뒤 하이라이트 색'),
                F('subtitle', 'text', '한 줄 소개'),
                F('sub_font', 'select', '한 줄 소개 글꼴', ['opts' => ['' => '기본 (사이트 글꼴)'] + font_opts()]),
                F('sub_color', 'color', '한 줄 소개 글자색'),
                F('sub_hl', 'color', '한 줄 소개 뒤 하이라이트 색'),
                F('intro', 'textarea', '한 줄 소개 아래 짧은 문구 (선택)', ['h' => '한 줄 소개 밑에 작은 글씨로 나오는 두세 줄짜리 소개예요. 줄바꿈도 그대로 보여요.']),
                F('head_img', 'image', '카드에 들어갈 두상 이미지', ['h' => '정사각형에 가까운 얼굴 이미지를 추천해요.']),
                F('card_bg', 'image', '카드 배경 이미지'),
                F('card_color', 'color', '카드 배경색 (배경 이미지가 없을 때)'),
                F('full_img', 'image', '전신/반신 이미지', ['h' => '투명 PNG, GIF 모두 가능해요.']),
                F('page_bg', 'image', '프로필 페이지 배경 이미지'),
                F('page_color', 'color', '프로필 페이지 배경색'),
                F('page_color2', 'color', '프로필 페이지 배경 그라데이션 (두 번째 색)'),
                F('accent_color', 'color', '이 프로필만의 강조색 (선택)', ['h' => '버튼·탭·링크 등에 쓰이는 사이트 강조색을 이 프로필에서만 다른 색으로 바꿔요. 체크하지 않으면 사이트 기본 강조색을 그대로 써요.']),
                F('accent_ink_color', 'color', '강조색 위 글자색 (선택)', ['h' => '위 강조색이 배경으로 깔릴 때 그 위에 놓이는 글자색이에요.']),
                F('text_color', 'color', '글자색 (정보·문구·버튼·상세 내용, 선택)', ['h' => '이름과 한 줄 소개를 뺀 나머지 글씨와 선, 버튼 테두리 색이에요. 밝은 배경을 쓴다면 어두운 색으로 바꿔 주세요. 체크하지 않으면 사이트 기본 글자색을 써요.']),
                F('ov_color', 'color', '상세 프로필 오버레이 색 (선택)', ['h' => '"상세 프로필" 탭을 누르면 화면 오른쪽 반을 덮는 흐릿한 막의 색이에요. 화면 아래쪽에 살짝 깔리는 그림자도 이 색을 따라가요. 체크하지 않으면 어두운 남색이에요. 밝은 배경이라면 흰색 계열을 추천해요.']),
                F('ov_opacity', 'int', '오버레이 진하기 (1~100)', ['h' => '0이거나 비워두면 50이에요. 배경 이미지가 화려해서 글씨가 잘 안 읽히면 올려 주세요.']),
                F('ov_blur', 'int', '오버레이 흐림 정도 (1~40)', ['h' => '0이거나 비워두면 22예요. 1로 하면 뒤가 거의 흐려지지 않고 색만 깔려요.']),
                F('bg_ghost', 'check', '고스트 이미지 켜기 (전신 이미지를 뒤에 크게 흐리게)', ['d' => '0', 'h' => '전신 이미지를 크게 키워서 흐릿하게 전신 뒤에 깔아요. 끄면 전신 이미지만 보여요.']),
                F('bg_ghost_op', 'int', '고스트 이미지 진하기 (5~60)', ['d' => '30']),
                F('theme_title', 'text', '테마곡 제목'),
                F('theme_file', 'audio', '테마곡 파일 (mp3 등)', ['h' => '파일을 올리거나, 아래 주소 칸에 mp3 직접 주소를 넣을 수 있어요.']),
            F('theme_auto', 'check', '프로필 열면 자동 재생'),
                F('info', 'textarea', '기본 정보', ['h' => '한 줄에 하나씩 "항목: 내용" 형식으로 적어주세요. 예)  나이: 24세']),
                F('body', 'html', '상세 내용'),
                F('sort_order', 'int', '정렬 순서 (작을수록 먼저)'),
            ],
        ],
        'gallery' => [
            'label' => '갤러리', 'title' => 'title', 'protect' => true,
            'order' => 'created_at DESC, id DESC',
            'sub' => 'commissioner',
            'fields' => [
                F('title', 'text', '제목'),
                F('image', 'image', '이미지 (대표)', ['h' => '한 장짜리 글은 여기만 채우면 돼요. 여러 장이면 이 이미지가 목록 카드의 대표 이미지예요.']),
                F('more_images', 'images', '추가 이미지·영상 (여러 개 묶기)', ['media' => true, 'h' => '비슷한 그림을 한 글에 묶거나, 짧은 영상(움짤, mp4·webm, 50MB 이하)을 올리고 싶을 때 여기에 올려요(파일 여러 개 선택 가능, 끌어서 순서 변경). 영상만 올릴 땐 위의 대표 이미지를 비워도 되고, 그러면 영상의 첫 장면이 카드에 나와요(기기에 따라 검게 보일 수 있으니 대표 이미지를 따로 올리는 걸 권해요). 카드에는 개수가 표시되고, 크게 볼 때는 ← → 로 넘겨요. 서버의 업로드 용량 제한이 더 작으면 그 크기까지만 올라가요.']),
                F('commissioner', 'text', '커미션주 / 작가', ['h' => '예: @artist_name']),
                F('card_color', 'color', '카드 배경색 (선택)', ['h' => '체크하지 않으면 "사이트 설정 → 카드 디자인"의 기본 색을 써요.']),
                F('tags', 'text', '태그', ['h' => '쉼표로 구분해요. 예: 일러스트, 커미션, 겨울']),
                F('blur', 'check', '이미지 흐리게 가리기', ['d' => '0', 'h' => '살짝 조심스러운 그림일 때 켜 보세요. 목록 카드와 크게 볼 때 모두 흐리게 나오고, 누르면 그 자리에서 바로 풀려요.']),
                F('body', 'textarea', '설명'),
            ],
        ],
        'logs' => [
            'label' => '로그', 'title' => 'title', 'protect' => true,
            'order' => 'created_at DESC, id DESC',
            'sub' => 'category',
            'fields' => [
                F('category', 'text', '분류', ['h' => '예: 만화 / 소설 / 잡담. 같은 분류끼리 묶어서 볼 수 있어요.']),
                F('title', 'text', '제목'),
                F('thumb', 'image', '카드 대표 이미지', ['h' => '비워두면 이미지 없이 그라데이션만 보여요. 아래 "여러 장 이미지"의 첫 번째 사진을 대표 이미지로 자동 지정하고 싶다면, 그 사진을 여기에도 같이 올려 주세요.']),
                F('card_color', 'color', '목록 카드 배경색 (선택)', ['h' => '게시판 목록에서 보이는 미리보기 카드의 색이에요.']),
                F('card_text', 'color', '목록 카드 글자색 (선택)', ['h' => '카드 배경을 밝은 색으로 바꿔서 제목·소개 글씨가 잘 안 보일 때 사용하세요.']),
                F('title_color', 'color', '본문 제목 글자색 (선택)', ['h' => '글을 열었을 때 위쪽에 크게 나오는 제목의 글자색이에요. 본문 카드 배경을 밝게 바꿨을 때 함께 써 보세요.']),
                F('panel_color', 'color', '본문 카드 배경색 (선택)', ['h' => '글을 열었을 때 본문을 감싸는 큰 카드의 색이에요. 체크하지 않으면 "사이트 설정 → 카드 디자인"의 기본 색을 써요.']),
                F('panel_opacity', 'int', '본문 카드 진하기 (10~100)', ['h' => '비워두면 기본 진하기를 따라요.']),
                F('summary', 'textarea', '카드에 보일 짧은 소개'),
                F('images', 'images', '이미지 여러 장 (순서 변경 가능)', ['h' => '파일을 여러 개 한 번에 선택해서 올리거나, 이미지 주소를 하나씩 추가할 수 있어요. 목록에서 위/아래 화살표로 순서를 바꾸고, ×로 뺄 수 있어요. 글 안에서 클릭하면 크게 볼 수 있고, 여러 장이면 그 상태에서 옆으로 넘겨볼 수 있어요.']),
                F('bgm_title', 'text', '이 로그 음악 제목 (선택)'),
                F('bgm_file', 'audio', '이 로그 음악 (mp3 또는 유튜브 링크, 선택)', ['h' => '파일을 올리거나, mp3 직접 주소나 유튜브 링크를 붙여넣을 수 있어요. 비워두면 사이트 기본 배경음악이 계속 나와요.']),
                F('bgm_auto', 'check', '이 로그 열면 자동 재생'),
                F('body', 'html', '본문'),
            ],
        ],
        'running' => [
            'label' => '러닝', 'title' => 'title', 'protect' => true,
            'order' => 'created_at DESC, id DESC',
            'sub' => 'character',
            'fields' => [
                F('character', 'select', '어느 캐릭터의 러닝인가요', ['opts' => ['1' => S('char1_name', '캐릭터 A'), '2' => S('char2_name', '캐릭터 B')], 'd' => '1', 'h' => '진입 화면에서 어느 카드(캐릭터)로 들어가야 이 글이 보이는지 정해요.']),
                F('title', 'text', '제목'),
                F('thumb', 'image', '카드 대표 이미지', ['h' => '비워두면 이미지 없이 그라데이션만 보여요.']),
                F('card_color', 'color', '목록 카드 배경색 (선택)'),
                F('card_text', 'color', '목록 카드 글자색 (선택)'),
                F('title_color', 'color', '본문 제목 글자색 (선택)'),
                F('panel_color', 'color', '본문 카드 배경색 (선택)'),
                F('panel_opacity', 'int', '본문 카드 진하기 (10~100)'),
                F('summary', 'textarea', '카드에 보일 짧은 소개'),
                F('images', 'images', '이미지 여러 장 (순서 변경 가능)'),
                F('bgm_title', 'text', '이 러닝 음악 제목 (선택)'),
                F('bgm_file', 'audio', '이 러닝 음악 (mp3 또는 유튜브 링크, 선택)', ['h' => '파일을 올리거나, mp3 직접 주소나 유튜브 링크를 붙여넣을 수 있어요. 비워두면 사이트 기본 배경음악이 계속 나와요.']),
                F('bgm_auto', 'check', '이 러닝 열면 자동 재생'),
                F('body', 'html', '본문'),
            ],
        ],
        'trpg' => [
            'label' => 'TRPG', 'title' => 'title', 'protect' => true,
            'order' => 'created_at DESC, id DESC',
            'sub' => 'trpg_system',
            'fields' => [
                F('title', 'text', '세션 제목'),
                F('trpg_system', 'text', '룰 / 시스템', ['h' => '예: CoC 7판, 신화 TRPG, 인세인']),
                F('thumb', 'image', '세션 카드 이미지', ['h' => '목록 카드와 글 안쪽 위에 함께 보여요. 비워두면 그라데이션만 보여요.']),
                F('card_color', 'color', '카드 배경색 (선택)'),
                F('card_text', 'color', '카드 글자색 (선택)', ['h' => '카드 배경을 밝은 색으로 바꿔서 제목·소개 글씨가 잘 안 보일 때 사용하세요.']),
                F('title_color', 'color', '본문 제목 글자색 (선택)', ['h' => '글을 열었을 때 위쪽에 크게 나오는 제목의 글자색이에요.']),
                F('players', 'text', 'PC 정보', ['h' => '카드에서 제목 다음에 보여요. 예: PC 로모스 누아 / KPC 에르네스토스']),
                F('play_start', 'date', '플레이 시작일'),
                F('play_end', 'date', '플레이 종료일', ['h' => '하루짜리 세션이면 비워두거나 시작일과 같게 해 주세요.']),
                F('room_url', 'text', '롤20 방 링크', ['h' => '붙여넣으면 글 안쪽에 "세션방 열기" 버튼이 생겨요.']),
                F('summary', 'textarea', '카드에 보일 짧은 소개'),
                F('bgm_title', 'text', '이 세션 음악 제목 (선택)'),
                F('bgm_file', 'audio', '이 세션 음악 (mp3 또는 유튜브 링크, 선택)', ['h' => '파일을 올리거나, mp3 직접 주소나 유튜브 링크를 붙여넣을 수 있어요. 비워두면 사이트 기본 배경음악이 계속 나와요.']),
                F('bgm_auto', 'check', '이 세션 열면 자동 재생'),
                F('t_convert', 'check', '롤20 로그 자동 정리 (줄바꿈 유지)', ['def' => '1', 'h' => '저장할 때 붙여넣은 로그의 줄바꿈을 화면에서도 그대로 보이게 정리하고, "This message was hidden" 같은 숨김 메시지 표시 줄도 자동으로 지워요. 깨진 이미지는 본문 편집기의 "이미지 관리"에서 바꿀 수 있어요.']),
                F('t_skin', 'select', '롤20 채팅 모양 (기본 스킨)', ['opts' => ['' => '자동 (롤20 로그로 보이면 적용)', 'on' => '항상 적용', 'off' => '적용 안 함'], 'h' => '롤20 채팅창에서 복사한 로그는 디자인 코드가 함께 오지 않아서, 사이트가 롤20 채팅과 비슷한 모양(아바타, 굵은 이름, 구분선, 판정 표)을 대신 입혀 줘요.']),
                F('t_skin_line', 'color', '로그 구분선 색', ['h' => '롤20 채팅 모양의 줄 사이 선, 로그 상자 테두리, 본문 안의 구분선(hr)에 적용돼요. 체크하지 않으면 롤20 채팅 모양은 검은색 선이 나와요.']),
                F('t_skin_row', 'color', '스킨: 줄 배경색', ['h' => '체크하지 않으면 흰색이에요.']),
                F('t_skin_op', 'text', '스킨: 줄 배경 진하기 (0~100)', ['h' => '비워두면 55예요. 0이면 완전히 투명, 100이면 불투명이에요.']),
                F('t_font', 'select', '로그 글꼴', ['opts' => ['' => '붙여넣은 로그의 원래 글꼴 따름'] + font_opts()]),
                F('t_size', 'int', '글자 크기 (px, 0이면 기본)'),
                F('t_color', 'color', '글자색'),
                F('t_bg', 'color', '로그 배경색'),
                F('t_width', 'int', '로그 가로폭 (px, 0이면 기본)'),
                F('raw_html', 'code', '★ 롤20 채팅 로그 붙여넣기 (원본 모양 그대로 보기)', ['raw' => true, 'h' => '롤20 방에서 "이 게임의 모든 채팅 내용 보기(Show all chat in this game)"를 누르고, 새로 뜬 화면에서 "Show on one page"를 눌러요. 그 화면에서 F12를 눌러 개발자 도구를 연 뒤 "textchatcontainer" 요소를 찾아 선택하고, 그 부분만 복사해서 여기에 붙여넣으세요. 이 칸에 내용이 있으면 아래 본문 대신 롤20에서 보이던 모양 그대로(글꼴·색·줄바꿈·이미지 배치 포함) 표시돼요. 스크립트 등 위험한 코드는 저장할 때 자동으로 제거돼요. 이 칸을 비우면 아래 본문 편집기를 써요.']),
                F('body', 'html', '로그 본문 (직접 쓰거나 서식 있는 내용 붙여넣기)', ['h' => '팁: 편집기 오른쪽 위의 "HTML" 버튼을 누른 뒤 코드를 붙여넣으면 서식이 가장 잘 유지돼요. <style> 안의 디자인(CSS)도 자동으로 아래 칸에 보관돼요.']),
                F('body_css', 'code', '로그 스타일 (CSS)', ['h' => '롤20 로그의 디자인 코드예요. 본문에 붙여넣은 <style> 내용은 저장할 때 여기로 자동 정리돼서 합쳐지고, 직접 붙여넣어도 돼요. 이 글 안쪽에만 적용돼요.']),
            ],
        ],
        'aus' => [
            'label' => 'AU', 'title' => 'title', 'protect' => true,
            'order' => 'sort_order ASC, id ASC',
            'sub' => 'summary',
            'edit_groups' => ['char1' => '캐릭터 A 정보 편집', 'char2' => '캐릭터 B 정보 편집'],
            'fields' => [
                F('title', 'text', 'AU 이름'),
                F('title_img', 'image', '제목 대신 보여줄 로고 이미지 (선택)', ['h' => '넣으면 목록 카드에 마우스를 올릴 때 나오는 제목이 이 이미지로 바뀌어요. (AU 상세 페이지에서는 위쪽에 글자 제목이 그대로 나와요.) 투명 PNG를 추천해요. (AU 이름은 관리용으로 그대로 적어 주세요.)']),
                F('title_img_w', 'int', '카드 호버 로고 이미지 가로 크기 (px, 0이면 자동)'),
                F('summary', 'text', '한 줄 설명'),
                F('title_color', 'color', '본문 제목 글자색 (선택)', ['h' => 'AU 상세 페이지 위쪽에 크게 나오는 제목의 글자색이에요.']),
                F('accent_color', 'color', '이 AU만의 강조색 (선택)', ['h' => '버튼·탭·링크 등에 쓰이는 사이트 강조색을 이 AU에서만 다른 색으로 바꿔요. 체크하지 않으면 사이트 기본 강조색을 그대로 써요.']),
                F('accent_ink_color', 'color', '강조색 위 글자색 (선택)', ['h' => '위 강조색이 배경으로 깔릴 때(선택된 탭 등) 그 위에 놓이는 글자색이에요. 강조색을 밝게 하면 어두운 색이, 어둡게 하면 밝은 색이 어울려요.']),
                F('card_bg', 'image', '카드 배경 이미지'),
                F('card_color', 'color', '카드 배경색 (배경 이미지가 없을 때)'),
                F('info_image', 'image', '정보 탭 대표 이미지 (선택)', ['h' => 'AU 상세 페이지의 "정보" 탭 왼쪽에 크게 나오는 세로 이미지예요. 비워두면 이미지 없이 글만 나와요.']),
                F('au_facts', 'textarea', '정보 탭 태그', ['h' => '한 줄에 하나씩 "항목: 내용" 형식으로 적어주세요. 예)  배경: 작은 카페 / 관계: 사장과 손님 / 분위기: 잔잔한 일상 (각각 새 줄에 적어주세요)']),
                F('tag_bg_color', 'color', '정보 탭 태그 배경색 (선택)', ['h' => '체크하지 않으면 은은한 기본 배경을 써요.']),
                F('tag_bg_opacity', 'int', '정보 탭 태그 배경 진하기 (10~100)', ['h' => '위의 배경색을 지정했을 때만 적용돼요. 숫자가 작을수록 뒤가 비쳐 보여요.']),
                F('tag_text_color', 'color', '정보 탭 태그 글자색 (선택)', ['h' => '태그 안의 "내용" 글자색이에요(항목 이름 색은 위의 제목 글자색을 따라가요).']),
                F('story', 'html', 'AU 서사 요약', ['h' => '"정보" 탭 아래쪽에 나오는 이 AU의 이야기 요약이에요.']),
                F('combo_img', 'image', '캐릭터 합성 이미지 (선택)', ['h' => '두 캐릭터가 함께 있는 이미지 한 장을 올리면, "캐릭터" 탭에서 캐릭터를 각각 보여주는 대신 이 이미지 하나를 화면 중앙에 보여줘요. 화면의 왼쪽/오른쪽을 누르면 그쪽으로 이미지가 옮겨가면서 반대쪽에 정보 카드가 떠요. 비워두면 지금처럼 캐릭터 A·B 이미지를 각각 보여줘요.']),
                F('sd1', 'image', '카드용 SD 이미지 (캐릭터 A)'),
                F('sd2', 'image', '카드용 SD 이미지 (캐릭터 B)'),
                F('card_name_font', 'select', '카드 이름 글꼴 (마우스를 올렸을 때 나와요)', ['opts' => ['' => '기본 (제목용 글꼴)'] + font_opts(), 'h' => 'AU 목록 카드에 마우스를 올리면 나오는 AU 이름이에요. 내가 올린 폰트는 "꾸미기 → 글꼴 → 내 폰트"에서 먼저 올려 주세요.']),
                F('card_name_color', 'color', '카드 이름 글자색', ['h' => '체크하지 않으면 흰색이에요.']),
                F('card_name_size', 'int', '카드 이름 글자 크기 (px)', ['h' => '0이면 기본 크기예요.']),
                F('stage_font', 'select', '상세 페이지 캐릭터 이름표 글꼴', ['opts' => ['' => '기본 (제목용 글꼴)'] + font_opts(), 'h' => 'AU를 열었을 때 캐릭터 그림 아래에 나오는 작은 이름표예요. (정보 카드 속 큰 이름은 각 캐릭터 편집에서 따로 정해요.)']),
                F('stage_color', 'color', '상세 페이지 캐릭터 이름표 글자색'),
                F('stage_size', 'int', '상세 페이지 캐릭터 이름표 글자 크기 (px)', ['h' => '0이면 기본 크기예요.']),
                F('page_bg', 'image', 'AU 페이지 배경 이미지'),
                F('page_color', 'color', 'AU 페이지 배경색'),
                F('page_color2', 'color', 'AU 페이지 배경 그라데이션 (두 번째 색)'),
                F('img1', 'image', '캐릭터 A 전신/반신 이미지', ['h' => '비워두면 카드용 SD 이미지를 대신 보여줘요.', 'group' => 'char1']),
                F('name1', 'text', '이름 (정보 카드 맨 위에 크게)', ['h' => '비워두면 "사이트 설정 → 기본 설정"의 캐릭터 A 이름을 써요.', 'group' => 'char1']),
                F('name_font1', 'select', '이름 글꼴', ['opts' => ['' => '기본 (제목용 글꼴)'] + font_opts(), 'group' => 'char1']),
                F('name_color1', 'color', '이름 글자색', ['group' => 'char1']),
                F('name_hl1', 'color', '이름 뒤 하이라이트 색', ['group' => 'char1']),
                F('subtitle1', 'text', '한 줄 소개', ['group' => 'char1']),
                F('sub_font1', 'select', '한 줄 소개 글꼴', ['opts' => ['' => '기본 (사이트 기본 글꼴)'] + font_opts(), 'group' => 'char1']),
                F('sub_color1', 'color', '한 줄 소개 글자색', ['group' => 'char1']),
                F('sub_hl1', 'color', '한 줄 소개 뒤 하이라이트 색', ['group' => 'char1']),
                F('facts1', 'textarea', '기본 정보', ['h' => '한 줄에 하나씩 "항목: 내용" 형식으로 적어주세요. 예)  나이: 24세', 'group' => 'char1']),
                F('info1', 'html', '상세 내용', ['group' => 'char1']),
                F('panel_color1', 'color', '정보 카드 배경색', ['h' => '이 캐릭터를 클릭하면 뜨는 정보 카드의 배경색이에요. 체크하지 않으면 "사이트 설정 → 카드 디자인"의 기본 색을 써요.', 'group' => 'char1']),
                F('panel_opacity1', 'int', '정보 카드 진하기 (10~100)', ['h' => '비워두면 기본 진하기를 따라요.', 'group' => 'char1']),
                F('img2', 'image', '캐릭터 B 전신/반신 이미지', ['group' => 'char2']),
                F('name2', 'text', '이름 (정보 카드 맨 위에 크게)', ['h' => '비워두면 "사이트 설정 → 기본 설정"의 캐릭터 B 이름을 써요.', 'group' => 'char2']),
                F('name_font2', 'select', '이름 글꼴', ['opts' => ['' => '기본 (제목용 글꼴)'] + font_opts(), 'group' => 'char2']),
                F('name_color2', 'color', '이름 글자색', ['group' => 'char2']),
                F('name_hl2', 'color', '이름 뒤 하이라이트 색', ['group' => 'char2']),
                F('subtitle2', 'text', '한 줄 소개', ['group' => 'char2']),
                F('sub_font2', 'select', '한 줄 소개 글꼴', ['opts' => ['' => '기본 (사이트 기본 글꼴)'] + font_opts(), 'group' => 'char2']),
                F('sub_color2', 'color', '한 줄 소개 글자색', ['group' => 'char2']),
                F('sub_hl2', 'color', '한 줄 소개 뒤 하이라이트 색', ['group' => 'char2']),
                F('facts2', 'textarea', '기본 정보', ['h' => '한 줄에 하나씩 "항목: 내용" 형식으로 적어주세요.', 'group' => 'char2']),
                F('info2', 'html', '상세 내용', ['group' => 'char2']),
                F('panel_color2', 'color', '정보 카드 배경색', ['group' => 'char2']),
                F('panel_opacity2', 'int', '정보 카드 진하기 (10~100)', ['group' => 'char2']),
                F('bgm_title', 'text', 'AU 배경음악 제목'),
                F('bgm_file', 'audio', 'AU 배경음악 (mp3 등)', ['h' => '이 AU 상세 페이지에서만 재생돼요. 재생하는 동안 기본 뮤직 플레이어는 잠시 멈췄다가, 이 페이지를 나가면 이어서 재생돼요.']),
                F('bgm_auto', 'check', '상세 페이지에 들어가면 자동 재생'),
                F('sort_order', 'int', '정렬 순서 (작을수록 먼저)'),
            ],
        ],
        'stickers' => [
            'label' => '홈 스티커', 'title' => 'title', 'protect' => false,
            'order' => 'z ASC, id ASC',
            'sub' => 'image',
            'fields' => [
                F('title', 'text', '스티커 이름 (관리용)'),
                F('image', 'image', '스티커 이미지 (PNG, JPG, GIF, WebP)', ['h' => '투명 PNG를 쓰면 스티커처럼 깔끔하게 붙어요.']),
                F('card_style', 'check', '둥근 카드 모양으로 보이기', ['h' => 'JPG처럼 배경이 투명하지 않은 이미지를 올릴 때 체크하면, 네모난 이미지 그대로가 아니라 모서리가 둥근 카드처럼 보여요.']),
                F('w', 'int', '가로 크기 (px)', ['h' => '0이면 150px로 보여요.']),
                F('rot', 'int', '기울기 (도, -180 ~ 180)', ['h' => '예: 이미지를 살짝 비스듬히 붙이고 싶으면 -8 또는 12']),
                F('z', 'int', '겹침 순서 (0~5, 클수록 위)', ['h' => '다른 스티커나 메인 이미지와 겹칠 때 무엇이 위에 올지 정해요.']),
                F('pos_x', 'int', '가로 위치', ['hide' => true, 'def' => '400']),
                F('pos_y', 'int', '세로 위치', ['hide' => true, 'def' => '300']),
            ],
        ],
        'fonts' => [
            'label' => '내 폰트 (개수 제한 없음)', 'title' => 'name', 'protect' => false,
            'order' => 'id ASC',
            'sub' => 'file',
            'fields' => [
                F('name', 'text', '폰트 이름', ['h' => '글꼴 목록에 보일 이름이에요. (한글·영문·숫자·공백만 쓰여요)']),
                F('file', 'font', '폰트 파일 (ttf, otf, woff, woff2)', ['h' => '서버 업로드 제한(보통 2~10MB)보다 작아야 해요. 큰 파일은 woff2로 줄이거나 다른 곳에 올린 뒤 주소를 붙여넣으세요. 저장하면 사이트 기본 글꼴, 제목용 글꼴, 프로필·AU 이름, TRPG 로그, 본문 편집기의 글꼴 목록에 나타나요.']),
            ],
        ],
        'guestbook_replies' => [
            'label' => '방명록 답글', 'title' => 'name', 'protect' => true, 'readonly' => true,
            'order' => 'created_at ASC, id ASC',
            'sub' => 'gb_id',
            'fields' => [
                F('gb_id', 'int', '방명록 글 번호', ['hide' => true]),
                F('name', 'text', '이름'),
                F('message', 'textarea', '내용'),
                F('is_admin', 'check', '주인장 답글', ['hide' => true]),
            ],
        ],
        'voices' => [
            'label' => '보이스 (프로필 대사)', 'title' => 'line', 'protect' => false,
            'order' => 'profile_id ASC, id ASC',
            'sub' => 'profile_id',
            'fields' => [
                F('profile_id', 'select', '어느 프로필의 보이스인가요?', ['opts' => '@profiles']),
                F('line', 'text', '대사', ['h' => '프로필 상세 페이지에서 이 보이스 버튼을 누르면 버튼 위에 이 대사가 자막처럼 떠요.']),
                F('file', 'audio', '보이스 파일'),
            ],
        ],
        'timeline' => [
            'label' => '타임라인 사건', 'title' => 'title', 'protect' => true,
            'order' => 'sort_order ASC, id ASC',
            'sub' => 'date_text',
            'fields' => [
                F('title', 'text', '사건 제목'),
                F('date_text', 'text', '날짜 (자유롭게 적어도 돼요)', ['h' => '달력에서 고르는 게 아니라 직접 입력해요. 예: 2023.03, 제국력 31년, 첫눈이 오던 날 등 자유롭게 적을 수 있어요.']),
                F('summary', 'textarea', '한 줄 요약 (타임라인에 보여요)'),
                F('image', 'image', '이미지 (선택)', ['h' => '넣으면 타임라인 카드 위쪽에 가로로 긴 이미지로, 상세 페이지에서는 큰 이미지로 함께 보여요. 비워두면 이 사건은 글로만 나와요.']),
                F('img_layout', 'select', '상세 페이지 이미지 위치', ['opts' => ['side' => '이미지 옆(측면)형', 'top' => '이미지 위(상단)형'], 'd' => 'side', 'h' => '이미지가 있을 때만 적용돼요. 사건마다 다르게 정할 수 있어요.']),
                F('img_side', 'select', '측면형일 때 이미지 위치 (좌/우)', ['opts' => ['right' => '오른쪽', 'left' => '왼쪽'], 'd' => 'right', 'h' => '위에서 "이미지 옆(측면)형"을 골랐을 때만 적용돼요. 타임라인 메인 페이지의 대표 이미지 위치와는 별개로, 사건마다 다르게 정할 수 있어요.']),
                F('title_font', 'select', '제목 글꼴 (선택)', ['opts' => font_opts(), 'h' => '체크하지 않으면 사이트 기본 제목 글꼴을 써요.']),
                F('card_title_color', 'color', '타임라인 목록 카드 제목 글자색 (선택)', ['h' => '타임라인 화면에 나오는 카드 제목 색이에요. 상세 페이지 제목 색과는 별개예요.']),
                F('title_color', 'color', '상세 페이지 제목 글자색 (선택)'),
                F('hl_color', 'color', '상세 페이지 하이라이트 색상 (선택)', ['h' => '상세 페이지 위쪽의 "타임라인 · 날짜" 글자색이에요. 제목 글자색과는 따로 정할 수 있어요. 체크하지 않으면 타임라인 기본 색을 써요.']),
                F('panel_color', 'color', '상세 페이지 글 상자 배경색 (선택)', ['h' => '이미지 옆(또는 위)에 있는 글 상자의 색이에요. 체크하지 않으면 기본 색을 써요.']),
                F('panel_opacity', 'int', '글 상자 진하기 (10~100)'),
                F('page_bg', 'image', '사건 페이지 배경 이미지 (선택)', ['h' => '비워두면 "사이트 설정 → 페이지별 배경"의 타임라인 배경을 그대로 써요. 이 사건만 다른 배경을 쓰고 싶을 때 넣으세요.']),
                F('page_color', 'color', '사건 페이지 배경색 (선택)'),
                F('page_color2', 'color', '사건 페이지 배경 그라데이션 (두 번째 색, 선택)'),
                F('accent_color', 'color', '이 사건만의 강조색 (선택)', ['h' => '버튼·링크 등에 쓰이는 사이트 강조색을 이 사건에서만 다른 색으로 바꿔요. 체크하지 않으면 사이트 기본 강조색을 그대로 써요.']),
                F('accent_ink_color', 'color', '강조색 위 글자색 (선택)', ['h' => '위 강조색이 배경으로 깔릴 때 그 위에 놓이는 글자색이에요.']),
                                F('bgm_title', 'text', '이 사건 음악 제목 (선택)'),
                                F('bgm_file', 'audio', '이 사건 음악 (mp3 또는 유튜브 링크, 선택)', ['h' => '파일을 올리거나, mp3 직접 주소나 유튜브 링크를 붙여넣을 수 있어요. 비워두면 사이트 기본 배경음악이 계속 나와요.']),
                                F('bgm_auto', 'check', '이 사건 열면 자동 재생'),
F('body', 'html', '상세 내용 (긴 서술)'),
                F('sort_order', 'int', '타임라인 순서 (작을수록 먼저)'),
            ],
        ],
        'side' => [
            'label' => '썰 백업', 'title' => 'title', 'protect' => true,
            'order' => 'created_at DESC, id DESC',
            'sub' => 'name1',
            'edit_groups' => ['conv2' => '대화 2 편집', 'conv3' => '대화 3 편집', 'conv4' => '대화 4 편집', 'conv5' => '대화 5 편집', 'images' => '대화에 쓸 이미지 올리기'],
            'fields' => [
                F('title', 'text', '썰 제목'),
                F('name1', 'text', '대화 참여자 1 닉네임 (왼쪽)', ['d' => '상대방']),
                F('name1_color', 'color', '참여자 1 이름 글자색 (선택)'),
                F('bub1_color', 'color', '참여자 1 말풍선 배경색 (선택)', ['h' => '체크하지 않으면 기본 회색 말풍선을 써요.']),
                F('bub1_text', 'color', '참여자 1 말풍선 글자색 (선택)'),
                F('name2', 'text', '대화 참여자 2 닉네임 (오른쪽)', ['d' => '나']),
                F('name2_color', 'color', '참여자 2 이름 글자색 (선택)'),
                F('bub2_color', 'color', '참여자 2 말풍선 배경색 (선택)', ['h' => '체크하지 않으면 강조색 말풍선을 써요.']),
                F('bub2_text', 'color', '참여자 2 말풍선 글자색 (선택)'),
                F('tab_color', 'color', '대화 선택 버튼 배경색 (선택)', ['h' => '대화가 2개 이상일 때 뜨는 "대화 1/2/…" 탭의 선택된 상태 배경색이에요.']),
                F('tab_text', 'color', '대화 선택 버튼 글자색 (선택)'),
                F('conv1_title', 'text', '대화 1 제목 (선택)', ['h' => '비워두면 "대화 1"로 나와요.']),
                F('conv1_body', 'textarea', '대화 1 내용', ['h' => '한 줄에 하나씩 적어주세요. 텍스트는 "1: 내용" / "2: 내용", 이미지는 "1img: 번호" / "2img: 번호"(아래 "대화에 쓸 이미지 올리기"에서 올린 번호), 링크는 "1link: 제목 | 주소" / "2link: 제목 | 주소" 형식이에요. 1은 왼쪽(참여자 1), 2는 오른쪽(참여자 2)이에요.']),
                F('conv2_title', 'text', '대화 2 제목 (선택)', ['group' => 'conv2']),
                F('conv2_body', 'textarea', '대화 2 내용 (선택)', ['group' => 'conv2', 'h' => '이어지는 썰을 다른 대화방에서 나눈 경우, 여기에 이어서 적어주세요. 형식은 대화 1과 같아요.']),
                F('conv3_title', 'text', '대화 3 제목 (선택)', ['group' => 'conv3']),
                F('conv3_body', 'textarea', '대화 3 내용 (선택)', ['group' => 'conv3']),
                F('conv4_title', 'text', '대화 4 제목 (선택)', ['group' => 'conv4']),
                F('conv4_body', 'textarea', '대화 4 내용 (선택)', ['group' => 'conv4']),
                F('conv5_title', 'text', '대화 5 제목 (선택)', ['group' => 'conv5']),
                F('conv5_body', 'textarea', '대화 5 내용 (선택)', ['group' => 'conv5']),
                F('sideimg1', 'image', '이미지 1', ['group' => 'images', 'h' => '대화 내용에서 "1img: 1" 또는 "2img: 1"처럼 번호로 불러와요.']),
                F('sideimg2', 'image', '이미지 2', ['group' => 'images']),
                F('sideimg3', 'image', '이미지 3', ['group' => 'images']),
                F('sideimg4', 'image', '이미지 4', ['group' => 'images']),
                F('sideimg5', 'image', '이미지 5', ['group' => 'images']),
                F('sideimg6', 'image', '이미지 6', ['group' => 'images']),
                F('sideimg7', 'image', '이미지 7', ['group' => 'images']),
                F('sideimg8', 'image', '이미지 8', ['group' => 'images']),
                F('sideimg9', 'image', '이미지 9', ['group' => 'images']),
                F('sideimg10', 'image', '이미지 10', ['group' => 'images']),
            ],
        ],
        'rp' => [
            'label' => 'RP', 'title' => 'title', 'protect' => true,
            'order' => 'sort_order ASC, id ASC',
            'sub' => 'name1',
            'edit_groups' => ['conv2' => '대화 2 편집', 'conv3' => '대화 3 편집', 'conv4' => '대화 4 편집', 'conv5' => '대화 5 편집', 'images' => '대화에 쓸 이미지 올리기'],
            'fields' => [
                F('title', 'text', '부 제목', ['h' => '예: 1부 · 도착, 2부 · 균열']),
                F('card_image', 'image', '선택 화면 카드 이미지', ['h' => 'RP 진입 화면에서 이 부를 고르는 카드에 나오는 이미지예요.']),
                F('sort_order', 'int', '순서 (작을수록 먼저)'),
                F('img_layout', 'select', '대표 이미지 위치', ['opts' => ['side' => '이미지 옆(측면)형', 'top' => '이미지 위(상단)형'], 'd' => 'side']),
                F('page_bg', 'image', '이 부의 페이지 배경 이미지 (선택)', ['h' => '비워두면 "사이트 설정 → 페이지별 배경"의 RP 배경을 그대로 써요. 이 부만 다른 배경을 쓰고 싶을 때 넣으세요.']),
                F('page_color', 'color', '이 부의 페이지 배경색 (선택)'),
                F('page_color2', 'color', '이 부의 페이지 배경 그라데이션 (두 번째 색, 선택)'),
                F('rep_image', 'image', '상세 페이지 대표 이미지 (선택)', ['h' => '비워두면 이미지 없이 대화만 나와요.']),
                F('bgm_title', 'text', '이 부(RP) 음악 제목 (선택)'),
                F('bgm_file', 'audio', '이 부(RP) 음악 (mp3 또는 유튜브 링크, 선택)', ['h' => '파일을 올리거나, mp3 직접 주소나 유튜브 링크를 붙여넣을 수 있어요. 비워두면 사이트 기본 배경음악이 계속 나와요. 대화가 여러 개여도 음악은 하나만 정해요.']),
                F('bgm_auto', 'check', '이 부를 열면 자동 재생'),
                F('title_font', 'select', '대화 제목 글꼴 (선택)', ['opts' => font_opts(), 'h' => '체크하지 않으면 사이트 기본 제목 글꼴을 써요.']),
                F('title_color', 'color', '대화 제목 글자색 (선택)'),
                F('accent_color', 'color', '이 부(RP)만의 강조색 (선택)', ['h' => '탭·버튼 등에 쓰이는 사이트 강조색을 이 부에서만 다른 색으로 바꿔요. 체크하지 않으면 사이트 기본 강조색을 그대로 써요.']),
                F('accent_ink_color', 'color', '강조색 위 글자색 (선택)', ['h' => '위 강조색이 배경으로 깔릴 때 그 위에 놓이는 글자색이에요.']),
                F('name1', 'text', '참여자 1 닉네임 (왼쪽)', ['d' => '상대방']),
                F('name1_color', 'color', '참여자 1 이름 글자색 (선택)'),
                F('img1', 'image', '참여자 1 프로필 사진 (선택)'),
                F('bub1_color', 'color', '참여자 1 말풍선 배경색 (선택)'),
                F('bub1_text', 'color', '참여자 1 말풍선 글자색 (선택)'),
                F('name2', 'text', '참여자 2 닉네임 (오른쪽)', ['d' => '나']),
                F('name2_color', 'color', '참여자 2 이름 글자색 (선택)'),
                F('img2', 'image', '참여자 2 프로필 사진 (선택)'),
                F('bub2_color', 'color', '참여자 2 말풍선 배경색 (선택)'),
                F('bub2_text', 'color', '참여자 2 말풍선 글자색 (선택)'),
                F('tab_color', 'color', '대화 선택 버튼 배경색 (선택)'),
                F('tab_text', 'color', '대화 선택 버튼 글자색 (선택)'),
                F('conv1_title', 'text', '대화 1 제목 (선택)', ['h' => '비워두면 "대화 1"로 나와요.']),
                F('conv1_body', 'textarea', '대화 1 내용', ['h' => '한 줄에 하나씩 적어주세요. 텍스트는 "1: 내용" / "2: 내용", 이미지는 "1img: 번호" / "2img: 번호"(아래 "대화에 쓸 이미지 올리기"에서 올린 번호), 링크는 "1link: 제목 | 주소" / "2link: 제목 | 주소" 형식이에요. 1은 왼쪽(참여자 1), 2는 오른쪽(참여자 2)이에요.']),
                F('conv2_title', 'text', '대화 2 제목 (선택)', ['group' => 'conv2']),
                F('conv2_body', 'textarea', '대화 2 내용 (선택)', ['group' => 'conv2', 'h' => '형식은 대화 1과 같아요.']),
                F('conv3_title', 'text', '대화 3 제목 (선택)', ['group' => 'conv3']),
                F('conv3_body', 'textarea', '대화 3 내용 (선택)', ['group' => 'conv3']),
                F('conv4_title', 'text', '대화 4 제목 (선택)', ['group' => 'conv4']),
                F('conv4_body', 'textarea', '대화 4 내용 (선택)', ['group' => 'conv4']),
                F('conv5_title', 'text', '대화 5 제목 (선택)', ['group' => 'conv5']),
                F('conv5_body', 'textarea', '대화 5 내용 (선택)', ['group' => 'conv5']),
                F('rpimg1', 'image', '이미지 1', ['group' => 'images', 'h' => '대화 내용에서 "1img: 1" 또는 "2img: 1"처럼 번호로 불러와요.']),
                F('rpimg2', 'image', '이미지 2', ['group' => 'images']),
                F('rpimg3', 'image', '이미지 3', ['group' => 'images']),
                F('rpimg4', 'image', '이미지 4', ['group' => 'images']),
                F('rpimg5', 'image', '이미지 5', ['group' => 'images']),
                F('rpimg6', 'image', '이미지 6', ['group' => 'images']),
                F('rpimg7', 'image', '이미지 7', ['group' => 'images']),
                F('rpimg8', 'image', '이미지 8', ['group' => 'images']),
                F('rpimg9', 'image', '이미지 9', ['group' => 'images']),
                F('rpimg10', 'image', '이미지 10', ['group' => 'images']),
            ],
        ],
        'guestbook' => [
            'label' => '방명록', 'title' => 'name', 'protect' => true, 'readonly' => true,
            'order' => 'created_at DESC, id DESC',
            'sub' => 'message',
            'fields' => [
                F('name', 'text', '이름'),
                F('message', 'textarea', '내용'),
                F('reply', 'textarea', '관리자 답글 (선택)', ['h' => '여기에 답글을 적어두면 방명록 글 아래에 "답글"로 표시돼요. 방명록 페이지에서 바로 답글을 달 수도 있어요.']),
                F('reply_at', 'text', '답글 시간 (자동 기록)', ['hide' => true]),
                F('reply_by', 'text', '답글 작성자 (자동 기록)', ['hide' => true]),
            ],
        ],
        'banners' => [
            'label' => '배너', 'title' => 'title', 'protect' => false,
            'order' => 'sort_order ASC, id ASC',
            'sub' => 'link',
            'fields' => [
                F('title', 'text', '배너 이름 (관리용)'),
                F('image', 'image', '배너 이미지'),
                F('link', 'text', '클릭 시 이동할 주소', ['h' => '예: https://twitter.com/... (비워두면 클릭해도 이동하지 않아요)']),
                F('newtab', 'check', '새 창으로 열기'),
                F('sort_order', 'int', '정렬 순서 (작을수록 먼저)'),
            ],
        ],
        'tracks' => [
            'label' => '음악 트랙', 'title' => 'title', 'protect' => false,
            'order' => 'sort_order ASC, id ASC',
            'sub' => 'artist',
            'fields' => [
                F('title', 'text', '곡 제목'),
                F('artist', 'text', '아티스트'),
                F('cover', 'image', '커버 이미지 (선택)', ['h' => '플레이어에 보이는 이미지예요. 유튜브 링크는 비워두면 영상 썸네일이 커버로 쓰여요.']),
                F('file', 'audio', '음악 파일 (mp3 등)', ['h' => 'mp3 파일 업로드, mp3 직접 주소, 또는 유튜브 링크(https://youtu.be/...)를 넣을 수 있어요.']),
                F('sort_order', 'int', '재생 순서 (작을수록 먼저)'),
            ],
        ],
    ];
    return $E;
}

/** 컬럼 정의 (이름 => SQL 타입) */
function sql_type($f) {
    switch ($f['t']) {
        case 'text': return "VARCHAR(255) NOT NULL DEFAULT ''";
        case 'textarea': return 'TEXT NULL';
        case 'html':
        case 'code':
        case 'images': return 'MEDIUMTEXT NULL';
        case 'icon':
        case 'image':
        case 'audio': return "VARCHAR(500) NOT NULL DEFAULT ''";
        case 'select': return "VARCHAR(50) NOT NULL DEFAULT ''";
        case 'int': return 'INT NOT NULL DEFAULT 0';
        case 'check': return 'TINYINT(1) NOT NULL DEFAULT 0';
        case 'color':
        case 'date': return "VARCHAR(20) NOT NULL DEFAULT ''";
        case 'font': return "VARCHAR(500) NOT NULL DEFAULT ''";
    }
    return 'TEXT NULL';
}
function column_defs($e) {
    $c = [];
    foreach ($e['fields'] as $f) { $c[$f['n']] = sql_type($f); }
    if (!empty($e['protect'])) {
        $c['visibility'] = "VARCHAR(10) NOT NULL DEFAULT 'public'";
        $c['pw_hash'] = "VARCHAR(255) NOT NULL DEFAULT ''";
    }
    $c['created_at'] = 'DATETIME NULL';
    return $c;
}
function create_sql($t, $e) {
    $cols = ['`id` INT NOT NULL AUTO_INCREMENT'];
    foreach (column_defs($e) as $n => $d) { $cols[] = '`' . $n . '` ' . $d; }
    $cols[] = 'PRIMARY KEY (`id`)';
    return 'CREATE TABLE IF NOT EXISTS `' . $t . '` (' . implode(', ', $cols) . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
}

/** 사이트 설정 화면 정의. 'd' = 기본값 */
function setting_groups() {
    static $G = null;
    if ($G !== null) return $G;

    $bgFields = [];
    $pages = ['home' => '메인 홈', 'profile' => '프로필', 'gallery' => '갤러리', 'log' => '로그', 'au' => 'AU', 'trpg' => 'TRPG', 'timeline' => '타임라인', 'side' => '썰 백업', 'rp' => 'RP', 'running' => '러닝', 'guestbook' => '방명록'];
    foreach ($pages as $k => $label) {
        $bgFields[] = F('bgc_' . $k, 'color', $label . ' 배경색', ['d' => ($k === 'home' ? '#14151c' : '')]);
        $bgFields[] = F('bgc2_' . $k, 'color', $label . ' 배경 그라데이션 (두 번째 색)', $k === 'home' ? ['h' => '배경색과 이 색을 함께 체크하면 두 색이 자연스럽게 이어지는 그라데이션 배경이 돼요. 이미지를 함께 쓰면 이미지 뒤(투명한 부분)에 깔려요.'] : []);
        $bgFields[] = F('bgi_' . $k, 'image', $label . ' 배경 이미지', $k === 'home' ? ['h' => '다른 페이지는 비워두면 메인 홈 배경을 그대로 써요.'] : []);
    }
    $bgFields[] = F('rp_accent_color', 'color', 'RP 섹션 선택 화면 강조색 (선택)', ['h' => 'RP 게시판에 들어가면 처음 보이는 "1부/2부/3부" 선택 화면에서, 버튼·테두리 등에 쓰이는 사이트 강조색을 이 화면에서만 다른 색으로 바꿔요. 체크하지 않으면 사이트 기본 강조색을 그대로 써요. 부(RP)를 열고 난 뒤의 화면은 각 부마다 따로 정하는 강조색을 따라요.']);
    $bgFields[] = F('rp_accent_ink_color', 'color', 'RP 섹션 선택 화면 강조색 위 글자색 (선택)', ['h' => '위 강조색이 배경으로 깔릴 때 그 위에 놓이는 글자색이에요.']);
    $bgFields[] = F('bg_angle', 'int', '그라데이션 방향 (도)', ['d' => '180', 'h' => '180 = 위에서 아래로, 90 = 왼쪽에서 오른쪽, 135 = 왼쪽 위에서 오른쪽 아래 (모든 페이지 공통)']);
    $bgFields[] = F('bg_dim', 'int', '배경 어둡게 (0~80)', ['d' => '0', 'h' => '배경 이미지 위에 글씨가 잘 안 보일 때 숫자를 올려보세요.']);

    $G = [
        'site' => ['label' => '기본 설정', 'fields' => [
            F('site_title', 'text', '사이트 이름', ['d' => '우리의 홈']),
            F('char1_name', 'text', '캐릭터 A 이름', ['d' => 'A']),
            F('char2_name', 'text', '캐릭터 B 이름', ['d' => 'B']),
            F('font', 'select', '사이트 기본 글꼴', ['opts' => font_opts(), 'd' => 'pretendard']),
            F('head_font', 'select', '제목용 글꼴 (명조체로 나오던 글자 전체)', ['opts' => font_opts(), 'd' => 'gowun_batang', 'h' => '페이지 제목, 카드 제목, 글 제목, 프로필·AU 이름, 디데이 숫자, 뮤직 플레이어 등 지금 명조체로 나오는 글자들의 글꼴을 한 번에 바꿔요. 내가 올린 폰트도 고를 수 있어요.']),
            F('text_color', 'color', '기본 글자색', ['d' => '#ecebf1']),
            F('accent_color', 'color', '강조색 (버튼/링크)', ['d' => '#45d6c8']),
            F('accent_ink_color', 'color', '강조색 위 글자색', ['d' => '#08201d', 'h' => '버튼이나 선택된 탭처럼 강조색이 배경으로 깔릴 때, 그 위에 놓이는 글자색이에요. 강조색을 밝게 바꿨다면 기본값(어두운 색)이 잘 어울리고, 어둡게 바꿨다면 밝은 색으로 바꿔야 글씨가 보여요.']),
            F('editor_theme', 'select', '글 에디터 화면 모드', ['opts' => ['dark' => '다크 모드', 'light' => '라이트 모드'], 'd' => 'dark', 'h' => '"+ 새 글 쓰기"나 "수정"을 눌렀을 때 뜨는 글쓰기 화면의 밝기예요. 다크 모드는 사이트와 같은 어두운 톤, 라이트 모드는 밝은 화면이에요.']),
        ]],
        'og' => ['label' => '링크 미리보기 (카톡·디스코드·트위터 등)', 'fields' => [
            F('og_title', 'text', '미리보기 제목 (선택)', ['h' => '비워두면 사이트 이름을 그대로 써요.']),
            F('og_desc', 'textarea', '미리보기 설명 (선택)', ['h' => '한두 문장으로 짧게 적어주세요. 비워두면 안내 문구 없이 나와요.']),
            F('og_image', 'image', '미리보기 이미지', ['h' => '링크를 붙였을 때 나오는 큰 이미지예요. 가로로 넓은 1200×630px 정도를 권장해요(정사각형에 가까우면 카카오톡 등에서 양옆이 잘려 보일 수 있어요). 비워두면 미리보기에 이미지가 안 나올 수 있어요.']),
        ]],
        'boards' => ['label' => '게시판 사용 설정', 'fields' => [
            F('bd_profile', 'check', '프로필', ['d' => '1', 'h' => '체크를 끄면 그 게시판은 왼쪽 메뉴에서 사라지고, 주소로 들어가도 열리지 않아요. 올려둔 글은 지워지지 않고 그대로 남아 있어서, 다시 켜면 돌아와요.']),
            F('bd_running', 'check', '러닝', ['d' => '1']),
            F('bd_timeline', 'check', '타임라인', ['d' => '1']),
            F('bd_rp', 'check', 'RP', ['d' => '1']),
            F('bd_side', 'check', '썰 백업', ['d' => '1']),
            F('bd_gallery', 'check', '갤러리', ['d' => '1']),
            F('bd_log', 'check', '로그', ['d' => '1']),
            F('bd_au', 'check', 'AU', ['d' => '1']),
            F('bd_trpg', 'check', 'TRPG', ['d' => '1']),
            F('bd_guestbook', 'check', '방명록', ['d' => '1', 'h' => '방명록을 끄면 방문자가 글을 남기는 것도 막혀요.']),
        ]],
        'favicon' => ['label' => '파비콘 (탭 아이콘)', 'fields' => [
            F('favicon', 'icon', '파비콘 이미지', ['h' => '브라우저 탭·북마크·주소창 옆에 보이는 작은 아이콘이에요. 정사각형 PNG(권장 512×512 이상) 또는 ICO 파일을 올려 주세요. 파일은 1MB 이하예요.']),
            F('apple_icon', 'icon', '홈 화면 추가용 아이콘 (선택)', ['h' => '폰에서 "홈 화면에 추가"를 했을 때 보이는 아이콘이에요. 정사각형 PNG(권장 180×180 이상)가 좋아요. 비워두면 위의 파비콘 이미지를 그대로 써요(ICO 파일은 제외).']),
            F('theme_color', 'color', '폰 브라우저 상단바 색 (선택)', ['h' => '폰 크롬·사파리 등에서 주소창 주변에 입혀지는 색이에요. 체크하지 않으면 브라우저 기본색이에요.']),
        ]],
        'landing' => ['label' => '랜딩 페이지', 'fields' => [
            F('landing_on', 'check', '랜딩 페이지 사용', ['d' => '1', 'h' => '끄면 사이트 주소로 들어왔을 때 바로 메인 홈이 열려요.']),
            F('landing_bg_color', 'color', '배경색', ['d' => '#0d0e14']),
            F('landing_bg_color2', 'color', '배경 그라데이션 (두 번째 색)', ['h' => '배경색과 함께 체크하면 그라데이션이 돼요. 방향은 "페이지별 배경"의 그라데이션 방향을 따라요.']),
            F('landing_bg_image', 'image', '배경 이미지'),
            F('landing_logo', 'image', '로고(입장) 이미지', ['h' => '이 이미지를 클릭하면 메인 홈으로 들어가요. GIF/투명 PNG 가능.']),
            F('landing_logo_w', 'int', '로고 가로 크기 (px)', ['d' => '420']),
            F('landing_caption', 'text', '로고 아래 안내 문구', ['d' => '클릭해서 입장']),
            F('landing_ink', 'color', '안내 문구 글자색', ['d' => '#ecebf1']),
        ]],
        'home' => ['label' => '메인 홈', 'fields' => [
            F('home_image', 'image', '메인 큰 이미지', ['h' => 'GIF, 투명 PNG 모두 그대로 보여요.']),
            F('home_link', 'text', '메인 이미지 클릭 시 이동할 주소 (선택)'),
            F('login_title', 'text', '로그인 카드 제목', ['d' => 'ADMIN']),
            F('login_bg', 'image', '로그인 카드 배경 이미지'),
            F('login_color', 'color', '로그인 카드 배경색 (따로 정할 때만)', ['d' => '', 'h' => '체크하지 않으면 "사이트 설정 → 카드 디자인"의 카드 색·진하기·글자색을 그대로 써요.']),
        ]],
        'bg' => ['label' => '페이지별 배경', 'fields' => $bgFields],
        'menu' => ['label' => '왼쪽 메뉴', 'fields' => [
            F('menu_c_top', 'color', '선 색 (맨 위)', ['d' => '#45d6c8']),
            F('menu_c_mid', 'color', '선 색 (가운데)', ['d' => '#ffffff']),
            F('menu_c_bot', 'color', '선 색 (맨 아래)', ['d' => '#e5483f']),
            F('menu_text', 'color', '메뉴 글자색', ['d' => '#ffffff']),
            F('rail_logo', 'image', '왼쪽 위 로고 이미지 (PNG)', ['h' => '메뉴 목록 바로 위에 보이고, 메뉴가 움직이면 함께 움직여요. 비워두면 사이트 이름 글자가 작게 나와요. 투명 PNG를 추천해요.']),
            F('rail_logo_w', 'int', '로고 가로 크기 (px)', ['d' => '200', 'h' => '기본 200px, 최대 400px까지 (메뉴 영역보다 넓으면 본문 쪽으로 살짝 넘어가요).']),
            F('rail_style', 'select', '왼쪽 메뉴 모양', ['opts' => ['line' => '선과 점 + 글자 (기본)', 'line_icon' => '선과 점 + 아이콘 + 글자', 'icon' => '아이콘 + 글자만 (선 없음)'], 'd' => 'line', 'h' => '"아이콘 + 글자만"을 고르면 선과 점이 없어지고, 메뉴가 크기 변화 없이 깔끔한 목록으로 나와요.']),
            F('rail_width', 'int', '왼쪽 메뉴 영역 가로 폭 (px)', ['d' => '160', 'h' => '메뉴가 차지하는 왼쪽 여백의 폭이에요. 줄일수록 가운데 이미지·스티커 영역이 넓어져요(기본 160, 너무 줄이면 메뉴 글자가 본문에 겹쳐 보일 수 있어요). PC 화면에만 적용돼요.']),
            F('logo_shadow_color', 'color', '로고 그림자색', ['h' => '로고 이미지 아래에 은은하게 깔리는 그림자 색이에요. 체크하지 않으면 기본 검은색 그림자를 써요.']),
            F('m_profile', 'text', '메뉴 이름 1', ['d' => 'PROFILE']),
            F('m_gallery', 'text', '메뉴 이름 2', ['d' => 'GALLERY']),
            F('m_log', 'text', '메뉴 이름 3', ['d' => 'LOG']),
            F('m_au', 'text', '메뉴 이름 4', ['d' => 'AU']),
            F('m_trpg', 'text', '메뉴 이름 5', ['d' => 'TRPG']),
            F('m_guestbook', 'text', '메뉴 이름 6', ['d' => 'GUESTBOOK']),
            F('m_timeline', 'text', '메뉴 이름 7', ['d' => 'TIMELINE']),
            F('m_character', 'text', '메뉴 묶음 이름 (캐릭터)', ['d' => 'CHARACTER', 'h' => '프로필·러닝을 묶는 메뉴 이름이에요. 이 메뉴를 누르면 하위 메뉴가 펼쳐져요.']),
            F('m_running', 'text', '메뉴 이름 (러닝)', ['d' => 'RUNNING']),
            F('m_story', 'text', '메뉴 묶음 이름 (스토리)', ['d' => 'STORY', 'h' => '타임라인·RP·썰 백업을 묶는 메뉴 이름이에요. 이 메뉴를 누르면 하위 메뉴가 펼쳐져요.']),
            F('m_rp', 'text', '메뉴 이름 (RP)', ['d' => 'RP']),
            F('m_side', 'text', '메뉴 이름 (썰 백업)', ['d' => 'SIDE']),
        ]],
        'cards' => ['label' => '카드 디자인', 'fields' => [
            F('card_color', 'color', '카드 기본 배경색', ['d' => '#101118', 'h' => '갤러리·로그·TRPG 카드와 글 읽기 화면의 기본 상자 색이에요. 글마다 따로 정할 수도 있어요.']),
            F('card_text', 'color', '카드 글자색', ['d' => '#ecebf1', 'h' => '카드 배경을 밝게 바꿨다면 어두운 글자색으로 바꿔 주세요. 로그인 카드에도 적용돼요.']),
            F('cursor_img', 'image', '마우스 커서 이미지 (선택)', ['h' => '32x32px 정도의 작은 PNG를 추천해요. 비워두면 기본 화살표 커서를 써요. 사이트 전체에 적용돼요. (GIF도 올라가지만 애니메이션 없이 첫 장면만 정지 상태로 보여요.)']),
            F('cursor_hover_img', 'image', '링크·버튼 위에 올렸을 때의 커서 이미지 (선택)', ['h' => '메뉴, 버튼, 카드처럼 누를 수 있는 것 위에 마우스를 올렸을 때만 다른 이미지로 바꿔요. 비워두면 위의 기본 커서 이미지를 그대로 쓰거나(기본 커서도 없으면 브라우저의 손가락 커서를) 써요.']),
            F('cursor_fx_on', 'check', '움직이는(GIF) 커서 사용', ['h' => '브라우저 기본 커서는 GIF 애니메이션이 멈춰 보여서, 이 기능은 GIF 이미지를 마우스 위치에 그대로 그려서 진짜 움직이는 커서처럼 보이게 해요. 마우스에 딱 붙어 움직이고(늦게 따라오지 않아요), 위의 일반 커서 이미지 대신 쓰여요. 터치 화면(폰·태블릿)에서는 나타나지 않아요.']),
            F('cursor_fx_img', 'image', '움직이는 커서 이미지 (GIF 추천)'),
            F('cursor_fx_hover_img', 'image', '링크·버튼 위에서 바뀌는 이미지 (선택)', ['h' => '비워두면 그대로 같은 이미지를 써요.']),
            F('cursor_fx_size', 'int', '움직이는 커서 크기 (px)', ['d' => '32']),
            F('cursor_fx_hx', 'int', '커서 끝 위치: 이미지 왼쪽에서 (px)', ['d' => '0', 'h' => '실제로 클릭되는 지점이에요. 화살표 모양이면 화살표 끝이 이미지의 어디쯤인지 정해요. 0이면 이미지 왼쪽 위 모서리예요.']),
            F('cursor_fx_hy', 'int', '커서 끝 위치: 이미지 위쪽에서 (px)', ['d' => '0']),
            F('cursor_fx_hide', 'check', '기본 마우스 화살표 숨기기', ['d' => '1']),
            F('card_opacity', 'int', '카드 진하기 (10~100)', ['d' => '68', 'h' => '숫자가 작을수록 배경이 비쳐 보여요.']),
            F('card_shadow_color', 'color', '카드 그림자색', ['h' => '갤러리·로그·TRPG 카드에 마우스를 올렸을 때 생기는 그림자 색이에요. 체크하지 않으면 기본 검은색 그림자를 써요.']),
        ]],
        'clicksound' => ['label' => '클릭 효과음', 'fields' => [
            F('click_sound_on', 'check', '켜기', ['d' => '0', 'h' => '버튼이나 메뉴, 링크 같은 것을 누를 때마다 짧은 "달칵" 소리가 나요.']),
            F('click_sound_file', 'audio', '효과음 파일 (선택)', ['h' => '올리지 않으면 사이트에 내장된 기본 클릭 소리를 사용해요. 아주 짧은(1초 이하) mp3를 추천해요.']),
            F('click_sound_volume', 'int', '소리 크기 (1~100)', ['d' => '50']),
        ]],
        'particles' => ['label' => '떨어지는 효과', 'fields' => [
            F('particles_on', 'check', '켜기', ['d' => '0', 'h' => '화면 위로 별가루나 물방울 같은 것이 은은하게 떨어지는 장식 효과예요. 사이트 전체에 나와요.']),
            F('particles_shape', 'select', '모양', ['opts' => ['dot' => '동그라미', 'star' => '별', 'drop' => '물방울', 'heart' => '하트'], 'd' => 'star']),
            F('particles_color', 'color', '색', ['d' => '#ffffff']),
            F('particles_count', 'int', '개수 (5~150)', ['d' => '40']),
            F('particles_speed', 'int', '떨어지는 속도 (1~10)', ['d' => '4']),
            F('particles_size', 'int', '크기 (2~20px)', ['d' => '7']),
        ]],
        'mtrail' => ['label' => '마우스 효과 (별 떨어지기)', 'fields' => [
            F('mtrail_on', 'check', '켜기', ['d' => '0', 'h' => '마우스를 움직이면 지나간 자리에서 별 같은 조각이 반짝이며 떨어져요. 사이트 전체에 나오고, 터치 화면(폰·태블릿)에서는 나오지 않아요.']),
            F('mtrail_shape', 'select', '모양', ['opts' => ['star' => '별', 'sparkle' => '반짝이(✦)', 'heart' => '하트', 'dot' => '동그라미', 'drop' => '물방울', 'mix' => '여러 모양 섞기'], 'd' => 'star']),
            F('mtrail_color', 'color', '색', ['d' => '#ffffff']),
            F('mtrail_color2', 'color', '두 번째 색 (선택)', ['h' => '체크하면 조각마다 두 색 중 하나로 섞여 나와요.']),
            F('mtrail_size', 'int', '크기 (4~30px)', ['d' => '10']),
            F('mtrail_amount', 'int', '양 (1~10)', ['d' => '4', 'h' => '클수록 많이 흩날려요. 너무 크면 화면이 산만하고 조금 무거워질 수 있어요.']),
            F('mtrail_life', 'int', '오래 남는 정도 (1~10)', ['d' => '5', 'h' => '클수록 더 오래, 더 멀리 떨어지다 사라져요.']),
        ]],
        'homebanner' => ['label' => '내 홈페이지 배너', 'fields' => [
            F('own_banner', 'image', '배너 이미지', ['h' => '메인 홈 배너 목록의 맨 앞에 자동으로 나와요. 방문자가 이 배너를 누르면, 다른 사이트에 걸 수 있는 고정 주소(유동 이미지 주소)가 클립보드에 복사돼요. 친구가 이 배너를 자기 사이트에 걸어줄 때, 이 배너를 눌러서 바로 복사해가면 돼요.']),
        ]],
        'timeline_set' => ['label' => '타임라인', 'fields' => [
            F('tl_title', 'text', '브라우저 탭 제목', ['d' => '타임라인', 'h' => '페이지 안에는 이제 큰 제목 글자가 보이지 않아요. 이 값은 브라우저 탭과 창 제목에만 쓰여요.']),
            F('tl_intro', 'textarea', '타임라인 위에 보일 짧은 소개 글 (선택)'),
            F('tl_accent', 'color', '타임라인 색 (위쪽)', ['d' => '#45d6c8']),
            F('tl_accent_mid', 'color', '타임라인 색 (가운데)', ['d' => '#ffffff']),
            F('tl_accent2', 'color', '타임라인 색 (아래쪽)', ['d' => '#e5483f']),
            F('tl_image', 'image', '대표 이미지 (선택)', ['h' => '타임라인 옆에 항상 고정으로 보이는 이미지예요. 사건별 이미지와는 달라요.']),
            F('tl_image_side', 'select', '대표 이미지 위치', ['opts' => ['right' => '오른쪽', 'left' => '왼쪽'], 'd' => 'right']),
        ]],
        'running_set' => ['label' => '러닝', 'fields' => [
            F('run1_card', 'image', S('char1_name', '캐릭터 A') . ' 진입 카드 이미지 (선택)', ['h' => '러닝 진입 화면에서 이 캐릭터를 고르는 카드에 나오는 이미지예요.']),
            F('run2_card', 'image', S('char2_name', '캐릭터 B') . ' 진입 카드 이미지 (선택)'),
            F('run1_bg', 'image', S('char1_name', '캐릭터 A') . ' 러닝 배경 이미지 (선택)', ['h' => '이 캐릭터의 러닝 목록·글 화면에 적용되는 배경이에요. 비워두면 "사이트 설정 → 페이지별 배경"의 러닝 배경을 그대로 써요.']),
            F('run1_bg_color', 'color', S('char1_name', '캐릭터 A') . ' 러닝 배경색 (선택)'),
            F('run1_bg_color2', 'color', S('char1_name', '캐릭터 A') . ' 러닝 배경 그라데이션 (두 번째 색, 선택)'),
            F('run2_bg', 'image', S('char2_name', '캐릭터 B') . ' 러닝 배경 이미지 (선택)'),
            F('run2_bg_color', 'color', S('char2_name', '캐릭터 B') . ' 러닝 배경색 (선택)'),
            F('run2_bg_color2', 'color', S('char2_name', '캐릭터 B') . ' 러닝 배경 그라데이션 (두 번째 색, 선택)'),
        ]],
        'hover' => ['label' => '호버 그라데이션', 'fields' => [
            F('hover_c_top', 'color', '위쪽 색', ['d' => '#45d6c8', 'h' => 'AU·갤러리·로그·TRPG 카드에 마우스를 올릴 때 나타나는 그라데이션이에요.']),
            F('hover_c_mid', 'color', '가운데 색', ['d' => '#ffffff']),
            F('hover_c_bot', 'color', '아래쪽 색', ['d' => '#e5483f']),
            F('hover_strength', 'int', '진하기 (10~100)', ['d' => '60', 'h' => '숫자가 클수록 색이 진해져요. 이미지가 잘 안 보이면 낮춰 보세요.']),
        ]],
        'dday' => ['label' => '디데이 위젯', 'fields' => [
            F('dday_on', 'check', '디데이 위젯 사용', ['d' => '1']),
            F('dday_title', 'text', '위젯 제목', ['d' => 'D-DAY', 'h' => '비워두면 제목 없이 표시돼요.']),
            F('dday_img', 'image', '위젯에 넣을 작은 이미지', ['h' => '예: 두 캐릭터 SD 이미지. 투명 PNG나 GIF를 추천해요. 디데이 목록 위쪽에 나와요.']),
            F('dday_img_w', 'int', '작은 이미지 가로 크기 (px)', ['d' => '120']),
            F('dday_plain', 'check', '배경 없이 표시 (이름 ······ D+숫자 점선 스타일)', ['d' => '0', 'h' => '켜면 배경 상자 없이 이미지와 글씨만 홈 배경 위에 나와요.']),
            F('dday_spaced', 'check', '"D + 1075"처럼 띄어서 표시', ['d' => '0']),
            F('dday_num_color', 'color', 'D-day 숫자 글자색', ['d' => '', 'h' => '체크하지 않으면 위젯 글자색과 같아요.']),
            F('dday_items', 'textarea', '디데이 목록', ['d' => '', 'h' => "한 줄에 하나씩 \"이름 | 날짜\" 형식으로 적어요.\n예)  함께한 날 | 2023-05-01\n예)  다음 오프모임 | 2026-12-25\n과거 날짜는 D+숫자, 미래 날짜는 D-숫자로 자동 계산돼요."]),
            F('dday_first', 'check', '과거 날짜는 시작일을 1일로 계산', ['d' => '1', 'h' => '체크하면 시작한 날이 D+1이 돼요 (커플 기념일 방식).']),
            F('dday_bg', 'image', '위젯 배경 이미지'),
            F('dday_bg_color', 'color', '위젯 배경색', ['d' => '#1a1b25']),
            F('dday_text', 'color', '위젯 글자색', ['d' => '#ffffff']),
        ]],
        'music' => ['label' => '뮤직 플레이어', 'fields' => [
            F('music_on', 'check', '뮤직 플레이어 사용', ['d' => '1', 'h' => '곡은 왼쪽 메뉴 "콘텐츠 → 음악 트랙"에서 mp3 파일을 올리거나 주소(유튜브 링크 포함)를 넣어서 추가해요.']),
                        F('music_scope', 'select', '어느 페이지에서 보일까요?', ['opts' => ['all' => '모든 페이지 (페이지를 옮겨도 음악이 이어져요)', 'home' => '메인 홈에서만'], 'd' => 'all']),
            F('music_autoplay', 'check', '입장하면 자동 재생 시도', ['d' => '0', 'h' => '브라우저 정책상 첫 클릭 전에는 막힐 수 있어요. 막히면 화면을 처음 클릭할 때 시작돼요.']),
            F('music_bg', 'image', '플레이어 배경 이미지'),
            F('music_bg_color', 'color', '플레이어 배경색', ['d' => '#1a1b25']),
            F('music_text', 'color', '플레이어 글자색', ['d' => '#ffffff']),
        ]],
        'profile' => ['label' => '프로필 선택 화면', 'fields' => [
            F('pf_char1_img', 'image', '캐릭터 A 전신/반신 이미지', ['h' => '프로필 메뉴에 들어가면 나란히 보이는 이미지예요. 투명 PNG 추천.']),
            F('pf_char2_img', 'image', '캐릭터 B 전신/반신 이미지'),
            F('pf_gap', 'int', '두 캐릭터 사이 간격 (px)', ['d' => '0', 'h' => '프로필 선택 화면에서 캐릭터 A·B 사이 간격이에요. 0이면 기본값(거의 붙어있음)이고, 숫자를 키우면 둘 사이가 벌어져요. 전신 이미지끼리 겹쳐 보이는 게 어색하면 늘려 보세요.']),
            F('pf_combo_img', 'image', '두 캐릭터 합성 이미지 (선택)', ['h' => '두 캐릭터가 함께 있는 이미지 한 장을 올리면, 프로필 선택 화면에서 캐릭터 A·B를 따로 보여주는 대신 이 이미지 하나를 화면 중앙에 보여줘요. 화면의 왼쪽/오른쪽을 누르면 그쪽 캐릭터의 프로필 목록이 떠요. 비워두면 지금처럼 캐릭터 A·B 이미지를 각각 보여줘요.']),
            F('pf_name_font', 'select', '캐릭터 이름 글꼴 (이미지 아래쪽)', ['opts' => ['' => '기본 (제목용 명조체)'] + font_opts(), 'd' => '', 'h' => '내가 올린 폰트도 여기서 고를 수 있어요. ("내 폰트 올리기"에서 먼저 올려 주세요.)']),
            F('pf_name_color', 'color', '캐릭터 이름 글자색', ['d' => '', 'h' => '체크하지 않으면 사이트 기본 글자색이에요.']),
            F('pf_name_size', 'int', '캐릭터 이름 글자 크기 (px)', ['d' => '0', 'h' => '0이면 기본 크기예요. 화면이 커지면 자동으로 함께 커져요.']),
        ]],
        'guestbook' => ['label' => '방명록', 'fields' => [
            F('gb_open', 'check', '방문자도 방명록에 글 쓰기 허용', ['d' => '1', 'h' => '끄면 관리자만 남길 수 있어요. 답글은 이 설정과 별개로, 방문자가 항상 남길 수 있어요.']),
            F('gb_notice', 'text', '방명록 안내 문구', ['d' => '편하게 한마디 남겨주세요.']),
            F('gb_card_color', 'color', '방명록 카드 배경색 (선택)', ['h' => '체크하지 않으면 "사이트 설정 → 카드 디자인"의 기본 카드색을 그대로 써요.']),
            F('gb_card_opacity', 'int', '방명록 카드 투명도 (진하기 0~100)', ['h' => '위의 배경색을 지정했을 때만 적용돼요.']),
            F('gb_text_color', 'color', '방명록 글자색 (선택)'),
            F('gb_line_color', 'color', '방명록 테두리·구분선 색 (선택)'),
            F('gb_reply_color', 'color', '답글 카드 색 (선택)', ['h' => '체크하지 않으면 방명록 카드 색(배경·테두리·글자색)에 맞춰져요. 따로 정하면 모든 답글 카드의 배경·테두리와 "답글"(주인장) 표시가 이 색으로 바뀌어요. 방문자가 남긴 답글에도 같이 적용돼요.']),
            F('gb_reply_opacity', 'int', '답글 카드 배경 진하기 (5~60)', ['d' => '12', 'h' => '위의 답글 카드 색을 지정했을 때만 적용돼요. 숫자가 클수록 배경이 진해져요.']),
            F('gb_reply_open', 'check', '답글을 처음부터 펼쳐서 보여주기', ['d' => '0', 'h' => '끄면(기본) 답글은 접혀 있고, "답글 N개"를 눌러야 보여요. 답글은 주인장뿐 아니라 방문자도 남길 수 있고, 여러 개가 쌓일 수 있어요.']),
        ]],
    ];
    return $G;
}

function settings_defaults() {
    static $D = null;
    if ($D !== null) return $D;
    $D = [];
    foreach (setting_groups() as $g) {
        foreach ($g['fields'] as $f) { $D[$f['n']] = isset($f['d']) ? $f['d'] : ''; }
    }
    return $D;
}
