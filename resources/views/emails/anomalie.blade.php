<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Anomalie de pointage</title>
</head>
<body style="margin:0;padding:0;background:#F6F6FA;font-family:Arial,Helvetica,sans-serif;color:#14141B;">
    <div style="max-width:560px;margin:0 auto;padding:32px 24px;">
        <div style="background:#FFFFFF;border:1px solid #ECECF2;border-radius:12px;padding:28px 26px;">
            <p style="margin:0 0 16px;font-size:14px;">Bonjour {{ $nom }},</p>

            <p style="margin:0 0 16px;font-size:14px;line-height:1.6;">
                Une anomalie a été détectée dans votre pointage du <strong>{{ $workDateLabel }}</strong>
                (nombre d'entrées/sorties incohérent, ou une sortie enregistrée avant l'entrée correspondante).
            </p>

            <p style="margin:0 0 16px;font-size:14px;line-height:1.6;">
                Merci de vérifier vos pointages de cette journée et, si nécessaire, de contacter le service RH
                pour régulariser votre dossier.
            </p>

            <p style="margin:24px 0 0;font-size:12.5px;color:#8C8C98;">
                Ceci est un message automatique du système de Gestion des Présences — UEMF.
            </p>
        </div>
    </div>
</body>
</html>
