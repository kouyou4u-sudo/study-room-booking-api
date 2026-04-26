<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>本予約が確定しました</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.8; color: #222;">
    <h2>自習室の本予約が確定しました</h2>

    <p>
        {{ $reservation->student_name }} 様
    </p>

    <p>
        自習室の本予約が確定しました。<br>
        以下の内容で予約を受け付けております。
    </p>

    <hr>

    <h3>予約内容</h3>

    <ul>
        <li>予約番号：{{ $reservation->reservation_code }}</li>
        <li>氏名：{{ $reservation->student_name }}</li>
        <li>学年：{{ $reservation->grade }}</li>
        <li>利用区分：{{ $reservation->usage_type }}</li>
        <li>予約日：{{ $reservation->date->format('Y年n月j日') }}</li>
        <li>時間帯：{{ $reservation->time_slot }}</li>
        <li>座席番号：{{ $reservation->seat_number }}</li>
    </ul>

    <hr>

    <h3>ご来室時のお願い</h3>

    <ul>
        <li>予約時刻の5分前を目安にご来室ください。</li>
        <li>利用後は、机の整理整頓・清掃を行ってから退出してください。</li>
        <li>ご不明な点はスタッフまでお気軽にお尋ねください。</li>
    </ul>

    <hr>

    <h3>キャンセルについて</h3>

    <p>
        予約をキャンセルする場合は、以下のリンクからお手続きください。
    </p>

    <p style="margin: 24px 0;">
        <a href="{{ $cancelUrl }}"
           style="display: inline-block; padding: 12px 20px; background: #dc2626; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: bold;">
            予約をキャンセルする
        </a>
    </p>

    <p>
        ボタンが開けない場合は、以下のURLをブラウザに貼り付けてください。
    </p>

    <p style="word-break: break-all;">
        {{ $cancelUrl }}
    </p>
</body>
</html>