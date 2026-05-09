<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>仮予約確認のお願い</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.8; color: #222;">
    <h2>自習室の仮予約を受け付けました</h2>

    <p>{{ $reservation->student_name }} 様</p>

    <p>
        自習室の仮予約を受け付けました。<br>
        まだ本予約は完了していません。<br>
        以下の確認ボタンをクリックすると、本予約が確定します。
    </p>

    <hr>

    <h3>仮予約内容</h3>
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

    <p>
        この仮予約は <strong>30分間有効</strong> です。<br>
        期限内に確認されない場合、仮予約は自動的に無効になります。
    </p>

    <p style="margin: 24px 0;">
        <a href="{{ $confirmationUrl }}"
           style="display: inline-block; padding: 12px 20px; background: #2563eb; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: bold;">
            本予約を確定する
        </a>
    </p>

    <p>
        ボタンが開けない場合は、以下のURLをブラウザに貼り付けてください。
    </p>

    <p style="word-break: break-all;">
        {{ $confirmationUrl }}
    </p>
</body>
</html>