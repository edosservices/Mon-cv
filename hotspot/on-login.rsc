# Script on-login LIMETE WIFI
# IP > Hotspot > Server Profiles > on-login
#
# Remplacer LIMETE_APP_URL par l'origine affichée dans l'écran MikroTik
# de cette installation. Ne pas laisser un domaine d'exemple.
#
# Le script demande l'expiration déjà enregistrée (voucher.expires_at).
# Il ne calcule pas une nouvelle durée à chaque reconnexion.
# Il n'envoie pas le mot de passe du routeur.

:local limeteUser $"user"
:local limeteApp "__LIMETE_APP_URL__"
:if ([:len $limeteUser] > 0) do={
  :do {
    /tool fetch url=($limeteApp . "/hotspot/session/" . $limeteUser) keep-result=no
  } on-error={}
}
