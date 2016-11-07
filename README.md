VitaEmu.com github parser
=========================

Trying to know what's in the last Libretro nightly build by getting closed requests with Github API.

CLI app :

```
php bin/console script:github:parse github_organisation search
```

So to get Vita related issues on libretro's repositories :

```
php bin/console script:github:parse libretro vita
```

And it then generates a Markdown ready to be used by a Jekyll. So it's doing all the job for VitaEmu.com :-)

My ugly crontab :

```
0 10 * * * cd ~/vitaemu.com-parser && php bin/console script:github:parse libretro vita && git add -A && git commit -am "Last github update" && git push
```
