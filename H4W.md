# h4w-Anpassungen

Dieser Branch ist `main` **plus** die Anpassungen für das h4w-Forum. Regeln: Dev-Kit
`standards/conventions.md` §12 – Neuerungen zuerst auf `main`, dann `main` → `h4w` mergen,
nie umgekehrt. Deploy ins h4w-Test-/Live-Forum von diesem Branch.

## Anpassungen

| Bereich | Dateien | Zweck |
|---|---|---|
| UserCP-Menü in der RPG-Kategorie | `inc/plugins/jobliste/core/Installer.php` | Der UserCP-Menüpunkt „Berufe“ steht in der Kategorie **RPG** (Template `usercp_nav_rpg_chara`, Gruppen-Templates). `install_usercp_nav_misc_link()` hängt ihn nicht mehr in `usercp_nav_misc` („Sonstiges“), sondern entfernt nur Zeilen älterer Versionen. Link: `jobliste.php`, Label `{$lang->jobliste_usercp_menu_item}`. |
