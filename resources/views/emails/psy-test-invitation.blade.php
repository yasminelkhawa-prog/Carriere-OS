<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invitation — Évaluation psychométrique</title>
</head>
<body style="margin:0; padding:0; background-color:#f8fafc; font-family:'Segoe UI',Roboto,Arial,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f8fafc;">
        <tr>
            <td align="center" style="padding:40px 20px;">
                <table role="presentation" width="580" cellspacing="0" cellpadding="0" style="background-color:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,0.08);">
                    {{-- Header --}}
                    <tr>
                        <td style="background:linear-gradient(135deg,#6d28d9 0%,#8b5cf6 100%); padding:32px 40px; text-align:center;">
                            <h1 style="margin:0; color:#ffffff; font-size:22px; font-weight:600;">Évaluation psychométrique</h1>
                            <p style="margin:8px 0 0; color:rgba(255,255,255,0.85); font-size:14px;">Profil {{ $psyTest->profile_label }}</p>
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td style="padding:36px 40px;">
                            <p style="margin:0 0 20px; color:#1e293b; font-size:16px; line-height:1.7;">
                                Bonjour <strong>{{ $psyTest->candidate_first_name }}</strong>,
                            </p>
                            <p style="margin:0 0 20px; color:#475569; font-size:15px; line-height:1.7;">
                                Vous avez été invité(e) à passer une évaluation psychométrique dans le cadre de votre candidature. Ce test comprend 20 questions et prend environ 12 à 15 minutes.
                            </p>

                            <div style="background:#f1f5f9; border-radius:12px; padding:20px; margin:0 0 24px;">
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                    <tr>
                                        <td style="color:#64748b; font-size:13px; padding:4px 0;">Profil évalué</td>
                                        <td style="color:#1e293b; font-size:13px; font-weight:600; text-align:right; padding:4px 0;">{{ $psyTest->profile_label }}</td>
                                    </tr>
                                    <tr>
                                        <td style="color:#64748b; font-size:13px; padding:4px 0;">Valide jusqu'au</td>
                                        <td style="color:#1e293b; font-size:13px; font-weight:600; text-align:right; padding:4px 0;">{{ $psyTest->expires_at->format('d/m/Y à H:i') }}</td>
                                    </tr>
                                </table>
                            </div>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td align="center" style="padding:8px 0 24px;">
                                        <a href="{{ $testUrl }}" style="display:inline-block; background:linear-gradient(135deg,#6d28d9 0%,#8b5cf6 100%); color:#ffffff; text-decoration:none; padding:14px 36px; border-radius:12px; font-size:15px; font-weight:600;">
                                            Commencer le test →
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 8px; color:#94a3b8; font-size:12px; line-height:1.6;">
                                Il n'y a pas de réponse « juste » ou « fausse ». Répondez selon ce que vous feriez réellement. Vos résultats seront traités de manière confidentielle.
                            </p>
                            <p style="margin:0; color:#94a3b8; font-size:12px; line-height:1.6;">
                                Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :<br>
                                <a href="{{ $testUrl }}" style="color:#6d28d9; word-break:break-all;">{{ $testUrl }}</a>
                            </p>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="background:#f8fafc; padding:20px 40px; border-top:1px solid #e2e8f0; text-align:center;">
                            <p style="margin:0; color:#94a3b8; font-size:11px;">
                                RGPD · Conservation 2 ans max · Droit d'accès et de rectification
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
