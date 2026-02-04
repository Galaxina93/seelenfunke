<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">

    <title>{{ $title ?? shop_setting('owner_name', 'Mein Seelenfunke') }}</title>

    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
            color: #333333;
            line-height: 1.5;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        /* HEADER */
        .header {
            border-bottom: 2px solid #C5A059;
            padding-bottom: 20px;
            margin-bottom: 30px;
            text-align: center;
        }
        .logo {
            max-width: 180px;
            height: auto;
        }

        /* TEXT */
        h1 { font-size: 22px; color: #111; margin-bottom: 10px; font-weight: bold; }
        p { font-size: 14px; color: #555; margin-bottom: 15px; }

        /* TABELLE */
        .table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 13px; }
        .table th { text-align: left; color: #888; text-transform: uppercase; font-size: 10px; border-bottom: 1px solid #eee; padding-bottom: 8px; }
        .table td { padding: 15px 0; border-bottom: 1px solid #f5f5f5; vertical-align: top; }
        .text-right { text-align: right; }

        /* PRODUKT VORSCHAU */
        .preview-wrapper {
            margin-top: 10px;
            display: block;
        }
        .preview-container {
            position: relative;
            width: 100px;
            height: 100px;
            display: inline-block;
            border: 1px solid #e5e5e5;
            border-radius: 4px;
            background-color: #f9f9f9;
            background-repeat: no-repeat;
            background-position: center center;
            background-size: contain;
            overflow: hidden;
        }
        .marker {
            position: absolute;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-left: -4px;
            margin-top: -4px;
            border: 1px solid white;
            box-shadow: 0 0 2px rgba(0,0,0,0.5);
            z-index: 20;
        }
        .marker-text { background-color: #007bff; }
        .marker-logo { background-color: #28a745; }

        /* DETAILS */
        .detail-info { font-size: 11px; color: #666; margin-top: 4px; line-height: 1.4; }
        .detail-label { font-weight: bold; color: #444; margin-right: 4px; }
        .note-box { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; padding: 8px; margin-top: 8px; font-size: 11px; border-radius: 4px; }

        /* SEAL / FINGERPRINT */
        .seal-box { margin-top: 10px; padding: 6px 10px; background-color: #f0fdf4; border: 1px solid #dcfce7; border-radius: 4px; display: inline-block; }
        .seal-text { font-size: 9px; color: #166534; font-family: monospace; }

        /* TOTALS */
        .totals { margin-top: 20px; border-top: 2px solid #eee; padding-top: 20px; }
        .totals-row { margin-bottom: 5px; font-size: 13px; }
        .totals-final { font-size: 18px; font-weight: bold; color: #C5A059; margin-top: 10px; border-top: 1px solid #eee; padding-top: 10px; }

        /* FOOTER */
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            text-align: center;
            font-size: 11px;
            color: #999;
        }
        .footer a { color: #C5A059; text-decoration: none; }
    </style>
</head>

<body>
