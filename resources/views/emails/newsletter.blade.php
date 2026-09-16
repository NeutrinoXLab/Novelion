<!DOCTYPE html>

<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

```
<title>{{ $campaign->subject }}</title>
```

</head>

<body style="margin:0; padding:0; background-color:#f1f5f9; font-family:Arial, Helvetica, sans-serif; color:#1e293b;">

```
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f1f5f9; padding:40px 15px;">
    <tr>
        <td align="center">

            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px; background-color:#ffffff; border-radius:16px; overflow:hidden;">

                {{-- Header --}}
                <tr>
                    <td style="background-color:#0891b2; padding:30px; text-align:center;">

                        <div style="font-size:32px; font-weight:bold; color:#ffffff;">
                            Novelion
                        </div>

                    </td>
                </tr>

                {{-- Conținut --}}
                <tr>
                    <td style="padding:40px 35px;">

                        <h1 style="margin:0 0 20px 0; font-size:28px; line-height:1.3; color:#0f172a;">
                            {{ $campaign->title }}
                        </h1>

                        <div style="font-size:16px; line-height:1.7; color:#475569;">
                            {!! nl2br(e($campaign->content)) !!}
                        </div>

                    </td>
                </tr>

                {{-- Footer --}}
                <tr>
                    <td style="padding:25px 35px; background-color:#f8fafc; text-align:center;">

                        <p style="margin:0 0 10px 0; font-size:13px; color:#64748b;">
                            Primești acest email deoarece te-ai abonat la newsletter-ul Novelion.
                        </p>

                        <p style="margin:0; font-size:13px;">
                            <a
                                href="{{ $unsubscribeUrl }}"
                                style="color:#0891b2; text-decoration:none;"
                            >
                                Dezabonează-te de la newsletter
                            </a>
                        </p>

                    </td>
                </tr>

            </table>

        </td>
    </tr>
</table>
```

</body>
</html>
