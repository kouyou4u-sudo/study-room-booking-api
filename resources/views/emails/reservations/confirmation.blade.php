<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>自習室 仮予約確認</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.8; color: #333; background: #f7f7f7; padding: 24px;">

@php
    $confirmationUrl = $confirmationUrl ?? url('/api/reservations/confirm/' . $reservation->confirmation_token);
@endphp

<div style="max-width: 640px; margin: 0 auto; background: #ffffff; padding: 24px; border-radius: 12px; border: 1px solid #e5e5e5;">

    <h2 style="margin-top: 0; color: #1f2937;">
        自習室 仮予約確認
    </h2>

    <p>
        {{ $reservation->student_name }} 様
    </p>

    <p>
        自習室の仮予約を受け付けました。<br>
        まだ予約は確定していません。
    </p>

    <p>
        以下の内容をご確認のうえ、確認ボタンを押して本予約を確定してください。
    </p>

    <hr style="border: none; border-top: 1px solid #e5e5e5; margin: 24px 0;">

    <h3 style="color: #1f2937;">
        予約内容
    </h3>

    <ul style="padding-left: 20px;">
        <li>日付：{{ $reservation->date }}</li>
        <li>時間帯：{{ $reservation->time_slot }}</li>
        <li>座席番号：{{ $reservation->seat_number }}</li>
    </ul>

    <h3 style="color: #1f2937;">
        利用者情報
    </h3>

    <ul style="padding-left: 20px;">
        <li>氏名：{{ $reservation->student_name }}</li>
        <li>学年：{{ $reservation->grade }}</li>
        <li>利用区分：{{ $reservation->usage_type }}</li>
        <li>メールアドレス：{{ $reservation->email }}</li>
        @if (!empty($reservation->phone))
            <li>電話番号：{{ $reservation->phone }}</li>
        @endif
    </ul>

    <hr style="border: none; border-top: 1px solid #e5e5e5; margin: 24px 0;">

    <p>
        この仮予約は <strong>30分間有効</strong> です。<br>
        期限内に確認されない場合、仮予約は自動的に無効になります。
    </p>

    <p style="margin: 28px 0; text-align: center;">
        <a href="{{ $confirmationUrl }}"
           style="display: inline-block; padding: 12px 24px; background: #2563eb; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: bold;">
            本予約を確定する
        </a>
    </p>

    <p>
        ボタンが開けない場合は、以下のURLをブラウザに貼り付けてください。
    </p>

    <p style="word-break: break-all; background: #f3f4f6; padding: 12px; border-radius: 8px;">
        {{ $confirmationUrl }}
    </p>

    <hr style="border: none; border-top: 1px solid #e5e5e5; margin: 24px 0;">

    <p style="font-size: 13px; color: #6b7280;">
        このメールは自習室予約システムから自動送信されています。<br>
        お心当たりがない場合は、このメールを破棄してください。
    </p>

</div>

</body>
</html>